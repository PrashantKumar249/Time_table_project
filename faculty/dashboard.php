<?php
include '../include/db.php';

// Check if user is logged in and is Faculty
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    header('Location: login.php');
    exit;
}

// Redirect to Faculty dashboard
header('Location: faculty_dashboard.php');
exit;
?>