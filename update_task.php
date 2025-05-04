<?php
// update_task.php
require 'db_connect.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { // Or PUT if you prefer
    sendJsonError('Invalid request method.', 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    sendJsonError('Invalid JSON received.', 400);
}

// Validation
if (empty($input['id']) || !isset($input['updates'])) {
    sendJsonError('Missing required fields (id, updates).', 400);
}

$taskId = filter_var($input['id'], FILTER_VALIDATE_INT);
$updates = $input['updates']; // Expecting an object like {name: "...", desc: "...", dueDate: "...", collaborators: [...]}

if ($taskId === false) {
     sendJsonError('Invalid Task ID.', 400);
}

$pdo->beginTransaction();

try {
    // 1. Update core task details if provided
    $updateFields = [];
    $params = [':id' => $taskId];

    if (isset($updates['name'])) {
        $updateFields[] = "name = :name";
        $params[':name'] = trim($updates['name']);
    }
    if (isset($updates['desc'])) {
        $updateFields[] = "description = :description";
        $params[':description'] = trim($updates['desc']);
    }
    if (isset($updates['dueDate'])) {
        try {
            $dateTime = new DateTime($updates['dueDate']);
            $formattedDueDate = $dateTime->format('Y-m-d H:i:s');
            $updateFields[] = "due_date = :due_date";
            $params[':due_date'] = $formattedDueDate;

             // Also update status if due date changes and task was overdue/pending
            $updateFields[] = "status = CASE
                                WHEN status = 'Overdue' AND :due_date >= NOW() THEN 'Pending'
                                WHEN status = 'Pending' AND :due_date < NOW() THEN 'Overdue'
                                ELSE status
                              END";

        } catch (Exception $e) {
            $pdo->rollBack();
            sendJsonError('Invalid date format for dueDate.', 400);
        }
    }
     // Allow updating status directly (e.g., marking as Completed)
    if (isset($updates['status']) && in_array($updates['status'], ['Pending', 'Completed', 'Overdue', 'Cancelled'])) {
         $updateFields[] = "status = :status";
         $params[':status'] = $updates['status'];
    }


    if (!empty($updateFields)) {
        $sqlTaskUpdate = "UPDATE tasks SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = :id";
        $stmtTaskUpdate = $pdo->prepare($sqlTaskUpdate);
        $stmtTaskUpdate->execute($params);
    }

    // 2. Update collaborators if provided
    if (isset($updates['collaborators']) && is_array($updates['collaborators'])) {
        // A) Delete existing collaborators for this task
        $sqlDeleteCollab = "DELETE FROM task_collaborators WHERE task_id = :task_id";
        $stmtDeleteCollab = $pdo->prepare($sqlDeleteCollab);
        $stmtDeleteCollab->execute([':task_id' => $taskId]);

        // B) Insert the new list of collaborators
        $collaborators = $updates['collaborators'];
        if (!empty($collaborators)) {
            $sqlCollab = "INSERT INTO task_collaborators (task_id, collaborator_name, collaborator_photo) VALUES (:task_id, :name, :photo)";
            $stmtCollab = $pdo->prepare($sqlCollab);

            foreach ($collaborators as $collab) {
                 if (isset($collab['name'])) {
                     $stmtCollab->execute([
                        ':task_id' => $taskId,
                        ':name' => trim($collab['name']),
                        ':photo' => isset($collab['photo']) ? trim($collab['photo']) : null
                    ]);
                 }
            }
        }
    }

    $pdo->commit();
    sendJsonResponse(['success' => true, 'message' => 'Task updated successfully.']);

} catch (PDOException $e) {
    $pdo->rollBack();
    sendJsonError('Failed to update task: ' . $e->getMessage(), 500);
} catch (Exception $e) {
     $pdo->rollBack();
     sendJsonError('An unexpected error occurred: ' . $e->getMessage(), 500);
}
?>