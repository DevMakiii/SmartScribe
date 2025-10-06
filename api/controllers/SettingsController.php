<?php
require_once __DIR__ . '/BaseController.php';

class SettingsController extends BaseController {
    public function __construct() {
        parent::__construct();
    }

    public function getSettings() {
        $userId = $this->getUserIdFromHeader();

        if (!$userId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }

        try {
            // Check if settings table exists, if not create it
            $this->ensureSettingsTableExists();

            $query = "SELECT settings FROM user_settings WHERE user_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();

            $result = $stmt->fetch();

            if ($result && $result['settings']) {
                $settings = json_decode($result['settings'], true);
                echo json_encode([
                    'success' => true,
                    'data' => $settings
                ]);
            } else {
                // Return default settings
                $defaultSettings = [
                    'fontSize' => 16,
                    'theme' => 'dark',
                    'notifications' => [
                        'weeklySummary' => false,
                        'studyReminders' => false,
                        'newFeatures' => false,
                        'quizResults' => false,
                        'goalProgress' => false
                    ],
                    'api' => [
                        'openaiKey' => '',
                        'openaiModel' => 'gpt-3.5-turbo',
                        'ocrEngine' => 'tesseract',
                        'ocrKey' => ''
                    ]
                ];

                echo json_encode([
                    'success' => true,
                    'data' => $defaultSettings
                ]);
            }
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => 'Failed to fetch settings: ' . $e->getMessage()
            ]);
        }
    }

    public function updateSettings() {
        $userId = $this->getUserIdFromHeader();

        if (!$userId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
            return;
        }

        try {
            // Ensure settings table exists
            $this->ensureSettingsTableExists();

            $settingsJson = json_encode($data);

            // Insert or update settings
            $query = "INSERT INTO user_settings (user_id, settings, created_at, updated_at)
                      VALUES (:user_id, :settings, NOW(), NOW())
                      ON DUPLICATE KEY UPDATE
                      settings = :settings,
                      updated_at = NOW()";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':settings', $settingsJson);

            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Settings updated successfully'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to update settings'
                ]);
            }
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => 'Failed to update settings: ' . $e->getMessage()
            ]);
        }
    }

    private function ensureSettingsTableExists() {
        $query = "CREATE TABLE IF NOT EXISTS user_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL UNIQUE,
            settings JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";

        $this->db->exec($query);
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

    protected function validateToken($token) {
        if (!$token) return null;

        try {
            $stmt = $this->db->prepare("SELECT user_id FROM user_tokens WHERE token = ? AND expires_at > UTC_TIMESTAMP()");
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