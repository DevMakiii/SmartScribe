<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/Note.php';
require_once __DIR__ . '/../models/Goal.php';
require_once __DIR__ . '/../helpers/FileUpload.php';
require_once __DIR__ . '/../services/AIKeywordService.php';

class NoteController extends BaseController {
    private $note;
    private $goal;
    private $keywordService;

    public function __construct($db = null) {
        error_log("NoteController::__construct() - Starting construction");
        if ($db) {
            $this->db = $db;
            error_log("NoteController::__construct() - Database connection provided");
        }
        parent::__construct();
        error_log("NoteController::__construct() - Parent constructor called");

        try {
            $this->note = new Note($this->db);
            error_log("NoteController::__construct() - Note model instantiated");
        } catch (Exception $e) {
            error_log("NoteController::__construct() - Error instantiating Note model: " . $e->getMessage());
            throw $e;
        }

        try {
            $this->goal = new Goal($this->db);
            error_log("NoteController::__construct() - Goal model instantiated");
        } catch (Exception $e) {
            error_log("NoteController::__construct() - Error instantiating Goal model: " . $e->getMessage());
            throw $e;
        }

        try {
            $this->keywordService = new AIKeywordService();
            error_log("NoteController::__construct() - AIKeywordService instantiated");
        } catch (Exception $e) {
            error_log("NoteController::__construct() - Error instantiating AIKeywordService: " . $e->getMessage());
            throw $e;
        }

        error_log("NoteController::__construct() - Construction completed");
    }

