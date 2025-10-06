<?php
// api/controllers/AuthController.php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../services/EmailService.php';
require_once __DIR__ . '/../../vendor/autoload.php'; // For Composer autoload

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthController extends BaseController {

    public function register() {
        try {
            $logFile = __DIR__ . '/../../logs/registration_debug.log';
            $logMessage = "[" . date('Y-m-d H:i:s') . "] DEBUG: Registration attempt started\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);

            $data = json_decode(file_get_contents('php://input'), true);
            $logMessage = "[" . date('Y-m-d H:i:s') . "] DEBUG: Received data: " . json_encode($data) . "\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);

            // Validate input
            $validationErrors = $this->validateRequired($data, ['first_name', 'last_name', 'email', 'password']);
            if (!empty($validationErrors)) {
                $logMessage = "[" . date('Y-m-d H:i:s') . "] DEBUG: Validation errors: " . json_encode($validationErrors) . "\n";
                file_put_contents($logFile, $logMessage, FILE_APPEND);
                $this->validationErrorResponse($validationErrors);
                return;
            }

            // Validate email format
            $emailError = $this->validateEmail($data['email']);
            if ($emailError) {
                $logMessage = "[" . date('Y-m-d H:i:s') . "] DEBUG: Email validation error: " . $emailError . "\n";
                file_put_contents($logFile, $logMessage, FILE_APPEND);
                $this->validationErrorResponse(['email' => $emailError]);
                return;
            }

            // Validate password length
            $passwordError = $this->validateLength($data['password'], 'password', 6);
            if ($passwordError) {
                $logMessage = "[" . date('Y-m-d H:i:s') . "] DEBUG: Password validation error: " . $passwordError . "\n";
                file_put_contents($logFile, $logMessage, FILE_APPEND);
                $this->validationErrorResponse(['password' => $passwordError]);
                return;
            }

            // Check if email already exists
            $logMessage = "[" . date('Y-m-d H:i:s') . "] DEBUG: Checking if email exists: " . $data['email'] . "\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);
            $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$data['email']]);
            if ($stmt->fetch()) {
                $logMessage = "[" . date('Y-m-d H:i:s') . "] DEBUG: Email already exists: " . $data['email'] . "\n";
                file_put_contents($logFile, $logMessage, FILE_APPEND);
                $this->errorResponse('Email already exists', 409);
                return;
            }

            // Hash password
            $logMessage = "[" . date('Y-m-d H:i:s') . "] DEBUG: Hashing password\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
            if (!$hashedPassword) {
                $logMessage = "[" . date('Y-m-d H:i:s') . "] DEBUG: Password hashing failed\n";
                file_put_contents($logFile, $logMessage, FILE_APPEND);
                throw new Exception('Password hashing failed');
            }

            // Insert user
            $logMessage = "[" . date('Y-m-d H:i:s') . "] DEBUG: Inserting user into database\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);
            $stmt = $this->db->prepare("INSERT INTO users (first_name, last_name, name, email, password) VALUES (?, ?, ?, ?, ?)");
            $result = $stmt->execute([$data['first_name'], $data['last_name'], $data['name'], $data['email'], $hashedPassword]);
            if (!$result) {
                $logMessage = "[" . date('Y-m-d H:i:s') . "] DEBUG: User insert failed\n";
                file_put_contents($logFile, $logMessage, FILE_APPEND);
                throw new Exception('Database insert failed');
            }

            // Generate JWT token
            $userId = $this->db->lastInsertId();
            $logMessage = "[" . date('Y-m-d H:i:s') . "] DEBUG: User inserted with ID: " . $userId . "\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);
            $token = $this->generateJWT([
                'id' => $userId,
                'name' => $data['name'],
                'email' => $data['email'],
                'google_id' => null
            ]);
            if (!$token) {
                $logMessage = "[" . date('Y-m-d H:i:s') . "] DEBUG: JWT generation failed\n";
                file_put_contents($logFile, $logMessage, FILE_APPEND);
                throw new Exception('JWT generation failed');
            }

            $logMessage = "[" . date('Y-m-d H:i:s') . "] DEBUG: Registration successful for user ID: " . $userId . "\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);
            $this->successResponse([
                'user' => [
                    'id' => $userId,
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'google_id' => null
                ],
                'token' => $token
            ], 'User registered successfully', 201);

        } catch (Exception $e) {
            $logMessage = "[" . date('Y-m-d H:i:s') . "] DEBUG: Registration failed with exception: " . $e->getMessage() . "\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);
            $logMessage = "[" . date('Y-m-d H:i:s') . "] DEBUG: Exception trace: " . $e->getTraceAsString() . "\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND);
            $this->errorResponse('Registration failed: ' . $e->getMessage());
        }
    }

    public function login() {
        try {
            // Get input data
            $data = json_decode(file_get_contents('php://input'), true);

            // Validate input
            $validationErrors = $this->validateRequired($data, ['email', 'password']);
            if (!empty($validationErrors)) {
                $this->validationErrorResponse($validationErrors);
                return;
            }

            // Validate email format
            $emailError = $this->validateEmail($data['email']);
            if ($emailError) {
                $this->validationErrorResponse(['email' => $emailError]);
                return;
            }

            // Find user
            $stmt = $this->db->prepare("SELECT id, name, email, password FROM users WHERE email = ?");
            $stmt->execute([$data['email']]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($data['password'], $user['password'])) {
                $this->unauthorizedResponse('Invalid credentials');
                return;
            }

            // Generate JWT token
            $token = $this->generateJWT([
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'google_id' => $user['google_id'] ?? null
            ]);

            $this->successResponse([
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'google_id' => $user['google_id'] ?? null
                ],
                'token' => $token
            ], 'Login successful');

        } catch (Exception $e) {
            $this->errorResponse('Login failed: ' . $e->getMessage());
        }
    }

    public function googleLogin() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            // DEBUG: Log the incoming request data
            error_log("DEBUG: Google login request data: " . json_encode($data));

            // Validate input
            $validationErrors = $this->validateRequired($data, ['access_token']);
            if (!empty($validationErrors)) {
                error_log("DEBUG: Validation errors: " . json_encode($validationErrors));
                $this->validationErrorResponse($validationErrors);
                return;
            }

            // Verify Google access token
            error_log("DEBUG: Starting Google access token verification");
            $googleUser = $this->verifyGoogleAccessToken($data['access_token']);
            if (!$googleUser) {
                error_log("DEBUG: Google access token verification failed");
                $this->unauthorizedResponse('Invalid Google access token');
                return;
            }

            error_log("DEBUG: Google user data received: " . json_encode($googleUser));

            // Check if user exists with this Google ID
            $stmt = $this->db->prepare("SELECT id, name, email FROM users WHERE google_id = ?");
            $stmt->execute([$googleUser['sub']]);
            $user = $stmt->fetch();

            if ($user) {
                // Existing Google user - update profile if needed
                $this->updateGoogleUserProfile($user['id'], $googleUser);
                $userId = $user['id'];
            } else {
                // Check if email already exists (traditional signup)
                $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$googleUser['email']]);
                $existingUser = $stmt->fetch();

                if ($existingUser) {
                    // Link Google account to existing user
                    $this->linkGoogleAccount($existingUser['id'], $googleUser);
                    $userId = $existingUser['id'];
                } else {
                    // Create new user from Google data
                    $userId = $this->createGoogleUser($googleUser);
                }
            }

            // Get updated user data
            $stmt = $this->db->prepare("SELECT id, name, email, profile_picture, google_id FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $userData = $stmt->fetch();

            // Generate JWT token
            $token = $this->generateJWT([
                'id' => $userData['id'],
                'name' => $userData['name'],
                'email' => $userData['email'],
                'profile_picture' => $userData['profile_picture'],
                'google_id' => $userData['google_id'] ?? null
            ]);

            $this->successResponse([
                'user' => [
                    'id' => $userData['id'],
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'profile_picture' => $userData['profile_picture'],
                    'google_id' => $userData['google_id'] ?? null
                ],
                'token' => $token
            ], 'Google login successful');

        } catch (Exception $e) {
            $this->errorResponse('Google login failed: ' . $e->getMessage());
        }
    }

    public function logout() {
        try {
            $headers = getallheaders();

            // Get token from Authorization header
            if (isset($headers['Authorization'])) {
                $authHeader = $headers['Authorization'];
                if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                    $token = $matches[1];
                    // Remove token from database
                    $stmt = $this->db->prepare("DELETE FROM user_tokens WHERE token = ?");
                    $stmt->execute([$token]);
                }
            }

            $this->successResponse(null, 'Logged out successfully');

        } catch (Exception $e) {
            $this->errorResponse('Logout failed: ' . $e->getMessage());
        }
    }

    public function profile() {
        try {
            // Debug logging - check headers
            $headers = getallheaders();
            error_log("Profile request headers: " . json_encode($headers));

            // Authenticate user first
            if (!$this->authenticateUser()) {
                error_log("Profile authentication failed");
                $this->unauthorizedResponse();
                return;
            }

            // Get user ID from header
            $userId = $this->getUserId();
            error_log("Profile user ID: " . $userId);

            if (!$userId) {
                error_log("Profile user ID is null/empty");
                $this->unauthorizedResponse();
                return;
            }

            // Debug logging
            error_log("Profile request for user ID: " . $userId);

            $stmt = $this->db->prepare("SELECT id, first_name, last_name, name, email, profile_picture, google_id, created_at FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if ($user) {
                error_log("User found: " . json_encode($user));
                $this->successResponse([
                    'user' => [
                        'id' => $user['id'],
                        'first_name' => $user['first_name'] ?? '',
                        'last_name' => $user['last_name'] ?? '',
                        'name' => $user['name'] ?? trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
                        'email' => $user['email'],
                        'profile_picture' => $user['profile_picture'] ?? null,
                        'google_id' => $user['google_id'] ?? null,
                        'created_at' => $user['created_at']
                    ]
                ]);
            } else {
                error_log("User not found for ID: " . $userId);
                // Try to find user by email if ID doesn't work (for Supabase users)
                $headers = getallheaders();
                $email = null;

                // Extract email from JWT token payload if available
                if (isset($headers['Authorization'])) {
                    $authHeader = $headers['Authorization'];
                    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                        $token = $matches[1];
                        try {
                            $payload = $this->decodeJWT($token);
                            if (isset($payload['email'])) {
                                $email = $payload['email'];
                                error_log("Trying to find user by email: " . $email);
                                $stmt = $this->db->prepare("SELECT id, first_name, last_name, name, email, profile_picture, google_id, created_at FROM users WHERE email = ?");
                                $stmt->execute([$email]);
                                $user = $stmt->fetch();
                                if ($user) {
                                    error_log("User found by email: " . json_encode($user));
                                    $this->successResponse([
                                        'user' => [
                                            'id' => $user['id'],
                                            'first_name' => $user['first_name'] ?? '',
                                            'last_name' => $user['last_name'] ?? '',
                                            'name' => $user['name'] ?? trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
                                            'email' => $user['email'],
                                            'profile_picture' => $user['profile_picture'] ?? null,
                                            'google_id' => $user['google_id'] ?? null,
                                            'created_at' => $user['created_at']
                                        ]
                                    ]);
                                    return;
                                }
                            }
                        } catch (Exception $e) {
                            error_log("Failed to decode JWT: " . $e->getMessage());
                        }
                    }
                }

                $this->notFoundResponse('User not found');
            }

        } catch (Exception $e) {
            error_log("Profile error: " . $e->getMessage());
            $this->errorResponse('Failed to retrieve profile: ' . $e->getMessage());
        }
    }

    public function updateProfile() {
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

            // Get input data - handle both JSON and form data
            $data = null;
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

            if (strpos($contentType, 'application/json') !== false) {
                // Handle JSON data
                $rawInput = file_get_contents('php://input');
                $data = json_decode($rawInput, true);

                if ($data === null && !empty($rawInput)) {
                    $this->badRequestResponse('Invalid JSON data');
                    return;
                }
            } else {
                // Handle form data
                $data = $_POST;

                // If no POST data, try parsing raw input as form data
                if (empty($data)) {
                    $rawInput = file_get_contents('php://input');
                    if (!empty($rawInput)) {
                        parse_str($rawInput, $data);
                    }
                }
            }

            if (!$data) {
                $this->badRequestResponse('No data provided');
                return;
            }

            // Get current user data to handle partial updates
            $stmt = $this->db->prepare("SELECT first_name, last_name, name, email FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $currentUser = $stmt->fetch();

            if (!$currentUser) {
                $this->notFoundResponse('User not found');
                return;
            }

            // Build update query dynamically
            $updateFields = [];
            $params = [];

            if (isset($data['first_name'])) {
                $updateFields[] = "first_name = ?";
                $params[] = $this->sanitizeInput($data['first_name']);
            }

            if (isset($data['last_name'])) {
                $updateFields[] = "last_name = ?";
                $params[] = $this->sanitizeInput($data['last_name']);
            }

            // Update the combined name field when first_name or last_name is updated
            if (isset($data['first_name']) || isset($data['last_name'])) {
                $firstName = isset($data['first_name']) ? $data['first_name'] : $currentUser['first_name'];
                $lastName = isset($data['last_name']) ? $data['last_name'] : $currentUser['last_name'];
                $fullName = trim($firstName . ' ' . $lastName);
                $updateFields[] = "name = ?";
                $params[] = $fullName;
            }

            if (isset($data['name'])) {
                $updateFields[] = "name = ?";
                $params[] = $this->sanitizeInput($data['name']);
            }

            if (isset($data['email'])) {
                // Validate email format
                $emailError = $this->validateEmail($data['email']);
                if ($emailError) {
                    $this->validationErrorResponse(['email' => $emailError]);
                    return;
                }

                // Check if email is already taken by another user
                $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$data['email'], $userId]);
                if ($stmt->fetch()) {
                    $this->errorResponse('Email already taken', 409);
                    return;
                }
                $updateFields[] = "email = ?";
                $params[] = $data['email'];
            }

            if (empty($updateFields)) {
                $this->badRequestResponse('No valid fields to update');
                return;
            }

            $params[] = $userId;
            $query = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $stmt = $this->db->prepare($query);

            if ($stmt->execute($params)) {
                $this->successResponse(null, 'Profile updated successfully');
            } else {
                $this->errorResponse('Failed to update profile');
            }

        } catch (Exception $e) {
            $this->errorResponse('Failed to update profile: ' . $e->getMessage());
        }
    }

    public function uploadProfilePicture() {
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

        // Debug: Log file upload info
        error_log('Upload debug - FILES: ' . json_encode($_FILES));
        error_log('Upload debug - POST: ' . json_encode($_POST));

        // Check if file was uploaded
        if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
            $error = $_FILES['profile_picture']['error'] ?? 'No file uploaded';
            error_log('Upload error: ' . $error);
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error: ' . $error]);
            return;
        }

        $file = $_FILES['profile_picture'];

        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowedTypes)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.']);
            return;
        }

        // Validate file size (max 5MB)
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $maxSize) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'File too large. Maximum size is 5MB.']);
            return;
        }

        // Create uploads directory if it doesn't exist (in public directory for web access)
        // Use absolute path to ensure correct directory resolution
        $baseDir = dirname(__DIR__, 2); // Go up two levels from api/controllers/ to project root
        $uploadDir = $baseDir . '/public/uploads/profile_pictures/';

        // Ensure directory exists and is absolute path
        $uploadDir = realpath($uploadDir);
        if (!$uploadDir) {
            // If realpath fails, construct manually and ensure it exists
            $uploadDir = $baseDir . '/public/uploads/profile_pictures/';
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    error_log('Upload debug - Failed to create directory: ' . $uploadDir);
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Failed to create upload directory']);
                    return;
                }
            }
            $uploadDir = realpath($uploadDir);
        }

        $uploadDir = rtrim($uploadDir, '/') . '/';

        error_log('Upload debug - Base dir: ' . $baseDir);
        error_log('Upload debug - Upload dir: ' . $uploadDir);
        error_log('Upload debug - Current dir: ' . __DIR__);
        error_log('Upload debug - Directory exists: ' . (is_dir($uploadDir) ? 'YES' : 'NO'));
        error_log('Upload debug - Directory writable: ' . (is_writable($uploadDir) ? 'YES' : 'NO'));

        // Generate unique filename
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $fileName = 'profile_' . $userId . '_' . time() . '.' . $fileExtension;
        $filePath = $uploadDir . $fileName;

        error_log('Upload debug - File path: ' . $filePath);
        error_log('Upload debug - Temp file: ' . $file['tmp_name']);

        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            error_log('Upload debug - File moved successfully');
            error_log('Upload debug - File exists after move: ' . (file_exists($filePath) ? 'YES' : 'NO'));
            error_log('Upload debug - File size after move: ' . (file_exists($filePath) ? filesize($filePath) : 'N/A'));
            // Delete old profile picture if exists
            $stmt = $this->db->prepare("SELECT profile_picture FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $oldPicture = $stmt->fetchColumn();

            error_log('Upload debug - Old picture: ' . ($oldPicture ?: 'NULL'));

            if ($oldPicture) {
                $baseDir = dirname(__DIR__, 2); // Go up two levels from api/controllers/ to project root
                $oldFilePath = $baseDir . '/public/' . $oldPicture;
                error_log('Upload debug - Old file path: ' . $oldFilePath);
                error_log('Upload debug - Old file exists: ' . (file_exists($oldFilePath) ? 'YES' : 'NO'));
                if (file_exists($oldFilePath)) {
                    $unlinkResult = unlink($oldFilePath);
                    error_log('Upload debug - Unlink result: ' . ($unlinkResult ? 'SUCCESS' : 'FAILED'));
                }
            }

            // Update database with new profile picture path (relative to public directory)
            $relativePath = 'uploads/profile_pictures/' . $fileName;
            $stmt = $this->db->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
            $result = $stmt->execute([$relativePath, $userId]);

            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Profile picture uploaded successfully',
                    'profile_picture' => $relativePath
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Failed to update database']);
            }
        } else {
            $moveError = error_get_last();
            error_log('Upload debug - Move failed. Last error: ' . json_encode($moveError));
            error_log('Upload debug - File exists check: ' . (file_exists($file['tmp_name']) ? 'YES' : 'NO'));
            error_log('Upload debug - Target dir writable: ' . (is_writable($uploadDir) ? 'YES' : 'NO'));

            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to save file to: ' . $filePath,
                'debug' => [
                    'temp_file_exists' => file_exists($file['tmp_name']),
                    'target_dir_writable' => is_writable($uploadDir),
                    'upload_dir' => $uploadDir,
                    'file_path' => $filePath,
                    'last_error' => $moveError
                ]
            ]);
        }

        } catch (Exception $e) {
            $this->errorResponse('Failed to upload profile picture: ' . $e->getMessage());
        }
    }

    public function google() {
        try {
            // Placeholder for initiating Google OAuth flow
            // In a full implementation, this would redirect to Google's authorization URL
            // For now, just return a message indicating the endpoint is ready
            $this->successResponse(['message' => 'Google OAuth initiation endpoint - redirect to Google auth URL here']);
        } catch (Exception $e) {
            $this->errorResponse('Failed to initiate Google OAuth: ' . $e->getMessage());
        }
    }

    public function googleCallback() {
        try {
            // Get the ID token from the request
            $data = json_decode(file_get_contents('php://input'), true);
            $idToken = $data['id_token'] ?? null;

            if (!$idToken) {
                $this->badRequestResponse('ID token is required');
                return;
            }

            // Create Google Client
            $client = new Google_Client(['client_id' => getenv('GOOGLE_CLIENT_ID')]); // Assuming env var is set

            // Verify the ID token
            $payload = $client->verifyIdToken($idToken);

            if (!$payload) {
                $this->unauthorizedResponse('Invalid ID token');
                return;
            }

            // Extract user profile information
            $profile = [
                'google_id' => $payload['sub'],
                'email' => $payload['email'] ?? null,
                'email_verified' => $payload['email_verified'] ?? false,
                'name' => $payload['name'] ?? null,
                'given_name' => $payload['given_name'] ?? null,
                'family_name' => $payload['family_name'] ?? null,
                'picture' => $payload['picture'] ?? null,
                'locale' => $payload['locale'] ?? null
            ];

            // Check if user exists by google_id or email
            $stmt = $this->db->prepare("SELECT id, first_name, last_name, name, email, profile_picture FROM users WHERE google_id = ? OR email = ?");
            $stmt->execute([$profile['google_id'], $profile['email']]);
            $existingUser = $stmt->fetch();

            if ($existingUser) {
                // User exists, update with new information
                $updateFields = [];
                $params = [];

                if ($profile['name'] !== null && $profile['name'] !== $existingUser['name']) {
                    $updateFields[] = "name = ?";
                    $params[] = $profile['name'];
                }

                if ($profile['given_name'] !== null && $profile['given_name'] !== $existingUser['first_name']) {
                    $updateFields[] = "first_name = ?";
                    $params[] = $profile['given_name'];
                }

                if ($profile['family_name'] !== null && $profile['family_name'] !== $existingUser['last_name']) {
                    $updateFields[] = "last_name = ?";
                    $params[] = $profile['family_name'];
                }

                if ($profile['email'] !== null && $profile['email'] !== $existingUser['email']) {
                    $updateFields[] = "email = ?";
                    $params[] = $profile['email'];
                }

                if ($profile['google_id'] !== null) {
                    $updateFields[] = "google_id = ?";
                    $params[] = $profile['google_id'];
                }

                if ($profile['picture'] !== null && $profile['picture'] !== $existingUser['profile_picture']) {
                    $updateFields[] = "profile_picture = ?";
                    $params[] = $profile['picture'];
                }

                if (!empty($updateFields)) {
                    $params[] = $existingUser['id'];
                    $query = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
                    $stmt = $this->db->prepare($query);
                    $stmt->execute($params);
                }

                $userId = $existingUser['id'];
                $message = 'User record updated successfully';

            } else {
                // User does not exist, create new user
                $firstName = $profile['given_name'] ?? '';
                $lastName = $profile['family_name'] ?? '';
                $fullName = $profile['name'] ?? trim($firstName . ' ' . $lastName);

                $stmt = $this->db->prepare("INSERT INTO users (first_name, last_name, name, email, google_id, profile_picture) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $firstName,
                    $lastName,
                    $fullName,
                    $profile['email'],
                    $profile['google_id'],
                    $profile['picture']
                ]);

                $userId = $this->db->lastInsertId();
                $message = 'New user created successfully';
            }

            // Get the updated user data
            $stmt = $this->db->prepare("SELECT id, first_name, last_name, name, email, profile_picture FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            // Generate JWT token
            $jwtToken = $this->generateJWT($user);

            $this->successResponse([
                'user' => [
                    'id' => $user['id'],
                    'first_name' => $user['first_name'] ?? '',
                    'last_name' => $user['last_name'] ?? '',
                    'name' => $user['name'] ?? '',
                    'email' => $user['email'],
                    'profile_picture' => $user['profile_picture'] ?? null
                ],
                'token' => $jwtToken,
                'message' => $message
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to handle Google OAuth callback: ' . $e->getMessage());
        }
    }

    private function generateJWT($user) {
        try {
            $secretKey = getenv('JWT_SECRET') ?: 'your_default_secret_key_here';
            $issuedAt = time();
            $expirationTime = $issuedAt + (24 * 60 * 60); // 24 hours

            $payload = [
                'iat' => $issuedAt,
                'exp' => $expirationTime,
                'user_id' => $user['id'],
                'email' => $user['email'],
                'name' => $user['name']
            ];

            return JWT::encode($payload, $secretKey, 'HS256');
        } catch (Exception $e) {
            $this->debugLog("JWT generation error: " . $e->getMessage());
            return null;
        }
    }

    private function decodeJWT($token) {
        try {
            $secretKey = getenv('JWT_SECRET') ?: 'your_default_secret_key_here';
            $payload = JWT::decode($token, new Key($secretKey, 'HS256'));
            return (array) $payload;
        } catch (Exception $e) {
            $this->debugLog("JWT decode error: " . $e->getMessage());
            return null;
        }
    }

    private function storeToken($userId, $token) {
        try {
            // Remove any existing tokens for this user
            $stmt = $this->db->prepare("DELETE FROM user_tokens WHERE user_id = ?");
            $stmt->execute([$userId]);

            // Store new token
            $stmt = $this->db->prepare("INSERT INTO user_tokens (user_id, token, created_at, expires_at) VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 24 HOUR))");
            $stmt->execute([$userId, $token]);

        } catch (Exception $e) {
            $this->debugLog("Token storage error: " . $e->getMessage());
        }
    }

    protected function validateToken($token) {
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

    private function verifyGoogleToken($idToken) {
        try {
            // Get Google client ID from environment
            $clientId = getenv('GOOGLE_OAUTH_CLIENT_ID');
            error_log("DEBUG: GOOGLE_OAUTH_CLIENT_ID = '" . $clientId . "' (length: " . strlen($clientId) . ")");
            if (!$clientId) {
                error_log("Google OAuth client ID not configured");
                return false;
            }

            if ($clientId === 'your_production_google_oauth_client_id') {
                error_log("ERROR: Google OAuth client ID is still set to placeholder value");
                return false;
            }

            // Verify token with Google's servers
            $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);
            $response = file_get_contents($url);

            if (!$response) {
                error_log("Failed to verify Google token");
                return false;
            }

            $tokenData = json_decode($response, true);

            // Validate token
            if (!isset($tokenData['sub']) || $tokenData['aud'] !== $clientId) {
                error_log("Invalid Google token data");
                return false;
            }

            return $tokenData;

        } catch (Exception $e) {
            error_log("Google token verification error: " . $e->getMessage());
            return false;
        }
    }

    private function verifyGoogleAccessToken($accessToken) {
        try {
            // DEBUG: Log the access token (first 20 chars for security)
            error_log("DEBUG: Verifying access token: " . substr($accessToken, 0, 20) . "...");

            // Use the access token to get user info from Google's userinfo endpoint
            // Note: Client ID is not required for userinfo endpoint validation
            $url = 'https://www.googleapis.com/oauth2/v2/userinfo';
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => 'Authorization: Bearer ' . $accessToken
                ]
            ]);

            $response = file_get_contents($url, false, $context);

            if (!$response) {
                error_log("Failed to get user info from Google - no response");
                error_log("DEBUG: Access token used: " . substr($accessToken, 0, 20) . "...");
                return false;
            }

            error_log("DEBUG: Google userinfo response: " . substr($response, 0, 200) . "...");

            $userData = json_decode($response, true);

            if (!$userData) {
                error_log("Failed to decode Google userinfo response as JSON");
                return false;
            }

            if (!isset($userData['id'])) {
                error_log("Invalid user data from Google - missing 'id': " . json_encode($userData));
                return false;
            }

            // Transform to match expected format
            return [
                'sub' => $userData['id'],
                'email' => $userData['email'] ?? null,
                'email_verified' => $userData['verified_email'] ?? false,
                'name' => $userData['name'] ?? null,
                'given_name' => $userData['given_name'] ?? null,
                'family_name' => $userData['family_name'] ?? null,
                'picture' => $userData['picture'] ?? null,
                'locale' => $userData['locale'] ?? null
            ];

        } catch (Exception $e) {
            error_log("Google access token verification error: " . $e->getMessage());
            return false;
        }
    }

    private function createGoogleUser($googleUser) {
        try {
            // Generate a unique name from Google data
            $firstName = $googleUser['given_name'] ?? '';
            $lastName = $googleUser['family_name'] ?? '';
            $fullName = trim($firstName . ' ' . $lastName);

            if (empty($fullName)) {
                $fullName = $googleUser['name'] ?? 'Google User';
            }

            // Insert new user
            $stmt = $this->db->prepare("INSERT INTO users (first_name, last_name, name, email, google_id, profile_picture, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");

            $stmt->execute([
                $firstName,
                $lastName,
                $fullName,
                $googleUser['email'],
                $googleUser['sub'],
                $googleUser['picture'] ?? null
            ]);

            return $this->db->lastInsertId();

        } catch (Exception $e) {
            error_log("Create Google user error: " . $e->getMessage());
            throw $e;
        }
    }

    private function updateGoogleUserProfile($userId, $googleUser) {
        try {
            // Update user profile with latest Google data
            $stmt = $this->db->prepare("UPDATE users SET name = ?, profile_picture = ? WHERE id = ?");

            $fullName = $googleUser['name'] ?? 'Google User';
            $stmt->execute([$fullName, $googleUser['picture'] ?? null, $userId]);

        } catch (Exception $e) {
            error_log("Update Google user profile error: " . $e->getMessage());
        }
    }

    private function linkGoogleAccount($userId, $googleUser) {
        try {
            // Link Google account to existing user
            $stmt = $this->db->prepare("UPDATE users SET google_id = ?, profile_picture = ? WHERE id = ?");
            $stmt->execute([$googleUser['sub'], $googleUser['picture'] ?? null, $userId]);

        } catch (Exception $e) {
            error_log("Link Google account error: " . $e->getMessage());
            throw $e;
        }
    }

    public function requestPasswordReset() {
        try {
            // Get input data - handle both JSON and form data
            $data = null;
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

            if (strpos($contentType, 'application/json') !== false) {
                // Handle JSON data
                $rawInput = file_get_contents('php://input');
                $data = json_decode($rawInput, true);

                if ($data === null && !empty($rawInput)) {
                    $this->badRequestResponse('Invalid JSON data');
                    return;
                }
            } else {
                // Handle form data
                $data = $_POST;

                // If no POST data, try parsing raw input as form data
                if (empty($data)) {
                    $rawInput = file_get_contents('php://input');
                    if (!empty($rawInput)) {
                        parse_str($rawInput, $data);
                    }
                }
            }

            if (!$data) {
                $this->badRequestResponse('No data provided');
                return;
            }

            // Validate input
            $validationErrors = $this->validateRequired($data, ['email']);
            if (!empty($validationErrors)) {
                $this->validationErrorResponse($validationErrors);
                return;
            }

            // Validate email format
            $emailError = $this->validateEmail($data['email']);
            if ($emailError) {
                $this->validationErrorResponse(['email' => $emailError]);
                return;
            }

            // Check if user exists
            $stmt = $this->db->prepare("SELECT id, name FROM users WHERE email = ?");
            $stmt->execute([$data['email']]);
            $user = $stmt->fetch();

            if (!$user) {
                // Don't reveal if email exists or not for security
                $this->successResponse(null, 'If an account with that email exists, a password reset link has been sent.');
                return;
            }

            // Generate reset token
            $resetToken = bin2hex(random_bytes(32));
            $expiresAt = gmdate('Y-m-d H:i:s', strtotime('+1 hour'));

            // Save reset token
            $stmt = $this->db->prepare("INSERT INTO password_reset_tokens (user_id, token, email, expires_at) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user['id'], $resetToken, $data['email'], $expiresAt]);

            // Send reset email - use configurable frontend URL
            $frontendUrl = getenv('FRONTEND_URL') ?: 'http://localhost:8080';
            $resetLink = $frontendUrl . "/reset-password?token=" . $resetToken;

            $emailService = new EmailService();
            $emailResult = $emailService->sendPasswordResetEmail($data['email'], $resetLink, $user['name']);

            if ($emailResult['success']) {
                $this->successResponse([
                    'reset_link' => $resetLink,
                    'reset_token' => $resetToken,
                    'message' => 'Password reset email sent successfully.'
                ], 'Password reset link has been sent to your email.');
            } else {
                // Email failed, but token was created - return link for manual use
                $this->successResponse([
                    'reset_link' => $resetLink,
                    'reset_token' => $resetToken,
                    'email_error' => $emailResult['message'],
                    'message' => 'Password reset link generated. Email sending failed, but you can use the link manually.'
                ], 'Password reset link has been generated. Check your email or use the provided link.');
            }

        } catch (Exception $e) {
            $this->errorResponse('Failed to request password reset: ' . $e->getMessage());
        }
    }

    public function resetPassword() {
        try {
            // Get input data - handle both JSON and form data
            $data = null;
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

            if (strpos($contentType, 'application/json') !== false) {
                // Handle JSON data
                $rawInput = file_get_contents('php://input');
                $data = json_decode($rawInput, true);

                if ($data === null && !empty($rawInput)) {
                    $this->badRequestResponse('Invalid JSON data');
                    return;
                }
            } else {
                // Handle form data
                $data = $_POST;

                // If no POST data, try parsing raw input as form data
                if (empty($data)) {
                    $rawInput = file_get_contents('php://input');
                    if (!empty($rawInput)) {
                        parse_str($rawInput, $data);
                    }
                }
            }

            if (!$data) {
                $this->badRequestResponse('No data provided');
                return;
            }

            // Validate input
            $validationErrors = $this->validateRequired($data, ['token', 'password']);
            if (!empty($validationErrors)) {
                $this->validationErrorResponse($validationErrors);
                return;
            }

            // Validate password length
            $passwordError = $this->validateLength($data['password'], 'password', 6);
            if ($passwordError) {
                $this->validationErrorResponse(['password' => $passwordError]);
                return;
            }

            // Find valid reset token
            $stmt = $this->db->prepare("SELECT user_id, email FROM password_reset_tokens WHERE token = ? AND expires_at > UTC_TIMESTAMP() AND used = 0");
            $stmt->execute([$data['token']]);
            $resetToken = $stmt->fetch();

            if (!$resetToken) {
                $this->errorResponse('Invalid or expired reset token', 400);
                return;
            }

            // Hash new password
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

            // Update user password
            $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $resetToken['user_id']]);

            // Mark token as used
            $stmt = $this->db->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token = ?");
            $stmt->execute([$data['token']]);

            // Clean up expired tokens
            $stmt = $this->db->prepare("DELETE FROM password_reset_tokens WHERE expires_at < UTC_TIMESTAMP()");
            $stmt->execute();

            $this->successResponse(null, 'Password has been reset successfully. You can now log in with your new password.');

        } catch (Exception $e) {
            $this->errorResponse('Failed to reset password: ' . $e->getMessage());
        }
    }

    public function updatePassword() {
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

            // Get input data
            $data = json_decode(file_get_contents('php://input'), true);

            // Validate input
            $validationErrors = $this->validateRequired($data, ['current_password', 'new_password']);
            if (!empty($validationErrors)) {
                $this->validationErrorResponse($validationErrors);
                return;
            }

            // Validate new password length
            $passwordError = $this->validateLength($data['new_password'], 'new_password', 6);
            if ($passwordError) {
                $this->validationErrorResponse(['new_password' => $passwordError]);
                return;
            }

            // Get current user data
            $stmt = $this->db->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user) {
                $this->notFoundResponse('User not found');
                return;
            }

            // Verify current password
            if (!password_verify($data['current_password'], $user['password'])) {
                $this->errorResponse('Current password is incorrect', 400);
                return;
            }

            // Hash new password
            $hashedPassword = password_hash($data['new_password'], PASSWORD_DEFAULT);

            // Update password
            $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $result = $stmt->execute([$hashedPassword, $userId]);

            if ($result) {
                $this->successResponse(null, 'Password updated successfully');
            } else {
                $this->errorResponse('Failed to update password');
            }

        } catch (Exception $e) {
            $this->errorResponse('Failed to update password: ' . $e->getMessage());
        }
    }

    public function validateResetToken() {
        try {
            $token = $_GET['token'] ?? '';

            if (empty($token)) {
                $this->errorResponse('Reset token is required', 400);
                return;
            }

            // Check if token is valid and not expired (use UTC to avoid timezone issues)
            $stmt = $this->db->prepare("SELECT email FROM password_reset_tokens WHERE token = ? AND expires_at > UTC_TIMESTAMP() AND used = 0");
            $stmt->execute([$token]);
            $resetToken = $stmt->fetch();

            if (!$resetToken) {
                $this->errorResponse('Invalid or expired reset token', 400);
                return;
            }

            $this->successResponse([
                'email' => $resetToken['email'],
                'valid' => true
            ], 'Reset token is valid');

        } catch (Exception $e) {
            $this->errorResponse('Failed to validate reset token: ' . $e->getMessage());
        }
    }

    public function deleteAccount() {
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

            error_log("DEBUG: Starting account deletion for user ID: " . $userId);

            // Start transaction for data consistency
            $this->db->beginTransaction();

            try {
                // Delete user-related data in correct order to avoid foreign key constraints

                // 1. Delete password reset tokens
                error_log("DEBUG: Deleting password reset tokens for user ID: " . $userId);
                try {
                    $stmt = $this->db->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    error_log("DEBUG: Successfully deleted password reset tokens");
                } catch (Exception $e) {
                    error_log("DEBUG: Error deleting password reset tokens: " . $e->getMessage());
                    throw $e;
                }

                // 2. Delete user tokens
                error_log("DEBUG: Deleting user tokens for user ID: " . $userId);
                try {
                    $stmt = $this->db->prepare("DELETE FROM user_tokens WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    error_log("DEBUG: Successfully deleted user tokens");
                } catch (Exception $e) {
                    error_log("DEBUG: Error deleting user tokens: " . $e->getMessage());
                    throw $e;
                }

                // 3. Delete study sessions
                error_log("DEBUG: Deleting study sessions for user ID: " . $userId);
                try {
                    $stmt = $this->db->prepare("DELETE FROM study_sessions WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    error_log("DEBUG: Successfully deleted study sessions");
                } catch (Exception $e) {
                    error_log("DEBUG: Error deleting study sessions: " . $e->getMessage());
                    throw $e;
                }

                // 4. Delete learning goals
                error_log("DEBUG: Deleting learning goals for user ID: " . $userId);
                try {
                    $stmt = $this->db->prepare("DELETE FROM learning_goals WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    error_log("DEBUG: Successfully deleted learning goals");
                } catch (Exception $e) {
                    error_log("DEBUG: Error deleting learning goals: " . $e->getMessage());
                    throw $e;
                }

                // 5. Delete summaries
                error_log("DEBUG: Deleting summaries for user ID: " . $userId);
                try {
                    $stmt = $this->db->prepare("DELETE FROM summaries WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    error_log("DEBUG: Successfully deleted summaries");
                } catch (Exception $e) {
                    error_log("DEBUG: Error deleting summaries: " . $e->getMessage());
                    throw $e;
                }

                // 6. Delete quizzes
                error_log("DEBUG: Deleting quizzes for user ID: " . $userId);
                try {
                    $stmt = $this->db->prepare("DELETE FROM quizzes WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    error_log("DEBUG: Successfully deleted quizzes");
                } catch (Exception $e) {
                    error_log("DEBUG: Error deleting quizzes: " . $e->getMessage());
                    throw $e;
                }

                // 7. Delete notes (this should be last as other tables might reference it)
                error_log("DEBUG: Deleting notes for user ID: " . $userId);
                try {
                    $stmt = $this->db->prepare("DELETE FROM notes WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    error_log("DEBUG: Successfully deleted notes");
                } catch (Exception $e) {
                    error_log("DEBUG: Error deleting notes: " . $e->getMessage());
                    throw $e;
                }

                // 8. Finally, delete the user record
                error_log("DEBUG: Deleting user record for user ID: " . $userId);
                try {
                    $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
                    $result = $stmt->execute([$userId]);
                    error_log("DEBUG: Successfully deleted user record");
                } catch (Exception $e) {
                    error_log("DEBUG: Error deleting user record: " . $e->getMessage());
                    throw $e;
                }

                if (!$result) {
                    throw new Exception('Failed to delete user record');
                }

                // Commit transaction
                $this->db->commit();

                error_log("DEBUG: Account deletion completed successfully for user ID: " . $userId);
                $this->successResponse(null, 'Account deleted successfully');

            } catch (Exception $e) {
                // Rollback transaction on error
                $this->db->rollBack();
                error_log("DEBUG: Account deletion failed, transaction rolled back. Error: " . $e->getMessage());
                throw $e;
            }

        } catch (Exception $e) {
            error_log("DEBUG: Account deletion error: " . $e->getMessage());
            $this->errorResponse('Failed to delete account: ' . $e->getMessage());
        }
    }

}