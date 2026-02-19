<?php
// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Load dependencies - use absolute path from document root
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/database.php';
    require_once __DIR__ . '/auth.php';
    require_once __DIR__ . '/functions.php';
}

// Check login
requireLogin();

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Get current user
$currentUser = null;
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Fail silently
}

// Fallback
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Dashboard'; ?> - Atrios ATS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --sidebar-width: 250px;
            --atrios-orange: #F16136;
            --atrios-gradient: linear-gradient(135deg, #F16136 0%, #FF8A65 100%);
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: #2c3e50;
            color: white;
            overflow-y: auto;
            z-index: 1000;
        }
        .sidebar-header {
            padding: 20px;
            background: var(--atrios-gradient);
            text-align: center;
        }
        .sidebar-menu {
            padding: 0;
            margin: 0;
            list-style: none;
        }
        .sidebar-menu li a {
            display: block;
            padding: 15px 20px;
            color: #ecf0f1;
            text-decoration: none;
            transition: all 0.3s;
        }
        .sidebar-menu li a:hover,
        .sidebar-menu li a.active {
            background: rgba(241, 97, 54, 0.2);
            border-left: 4px solid var(--atrios-orange);
        }
        .sidebar-menu li a i {
            width: 20px;
            margin-right: 10px;
        }
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }
        .top-navbar {
            background: white;
            padding: 15px 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .content-area {
            padding: 30px;
        }
        .card {
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-radius: 10px;
        }
        .btn-primary {
            background: var(--atrios-gradient);
            border: none;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h4><i class="fas fa-briefcase"></i> Atrios ATS</h4>
            <small>Recruitment System</small>
        </div>
        <ul class="sidebar-menu">
            <li><a href="<?php echo SITE_URL; ?>/dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="<?php echo SITE_URL; ?>/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="top-navbar">
            <h5 class="mb-0"><?php echo $pageTitle ?? 'Dashboard'; ?></h5>
            <div>
                <strong><?php echo htmlspecialchars($currentUser['full_name']); ?></strong>
                <small class="text-muted ms-2">(<?php echo ucfirst($currentUser['role']); ?>)</small>
            </div>
        </div>
        
        <div class="content-area">
            <?php
            $flash = getFlashMessage();
            if ($flash):
                $alertType = $flash['type'] === 'success' ? 'success' : ($flash['type'] === 'error' ? 'danger' : $flash['type']);
            ?>
                <div class="alert alert-<?php echo $alertType; ?> alert-dismissible fade show">
                    <?php echo $flash['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
