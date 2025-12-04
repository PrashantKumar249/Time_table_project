<?php
session_start();
include 'header.php'; // Assuming this includes the database connection $conn

// Check if user is HOD
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hod') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Get HOD's branch_id
$query = "SELECT h.branch_id FROM hod h WHERE h.user_id = " . (int)$_SESSION['user_id'];
$result = mysqli_query($conn, $query);
if (!$result || mysqli_num_rows($result) == 0) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid HOD session']);
    exit;
}
$hod_data = mysqli_fetch_assoc($result);
$branch_id = $hod_data['branch_id'];

// Get search query
$q = isset($_GET['q']) ? trim(mysqli_real_escape_string($conn, $_GET['q'])) : '';
if (empty($q)) {
    echo json_encode([]);
    exit;
}

// Fetch up to 5 matching faculty names
$search_query = "
    SELECT DISTINCT f.faculty_name, u.username
    FROM faculty f
    JOIN users u ON f.user_id = u.user_id
    WHERE f.branch_id = $branch_id 
    AND (f.faculty_name LIKE '$q%' OR u.username LIKE '$q%')
    ORDER BY f.faculty_name ASC
    LIMIT 5
";
$search_result = mysqli_query($conn, $search_query);
$suggestions = [];
while ($row = mysqli_fetch_assoc($search_result)) {
    $suggestions[] = [
        'name' => $row['faculty_name'],
        'username' => $row['username']
    ];
}

header('Content-Type: application/json');
echo json_encode($suggestions);
?>