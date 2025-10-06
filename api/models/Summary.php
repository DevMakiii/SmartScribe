<?php

class Summary {
    private $conn;
    public $note_id;
    public $user_id;
    public $content;
    public $length;
    public $format;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO summaries (note_id, content, length, created_at)
                  VALUES (:note_id, :content, :length, NOW())";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':note_id', $this->note_id);
        $stmt->bindParam(':content', $this->content);
        $stmt->bindParam(':length', $this->length);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }

        return false;
    }

    public function readAllByUser($userId) {
        $query = "SELECT s.*
                  FROM summaries s
                  INNER JOIN notes n ON s.note_id = n.id
                  WHERE n.user_id = :user_id
                  ORDER BY s.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();

        return $stmt;
    }

    public function readById($id, $userId) {
        $query = "SELECT s.*
                  FROM summaries s
                  INNER JOIN notes n ON s.note_id = n.id
                  WHERE s.id = :id AND n.user_id = :user_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();

        return $stmt->fetch();
    }
}
?>