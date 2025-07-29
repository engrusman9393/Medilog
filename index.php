<?php
// Redirect to dashboard if logged in, otherwise to login
session_start();

if (isset($_SESSION['user_id'])) {
    // User is logged in, redirect to dashboard
    header('Location: dashboard.php');
} else {
    // User is not logged in, redirect to login
    header('Location: login.php');
}
exit();
?>