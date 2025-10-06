<?php
// api/controllers/BaseController.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../vendor/autoload.php'; // For Composer autoload

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

abstract class BaseController {
    protected $db;
    protected $userId = null;

    public function __construct() {
        $this->db = getDbConnection();
    }

    /**
     * Get user ID from authentication headers
     * Supports both Bearer token and X-User-ID header for backward compatibility
     */
    protected function authenticateUser() {
        $headers = getallheaders();

        // First try to validate token from Authorization header (case-insensitive)
        $authHeader = null;
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'authorization') {
                $authHeader = $value;
                break;
            }
        }

        if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
            $this->userId = $this->validateToken($token);
            if ($this->userId) {
                return true;
            }
        }

        // Fallback to X-User-ID header (for backward compatibility)
        if (isset($headers['X-User-ID']) || isset($headers['x-user-id'])) {
            $userIdHeader = $headers['X-User-ID'] ?? $headers['x-user-id'];
            $this->userId = intval($userIdHeader);
            return true;
        }

        return false;
    }

    /**
     * Validate JWT token
     */
    protected function validateToken($token) {
        if (!$token) return null;

        try {
            $secretKey = getenv('JWT_SECRET') ?: 'your_default_secret_key_here';
            $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));

            // Check if token is expired
            if (isset($decoded->exp) && $decoded->exp < time()) {
                return null;
            }

            // Handle different JWT token formats
            // New format: direct user_id property
            if (isset($decoded->user_id)) {
                return $decoded->user_id;
            }

            // Legacy format: user data nested under 'data' object
            if (isset($decoded->data) && isset($decoded->data->id)) {
                return $decoded->data->id;
            }

            // Fallback: try other common formats
            return $decoded->id ?? $decoded->userId ?? null;
        } catch (Exception $e) {
            error_log("Token validation error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get current authenticated user ID
     */
    protected function getUserId() {
        return $this->userId;
    }

    /**
     * Check if user is authenticated
     */
    protected function requireAuth() {
        if (!$this->authenticateUser()) {
            $this->unauthorizedResponse('Authentication required');
            return false;
        }
        return true;
    }

    /**
     * Standard success response
     */
    protected function successResponse($data = null, $message = 'Success', $httpCode = 200) {
        error_log("BaseController::successResponse() - Sending response with HTTP code: $httpCode, message: $message");
        http_response_code($httpCode);
        $response = json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('c')
        ]);
        error_log("BaseController::successResponse() - Response JSON length: " . strlen($response));
        echo $response;
        error_log("BaseController::successResponse() - Response sent");
    }

    /**
     * Standard error response
     */
    protected function errorResponse($message = 'An error occurred', $httpCode = 500, $errors = null) {
        http_response_code($httpCode);
        $response = [
            'success' => false,
            'message' => $message,
            'timestamp' => date('c')
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        echo json_encode($response);
    }

    /**
     * Unauthorized response
     */
    protected function unauthorizedResponse($message = 'Unauthorized') {
        $this->errorResponse($message, 401);
    }

    /**
     * Bad request response
     */
    protected function badRequestResponse($message = 'Bad request', $errors = null) {
        $this->errorResponse($message, 400, $errors);
    }

    /**
     * Not found response
     */
    protected function notFoundResponse($message = 'Resource not found') {
        $this->errorResponse($message, 404);
    }

    /**
     * Validation error response
     */
    protected function validationErrorResponse($errors) {
        $this->errorResponse('Validation failed', 422, $errors);
    }

    /**
     * Sanitize input data
     */
    protected function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([$this, 'sanitizeInput'], $data);
        }

        if (is_string($data)) {
            return trim(strip_tags($data));
        }

        return $data;
    }

    /**
     * Validate required fields
     */
    protected function validateRequired($data, $requiredFields) {
        $errors = [];
        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            $errors['required_fields'] = 'Missing required fields: ' . implode(', ', $missingFields);
        }

        return $errors;
    }

    /**
     * Validate email format
     */
    protected function validateEmail($email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Invalid email format';
        }
        return null;
    }

    /**
     * Validate string length
     */
    protected function validateLength($value, $field, $min = null, $max = null) {
        $length = strlen($value);

        if ($min !== null && $length < $min) {
            return "{$field} must be at least {$min} characters long";
        }

        if ($max !== null && $length > $max) {
            return "{$field} must not exceed {$max} characters";
        }

        return null;
    }

    /**
     * Log debug information (only in development)
     */
    protected function debugLog($message, $data = null) {
        if (getenv('APP_ENV') === 'development') {
            error_log($message);
            if ($data) {
                error_log(json_encode($data));
            }
        }
    }
}
?>