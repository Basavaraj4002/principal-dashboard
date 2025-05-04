<?php
// get_stats.php
require 'db_connect.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonError('Invalid request method.', 405);
}

try {
    // Update overdue tasks first
    $updateSql = "UPDATE tasks SET status = 'Overdue' WHERE status = 'Pending' AND due_date < NOW()";
    $pdo->exec($updateSql);

    // Now fetch counts
    $sql = "SELECT
                COUNT(*) AS totalAssigned,
                SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN status = 'Overdue' THEN 1 ELSE 0 END) AS overdue
            FROM tasks
            WHERE status != 'Cancelled'"; // Exclude cancelled from stats

    $stmt = $pdo->query($sql);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Ensure stats are numbers, even if null (e.g., if no tasks exist)
    $stats['totalAssigned'] = (int)($stats['totalAssigned'] ?? 0);
    $stats['pending'] = (int)($stats['pending'] ?? 0);
    $stats['completed'] = (int)($stats['completed'] ?? 0);
    $stats['overdue'] = (int)($stats['overdue'] ?? 0);


    sendJsonResponse(['success' => true, 'stats' => $stats]);

} catch (PDOException $e) {
    sendJsonError('Failed to fetch stats: ' . $e->getMessage(), 500);
}
?>