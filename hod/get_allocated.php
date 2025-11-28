<?php
include '../include/db.php';
header('Content-Type: application/json');
$section_id = intval($_GET['section_id'] ?? 0);
$allocated = [];
if ($section_id > 0) {
    $query = "SELECT subject_id FROM section_subjects WHERE section_id = $section_id";
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        $allocated[] = $row['subject_id'];
    }
}
echo json_encode($allocated);
?>