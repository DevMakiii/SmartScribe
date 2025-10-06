<?php
// api/index.php - Main API entry point
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

header('Content-Type: application/json');
// CORS headers are handled by .htaccess

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get the resource and action from the query parameters
$resource = $_GET['resource'] ?? null;
$action = $_GET['action'] ?? null;

error_log("API Router - ===== API REQUEST RECEIVED =====");
error_log("API Router - Request method: " . $_SERVER['REQUEST_METHOD']);
error_log("API Router - Request URI: " . $_SERVER['REQUEST_URI']);
error_log("API Router - Query string: " . ($_SERVER['QUERY_STRING'] ?? 'none'));
error_log("API Router - Resource: " . ($resource ?: 'null'));
error_log("API Router - Action: " . ($action ?: 'null'));
error_log("API Router - Headers: " . json_encode(getallheaders()));

// Validate resource and action
if (!$resource) {
    error_log("API Router - ERROR: Missing resource parameter");
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Missing resource parameter'
    ]);
    exit();
}

// Initialize database connection for controllers that need it
$db = null;
if (in_array($resource, ['notes', 'dashboard', 'progress', 'settings', 'export', 'quizzes', 'auth', 'summaries', 'gpt', 'ocr', 'study-sessions', 'goals'])) {
    require_once __DIR__ . '/config/database.php';
    try {
        $db = getDbConnection();
        // Make $db available globally for controllers that use global $db
        $GLOBALS['db'] = $db;
        error_log("API Router - Database connection successful");
    } catch (Exception $e) {
        error_log("API Router - Database connection failed: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Database connection failed',
            'message' => $e->getMessage()
        ]);
        exit();
    }
}

