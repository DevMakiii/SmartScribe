<?php
require_once __DIR__ . '/BaseController.php';

class ProgressController extends BaseController {
    public function __construct() {
        parent::__construct();
    }

    public function getStats() {
        $userId = $this->getUserIdFromHeader();

        if (!$userId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }

        try {
            $stats = [];

            // Get total notes count
            $notesQuery = "SELECT COUNT(*) as total FROM notes WHERE user_id = :user_id";
            $stmt = $this->db->prepare($notesQuery);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            $totalNotesResult = $stmt->fetch();
            $stats['totalNotes'] = $totalNotesResult ? (int)$totalNotesResult['total'] : 0;

            // Get notes created this week
            $weekAgo = date('Y-m-d H:i:s', strtotime('-7 days'));
            $notesThisWeekQuery = "SELECT COUNT(*) as count FROM notes WHERE user_id = :user_id AND created_at >= :week_ago";
            $stmt = $this->db->prepare($notesThisWeekQuery);
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':week_ago', $weekAgo);
            $stmt->execute();
            $notesThisWeekResult = $stmt->fetch();
            $stats['notesThisWeek'] = $notesThisWeekResult ? (int)$notesThisWeekResult['count'] : 0;

            // Get quiz statistics
            $quizStatsQuery = "
                SELECT
                    COUNT(*) as total_quizzes,
                    AVG(score) as average_score,
                    COUNT(CASE WHEN score >= 80 THEN 1 END) as high_scores
                FROM quizzes q
                INNER JOIN notes n ON q.note_id = n.id
                WHERE n.user_id = :user_id
            ";
            $stmt = $this->db->prepare($quizStatsQuery);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            $quizData = $stmt->fetch() ?: [];

            $stats['quizzesCompleted'] = (int)($quizData['total_quizzes'] ?? 0);
            $stats['quizAverage'] = $quizData['average_score'] ? round($quizData['average_score'], 1) : 0;

            // Get study time estimation (based on note creation patterns)
            $studyTimeQuery = "
                SELECT
                    COUNT(*) as notes_today,
                    TIMESTAMPDIFF(MINUTE, MIN(created_at), MAX(created_at)) as time_span_minutes
                FROM notes
                WHERE user_id = :user_id
                AND DATE(created_at) = CURDATE()
            ";
            $stmt = $this->db->prepare($studyTimeQuery);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            $studyData = $stmt->fetch() ?: ['notes_today' => 0, 'time_span_minutes' => 0];

            // Estimate study hours (simple calculation)
            $estimatedHours = 0;
            if ($studyData['notes_today'] > 0) {
                // Assume 15 minutes per note on average
                $estimatedHours = round(($studyData['notes_today'] * 15) / 60, 1);
            }
            $stats['studyHours'] = (float)$estimatedHours;
            $stats['studyHoursThisWeek'] = (float)$estimatedHours; // Simplified for now

            // Get weekly activity data (last 7 days)
            $weeklyActivity = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                $activityQuery = "SELECT COUNT(*) as count FROM notes WHERE user_id = :user_id AND DATE(created_at) = :date";
                $stmt = $this->db->prepare($activityQuery);
                $stmt->bindParam(':user_id', $userId);
                $stmt->bindParam(':date', $date);
                $stmt->execute();
                $activityResult = $stmt->fetch();
                $count = $activityResult ? (int)$activityResult['count'] : 0;

                $weeklyActivity[] = [
                    'name' => date('D', strtotime($date)),
                    'activity' => $count > 0 ? min($count * 0.1, 1.0) : 0 // Normalize activity
                ];
            }
            $stats['weeklyActivity'] = $weeklyActivity;

            // Get subject distribution (based on note content keywords or titles)
            $subjectQuery = "
                SELECT
                    COUNT(*) as count,
                    CASE
                        WHEN LOWER(title) LIKE '%biology%' OR LOWER(original_text) LIKE '%biology%' THEN 'Biology'
                        WHEN LOWER(title) LIKE '%history%' OR LOWER(original_text) LIKE '%history%' THEN 'History'
                        WHEN LOWER(title) LIKE '%math%' OR LOWER(original_text) LIKE '%math%' THEN 'Mathematics'
                        WHEN LOWER(title) LIKE '%physics%' OR LOWER(original_text) LIKE '%physics%' THEN 'Physics'
                        WHEN LOWER(title) LIKE '%literature%' OR LOWER(original_text) LIKE '%literature%' THEN 'Literature'
                        ELSE 'Other'
                    END as subject
                FROM notes
                WHERE user_id = :user_id
                GROUP BY subject
                ORDER BY count DESC
                LIMIT 5
            ";
            $stmt = $this->db->prepare($subjectQuery);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            $subjectsData = $stmt->fetchAll() ?: [];

            $totalNotes = array_sum(array_column($subjectsData, 'count'));
            $subjects = [];
            $colors = ['bg-green-500', 'bg-blue-500', 'bg-yellow-500', 'bg-purple-500', 'bg-red-500'];

            foreach ($subjectsData as $index => $subject) {
                $percentage = $totalNotes > 0 ? round(($subject['count'] / $totalNotes) * 100, 1) : 0;
                $subjects[] = [
                    'name' => $subject['subject'],
                    'percentage' => $percentage,
                    'color' => $colors[$index % count($colors)]
                ];
            }
            $stats['subjects'] = $subjects;

            // Get recent activity (last 5 notes)
            $recentActivityQuery = "
                SELECT
                    title,
                    created_at,
                    'note_created' as activity_type
                FROM notes
                WHERE user_id = :user_id
                ORDER BY created_at DESC
                LIMIT 5
            ";
            $stmt = $this->db->prepare($recentActivityQuery);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            $recentNotes = $stmt->fetchAll() ?: [];

