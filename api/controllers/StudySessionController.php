<?php
// api/controllers/StudySessionController.php
require_once 'BaseController.php';
require_once __DIR__ . '/../models/StudySession.php';
require_once __DIR__ . '/../models/Goal.php';

class StudySessionController extends BaseController {

    private $studySessionModel;
    private $goalModel;

    public function __construct() {
        parent::__construct();
        $this->studySessionModel = new StudySession();
        $this->goalModel = new Goal($this->db);
    }

    /**
     * Start a new study session
     * POST /api/study-sessions/start
     */
    public function startSession() {
        if (!$this->requireAuth()) {
            return;
        }

        $userId = $this->getUserId();

        // Get request data
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $activity = $data['activity'] ?? 'general_study';

        // Check if user already has an active session
        $activeSession = $this->studySessionModel->getActiveSession($userId);
        if ($activeSession) {
            $this->successResponse($activeSession, 'Resumed existing active session');
            return;
        }

        // Start new session
        $session = $this->studySessionModel->startSession($userId, $activity);

        if ($session) {
            $this->successResponse($session, 'Study session started successfully');
        } else {
            $this->errorResponse('Failed to start study session');
        }
    }

    /**
     * End current study session
     * POST /api/study-sessions/end
     */
    public function endSession() {
        if (!$this->requireAuth()) {
            return;
        }

        $userId = $this->getUserId();

        // Get request data
        $data = json_decode(file_get_contents('php://input'), true) ?: [];

        $sessionId = $data['session_id'] ?? null;
        $notesStudied = $data['notes_studied'] ?? 0;
        $quizzesTaken = $data['quizzes_taken'] ?? 0;
        $averageScore = $data['average_score'] ?? null;
        $focusLevel = $data['focus_level'] ?? 'medium';

        // If no session_id provided, try to find active session
        if (!$sessionId) {
            $activeSession = $this->studySessionModel->getActiveSession($userId);
            if ($activeSession) {
                $sessionId = $activeSession['id'];
            } else {
                $this->errorResponse('No active session found', 404);
                return;
            }
        }

        // End the session
        $result = $this->studySessionModel->endSession(
            $sessionId,
            $userId,
            $notesStudied,
            $quizzesTaken,
            $averageScore,
            $focusLevel
        );

        if ($result) {
            // Update goal progress for study time and accuracy
            try {
                $studyTimeGoalsUpdated = $this->goalModel->updateProgressForStudyTime($userId);
                $accuracyGoalsUpdated = $this->goalModel->updateProgressForAccuracy($userId);
                error_log("StudySessionController::endSession() - Updated $studyTimeGoalsUpdated study time goals and $accuracyGoalsUpdated accuracy goals for user $userId");
            } catch (Exception $e) {
                error_log("StudySessionController::endSession() - Error updating goals: " . $e->getMessage());
                // Don't fail the session end if goal update fails
            }

            $session = $this->studySessionModel->findByIdAndUser($sessionId, $userId);
            $this->successResponse($session, 'Study session ended successfully');
        } else {
            $this->errorResponse('Failed to end study session');
        }
    }

    /**
     * Get current active session
     * GET /api/study-sessions/active
     */
    public function getActiveSession() {
        if (!$this->requireAuth()) {
            return;
        }

        $userId = $this->getUserId();
        $activeSession = $this->studySessionModel->getActiveSession($userId);

        if ($activeSession) {
            $this->successResponse($activeSession, 'Active session found');
        } else {
            $this->successResponse(null, 'No active session');
        }
    }

    /**
     * Update session activities
     * POST /api/study-sessions/update-activity
     */
    public function updateActivity() {
        if (!$this->requireAuth()) {
            return;
        }

        $userId = $this->getUserId();

        // Get request data
        $data = json_decode(file_get_contents('php://input'), true) ?: [];

        $validationErrors = $this->validateRequired($data, ['session_id', 'activity']);
        if (!empty($validationErrors)) {
            $this->validationErrorResponse($validationErrors);
            return;
        }

        $sessionId = $data['session_id'];
        $activity = $data['activity'];

        $result = $this->studySessionModel->updateActivities($sessionId, $userId, $activity);

        if ($result) {
            $session = $this->studySessionModel->findByIdAndUser($sessionId, $userId);
            $this->successResponse($session, 'Activity updated successfully');
        } else {
            $this->errorResponse('Failed to update activity');
        }
    }

