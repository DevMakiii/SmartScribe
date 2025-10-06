<?php

class Goal {
    private $conn;
    public $user_id;
    public $title;
    public $description;
    public $target_type;
    public $target_value;
    public $current_value;
    public $deadline;
    public $status;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO learning_goals (user_id, title, description, target_type, target_value, current_value, deadline, status, created_at)
                  VALUES (:user_id, :title, :description, :target_type, :target_value, :current_value, :deadline, :status, NOW())";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':user_id', $this->user_id);
        $stmt->bindParam(':title', $this->title);
        $stmt->bindParam(':description', $this->description);
        $stmt->bindParam(':target_type', $this->target_type);
        $stmt->bindParam(':target_value', $this->target_value);
        $stmt->bindParam(':current_value', $this->current_value);
        $stmt->bindParam(':deadline', $this->deadline);
        $stmt->bindParam(':status', $this->status);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }

        return false;
    }

    public function readAllByUser($userId) {
        $query = "SELECT * FROM learning_goals WHERE user_id = :user_id ORDER BY created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();

        $goals = $stmt->fetchAll();
        error_log("Goal::readAllByUser() - Retrieved " . count($goals) . " goals for user $userId");
        foreach ($goals as $goal) {
            error_log("Goal::readAllByUser() - Goal ID {$goal['id']}: {$goal['title']} - Type: {$goal['target_type']} - Progress: {$goal['current_value']}/{$goal['target_value']} - Status: {$goal['status']}");
        }

        // Re-prepare and execute for return
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();

        return $stmt;
    }

    public function readById($id, $userId) {
        $query = "SELECT * FROM learning_goals WHERE id = :id AND user_id = :user_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();

        return $stmt->fetch();
    }

    public function update($id, $data) {
        $updateFields = [];
        $params = [':id' => $id];

        if (isset($data['title'])) {
            $updateFields[] = "title = :title";
            $params[':title'] = $data['title'];
        }

        if (isset($data['description'])) {
            $updateFields[] = "description = :description";
            $params[':description'] = $data['description'];
        }

        if (isset($data['target_type'])) {
            $updateFields[] = "target_type = :target_type";
            $params[':target_type'] = $data['target_type'];
        }

        if (isset($data['target_value'])) {
            $updateFields[] = "target_value = :target_value";
            $params[':target_value'] = $data['target_value'];
        }

        if (isset($data['current_value'])) {
            $updateFields[] = "current_value = :current_value";
            $params[':current_value'] = $data['current_value'];
        }

        if (isset($data['deadline'])) {
            $updateFields[] = "deadline = :deadline";
            $params[':deadline'] = $data['deadline'];
        }

        if (isset($data['status'])) {
            $updateFields[] = "status = :status";
            $params[':status'] = $data['status'];
        }

        if (empty($updateFields)) {
            return false;
        }

        $query = "UPDATE learning_goals SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = :id";
        $stmt = $this->conn->prepare($query);

        return $stmt->execute($params);
    }

    public function updateProgress($id, $userId, $newValue) {
        $query = "UPDATE learning_goals SET current_value = :current_value, updated_at = NOW() WHERE id = :id AND user_id = :user_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':current_value', $newValue);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':user_id', $userId);

        return $stmt->execute();
    }

    public function delete($id, $userId) {
        $query = "DELETE FROM learning_goals WHERE id = :id AND user_id = :user_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':user_id', $userId);

        return $stmt->execute();
    }

    public function getActiveGoalsCount($userId) {
        $query = "SELECT COUNT(*) as count FROM learning_goals WHERE user_id = :user_id AND status = 'active'";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();

        $result = $stmt->fetch();
        return $result['count'] ?? 0;
    }

    public function getCompletedGoalsThisMonth($userId) {
        $query = "SELECT COUNT(*) as count FROM learning_goals
                  WHERE user_id = :user_id AND status = 'completed'
                  AND MONTH(updated_at) = MONTH(CURRENT_DATE())
                  AND YEAR(updated_at) = YEAR(CURRENT_DATE())";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();

        $result = $stmt->fetch();
        return $result['count'] ?? 0;
    }

    public function updateProgressForNotes($userId) {
        // Get count of notes for this user
        $notesQuery = "SELECT COUNT(*) as note_count FROM notes WHERE user_id = :user_id";
        $notesStmt = $this->conn->prepare($notesQuery);
        $notesStmt->bindParam(':user_id', $userId);
        $notesStmt->execute();
        $result = $notesStmt->fetch();
        $noteCount = $result['note_count'];

        error_log("Goal::updateProgressForNotes() - User $userId has $noteCount notes");

        // Update all active note goals for this user
        $goalsQuery = "SELECT id, title, target_value, current_value FROM learning_goals
                      WHERE user_id = :user_id AND target_type = 'notes' AND status = 'active'";
        $goalsStmt = $this->conn->prepare($goalsQuery);
        $goalsStmt->bindParam(':user_id', $userId);
        $goalsStmt->execute();
        $goals = $goalsStmt->fetchAll();

        error_log("Goal::updateProgressForNotes() - Found " . count($goals) . " active note goals for user $userId");

        foreach ($goals as $goal) {
            $newValue = min($noteCount, $goal['target_value']); // Don't exceed target
            $status = ($newValue >= $goal['target_value']) ? 'completed' : 'active';

            error_log("Goal::updateProgressForNotes() - Updating goal {$goal['id']} ({$goal['title']}): {$goal['current_value']} -> $newValue, status: $status");

            $updateQuery = "UPDATE learning_goals SET current_value = :current_value, status = :status, updated_at = NOW()
                           WHERE id = :id AND user_id = :user_id";
            $updateStmt = $this->conn->prepare($updateQuery);
            $updateStmt->bindParam(':current_value', $newValue);
            $updateStmt->bindParam(':status', $status);
            $updateStmt->bindParam(':id', $goal['id']);
            $updateStmt->bindParam(':user_id', $userId);
            $updateStmt->execute();
        }

        return count($goals);
    }

    public function updateProgressForQuizzes($userId) {
        // Get count of quizzes for this user
        $quizzesQuery = "SELECT COUNT(*) as quiz_count FROM quizzes WHERE user_id = :user_id";
        $quizzesStmt = $this->conn->prepare($quizzesQuery);
        $quizzesStmt->bindParam(':user_id', $userId);
        $quizzesStmt->execute();
        $result = $quizzesStmt->fetch();
        $quizCount = $result['quiz_count'];

        error_log("Goal::updateProgressForQuizzes() - User $userId has $quizCount quizzes");

        // Update all active quiz goals for this user
        $goalsQuery = "SELECT id, title, target_value, current_value FROM learning_goals
                      WHERE user_id = :user_id AND target_type = 'quizzes' AND status = 'active'";
        $goalsStmt = $this->conn->prepare($goalsQuery);
        $goalsStmt->bindParam(':user_id', $userId);
        $goalsStmt->execute();
        $goals = $goalsStmt->fetchAll();

        error_log("Goal::updateProgressForQuizzes() - Found " . count($goals) . " active quiz goals for user $userId");

        foreach ($goals as $goal) {
            $newValue = min($quizCount, $goal['target_value']); // Don't exceed target
            $status = ($newValue >= $goal['target_value']) ? 'completed' : 'active';

            error_log("Goal::updateProgressForQuizzes() - Updating goal {$goal['id']} ({$goal['title']}): {$goal['current_value']} -> $newValue, status: $status");

            $updateQuery = "UPDATE learning_goals SET current_value = :current_value, status = :status, updated_at = NOW()
                           WHERE id = :id AND user_id = :user_id";
            $updateStmt = $this->conn->prepare($updateQuery);
            $updateStmt->bindParam(':current_value', $newValue);
            $updateStmt->bindParam(':status', $status);
            $updateStmt->bindParam(':id', $goal['id']);
            $updateStmt->bindParam(':user_id', $userId);
            $updateStmt->execute();
        }

        return count($goals);
    }

    public function updateProgressForStudyTime($userId) {
        // Get total study time in hours for this user
        $studyTimeQuery = "SELECT SUM(duration_minutes) as total_minutes FROM study_sessions WHERE user_id = :user_id";
        $studyTimeStmt = $this->conn->prepare($studyTimeQuery);
        $studyTimeStmt->bindParam(':user_id', $userId);
        $studyTimeStmt->execute();
        $result = $studyTimeStmt->fetch();
        $totalMinutes = $result['total_minutes'] ?? 0;
        $totalHours = round($totalMinutes / 60, 2); // Convert to hours

        error_log("Goal::updateProgressForStudyTime() - User $userId has $totalHours hours of study time");

        // Update all active study_time goals for this user
        $goalsQuery = "SELECT id, title, target_value, current_value FROM learning_goals
                      WHERE user_id = :user_id AND target_type = 'study_time' AND status = 'active'";
        $goalsStmt = $this->conn->prepare($goalsQuery);
        $goalsStmt->bindParam(':user_id', $userId);
        $goalsStmt->execute();
        $goals = $goalsStmt->fetchAll();

        error_log("Goal::updateProgressForStudyTime() - Found " . count($goals) . " active study_time goals for user $userId");

        foreach ($goals as $goal) {
            $newValue = min($totalHours, $goal['target_value']); // Don't exceed target
            $status = ($newValue >= $goal['target_value']) ? 'completed' : 'active';

            error_log("Goal::updateProgressForStudyTime() - Updating goal {$goal['id']} ({$goal['title']}): {$goal['current_value']} -> $newValue, status: $status");

            $updateQuery = "UPDATE learning_goals SET current_value = :current_value, status = :status, updated_at = NOW()
                           WHERE id = :id AND user_id = :user_id";
            $updateStmt = $this->conn->prepare($updateQuery);
            $updateStmt->bindParam(':current_value', $newValue);
            $updateStmt->bindParam(':status', $status);
            $updateStmt->bindParam(':id', $goal['id']);
            $updateStmt->bindParam(':user_id', $userId);
            $updateStmt->execute();
        }

        return count($goals);
    }

    public function updateProgressForAccuracy($userId) {
        // Get average quiz score for this user from study sessions
        $accuracyQuery = "SELECT AVG(average_score) as avg_accuracy FROM study_sessions
                         WHERE user_id = :user_id AND average_score IS NOT NULL AND average_score > 0";
        $accuracyStmt = $this->conn->prepare($accuracyQuery);
        $accuracyStmt->bindParam(':user_id', $userId);
        $accuracyStmt->execute();
        $result = $accuracyStmt->fetch();
        $avgAccuracy = $result['avg_accuracy'] ? round($result['avg_accuracy'], 2) : 0;

        error_log("Goal::updateProgressForAccuracy() - User $userId has $avgAccuracy% average accuracy");

        // Update all active accuracy goals for this user
        $goalsQuery = "SELECT id, title, target_value, current_value FROM learning_goals
                      WHERE user_id = :user_id AND target_type = 'accuracy' AND status = 'active'";
        $goalsStmt = $this->conn->prepare($goalsQuery);
        $goalsStmt->bindParam(':user_id', $userId);
        $goalsStmt->execute();
        $goals = $goalsStmt->fetchAll();

        error_log("Goal::updateProgressForAccuracy() - Found " . count($goals) . " active accuracy goals for user $userId");

        foreach ($goals as $goal) {
            $newValue = min($avgAccuracy, $goal['target_value']); // Don't exceed target
            $status = ($newValue >= $goal['target_value']) ? 'completed' : 'active';

            error_log("Goal::updateProgressForAccuracy() - Updating goal {$goal['id']} ({$goal['title']}): {$goal['current_value']} -> $newValue, status: $status");

            $updateQuery = "UPDATE learning_goals SET current_value = :current_value, status = :status, updated_at = NOW()
                           WHERE id = :id AND user_id = :user_id";
            $updateStmt = $this->conn->prepare($updateQuery);
            $updateStmt->bindParam(':current_value', $newValue);
            $updateStmt->bindParam(':status', $status);
            $updateStmt->bindParam(':id', $goal['id']);
            $updateStmt->bindParam(':user_id', $userId);
            $updateStmt->execute();
        }

        return count($goals);
    }
}
?>