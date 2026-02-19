<?php
/* ============================================================
   FILE: header.php
   PURPOSE: Main navigation and layout wrapper
   DEPENDENCIES: config.php, auth.php, database.php, functions.php
   LAST MODIFIED: 2026-02-20
   
   SECTIONS:
   - [SESSION-START] Initialize session
   - [LOAD-DEPENDENCIES] Include required files
   - [AUTH-CHECK] Verify user is logged in
   - [DB-CONNECT] Database connection
   - [USER-DATA] Fetch current user data
   - [HTML-HEAD] Page meta and stylesheet links
   - [SIDEBAR] Navigation menu
   - [TOPBAR] User info and page title
   - [CONTENT-START] Begin content area
   
   CHANGE LOG:
   2026-02-20: Refactored to use design system CSS
   ============================================================ */

/* ============================================================
   [SESSION-START] - Initialize Session
   ============================================================ */
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/* ============================================================
   [LOAD-DEPENDENCIES] - Include Required Files
   ============================================================ */
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/database.php';
    require_once __DIR__ . '/auth.php';
    require_once __DIR__ . '/functions.php';
}

/* ============================================================
   [AUTH-CHECK] - Verify User is Logged In
   ============================================================ */
requireLogin();

/* ============================================================
   [DB-CONNECT] - Database Connection
   ============================================================ */
$database = new Database();
$db = $database->getConnection();

/* ============================================================
   [USER-DATA] - Fetch Current User Data
   ============================================================ */
$currentUser = null;
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Fail silently - use session data as fallback
}

// Fallback to session data if query fails
if (!$currentUser) {
    $currentUser = [
        'full_name' => $_SESSION['user_name'] ?? 'User',
        'role' => $_SESSION['user_role'] ?? 'user',
        'email' => $_SESSION['user_email'] ?? ''
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- ============================================================
         [HTML-HEAD] - Page Meta and Styles
         ============================================================ -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Atrios ATS - Recruitment Management System">
    <title><?php echo htmlspecialchars($pageTitle ?? 'Dashboard'); ?> - Atrios ATS</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo SITE_URL; ?>/assets/images/favicon.ico">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom Design System CSS -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
</head>
<body>
    <!-- ============================================================
         [SIDEBAR] - Navigation Menu
         ============================================================ -->
    <div class="sidebar">
        <!-- Sidebar Header with Logo -->
        <div class="sidebar-header">
            <a href="<?php echo SITE_URL; ?>/dashboard.php" class="sidebar-logo">
                <div class="sidebar-logo-icon">
                    <i class="fas fa-briefcase"></i>
                </div>
                <div class="sidebar-logo-text">
                    <h4>Atrios ATS</h4>
                    <small>Recruitment System</small>
                </div>
            </a>
        </div>
        
        <!-- Main Navigation Menu -->
        <ul class="sidebar-menu">
            <li>
                <a href="<?php echo SITE_URL; ?>/dashboard.php" 
                   class="<?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i> Dashboard
                </a>
            </li>
            
            <?php if (hasRole(['admin', 'manager'])): ?>
            <li>
                <a href="<?php echo SITE_URL; ?>/clients/">
                    <i class="fas fa-building"></i> Clients
                </a>
            </li>
            <?php endif; ?>
            
            <li>
                <a href="<?php echo SITE_URL; ?>/jobs/">
                    <i class="fas fa-briefcase"></i> Jobs
                </a>
            </li>
            
            <li>
                <a href="<?php echo SITE_URL; ?>/candidates/">
                    <i class="fas fa-user-tie"></i> Candidates
                </a>
            </li>
            
            <li>
                <a href="<?php echo SITE_URL; ?>/applications/">
                    <i class="fas fa-file-alt"></i> Applications
                </a>
            </li>
            
            <li>
                <a href="<?php echo SITE_URL; ?>/reports/">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
            </li>
            
            <?php if (hasRole('admin')): ?>
            <li>
                <a href="<?php echo SITE_URL; ?>/users/">
                    <i class="fas fa-users"></i> Users
                </a>
            </li>
            <li>
                <a href="<?php echo SITE_URL; ?>/settings.php">
                    <i class="fas fa-cog"></i> Settings
                </a>
            </li>
            <?php endif; ?>
        </ul>
        
        <!-- Logout at Bottom -->
        <div class="sidebar-footer">
            <ul class="sidebar-menu">
                <li>
                    <a href="<?php echo SITE_URL; ?>/logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
    
    <!-- ============================================================
         [MAIN-CONTENT] - Content Area
         ============================================================ -->
    <div class="main-content">
        <!-- ============================================================
             [TOPBAR] - Top Navigation Bar
             ============================================================ -->
        <div class="top-navbar">
            <h5><?php echo htmlspecialchars($pageTitle ?? 'Dashboard'); ?></h5>
            
            <div class="user-info">
                <div class="user-avatar">
                    <?php 
                    // Generate initials from name
                    $name = $currentUser['full_name'] ?? 'User';
                    $nameParts = explode(' ', $name);
                    if (count($nameParts) >= 2) {
                        echo strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
                    } else {
                        echo strtoupper(substr($name, 0, 2));
                    }
                    ?>
                </div>
                <div class="user-details">
                    <div class="user-name"><?php echo htmlspecialchars($currentUser['full_name']); ?></div>
                    <div class="user-role"><?php echo ucfirst($currentUser['role']); ?></div>
                </div>
            </div>
        </div>
        
        <!-- ============================================================
             [CONTENT-START] - Begin Content Area
             ============================================================ -->
        <div class="content-area">
            <?php
            /* ============================================================
               [FLASH-MESSAGES] - Display Flash Messages
               ============================================================ */
            $flash = getFlashMessage();
            if ($flash):
                $alertType = $flash['type'] === 'success' ? 'success' : 
                            ($flash['type'] === 'error' ? 'danger' : $flash['type']);
            ?>
                <div class="alert alert-<?php echo $alertType; ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($flash['message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Page content starts here -->
