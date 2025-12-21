<?php
/**
 * User Logout Handler
 * Destroys session and redirects to login
 */

session_start();

// Destroy session
session_destroy();

// Redirect to login page
header('Location: ../../../index.html');
exit;
?>
