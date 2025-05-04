<?php
// update_task_status.php
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
if (empty($input['id']) || empty($input['status'])) {
    sendJsonError('Missing required fields (id, status).', 400);
}

$taskId = filter_var($input['id'], FILTER_VALIDATE_INT);
$status = trim($input['status']);

if ($taskId === false) {
     sendJsonError('Invalid Task ID.', 400);
}

// Validate status value
$allowedStatuses = ['Pending', 'Completed', 'Overdue', 'Cancelled'];
if (!in_array($status, $allowedStatuses)) {
    sendJsonError('Invalid status value.', 400);
}

try {
    $sql = "UPDATE tasks SET status = :status, updated_at = NOW() WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':status' => $status, ':id' => $taskId]);

    if ($stmt->rowCount() > 0) {
        sendJsonResponse(['success' => true, 'message' => 'Task status updated successfully.']);
    } else {
        // Task might not exist or status was already the same
        // Check if task exists to give a more specific error
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE id = :id");
        $checkStmt->execute([':id' => $taskId]);
        if ($checkStmt->fetchColumn() == 0) {
            sendJsonError('Task not found.', 404);
        } else {
            sendJsonResponse(['success' => true, 'message' => 'Task status was already set to ' . $status . '.']); // Or treat as success
        }
    }

} catch (PDOException $e) {
    sendJsonError('Failed to update task status: ' . $e->getMessage(), 500);
}
?>