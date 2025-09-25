<?php
include '../include/db.php';

// Check if user is logged in and is HOD
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hod') {
    header('Location: login.php');
    exit;
}

// Redirect to HOD dashboard
header('Location: hod_dashboard.php');
exit;
?>