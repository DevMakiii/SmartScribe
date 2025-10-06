<?php
// api/models/BaseModel.php
require_once __DIR__ . '/../config/database.php';

abstract class BaseModel {
    protected $db;
    protected $table;
    protected $primaryKey = 'id';
    protected $fillable = [];
    protected $hidden = [];
    protected $timestamps = true;

    public function __construct() {
        $this->db = getDbConnection();
    }

    /**
     * Find record by ID
     */
    public function find($id) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? $this->formatResult($result) : null;
    }

    /**
     * Find record by ID with user ownership check
     */
    public function findByIdAndUser($id, $userId, $userField = 'user_id') {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ? AND {$userField} = ?");
        $stmt->execute([$id, $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? $this->formatResult($result) : null;
    }

    /**
     * Find all records
     */
    public function all($orderBy = null, $limit = null) {
        $sql = "SELECT * FROM {$this->table}";

        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }

        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }

        $stmt = $this->db->query($sql);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'formatResult'], $results);
    }

    /**
     * Find records by user ID
     */
    public function findByUser($userId, $userField = 'user_id', $orderBy = null, $limit = null) {
        $sql = "SELECT * FROM {$this->table} WHERE {$userField} = ?";

        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }

        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'formatResult'], $results);
    }

    /**
     * Create new record
     */
    public function create($data) {
        $data = $this->filterFillable($data);

        if ($this->timestamps) {
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        $columns = array_keys($data);
        $placeholders = str_repeat('?,', count($columns) - 1) . '?';

        $sql = "INSERT INTO {$this->table} (" . implode(',', $columns) . ") VALUES ({$placeholders})";
        $stmt = $this->db->prepare($sql);

        if ($stmt->execute(array_values($data))) {
            return $this->db->lastInsertId();
        }

        return false;
    }

    /**
     * Update record
     */
    public function update($id, $data) {
        $data = $this->filterFillable($data);

        if ($this->timestamps) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        $columns = array_keys($data);
        $setClause = implode(' = ?, ', $columns) . ' = ?';
        $data[$this->primaryKey] = $id;

        $sql = "UPDATE {$this->table} SET {$setClause} WHERE {$this->primaryKey} = ?";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute(array_values($data));
    }

    /**
     * Update record with user ownership check
     */
    public function updateByIdAndUser($id, $userId, $data, $userField = 'user_id') {
        $data = $this->filterFillable($data);

        if ($this->timestamps) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        $columns = array_keys($data);
        $setClause = implode(' = ?, ', $columns) . ' = ?';
        $data[$this->primaryKey] = $id;
        $data[$userField] = $userId;

        $sql = "UPDATE {$this->table} SET {$setClause} WHERE {$this->primaryKey} = ? AND {$userField} = ?";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute(array_values($data));
    }

    /**
     * Delete record
     */
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Delete record with user ownership check
     */
    public function deleteByIdAndUser($id, $userId, $userField = 'user_id') {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE {$this->primaryKey} = ? AND {$userField} = ?");
        return $stmt->execute([$id, $userId]);
    }

    /**
     * Count records
     */
    public function count($where = null) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}";

        if ($where) {
            $sql .= " WHERE {$where}";
        }

        $stmt = $this->db->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['count'];
    }

    /**
     * Count records by user
     */
    public function countByUser($userId, $userField = 'user_id') {
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM {$this->table} WHERE {$userField} = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['count'];
    }

    /**
     * Check if record exists
     */
    public function exists($id) {
        $stmt = $this->db->prepare("SELECT 1 FROM {$this->table} WHERE {$this->primaryKey} = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() !== false;
    }

    /**
     * Check if record exists with user ownership
     */
    public function existsByIdAndUser($id, $userId, $userField = 'user_id') {
        $stmt = $this->db->prepare("SELECT 1 FROM {$this->table} WHERE {$this->primaryKey} = ? AND {$userField} = ? LIMIT 1");
        $stmt->execute([$id, $userId]);
        return $stmt->fetch() !== false;
    }

    /**
     * Filter data to only include fillable fields
     */
    protected function filterFillable($data) {
        if (empty($this->fillable)) {
            return $data;
        }

        return array_intersect_key($data, array_flip($this->fillable));
    }

    /**
     * Format result by removing hidden fields
     */
    protected function formatResult($result) {
        if (empty($this->hidden)) {
            return $result;
        }

        return array_diff_key($result, array_flip($this->hidden));
    }

    /**
     * Begin database transaction
     */
    public function beginTransaction() {
        $this->db->beginTransaction();
    }

    /**
     * Commit database transaction
     */
    public function commit() {
        $this->db->commit();
    }

    /**
     * Rollback database transaction
     */
    public function rollback() {
        $this->db->rollback();
    }

    /**
     * Execute raw query (use with caution)
     */
    protected function rawQuery($sql, $params = []) {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}
?>