<?php
// hod/get_allocated.php
include '../include/db.php';
header('Content-Type: application/json');

$section_id = intval($_GET['section_id'] ?? 0);
$allocated = [];

if ($section_id > 0) {
    $query = "SELECT s.subject_id FROM section_subjects ss JOIN subjects s ON ss.subject_id = s.subject_id WHERE ss.section_id = $section_id";
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        $allocated[] = $row['subject_id'];
    }
}

echo json_encode($allocated);
?>