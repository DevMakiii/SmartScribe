<?php

class Quiz {
    private $conn;
    public $note_id;
    public $user_id;
    public $questions;
    public $difficulty;
    public $quiz_type;
    public $score;
    public $title;
    public $note_title;
    public $total_questions;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        error_log("QUIZ MODEL: create() called");
        error_log("QUIZ MODEL: note_id: " . $this->note_id);
        error_log("QUIZ MODEL: user_id: " . $this->user_id);
        error_log("QUIZ MODEL: questions length: " . strlen($this->questions));
        error_log("QUIZ MODEL: score: " . ($this->score ?? 'null'));
        error_log("QUIZ MODEL: title: " . ($this->title ?? 'null'));
        error_log("QUIZ MODEL: total_questions: " . ($this->total_questions ?? 'null'));

        $query = "INSERT INTO quizzes (note_id, user_id, questions, difficulty, quiz_type, score, title, total_questions, created_at)
                  VALUES (:note_id, :user_id, :questions, :difficulty, :quiz_type, :score, :title, :total_questions, NOW())";

        error_log("QUIZ MODEL: SQL query prepared");

        try {
            $stmt = $this->conn->prepare($query);
            error_log("QUIZ MODEL: Statement prepared successfully");

            $stmt->bindParam(':note_id', $this->note_id);
            $stmt->bindParam(':user_id', $this->user_id);
            $stmt->bindParam(':questions', $this->questions);
            $stmt->bindParam(':difficulty', $this->difficulty);
            $stmt->bindParam(':quiz_type', $this->quiz_type);
            $stmt->bindParam(':score', $this->score);
            $stmt->bindParam(':title', $this->title);
            $stmt->bindParam(':total_questions', $this->total_questions);

            error_log("QUIZ MODEL: Parameters bound, executing...");
            $result = $stmt->execute();

            if ($result) {
                $lastId = $this->conn->lastInsertId();
                error_log("QUIZ MODEL: Quiz created successfully with ID: " . $lastId);
                return $lastId;
            } else {
                error_log("QUIZ MODEL: Statement execution failed");
                return false;
            }
        } catch (Exception $e) {
            error_log("QUIZ MODEL: Exception during quiz creation: " . $e->getMessage());
            return false;
        }
    }

    public function readAllByUser($userId) {
        $query = "SELECT q.*
                  FROM quizzes q
                  INNER JOIN notes n ON q.note_id = n.id
                  WHERE n.user_id = :user_id
                  ORDER BY q.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();

        return $stmt;
    }

    public function readById($id, $userId) {
        $query = "SELECT q.*
                  FROM quizzes q
                  INNER JOIN notes n ON q.note_id = n.id
                  WHERE q.id = :id AND n.user_id = :user_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();

        $quiz = $stmt->fetch();
        if ($quiz) {
            $quiz['questions'] = json_decode($quiz['questions'], true);
        }

        return $quiz;
    }

    public function update($id, $data) {
        $updateFields = [];
        $params = [':id' => $id];

        if (isset($data['score'])) {
            $updateFields[] = "score = :score";
            $params[':score'] = $data['score'];
        }

        if (isset($data['questions'])) {
            $updateFields[] = "questions = :questions";
            $params[':questions'] = json_encode($data['questions']);

            // Also update total_questions when questions are updated
            $questionsArray = $data['questions'];
            if (is_array($questionsArray)) {
                $updateFields[] = "total_questions = :total_questions";
                $params[':total_questions'] = count($questionsArray);
            }
        }

        if (isset($data['difficulty'])) {
            $updateFields[] = "difficulty = :difficulty";
            $params[':difficulty'] = $data['difficulty'];
        }

        if (isset($data['quiz_type'])) {
            $updateFields[] = "quiz_type = :quiz_type";
            $params[':quiz_type'] = $data['quiz_type'];
        }

        if (isset($data['title'])) {
            $updateFields[] = "title = :title";
            $params[':title'] = $data['title'];
        }

        if (isset($data['note_title'])) {
            $updateFields[] = "note_title = :note_title";
            $params[':note_title'] = $data['note_title'];
        }

        if (empty($updateFields)) {
            return false;
        }

        $query = "UPDATE quizzes SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = :id";
        $stmt = $this->conn->prepare($query);

        return $stmt->execute($params);
    }

    public function delete($id, $userId) {
        // First verify the quiz belongs to the user
        $query = "SELECT q.id FROM quizzes q
                  INNER JOIN notes n ON q.note_id = n.id
                  WHERE q.id = :id AND n.user_id = :user_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();

        if (!$stmt->fetch()) {
            return false; // Quiz not found or doesn't belong to user
        }

        // Delete the quiz
        $deleteQuery = "DELETE FROM quizzes WHERE id = :id";
        $deleteStmt = $this->conn->prepare($deleteQuery);
        $deleteStmt->bindParam(':id', $id);

        return $deleteStmt->execute();
    }
}
?>