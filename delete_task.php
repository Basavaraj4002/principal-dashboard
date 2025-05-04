<?php
// delete_task.php
require 'db_connect.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Use POST for simplicity, though DELETE method is more semantically correct
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonError('Invalid request method.', 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    sendJsonError('Invalid JSON received.', 400);
}

// Validation
if (empty($input['id'])) {
    sendJsonError('Missing required field (id).', 400);
}

$taskId = filter_var($input['id'], FILTER_VALIDATE_INT);

if ($taskId === false) {
     sendJsonError('Invalid Task ID.', 400);
}

// Option 1: Physically Delete
$pdo->beginTransaction();
try {
    // CASCADE should handle collaborators and messages, but explicit delete is safer if CASCADE isn't set/reliable
    $sqlDeleteCollab = "DELETE FROM task_collaborators WHERE task_id = :task_id";
    $stmtDeleteCollab = $pdo->prepare($sqlDeleteCollab);
    $stmtDeleteCollab->execute([':task_id' => $taskId]);

    $sqlDeleteMsg = "DELETE FROM task_messages WHERE task_id = :task_id";
    $stmtDeleteMsg = $pdo->prepare($sqlDeleteMsg);
    $stmtDeleteMsg->execute([':task_id' => $taskId]);

    // Delete the task itself
    $sqlDeleteTask = "DELETE FROM tasks WHERE id = :id";
    $stmtDeleteTask = $pdo->prepare($sqlDeleteTask);
    $stmtDeleteTask->execute([':id' => $taskId]);

    $rowCount = $stmtDeleteTask->rowCount();

    $pdo->commit();

    if ($rowCount > 0) {
        sendJsonResponse(['success' => true, 'message' => 'Task deleted successfully.']);
    } else {
        sendJsonError('Task not found or already deleted.', 404); // Not Found
    }

} catch (PDOException $e) {
    $pdo->rollBack();
    sendJsonError('Failed to delete task: ' . $e->getMessage(), 500);
}
 
?>