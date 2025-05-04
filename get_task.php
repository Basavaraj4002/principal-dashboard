<?php
// get_task.php
require 'db_connect.php';

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonError('Invalid request method.', 405);
}

try {
    // Update overdue tasks before fetching
    $updateSql = "UPDATE tasks SET status = 'Overdue' WHERE status = 'Pending' AND due_date < NOW()";
    $pdo->exec($updateSql);

    // Fetch all tasks and their collaborators
    $sql = "SELECT t.*, GROUP_CONCAT(DISTINCT CONCAT_WS('||', tc.collaborator_name, tc.collaborator_photo) SEPARATOR ';;') as collaborators_str
            FROM tasks t
            LEFT JOIN task_collaborators tc ON t.id = tc.task_id
            WHERE t.status != 'Cancelled' -- Optionally exclude cancelled tasks from default view
            GROUP BY t.id
            ORDER BY t.due_date ASC"; // Or ORDER BY created_at DESC

    $stmt = $pdo->query($sql);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $processedTasks = [];
    foreach ($tasks as $task) {
        // Process collaborators string into an array
        $task['collaborators'] = [];
        if (!empty($task['collaborators_str'])) {
            $collabPairs = explode(';;', $task['collaborators_str']);
             foreach ($collabPairs as $pair) {
                // Handle cases where photo might be missing
                 $parts = explode('||', $pair, 2);
                 $cName = $parts[0];
                 $cPhoto = isset($parts[1]) ? $parts[1] : null; // Default to null if photo missing
                 if(!empty($cName)){ // Ensure name is not empty
                    $task['collaborators'][] = ['name' => $cName, 'photo' => $cPhoto];
                 }
            }
        }
        unset($task['collaborators_str']); // Clean up

        // Format dates/times for JS if needed (e.g., to match datetime-local)
        try {
            $task['dueDate'] = (new DateTime($task['due_date']))->format('Y-m-d\TH:i');
            // Keep original DB format if needed elsewhere
            // $task['due_date_db'] = $task['due_date'];
        } catch (Exception $e) {
            $task['dueDate'] = null; // Handle potential invalid dates from DB
        }

        $processedTasks[] = $task;
    }

    sendJsonResponse(['success' => true, 'tasks' => $processedTasks]);

} catch (PDOException $e) {
    // Log the error $e->getMessage()
    sendJsonError('Failed to fetch tasks: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    sendJsonError('An error occurred while processing tasks: ' . $e->getMessage(), 500);
}

?>