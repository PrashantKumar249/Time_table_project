<?php
// Start session for authentication
// session_start();

// Database configuration
$host = 'localhost';
$dbname = 'timetable_management';
$username = 'root'; // Change to your MySQL username
$password = ''; // Change to your MySQL password

// Create mysqli connection
$conn = mysqli_connect($host, $username, $password, $dbname);
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
?>