            // Format recent activity
            $recentActivity = [];
            foreach ($recentNotes as $note) {
                $recentActivity[] = [
                    'title' => 'Created note: ' . substr($note['title'], 0, 30) . '...',
                    'time' => $this->formatTimeAgo($note['created_at']),
                    'icon' => ['fas', 'file-alt'],
                    'iconColor' => 'bg-blue-600'
                ];
            }

            // If no recent activity, add a placeholder
            if (empty($recentActivity)) {
                $recentActivity[] = [
                    'title' => 'Welcome to SmartScribe!',
                    'time' => 'Just now',
                    'icon' => ['fas', 'rocket'],
                    'iconColor' => 'bg-green-600'
                ];
            }
            $stats['recentActivity'] = $recentActivity;

            echo json_encode([
                'success' => true,
                'data' => $stats
            ]);

        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => 'Failed to fetch progress statistics: ' . $e->getMessage()
            ]);
        }
    }

    private function formatTimeAgo($datetime) {
        $now = new DateTime();
        $created = new DateTime($datetime);
        $diff = $now->diff($created);

        if ($diff->d > 0) {
            return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
        } elseif ($diff->h > 0) {
            return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
        } elseif ($diff->i > 0) {
            return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
        } else {
            return 'Just now';
        }
    }

    private function getUserIdFromHeader() {
        $headers = getallheaders();

        // First try to validate token from Authorization header (case-insensitive)
        $authHeader = null;
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'authorization') {
                $authHeader = $value;
                break;
            }
        }

        if ($authHeader) {
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                $token = $matches[1];
                $userId = $this->validateToken($token);
                if ($userId) {
                    return $userId;
                }
            }
        }

        // Fallback to X-User-ID header (for backward compatibility)
        if (isset($headers['X-User-ID']) || isset($headers['x-user-id'])) {
            $userIdHeader = $headers['X-User-ID'] ?? $headers['x-user-id'];
            return intval($userIdHeader);
        }

        return null;
    }

    public function startStudySession() {
        $userId = $this->getUserIdFromHeader();

        if (!$userId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }

        try {
            // Get JSON input
            $input = json_decode(file_get_contents('php://input'), true);
            $activities = $input['activities'] ?? [];
            $startTime = $input['startTime'] ?? date('Y-m-d H:i:s');

            // Check if user already has an active session
            $checkQuery = "SELECT id FROM study_sessions WHERE user_id = :user_id AND end_time IS NULL";
            $stmt = $this->db->prepare($checkQuery);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();

            if ($stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'User already has an active study session']);
                return;
            }

            // Create new study session
            $insertQuery = "
                INSERT INTO study_sessions
                (user_id, session_date, start_time, activities, notes_studied, quizzes_taken, created_at)
                VALUES (:user_id, :session_date, :start_time, :activities, 0, 0, NOW())
            ";

            $stmt = $this->db->prepare($insertQuery);
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':session_date', date('Y-m-d', strtotime($startTime)));
            $stmt->bindParam(':start_time', $startTime);
            $stmt->bindParam(':activities', json_encode($activities));

            if ($stmt->execute()) {
                $sessionId = $this->db->lastInsertId();
                echo json_encode([
                    'success' => true,
                    'session_id' => $sessionId,
                    'start_time' => $startTime,
                    'message' => 'Study session started successfully'
                ]);
            } else {
                throw new Exception('Failed to create study session');
            }

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to start study session: ' . $e->getMessage()
            ]);
        }
    }

    public function endStudySession() {
        $userId = $this->getUserIdFromHeader();

        if (!$userId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }

        try {
            // Get JSON input
            $input = json_decode(file_get_contents('php://input'), true);
            $sessionId = $input['session_id'] ?? null;

            if (!$sessionId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Session ID is required']);
                return;
            }

            // Get the active session
            $selectQuery = "
                SELECT id, start_time, activities, notes_studied, quizzes_taken
                FROM study_sessions
                WHERE id = :session_id AND user_id = :user_id AND end_time IS NULL
            ";
            $stmt = $this->db->prepare($selectQuery);
            $stmt->bindParam(':session_id', $sessionId);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();

            $session = $stmt->fetch();
            if (!$session) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Active study session not found']);
                return;
            }

            // Calculate duration
            $startTime = new DateTime($session['start_time']);
            $endTime = new DateTime();
            $duration = $startTime->diff($endTime);
            $durationMinutes = ($duration->days * 24 * 60) + ($duration->h * 60) + $duration->i;

            // Update the session with end time and duration
            $updateQuery = "
                UPDATE study_sessions
                SET end_time = :end_time,
                    duration_minutes = :duration_minutes,
                    notes_studied = :notes_studied,
                    quizzes_taken = :quizzes_taken
                WHERE id = :session_id AND user_id = :user_id
            ";

            $stmt = $this->db->prepare($updateQuery);
            $stmt->bindParam(':end_time', $endTime->format('Y-m-d H:i:s'));
            $stmt->bindParam(':duration_minutes', $durationMinutes);
            $stmt->bindParam(':notes_studied', $session['notes_studied']);
            $stmt->bindParam(':quizzes_taken', $session['quizzes_taken']);
            $stmt->bindParam(':session_id', $sessionId);
            $stmt->bindParam(':user_id', $userId);

            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'session_id' => $sessionId,
                    'duration_minutes' => $durationMinutes,
                    'end_time' => $endTime->format('Y-m-d H:i:s'),
                    'message' => 'Study session ended successfully'
                ]);
            } else {
                throw new Exception('Failed to end study session');
            }

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to end study session: ' . $e->getMessage()
            ]);
        }
    }

}
?>