<?php
// add_holiday.php
require 'db_connect.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonError('Invalid request method.', 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    sendJsonError('Invalid JSON received.', 400);
}

// Validation
if (empty($input['date']) || empty($input['name'])) {
    sendJsonError('Missing required fields (date, name).', 400);
}

$holidayDate = $input['date']; // Expecting 'YYYY-MM-DD'
$holidayName = trim($input['name']);

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $holidayDate)) {
     sendJsonError('Invalid date format. Use YYYY-MM-DD.', 400);
}

try {
    $sql = "INSERT INTO holidays (holiday_date, name) VALUES (:holiday_date, :name)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':holiday_date' => $holidayDate,
        ':name' => $holidayName
    ]);

    sendJsonResponse(['success' => true, 'message' => 'Holiday added successfully.']);

} catch (PDOException $e) {
    if ($e->getCode() == 23000) { // Integrity constraint violation (likely duplicate date)
        sendJsonError('A holiday already exists for this date.', 409); // Conflict
    } else {
        sendJsonError('Failed to add holiday: ' . $e->getMessage(), 500);
    }
}
?>