<?php
// Authentication functions

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . SITE_URL . '/login.php');
        exit();
    }
}

function requireRole($roles) {
    requireLogin();
    
    if (is_string($roles)) {
        $roles = [$roles];
    }
    
    if (!in_array($_SESSION['user_role'], $roles)) {
        header('Location: ' . SITE_URL . '/dashboard.php');
        exit();
    }
}

function hasRole($roles) {
    if (!isset($_SESSION['user_role'])) {
        return false;
    }
    
    if (is_string($roles)) {
        $roles = [$roles];
    }
    
    return in_array($_SESSION['user_role'], $roles);
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function logout() {
    session_start();
    $_SESSION = array();
    session_destroy();
    header('Location: ' . SITE_URL . '/login.php');
    exit();
}

function getInitials($name) {
    if (empty($name)) {
        return 'U';
    }
    
    $parts = explode(' ', trim($name));
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}
?>
