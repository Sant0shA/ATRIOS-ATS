<?php
session_start();

// If logged in, go to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

// If not logged in, go to login
header('Location: login.php');
exit();
?>
