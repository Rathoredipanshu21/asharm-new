<?php
// Initialize the session
session_start();

// Unset all of the session variables
// $_SESSION = array(); is a common way to do this
// but session_unset() is specifically for this purpose.
session_unset();

// Destroy the session.
// This will remove the session data from the server.
session_destroy();

// Redirect to the login page
// After logging out, the user is sent back to the login screen.
header("location: login.php");
exit;
?>
