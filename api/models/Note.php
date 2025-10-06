<?php
require_once __DIR__ . '/BaseModel.php';

class Note extends BaseModel {
    protected $table = 'notes';
    protected $fillable = ['user_id', 'title', 'original_text', 'image_path', 'keywords'];
    protected $hidden = [];

    public function __construct($db = null) {
        if ($db) {
            $this->db = $db;
        }
        parent::__construct();
    }

    public function createNote($data) {
        error_log("Note::createNote() - Called with data: " . json_encode($data));
        $result = parent::create($data);
        error_log("Note::createNote() - Parent create result: " . ($result ? "Success (ID: $result)" : "Failed"));
        return $result;
    }

    public function getNotesWithSummaryCount($userId) {
        $stmt = $this->db->prepare("
            SELECT n.*,
                   (SELECT COUNT(*) FROM summaries s WHERE s.note_id = n.id) as summary_count
            FROM notes n
            WHERE user_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getByIdAndUser($id, $userId) {
        return $this->findByIdAndUser($id, $userId);
    }
}
