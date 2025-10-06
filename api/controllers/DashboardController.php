<?php
require_once __DIR__ . '/BaseController.php';

class DashboardController extends BaseController {
    public function __construct() {
        parent::__construct();
    }

    public function getStats() {
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

            // Get total notes count
            $totalNotesQuery = "SELECT COUNT(*) as total FROM notes WHERE user_id = :user_id";
            $stmt = $this->db->prepare($totalNotesQuery);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            $totalNotesResult = $stmt->fetch();
            $totalNotes = $totalNotesResult ? (int)$totalNotesResult['total'] : 0;

            // Get notes created this week
            $weekAgo = date('Y-m-d H:i:s', strtotime('-7 days'));
            $notesThisWeekQuery = "SELECT COUNT(*) as count FROM notes WHERE user_id = :user_id AND created_at >= :week_ago";
            $stmt = $this->db->prepare($notesThisWeekQuery);
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':week_ago', $weekAgo);
            $stmt->execute();
            $notesThisWeekResult = $stmt->fetch();
            $notesThisWeek = $notesThisWeekResult ? (int)$notesThisWeekResult['count'] : 0;

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

            $totalQuizzes = $quizData['total_quizzes'] ?? 0;
            $averageScore = $quizData['average_score'] ? round($quizData['average_score'], 1) : 0;

            // Get real study time from study_sessions table
            $totalStudyTimeQuery = "
                SELECT SUM(duration_minutes) as total_minutes
                FROM study_sessions
                WHERE user_id = :user_id
            ";
            $stmt = $this->db->prepare($totalStudyTimeQuery);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            $totalStudyData = $stmt->fetch() ?: ['total_minutes' => 0];
            $totalStudyMinutes = $totalStudyData['total_minutes'] ?? 0;
            $totalStudyHours = round($totalStudyMinutes / 60, 1);

            // Get study time for this week
            $weekAgo = date('Y-m-d', strtotime('-7 days'));
            $weeklyStudyTimeQuery = "
                SELECT SUM(duration_minutes) as weekly_minutes
                FROM study_sessions
                WHERE user_id = :user_id AND session_date >= :week_ago
            ";
            $stmt = $this->db->prepare($weeklyStudyTimeQuery);
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':week_ago', $weekAgo);
            $stmt->execute();
            $weeklyStudyData = $stmt->fetch() ?: ['weekly_minutes' => 0];
            $weeklyStudyMinutes = $weeklyStudyData['weekly_minutes'] ?? 0;
            $weeklyStudyHours = round($weeklyStudyMinutes / 60, 1);

            // Get additional study session statistics
            $studySessionStatsQuery = "
                SELECT
                    COUNT(*) as total_sessions,
                    AVG(duration_minutes) as avg_session_duration,
                    MAX(duration_minutes) as longest_session,
                    SUM(notes_studied) as total_notes_studied,
                    SUM(quizzes_taken) as total_quizzes_taken
                FROM study_sessions
                WHERE user_id = :user_id
            ";
            $stmt = $this->db->prepare($studySessionStatsQuery);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            $sessionStats = $stmt->fetch() ?: [
                'total_sessions' => 0,
                'avg_session_duration' => 0,
                'longest_session' => 0,
                'total_notes_studied' => 0,
                'total_quizzes_taken' => 0
            ];

            // Check if learning_goals table exists
            $tableCheckQuery = "SHOW TABLES LIKE 'learning_goals'";
            $stmt = $this->db->prepare($tableCheckQuery);
            $stmt->execute();
            $tableExists = $stmt->fetch();

            error_log("DEBUG: Table check result: " . ($tableExists ? 'EXISTS' : 'DOES NOT EXIST'));

            if (!$tableExists) {
                error_log("DEBUG: learning_goals table does not exist in database");
                throw new Exception("Table 'learning_goals' doesn't exist");
            }

            // Get goals statistics
            error_log("DEBUG: Fetching goals stats for user_id: $userId");
            $goalsStatsQuery = "
                SELECT
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_goals,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_goals
                FROM learning_goals
                WHERE user_id = :user_id
            ";
            $stmt = $this->db->prepare($goalsStatsQuery);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            $goalsStats = $stmt->fetch() ?: [
                'active_goals' => 0,
                'completed_goals' => 0
            ];
            error_log("DEBUG: Goals stats result: " . json_encode($goalsStats));

            // Calculate study streak (consecutive days with study sessions)
            $streakQuery = "
                SELECT session_date
                FROM study_sessions
                WHERE user_id = :user_id
                ORDER BY session_date DESC
            ";
            $stmt = $this->db->prepare($streakQuery);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            $sessionDates = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $studyStreak = 0;
            if (!empty($sessionDates)) {
                $today = date('Y-m-d');
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                $currentStreak = 0;
                $lastDate = null;

                foreach ($sessionDates as $date) {
                    $dateOnly = date('Y-m-d', strtotime($date));

                    if ($lastDate === null) {
                        // First date in the list
                        $currentStreak = 1;
                        $lastDate = $dateOnly;
                    } elseif ($dateOnly === date('Y-m-d', strtotime($lastDate . ' -1 day'))) {
                        // Consecutive day
                        $currentStreak++;
                        $lastDate = $dateOnly;
                    } elseif ($dateOnly !== $lastDate) {
                        // Gap in dates, streak broken
                        break;
                    }
                }

                // Check if today or yesterday has a session to continue the streak
                $mostRecentDate = $sessionDates[0] ? date('Y-m-d', strtotime($sessionDates[0])) : null;

                if ($mostRecentDate === $today || $mostRecentDate === $yesterday) {
                    $studyStreak = $currentStreak;
                } else {
                    $studyStreak = 0; // Streak broken
                }
            }

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

            $stats = [
                'totalNotes' => (int)$totalNotes,
                'notesThisWeek' => (int)$notesThisWeek,
                'studyHours' => (float)$totalStudyHours,
                'studyHoursThisWeek' => (float)$weeklyStudyHours,
                'quizAverage' => (float)$averageScore,
                'quizzesCompleted' => (int)$totalQuizzes,
                'activeGoals' => (int)$goalsStats['active_goals'],
                'completedGoals' => (int)$goalsStats['completed_goals'],
                'studyStreak' => (int)$studyStreak,
                'avgSessionDuration' => round($sessionStats['avg_session_duration'] ?? 0, 1),
                'longestSession' => (int)$sessionStats['longest_session'],
                'totalNotesStudied' => (int)$sessionStats['total_notes_studied'],
                'totalQuizzesTaken' => (int)$sessionStats['total_quizzes_taken'],
                'recentActivity' => $recentActivity,
                'lastUpdated' => date('Y-m-d H:i:s')
            ];

            error_log("DEBUG: Final stats response: " . json_encode($stats));
            $this->successResponse($stats);

        } catch (Exception $e) {
            $this->errorResponse('Failed to fetch dashboard statistics: ' . $e->getMessage());
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

}
?>