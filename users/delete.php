<?php
ob_start(); // Buffer output to prevent header warnings
/* ============================================================
   FILE: users/delete.php
   PURPOSE: Activate or deactivate user accounts
   ACCESS: Admin only
   
   SECTIONS:
   - [AUTH-CHECK] Verify admin access
   - [PARAMETER-VALIDATION] Validate user ID and action
   - [SELF-CHECK] Prevent user from deactivating themselves
   - [DATABASE-UPDATE] Update user status
   - [REDIRECT] Return to user list
   
   LAST MODIFIED: 2026-02-20
   ============================================================ */

session_start();
require_once '../config.php';
require_once '../includes/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

/* ============================================================
   [AUTH-CHECK] - Require Admin Role
   ============================================================ */
requireRole('admin');

$database = new Database();
$db = $database->getConnection();

/* ============================================================
   [PARAMETER-VALIDATION] - Get and Validate Parameters
   ============================================================ */
$userId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Validate user ID
if ($userId <= 0) {
    setFlashMessage('error', 'Invalid user ID');
    header('Location: index.php');
    exit();
}

// Validate action
if (!in_array($action, ['activate', 'deactivate'])) {
    setFlashMessage('error', 'Invalid action');
    header('Location: index.php');
    exit();
}

/* ============================================================
   [SELF-CHECK] - Prevent Deactivating Self
   ============================================================ */
if ($userId == $_SESSION['user_id']) {
    setFlashMessage('error', 'You cannot deactivate your own account');
    header('Location: index.php');
    exit();
}

/* ============================================================
   [DATABASE-UPDATE] - Update User Status
   ============================================================ */
try {
    // First, check if user exists
    $stmt = $db->prepare("SELECT user_id, username, full_name, role FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        setFlashMessage('error', 'User not found');
        header('Location: index.php');
        exit();
    }
    
    // Determine new status
    $newStatus = ($action === 'activate') ? 'active' : 'inactive';
    
    // Update user status
    $stmt = $db->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE user_id = ?");
    $stmt->execute([$newStatus, $userId]);
    
    // Log activity
    $actionText = ($action === 'activate') ? 'Activated' : 'Deactivated';
    logActivity($db, $_SESSION['user_id'], $action, 'user', $userId, 
               "{$actionText} user: {$user['full_name']} ({$user['role']})");
    
    // Set success message
    $message = ($action === 'activate') 
        ? "User '{$user['full_name']}' has been activated successfully" 
        : "User '{$user['full_name']}' has been deactivated successfully";
    
    setFlashMessage('success', $message);
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Database error: ' . $e->getMessage());
}

/* ============================================================
   [REDIRECT] - Return to Users List
   ============================================================ */
header('Location: index.php');
exit();
?>