// Route to the appropriate controller
try {
    switch ($resource) {
        case 'auth':
            require_once __DIR__ . '/controllers/AuthController.php';
            $controller = new AuthController();

            switch ($action) {
                case 'login':
                    $controller->login();
                    break;
                case 'register':
                    $controller->register();
                    break;
                case 'logout':
                    $controller->logout();
                    break;
                case 'profile':
                    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                        $controller->profile();
                    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
                        $controller->updateProfile();
                    } else {
                        http_response_code(405);
                        echo json_encode([
                            'success' => false,
                            'error' => 'Method not allowed'
                        ]);
                    }
                    break;
                case 'update-password':
                    $controller->updatePassword();
                    break;
                case 'upload-profile-picture':
                    $controller->uploadProfilePicture();
                    break;
                case 'google':
                   $controller->googleLogin();
                   break;
                case 'request-password-reset':
                   $controller->requestPasswordReset();
                   break;
                case 'reset-password':
                   $controller->resetPassword();
                   break;
                case 'validate-reset-token':
                   $controller->validateResetToken();
                   break;
                case 'delete-account':
                    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
                        $controller->deleteAccount();
                    } else {
                        http_response_code(405);
                        echo json_encode([
                            'success' => false,
                            'error' => 'Method not allowed'
                        ]);
                    }
                    break;
                default:
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Auth action not found'
                    ]);
            }
            break;

        case 'notes':
            error_log("API Router - Routing to notes controller");
            require_once __DIR__ . '/controllers/NoteController.php';
            $controller = new NoteController($db);

            switch ($_SERVER['REQUEST_METHOD']) {
                case 'GET':
                    error_log("API Router - Notes GET request");
                    if (($action === 'show' && isset($_GET['id'])) || isset($_GET['id'])) {
                        error_log("API Router - Calling notes show method with ID: " . $_GET['id']);
                        $controller->show($_GET['id']);
                    } else {
                        error_log("API Router - Calling notes index method");
                        $controller->index();
                    }
                    break;
                case 'POST':
                    if (isset($_GET['id'])) {
                        $controller->update($_GET['id']);
                    } else {
                        $controller->store();
                    }
                    break;
                case 'PUT':
                    if (isset($_GET['id'])) {
                        $controller->update($_GET['id']);
                    } else {
                        http_response_code(400);
                        echo json_encode([
                            'success' => false,
                            'error' => 'Missing ID for PUT request'
                        ]);
                    }
                    break;
                case 'DELETE':
                    if (isset($_GET['id'])) {
                        $controller->destroy($_GET['id']);
                    } else {
                        http_response_code(400);
                        echo json_encode([
                            'success' => false,
                            'error' => 'Missing note ID'
                        ]);
                    }
                    break;
                default:
                    http_response_code(405);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Method not allowed'
                    ]);
            }
            break;

        case 'summaries':
            require_once __DIR__ . '/controllers/SummaryController.php';
            $controller = new SummaryController();

            switch ($_SERVER['REQUEST_METHOD']) {
                case 'GET':
                    if (isset($_GET['id'])) {
                        $controller->show($_GET['id']);
                    } else {
                        $controller->index();
                    }
                    break;
                case 'POST':
                    $controller->store();
                    break;
                default:
                    http_response_code(405);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Method not allowed'
                    ]);
            }
            break;

        case 'quizzes':
            require_once __DIR__ . '/controllers/QuizController.php';
            $controller = new QuizController();

            switch ($_SERVER['REQUEST_METHOD']) {
                case 'GET':
                    if (isset($_GET['id'])) {
                        $controller->show($_GET['id']);
                    } else {
                        $controller->index();
                    }
                    break;
                case 'POST':
                    $controller->store();
                    break;
                case 'PUT':
                    if (isset($_GET['id'])) {
                        $controller->update($_GET['id']);
                    }
                    break;
                case 'DELETE':
                    if (isset($_GET['id'])) {
                        $controller->destroy($_GET['id']);
                    }
                    break;
                default:
                    http_response_code(405);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Method not allowed'
                    ]);
            }
            break;

        case 'progress':
            require_once __DIR__ . '/controllers/ProgressController.php';
            $controller = new ProgressController($db);

            switch ($action) {
                case 'stats':
                    $controller->getStats();
                    break;
                case 'startStudySession':
                    $controller->startStudySession();
                    break;
                case 'endStudySession':
                    $controller->endStudySession();
                    break;
                default:
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Progress action not found'
                    ]);
            }
            break;

        case 'dashboard':
            require_once __DIR__ . '/controllers/DashboardController.php';
            $controller = new DashboardController($db);

            switch ($action) {
                case 'stats':
                    $controller->getStats();
                    break;
                default:
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Dashboard action not found'
                    ]);
            }
            break;

        case 'settings':
            require_once __DIR__ . '/controllers/SettingsController.php';
            $controller = new SettingsController($db);

            switch ($_SERVER['REQUEST_METHOD']) {
                case 'GET':
                    $controller->index();
                    break;
                case 'PUT':
                    $controller->update();
                    break;
                default:
                    http_response_code(405);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Method not allowed'
                    ]);
            }
            break;

        case 'export':
            require_once __DIR__ . '/controllers/ExportController.php';
            $controller = new ExportController($db);

            if (isset($_GET['id']) && isset($_GET['format'])) {
                $controller->export($_GET['id'], $_GET['format']);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Missing id or format parameter'
                ]);
            }
            break;

        case 'ocr':
            require_once __DIR__ . '/controllers/OCRController.php';
            $controller = new OCRController();

            switch ($action) {
                case 'processImage':
                    $controller->processImage();
                    break;
                default:
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'error' => 'OCR action not found'
                    ]);
            }
            break;

        case 'gpt':
            require_once __DIR__ . '/controllers/GPTController.php';
            $controller = new GPTController();

            switch ($action) {
                case 'generateSummary':
                    $controller->generateSummary();
                    break;
                case 'generateQuiz':
                    $controller->generateQuiz();
                    break;
                case 'extractKeywords':
                    $controller->extractKeywords();
                    break;
                default:
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'error' => 'GPT action not found'
                    ]);
            }
            break;

        case 'goals':
            require_once __DIR__ . '/controllers/GoalController.php';
            $controller = new GoalController();

            switch ($action) {
                case 'stats':
                    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                        $controller->getStats();
                    } else {
                        http_response_code(405);
                        echo json_encode([
                            'success' => false,
                            'error' => 'Method not allowed'
                        ]);
                    }
                    break;
                case 'update-progress':
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['id'])) {
                        $controller->updateProgress($_GET['id']);
                    } else {
                        http_response_code(400);
                        echo json_encode([
                            'success' => false,
                            'error' => 'Missing goal ID or invalid method'
                        ]);
                    }
                    break;
                default:
                    // Handle standard CRUD operations
                    if (isset($_GET['id'])) {
                        $id = $_GET['id'];
                        switch ($_SERVER['REQUEST_METHOD']) {
                            case 'GET':
                                $controller->show($id);
                                break;
                            case 'POST':
                                // Handle both update and delete via POST with method override
                                if (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
                                    if ($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] === 'DELETE') {
                                        $controller->destroy($id);
                                    } else {
                                        http_response_code(405);
                                        echo json_encode([
                                            'success' => false,
                                            'error' => 'Method not allowed'
                                        ]);
                                    }
                                } else {
                                    $controller->update($id);
                                }
                                break;
                            case 'PUT':
                                $controller->update($id);
                                break;
                            case 'DELETE':
                                $controller->destroy($id);
                                break;
                            default:
                                http_response_code(405);
                                echo json_encode([
                                    'success' => false,
                                    'error' => 'Method not allowed'
                                ]);
                        }
                    } else {
                        switch ($_SERVER['REQUEST_METHOD']) {
                            case 'GET':
                                $controller->index();
                                break;
                            case 'POST':
                                $controller->store();
                                break;
                            default:
                                http_response_code(405);
                                echo json_encode([
                                    'success' => false,
                                    'error' => 'Method not allowed'
                                ]);
                        }
                    }
            }
            break;

        case 'study-sessions':
            require_once __DIR__ . '/controllers/StudySessionController.php';
            $controller = new StudySessionController();

            switch ($action) {
                case 'start':
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        $controller->startSession();
                    } else {
                        http_response_code(405);
                        echo json_encode([
                            'success' => false,
                            'error' => 'Method not allowed'
                        ]);
                    }
                    break;
                case 'end':
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        $controller->endSession();
                    } else {
                        http_response_code(405);
                        echo json_encode([
                            'success' => false,
                            'error' => 'Method not allowed'
                        ]);
                    }
                    break;
                case 'active':
                    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                        $controller->getActiveSession();
                    } else {
                        http_response_code(405);
                        echo json_encode([
                            'success' => false,
                            'error' => 'Method not allowed'
                        ]);
                    }
                    break;
                case 'update-activity':
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        $controller->updateActivity();
                    } else {
                        http_response_code(405);
                        echo json_encode([
                            'success' => false,
                            'error' => 'Method not allowed'
                        ]);
                    }
                    break;
                case 'stats':
                    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                        $controller->getStats();
                    } else {
                        http_response_code(405);
                        echo json_encode([
                            'success' => false,
                            'error' => 'Method not allowed'
                        ]);
                    }
                    break;
                case 'daily-stats':
                    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                        $controller->getDailyStats();
                    } else {
                        http_response_code(405);
                        echo json_encode([
                            'success' => false,
                            'error' => 'Method not allowed'
                        ]);
                    }
                    break;
                case 'streak':
                    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                        $controller->getStreak();
                    } else {
                        http_response_code(405);
                        echo json_encode([
                            'success' => false,
                            'error' => 'Method not allowed'
                        ]);
                    }
                    break;
                default:
                    // Handle ID-based routes
                    if (isset($_GET['id'])) {
                        $id = $_GET['id'];
                        switch ($_SERVER['REQUEST_METHOD']) {
                            case 'GET':
                                $controller->getSession($id);
                                break;
                            case 'PUT':
                                $controller->updateSession($id);
                                break;
                            case 'DELETE':
                                $controller->deleteSession($id);
                                break;
                            default:
                                http_response_code(405);
                                echo json_encode([
                                    'success' => false,
                                    'error' => 'Method not allowed'
                                ]);
                        }
                    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
                        $controller->getUserSessions();
                    } else {
                        http_response_code(404);
                        echo json_encode([
                            'success' => false,
                            'error' => 'Study sessions action not found'
                        ]);
                    }
            }
            break;

        default:
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Resource not found'
            ]);
    }

} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ]);
}
?>