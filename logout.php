<?php
session_start();

// Remove all session variables
session_unset();

// Destroy session
session_destroy();

// Redirect to login page
header("Location: index.php");
exit;
?>