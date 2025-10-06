<?php
require_once __DIR__ . '/../models/Goal.php';

class GoalController {
    private $db;
    private $goal;

    public function __construct() {
        global $db;
        $this->db = $db;
        $this->goal = new Goal($db);
    }

    public function index() {
        $userId = $this->getUserIdFromHeader();

        if (!$userId) {
            http_response_code(401);
            echo json_encode([
                "success" => false,
                "error" => "Unauthorized"
            ]);
            return;
        }

        $stmt = $this->goal->readAllByUser($userId);
        $goals = $stmt->fetchAll();

        echo json_encode([
            "success" => true,
            "data" => $goals
        ]);
    }

    public function store() {
        $userId = $this->getUserIdFromHeader();

        if (!$userId) {
            http_response_code(401);
            echo json_encode([
                "success" => false,
                "error" => "Unauthorized"
            ]);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['title']) || !isset($data['target_type']) || !isset($data['target_value'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing required fields']);
            return;
        }

        $this->goal->user_id = $userId;
        $this->goal->title = $data['title'];
        $this->goal->description = $data['description'] ?? '';
        $this->goal->target_type = $data['target_type'];
        $this->goal->target_value = $data['target_value'];
        $this->goal->current_value = $data['current_value'] ?? 0;
        $this->goal->deadline = $data['deadline'] ?? null;
        $this->goal->status = $data['status'] ?? 'active';

        $goalId = $this->goal->create();

        if ($goalId) {
            echo json_encode([
                "success" => true,
                "message" => "Goal created successfully",
                "goal_id" => $goalId
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "error" => "Failed to create goal"
            ]);
        }
    }

    public function show($id) {
        $userId = $this->getUserIdFromHeader();

        if (!$userId) {
            http_response_code(401);
            echo json_encode([
                "success" => false,
                "error" => "Unauthorized"
            ]);
            return;
        }

        $goal = $this->goal->readById($id, $userId);

        if ($goal) {
            echo json_encode([
                "success" => true,
                "data" => $goal
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Goal not found']);
        }
    }

    public function update($id) {
        $userId = $this->getUserIdFromHeader();

        if (!$userId) {
            http_response_code(401);
            echo json_encode([
                "success" => false,
                "error" => "Unauthorized"
            ]);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if ($this->goal->update($id, $data)) {
            echo json_encode([
                "success" => true,
                "message" => "Goal updated successfully"
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "error" => "Failed to update goal"
            ]);
        }
    }

    public function destroy($id) {
        $userId = $this->getUserIdFromHeader();

        if (!$userId) {
            http_response_code(401);
            echo json_encode([
                "success" => false,
                "error" => "Unauthorized"
            ]);
            return;
        }

        if ($this->goal->delete($id, $userId)) {
            echo json_encode([
                "success" => true,
                "message" => "Goal deleted successfully"
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "error" => "Failed to delete goal"
            ]);
        }
    }

    public function updateProgress($id) {
        $userId = $this->getUserIdFromHeader();

        if (!$userId) {
            http_response_code(401);
            echo json_encode([
                "success" => false,
                "error" => "Unauthorized"
            ]);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['current_value'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing current_value']);
            return;
        }

        if ($this->goal->updateProgress($id, $userId, $data['current_value'])) {
            echo json_encode([
                "success" => true,
                "message" => "Goal progress updated successfully"
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "error" => "Failed to update goal progress"
            ]);
        }
    }

    public function getStats() {
        $userId = $this->getUserIdFromHeader();

        if (!$userId) {
            http_response_code(401);
            echo json_encode([
                "success" => false,
                "error" => "Unauthorized"
            ]);
            return;
        }

        $activeGoals = $this->goal->getActiveGoalsCount($userId);
        $completedGoals = $this->goal->getCompletedGoalsThisMonth($userId);

        echo json_encode([
            "success" => true,
            "data" => [
                "activeGoals" => $activeGoals,
                "completedGoals" => $completedGoals
            ]
        ]);
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

    private function validateToken($token) {
        if (!$token) return null;

        try {
            $stmt = $this->db->prepare("SELECT user_id FROM user_tokens WHERE token = ? AND expires_at > NOW()");
            $stmt->execute([$token]);
            $result = $stmt->fetch();

            return $result ? $result['user_id'] : null;
        } catch (Exception $e) {
            error_log("Token validation error: " . $e->getMessage());
            return null;
        }
    }
}
?>