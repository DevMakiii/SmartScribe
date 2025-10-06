<?php
// api/config/database.php
require_once __DIR__ . '/../../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

function getDbConnection() {
    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $db   = $_ENV['DB_NAME'] ?? 'smartscribe_new';
    $user = $_ENV['DB_USER'] ?? 'root';
    $pass = $_ENV['DB_PASS'] ?? '';
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    // Log connection attempt
    $logMessage = "[" . date('Y-m-d H:i:s') . "] Attempting database connection to $db on $host as $user\n";
    file_put_contents(__DIR__ . '/../../logs/db_connection.log', $logMessage, FILE_APPEND);

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        // Log successful connection
        $successMessage = "[" . date('Y-m-d H:i:s') . "] Database connection successful\n";
        file_put_contents(__DIR__ . '/../../logs/db_connection.log', $successMessage, FILE_APPEND);
        return $pdo;
    } catch (\PDOException $e) {
        // Log connection error with details
        $errorMessage = "[" . date('Y-m-d H:i:s') . "] Database connection failed: " . $e->getMessage() . "\n";
        file_put_contents(__DIR__ . '/../../logs/db_connection.log', $errorMessage, FILE_APPEND);
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
    }
}