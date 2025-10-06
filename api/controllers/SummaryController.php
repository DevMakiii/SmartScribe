<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/Summary.php';
require_once __DIR__ . '/../models/Note.php';
require_once __DIR__ . '/../services/AISummaryService.php';

class SummaryController extends BaseController {
    private $summary;
    private $note;
    private $gptService;

    public function __construct() {
        parent::__construct();
        $this->summary = new Summary($this->db);
        $this->note = new Note($this->db);
        $this->gptService = new AISummaryService();
    }

    public function index() {
        if (!$this->authenticateUser()) {
            $this->unauthorizedResponse();
            return;
        }

        $userId = $this->getUserId();

        $query = "SELECT s.*
                  FROM summaries s
                  INNER JOIN notes n ON s.note_id = n.id
                  WHERE n.user_id = :user_id
                  ORDER BY s.created_at DESC";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();

        $this->successResponse($stmt->fetchAll());
    }

    public function store() {
        if (!$this->authenticateUser()) {
            $this->unauthorizedResponse();
            return;
        }

        $userId = $this->getUserId();

        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['note_id'])) {
            $this->badRequestResponse('Missing note_id');
            return;
        }

        // Verify the note belongs to the authenticated user
        $note = $this->note->getByIdAndUser($data['note_id'], $userId);
        if (!$note) {
            $this->errorResponse('Access denied or note not found', 403);
            return;
        }

        $content = $data['content'] ?? null;
        $length = $data['length'] ?? 'medium';
        $format = $data['format'] ?? 'paragraph';

        // If content is not provided, generate summary using AI
        if (!$content) {
            try {
                $generatedSummary = $this->gptService->generateSummary($note['original_text'], $length, $format);
                $content = $generatedSummary ?: $this->generateFallbackSummary($note['original_text'], $length, $format);
            } catch (Exception $e) {
                error_log("AI summary generation failed in SummaryController: " . $e->getMessage() . ", using fallback");
                $content = $this->generateFallbackSummary($note['original_text'], $length, $format);
            }
        }

        $this->summary->note_id = $data['note_id'];
        $this->summary->content = $content;
        $this->summary->length = $length;

        error_log("SummaryController::store() - Creating summary for note_id: {$data['note_id']}, user_id: $userId");

        $summaryId = $this->summary->create();

        if ($summaryId) {
            error_log("SummaryController::store() - Summary created successfully with ID: $summaryId");

            $this->successResponse([
                "summary_id" => $summaryId,
                "generated" => !isset($data['content']),
                "content_preview" => substr($content, 0, 100)
            ], 'Summary created successfully', 201);
        } else {
            error_log("SummaryController::store() - Failed to create summary in database");
            $this->errorResponse('Failed to create summary');
        }
    }

    public function show($id) {
        if (!$this->authenticateUser()) {
            $this->unauthorizedResponse();
            return;
        }

        $userId = $this->getUserId();

        $query = "SELECT s.*
                  FROM summaries s
                  INNER JOIN notes n ON s.note_id = n.id
                  WHERE s.id = :id AND n.user_id = :user_id";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();

        $summary = $stmt->fetch();

        if ($summary) {
            $this->successResponse($summary);
        } else {
            $this->notFoundResponse('Summary not found');
        }
    }

    /**
     * Generate fallback summary when AI is unavailable
     */
    private function generateFallbackSummary($text, $length, $format = 'paragraph') {
        $wordCount = str_word_count($text);
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $firstSentence = trim($sentences[0] ?? '');

        switch ($length) {
            case 'short':
                return "Summary: " . substr($firstSentence, 0, 100) . "... (" . $wordCount . " words)";
            case 'medium':
                $secondSentence = isset($sentences[1]) ? " " . trim($sentences[1]) : "";
                return "Summary: " . $firstSentence . $secondSentence . " (Total: " . $wordCount . " words)";
            case 'long':
                $summaryText = implode(" ", array_slice($sentences, 0, 3));
                return "Detailed Summary: " . $summaryText . "... (Total: " . $wordCount . " words, " . count($sentences) . " sentences)";
            default:
                return "Generated summary for " . $wordCount . " words of content.";
        }
    }
}
?>