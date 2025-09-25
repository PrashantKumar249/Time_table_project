<?php
session_start();

// Destroy session and redirect to HOD login
session_unset();
session_destroy();
header('Location: login.php');
exit;
?>