<?php
// create_task.php
require 'db_connect.php';

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonError('Invalid request method.', 405); // Method Not Allowed
}

// Get JSON data from the request body
$input = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    sendJsonError('Invalid JSON received.', 400);
}

// Basic Validation
if (empty($input['name']) || empty($input['desc']) || empty($input['dueDate']) || !isset($input['collaborators'])) {
    sendJsonError('Missing required fields (name, desc, dueDate, collaborators).', 400);
}

$name = trim($input['name']);
$description = trim($input['desc']);
$dueDate = $input['dueDate']; // Should be in 'YYYY-MM-DDTHH:MM' format
$collaborators = $input['collaborators']; // Expecting an array of objects [{name: "...", photo: "..."}, ...]

// More robust date validation could be added here
try {
    $dateTime = new DateTime($dueDate);
    $formattedDueDate = $dateTime->format('Y-m-d H:i:s');
} catch (Exception $e) {
    sendJsonError('Invalid date format for dueDate.', 400);
}

$pdo->beginTransaction();

try {
    // 1. Insert the task
    $sqlTask = "INSERT INTO tasks (name, description, due_date, status) VALUES (:name, :description, :due_date, :status)";
    $stmtTask = $pdo->prepare($sqlTask);
    $stmtTask->execute([
        ':name' => $name,
        ':description' => $description,
        ':due_date' => $formattedDueDate,
        ':status' => 'Pending' // Default status
    ]);

    $taskId = $pdo->lastInsertId();

    // 2. Insert collaborators
    if (!empty($collaborators) && is_array($collaborators)) {
        $sqlCollab = "INSERT INTO task_collaborators (task_id, collaborator_name, collaborator_photo) VALUES (:task_id, :name, :photo)";
        $stmtCollab = $pdo->prepare($sqlCollab);

        foreach ($collaborators as $collab) {
            if (isset($collab['name'])) { // Basic check
                 $stmtCollab->execute([
                    ':task_id' => $taskId,
                    ':name' => trim($collab['name']),
                    ':photo' => isset($collab['photo']) ? trim($collab['photo']) : null
                ]);
            }
        }
    }

    $pdo->commit();

    // Fetch the newly created task to return it (optional but good practice)
     $sqlFetch = "SELECT t.*, GROUP_CONCAT(CONCAT_WS('||', tc.collaborator_name, tc.collaborator_photo) SEPARATOR ';;') as collaborators_str
                 FROM tasks t
                 LEFT JOIN task_collaborators tc ON t.id = tc.task_id
                 WHERE t.id = :task_id
                 GROUP BY t.id";
    $stmtFetch = $pdo->prepare($sqlFetch);
    $stmtFetch->execute([':task_id' => $taskId]);
    $newTask = $stmtFetch->fetch(PDO::FETCH_ASSOC);

    if ($newTask) {
        // Process collaborators string back into an array
        $newTask['collaborators'] = [];
        if (!empty($newTask['collaborators_str'])) {
            $collabPairs = explode(';;', $newTask['collaborators_str']);
            foreach ($collabPairs as $pair) {
                list($cName, $cPhoto) = explode('||', $pair, 2);
                $newTask['collaborators'][] = ['name' => $cName, 'photo' => $cPhoto];
            }
        }
        unset($newTask['collaborators_str']); // Clean up temporary field

         // Format dates for consistency with JS expectations if needed
        $newTask['dueDate'] = (new DateTime($newTask['due_date']))->format('Y-m-d\TH:i');
        // Add other fields expected by JS if necessary

        sendJsonResponse(['success' => true, 'message' => 'Task created successfully.', 'task' => $newTask]);
    } else {
         sendJsonResponse(['success' => true, 'message' => 'Task created, but could not fetch details.', 'taskId' => $taskId]);
    }


} catch (PDOException $e) {
    $pdo->rollBack();
    // Log the error $e->getMessage()
    sendJsonError('Failed to create task: ' . $e->getMessage(), 500); // Internal Server Error
} catch (Exception $e) {
     $pdo->rollBack();
     sendJsonError('An unexpected error occurred: ' . $e->getMessage(), 500);
}

?>