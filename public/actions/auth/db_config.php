<?php
/**
 * Database Configuration File
 * Handles connection to MySQL database
 */

// Suppress PHP errors from displaying (they'll be logged instead)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'cf-rw-db');


// Database credentials
// define('DB_HOST', 'localhost');
// define('DB_USER', 'u491481127_jorgen29');
// define('DB_PASSWORD', '@Bossbabe010319');
// define('DB_NAME', 'u491481127_cf_rw_db');

try {
    // Create MySQL connection using MySQLi
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Set charset to utf8
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    // Create a log file for debugging
    $logFile = __DIR__ . '/../../logs/db_error.log';
    if (!is_dir(dirname($logFile))) {
        mkdir(dirname($logFile), 0777, true);
    }
    
    // Log error 
    error_log("Database Connection Error: " . $e->getMessage() . "\n", 3, $logFile);
    
    // Return JSON error if headers haven't been sent
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}
?>
