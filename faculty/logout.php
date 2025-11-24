<?php
session_start();

// Destroy session and redirect to Faculty login
session_unset();
session_destroy();
header('Location: ../index.php');
exit;
?>