    /**
     * Get user's study sessions
     * GET /api/study-sessions
     */
    public function getUserSessions() {
        if (!$this->requireAuth()) {
            return;
        }

        $userId = $this->getUserId();

        // Get query parameters
        $startDate = $_GET['start_date'] ?? null;
        $endDate = $_GET['end_date'] ?? null;
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : null;

        $sessions = $this->studySessionModel->getUserSessions($userId, $startDate, $endDate, $limit);

        $this->successResponse($sessions, 'Study sessions retrieved successfully');
    }

    /**
     * Get study statistics
     * GET /api/study-sessions/stats
     */
    public function getStats() {
        if (!$this->requireAuth()) {
            return;
        }

        $userId = $this->getUserId();

        // Get query parameters
        $startDate = $_GET['start_date'] ?? null;
        $endDate = $_GET['end_date'] ?? null;

        $stats = $this->studySessionModel->getUserStats($userId, $startDate, $endDate);

        if ($stats) {
            $this->successResponse($stats, 'Study statistics retrieved successfully');
        } else {
            $this->successResponse([
                'total_sessions' => 0,
                'total_minutes' => 0,
                'total_hours' => 0,
                'avg_session_minutes' => 0,
                'avg_session_hours' => 0,
                'total_notes_studied' => 0,
                'total_quizzes_taken' => 0,
                'avg_quiz_score' => null,
                'last_session_date' => null
            ], 'No study data found');
        }
    }

    /**
     * Get daily study statistics
     * GET /api/study-sessions/daily-stats
     */
    public function getDailyStats() {
        if (!$this->requireAuth()) {
            return;
        }

        $userId = $this->getUserId();

        // Get query parameters
        $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $_GET['end_date'] ?? date('Y-m-d');

        $dailyStats = $this->studySessionModel->getDailyStats($userId, $startDate, $endDate);

        $this->successResponse($dailyStats, 'Daily study statistics retrieved successfully');
    }

    /**
     * Get study streak information
     * GET /api/study-sessions/streak
     */
    public function getStreak() {
        if (!$this->requireAuth()) {
            return;
        }

        $userId = $this->getUserId();

        $streak = $this->studySessionModel->getStudyStreak($userId);

        $this->successResponse($streak, 'Study streak information retrieved successfully');
    }

    /**
     * Get specific session details
     * GET /api/study-sessions/{id}
     */
    public function getSession($id) {
        if (!$this->requireAuth()) {
            return;
        }

        $userId = $this->getUserId();

        $session = $this->studySessionModel->findByIdAndUser($id, $userId);

        if ($session) {
            $this->successResponse($session, 'Session details retrieved successfully');
        } else {
            $this->notFoundResponse('Session not found');
        }
    }

    /**
     * Update session details
     * PUT /api/study-sessions/{id}
     */
    public function updateSession($id) {
        if (!$this->requireAuth()) {
            return;
        }

        $userId = $this->getUserId();

        // Get request data
        $data = json_decode(file_get_contents('php://input'), true) ?: [];

        $result = $this->studySessionModel->updateByIdAndUser($id, $userId, $data);

        if ($result) {
            $session = $this->studySessionModel->findByIdAndUser($id, $userId);
            $this->successResponse($session, 'Session updated successfully');
        } else {
            $this->errorResponse('Failed to update session');
        }
    }

    /**
     * Delete a session
     * DELETE /api/study-sessions/{id}
     */
    public function deleteSession($id) {
        if (!$this->requireAuth()) {
            return;
        }

        $userId = $this->getUserId();

        $result = $this->studySessionModel->deleteByIdAndUser($id, $userId);

        if ($result) {
            $this->successResponse(null, 'Session deleted successfully');
        } else {
            $this->errorResponse('Failed to delete session');
        }
    }
}
?>