    public function index() {
        try {
            error_log("NoteController::index() - ===== NOTES API REQUEST STARTED =====");
            error_log("NoteController::index() - Request method: " . $_SERVER['REQUEST_METHOD']);
            error_log("NoteController::index() - Request URI: " . $_SERVER['REQUEST_URI']);
            error_log("NoteController::index() - Query string: " . ($_SERVER['QUERY_STRING'] ?? 'none'));
            error_log("NoteController::index() - Headers: " . json_encode(getallheaders()));
            error_log("NoteController::index() - Starting authentication");

            // Authenticate user first
            if (!$this->authenticateUser()) {
                error_log("NoteController::index() - Authentication failed");
                $this->unauthorizedResponse();
                return;
            }

            // Get user ID from header
            $userId = $this->getUserId();
            error_log("NoteController::index() - User ID: " . ($userId ?: 'null'));

            if (!$userId) {
                error_log("NoteController::index() - No user ID found");
                $this->unauthorizedResponse();
                return;
            }

            // Get search query from GET parameters
            $searchQuery = isset($_GET['search']) ? trim($_GET['search']) : null;
            error_log("NoteController::index() - Search query: " . ($searchQuery ?: 'none'));

            // Build query with optional search
            $whereConditions = ["user_id = ?"];
            $params = [$userId];

            if ($searchQuery) {
                $whereConditions[] = "(title LIKE ? OR original_text LIKE ? OR keywords LIKE ?)";
                $params[] = '%' . $searchQuery . '%';
                $params[] = '%' . $searchQuery . '%';
                $params[] = '%' . $searchQuery . '%';
            }

            $query = "SELECT n.*
                      FROM notes n
                      WHERE " . implode(' AND ', $whereConditions) . "
                      ORDER BY created_at DESC";

            error_log("NoteController::index() - Executing query: " . $query);
            error_log("NoteController::index() - Query parameters: " . json_encode($params));

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);

            $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("NoteController::index() - Query executed, found " . count($notes) . " notes");

            error_log("NoteController::index() - About to call successResponse");

            $this->successResponse($notes, 'Notes retrieved successfully');
            error_log("NoteController::index() - successResponse called");

        } catch (Exception $e) {
            $this->errorResponse('Failed to retrieve notes: ' . $e->getMessage());
        }
    }

    public function store() {
        try {
            // Authenticate user first
            if (!$this->authenticateUser()) {
                $this->unauthorizedResponse();
                return;
            }

            // Get user ID from header
            $userId = $this->getUserId();

            if (!$userId) {
                $this->unauthorizedResponse();
                return;
            }

            $title = null;
            $text = null;

            // Check if this is a JSON request (text-only notes) or FormData (with possible image)
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            if (strpos($contentType, 'application/json') !== false) {
                // Handle JSON data
                $jsonData = json_decode(file_get_contents('php://input'), true);
                if ($jsonData) {
                    $title = $jsonData['title'] ?? null;
                    $text = $jsonData['text'] ?? null;
                }
            } else {
                // Handle FormData
                $title = $_POST['title'] ?? null;
                $text = $_POST['text'] ?? null;
            }

            // Validate required fields
            if (!$title || !$text) {
                $this->badRequestResponse('Missing title or text');
                return;
            }

            $imagePath = null;

            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $fileUpload = new FileUpload();
                $uploadResult = $fileUpload->uploadImage($_FILES['image']);
                if (!$uploadResult['success']) {
                    $this->errorResponse($uploadResult['error']);
                    return;
                }
                $imagePath = $uploadResult['file_path'];
            }

            $this->note->user_id = $userId;
            $this->note->title = $this->sanitizeInput($title);
            $this->note->original_text = $this->sanitizeInput($text);
            $this->note->image_path = $imagePath;

            // Debug logging
            error_log("NoteController::store() - About to create note");
            error_log("NoteController::store() - User ID: $userId");
            error_log("NoteController::store() - Title: " . $this->sanitizeInput($title));
            error_log("NoteController::store() - Text length: " . strlen($this->sanitizeInput($text)));
            error_log("NoteController::store() - Image path: " . ($imagePath ?: 'null'));

            $noteId = $this->note->createNote([
                'user_id' => $userId,
                'title' => $this->sanitizeInput($title),
                'original_text' => $this->sanitizeInput($text),
                'image_path' => $imagePath
            ]);

            error_log("NoteController::store() - Note creation result: " . ($noteId ? "Success (ID: $noteId)" : "Failed"));

            // DEBUG: Check for goals that need updating
            if ($noteId) {
                error_log("NoteController::store() - Checking for goals to update for user $userId");

                // Query for active goals with target_type = 'notes'
                $goalQuery = "SELECT id, title, target_value, current_value, status FROM learning_goals
                             WHERE user_id = :user_id AND target_type = 'notes' AND status = 'active'";
                $goalStmt = $this->db->prepare($goalQuery);
                $goalStmt->bindParam(':user_id', $userId);
                $goalStmt->execute();
                $activeGoals = $goalStmt->fetchAll(PDO::FETCH_ASSOC);

                error_log("NoteController::store() - Found " . count($activeGoals) . " active note goals for user $userId");

                foreach ($activeGoals as $goal) {
                    error_log("NoteController::store() - Goal ID {$goal['id']}: {$goal['title']} - Current: {$goal['current_value']}/{$goal['target_value']} - Status: {$goal['status']}");
                }
            }

            if ($noteId) {
                // Auto-extract keywords from the note content using enhanced multi-pass extraction
                $keywords = $this->keywordService->extractKeywords($this->sanitizeInput($text), 7, ['topic', 'entity', 'concept']);
                $keywordsString = implode(',', $keywords);

                // Update the note with extracted keywords
                $updateQuery = "UPDATE notes SET keywords = :keywords WHERE id = :id AND user_id = :user_id";
                $updateStmt = $this->db->prepare($updateQuery);
                $updateStmt->bindParam(':keywords', $keywordsString);
                $updateStmt->bindParam(':id', $noteId);
                $updateStmt->bindParam(':user_id', $userId);
                $updateStmt->execute();

                error_log("NoteController::store() - Keywords extracted and saved: " . $keywordsString);

                // Update goal progress for this user
                try {
                    $goalsUpdated = $this->goal->updateProgressForNotes($userId);
                    error_log("NoteController::store() - Updated $goalsUpdated goals for user $userId");
                } catch (Exception $e) {
                    error_log("NoteController::store() - Error updating goals: " . $e->getMessage());
                    // Don't fail the note creation if goal update fails
                }

                $this->successResponse([
                    'note_id' => $noteId,
                    'keywords' => $keywords,
                    'keyword_count' => count($keywords)
                ], 'Note saved successfully with enhanced auto-extracted keywords', 201);
            } else {
                $this->errorResponse('Failed to save note');
            }

        } catch (Exception $e) {
            $this->errorResponse('Failed to create note: ' . $e->getMessage());
        }
    }

    public function show($id) {
        // Authenticate user first
        if (!$this->authenticateUser()) {
            $this->unauthorizedResponse();
            return;
        }

        $userId = $this->getUserId();

        if (!$userId) {
            $this->unauthorizedResponse();
            return;
        }

        $query = "SELECT n.*,
                         DATE_FORMAT(COALESCE(n.updated_at, n.created_at), '%M %e, %Y at %l:%i %p') as last_edited,
                         s.content as summary
                   FROM notes n
                   LEFT JOIN summaries s ON n.id = s.note_id
                   WHERE n.id = :id AND n.user_id = :user_id
                   ORDER BY s.created_at DESC
                   LIMIT 1";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();

        $note = $stmt->fetch();

        if ($note) {
            echo json_encode([
                "success" => true,
                "data" => $note
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                "success" => false,
                "error" => "Note not found"
            ]);
        }
    }

    public function update($id) {
        // Authenticate user first
        if (!$this->authenticateUser()) {
            $this->unauthorizedResponse();
            return;
        }

        $userId = $this->getUserId();

        if (!$userId) {
            $this->unauthorizedResponse();
            return;
        }

        $title = $_POST['title'] ?? NULL;
        $text = $_POST['text'] ?? NULL;
        $summary = $_POST['summary'] ?? NULL;
        $keywords = $_POST['keywords'] ?? NULL;
        $isFavoriteRaw = $_POST['is_favorite'] ?? NULL;
        $isFavorite = isset($_POST['is_favorite']) ? (bool)$_POST['is_favorite'] : NULL;

        error_log("🔄 NoteController::update() - Raw is_favorite from POST: '" . $isFavoriteRaw . "' (type: " . gettype($isFavoriteRaw) . ")");
        error_log("🔄 NoteController::update() - Converted is_favorite: " . ($isFavorite ? 'true' : 'false') . " (type: " . gettype($isFavorite) . ")");

        // For favorite updates, we only need the is_favorite field
        $isFavoriteOnly = ($title === NULL && $text === NULL && $isFavorite !== NULL);

        if (!$isFavoriteOnly && (!$title || !$text)) {
            error_log("NoteController::update() - Validation failed: missing required fields");
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "error" => "Missing title or text"
            ]);
            return;
        }

        // Check if note exists and belongs to user
        $checkQuery = "SELECT id FROM notes WHERE id = :id AND user_id = :user_id";
        $checkStmt = $this->db->prepare($checkQuery);
        $checkStmt->bindParam(':id', $id);
        $checkStmt->bindParam(':user_id', $userId);
        $checkStmt->execute();

        if (!$checkStmt->fetch()) {
            http_response_code(404);
            echo json_encode([
                "success" => false,
                "error" => "Note not found"
            ]);
            return;
        }

        // Build dynamic UPDATE query based on provided fields
        $updateFields = [];
        $params = [];

        if ($title !== NULL) {
            $updateFields[] = "title = :title";
            $params[':title'] = $title;
        }

        if ($text !== NULL) {
            $updateFields[] = "original_text = :text";
            $params[':text'] = $text;
        }

        if ($keywords !== NULL) {
            $updateFields[] = "keywords = :keywords";
            $params[':keywords'] = $keywords;
        }

        if ($isFavorite !== NULL) {
            $updateFields[] = "is_favorite = :is_favorite";
            $params[':is_favorite'] = $isFavorite;
        }

        // Always update the updated_at timestamp
        $updateFields[] = "updated_at = NOW()";

        $query = "UPDATE notes SET " . implode(', ', $updateFields) . " WHERE id = :id AND user_id = :user_id";
        error_log("NoteController::update() - Generated query: $query");
        error_log("NoteController::update() - Query parameters: " . json_encode($params));

        $stmt = $this->db->prepare($query);
        foreach ($params as $param => $value) {
            $stmt->bindValue($param, $value);
        }
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':user_id', $userId);

        if ($stmt->execute()) {
            echo json_encode([
                "success" => true,
                "message" => "Note updated successfully"
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "error" => "Failed to update note"
            ]);
        }
    }

    public function destroy($id) {
        // Authenticate user first
        if (!$this->authenticateUser()) {
            $this->unauthorizedResponse();
            return;
        }

        $userId = $this->getUserId();

        if (!$userId) {
            $this->unauthorizedResponse();
            return;
        }

        $query = "DELETE FROM notes WHERE id = :id AND user_id = :user_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':user_id', $userId);

        if ($stmt->execute()) {
            echo json_encode([
                "success" => true,
                "message" => "Note deleted successfully"
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "error" => "Failed to delete note"
            ]);
        }
    }

    /**
     * Extract keywords for an existing note
     */
    public function extractKeywords($id) {
        try {
            // Authenticate user first
            if (!$this->authenticateUser()) {
                $this->unauthorizedResponse();
                return;
            }

            $userId = $this->getUserId();

            if (!$userId) {
                $this->unauthorizedResponse();
                return;
            }

            // Check if note exists and belongs to user
            $checkQuery = "SELECT id, original_text FROM notes WHERE id = :id AND user_id = :user_id";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':id', $id);
            $checkStmt->bindParam(':user_id', $userId);
            $checkStmt->execute();

            $note = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$note) {
                $this->notFoundResponse('Note not found');
                return;
            }

            // Get parameters from request
            $data = json_decode(file_get_contents('php://input'), true);
            $count = $data['count'] ?? 7;
            $types = $data['types'] ?? ['topic', 'entity', 'concept'];

            // Extract keywords using enhanced service
            $keywords = $this->keywordService->extractKeywords($note['original_text'], $count, $types);
            $keywordsString = implode(',', $keywords);

            // Update the note with extracted keywords
            $updateQuery = "UPDATE notes SET keywords = :keywords WHERE id = :id AND user_id = :user_id";
            $updateStmt = $this->db->prepare($updateQuery);
            $updateStmt->bindParam(':keywords', $keywordsString);
            $updateStmt->bindParam(':id', $id);
            $updateStmt->bindParam(':user_id', $userId);
            $updateStmt->execute();

            error_log("NoteController::extractKeywords() - Keywords extracted and saved: " . $keywordsString);

            $this->successResponse([
                'note_id' => $id,
                'keywords' => $keywords,
                'keyword_count' => count($keywords)
            ], 'Keywords extracted successfully', 200);

        } catch (Exception $e) {
            error_log("NoteController::extractKeywords() - Error: " . $e->getMessage());
            $this->errorResponse('Failed to extract keywords: ' . $e->getMessage());
        }
    }


}
?>
