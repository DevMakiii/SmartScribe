<?php
// api/models/StudySession.php
require_once 'BaseModel.php';

class StudySession extends BaseModel {
    protected $table = 'study_sessions';
    protected $fillable = [
        'user_id',
        'session_date',
        'start_time',
        'end_time',
        'duration_minutes',
        'activities',
        'notes_studied',
        'quizzes_taken',
        'average_score',
        'focus_level'
    ];
    protected $hidden = [];
    protected $timestamps = false;

    /**
     * Get study sessions for a user within a date range
     */
    public function getUserSessions($userId, $startDate = null, $endDate = null, $limit = null) {
        $sql = "SELECT * FROM {$this->table} WHERE user_id = ?";
        $params = [$userId];

        if ($startDate && $endDate) {
            $sql .= " AND session_date BETWEEN ? AND ?";
            $params[] = $startDate;
            $params[] = $endDate;
        } elseif ($startDate) {
            $sql .= " AND session_date >= ?";
            $params[] = $startDate;
        } elseif ($endDate) {
            $sql .= " AND session_date <= ?";
            $params[] = $endDate;
        }

        $sql .= " ORDER BY session_date DESC, start_time DESC";

        if ($limit) {
            $sql .= " LIMIT ?";
            $params[] = $limit;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'formatResult'], $results);
    }

    /**
     * Get study statistics for a user
     */
    public function getUserStats($userId, $startDate = null, $endDate = null) {
        $sql = "SELECT
            COUNT(*) as total_sessions,
            SUM(duration_minutes) as total_minutes,
            AVG(duration_minutes) as avg_session_minutes,
            SUM(notes_studied) as total_notes_studied,
            SUM(quizzes_taken) as total_quizzes_taken,
            AVG(average_score) as avg_quiz_score,
            MAX(session_date) as last_session_date
        FROM {$this->table}
        WHERE user_id = ?";

        $params = [$userId];

        if ($startDate && $endDate) {
            $sql .= " AND session_date BETWEEN ? AND ?";
            $params[] = $startDate;
            $params[] = $endDate;
        } elseif ($startDate) {
            $sql .= " AND session_date >= ?";
            $params[] = $startDate;
        } elseif ($endDate) {
            $sql .= " AND session_date <= ?";
            $params[] = $endDate;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Format the results
        if ($result) {
            $result['total_hours'] = round($result['total_minutes'] / 60, 2);
            $result['avg_session_hours'] = round($result['avg_session_minutes'] / 60, 2);
            $result['avg_quiz_score'] = $result['avg_quiz_score'] ? round($result['avg_quiz_score'], 2) : null;
        }

        return $result;
    }

    /**
     * Get daily study time for a user within a date range
     */
    public function getDailyStats($userId, $startDate, $endDate) {
        $sql = "SELECT
            session_date,
            SUM(duration_minutes) as daily_minutes,
            COUNT(*) as session_count,
            SUM(notes_studied) as notes_studied,
            SUM(quizzes_taken) as quizzes_taken
        FROM {$this->table}
        WHERE user_id = ? AND session_date BETWEEN ? AND ?
        GROUP BY session_date
        ORDER BY session_date";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $startDate, $endDate]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format results
        foreach ($results as &$result) {
            $result['daily_hours'] = round($result['daily_minutes'] / 60, 2);
        }

        return $results;
    }

    /**
     * Start a new study session
     */
    public function startSession($userId, $activity = null) {
        $sessionData = [
            'user_id' => $userId,
            'session_date' => date('Y-m-d'),
            'start_time' => date('H:i:s'),
            'end_time' => date('H:i:s'), // Will be updated when session ends
            'duration_minutes' => 0, // Will be calculated when session ends
            'activities' => $activity ? json_encode([$activity]) : json_encode([]),
            'notes_studied' => 0,
            'quizzes_taken' => 0,
            'focus_level' => 'medium'
        ];

        $sessionId = $this->create($sessionData);

        if ($sessionId) {
            return $this->find($sessionId);
        }

        return false;
    }

    /**
     * End a study session and calculate duration
     */
    public function endSession($sessionId, $userId, $notesStudied = 0, $quizzesTaken = 0, $averageScore = null, $focusLevel = 'medium') {
        $session = $this->findByIdAndUser($sessionId, $userId);

        if (!$session) {
            return false;
        }

        $startTime = strtotime($session['start_time']);
        $endTime = time();
        $durationMinutes = round(($endTime - $startTime) / 60);

        // Update activities if provided
        $activities = json_decode($session['activities'], true) ?: [];

        $updateData = [
            'end_time' => date('H:i:s'),
            'duration_minutes' => $durationMinutes,
            'notes_studied' => $notesStudied,
            'quizzes_taken' => $quizzesTaken,
            'focus_level' => $focusLevel
        ];

        if ($averageScore !== null) {
            $updateData['average_score'] = $averageScore;
        }

        return $this->updateByIdAndUser($sessionId, $userId, $updateData);
    }

    /**
     * Update session activities
     */
    public function updateActivities($sessionId, $userId, $activity) {
        $session = $this->findByIdAndUser($sessionId, $userId);

        if (!$session) {
            return false;
        }

        $activities = json_decode($session['activities'], true) ?: [];
        $activities[] = $activity;

        return $this->updateByIdAndUser($sessionId, $userId, [
            'activities' => json_encode(array_unique($activities))
        ]);
    }

    /**
     * Get current active session for user
     */
    public function getActiveSession($userId) {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table}
            WHERE user_id = ? AND session_date = CURDATE() AND duration_minutes = 0
            ORDER BY start_time DESC LIMIT 1
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? $this->formatResult($result) : null;
    }

    /**
     * Get study streak for user
     */
    public function getStudyStreak($userId) {
        $sql = "
            SELECT
                COUNT(DISTINCT session_date) as streak_days,
                DATEDIFF(CURDATE(), MAX(session_date)) as days_since_last_session
            FROM {$this->table}
            WHERE user_id = ? AND session_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: ['streak_days' => 0, 'days_since_last_session' => null];
    }
}
?>