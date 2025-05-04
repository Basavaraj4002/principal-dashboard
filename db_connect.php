<?php
// db_connect.php
$host = 'localhost'; // or your database host
$dbname = 'principal_dashboard';
$username = 'root'; // your database username
$password = ''; // your database password

// Set timezone if necessary (match your server/application timezone)
date_default_timezone_set('Asia/Kolkata'); // Example: India timezone

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    // Set the PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Emulate prepared statements off for security (prevents some types of injection)
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    // In a real app, log this error instead of echoing directly
    header('Content-Type: application/json');
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit; // Stop script execution
}

// Function to send JSON responses (optional but helpful)
function sendJsonResponse($data, $statusCode = 200) {
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

// Function to handle errors and send JSON response
function sendJsonError($message, $statusCode = 400) { // Default to Bad Request
    sendJsonResponse(['success' => false, 'message' => $message], $statusCode);
}
?>