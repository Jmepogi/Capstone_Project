<?php
// Start session management
session_start();

// Destroy all session data
session_destroy();

// Clear all session variables
$_SESSION = [];

// Redirect to the login page
header("Location: ../00_login/login.php"); // Adjust the path if your login page is named differently
exit();
?>
