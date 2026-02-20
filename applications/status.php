<?php
ob_start();
/* ============================================================
   FILE: applications/status.php
   PURPOSE: Change application status
   ACCESS: Admin, Manager, assigned recruiters
   
   LAST MODIFIED: 2026-02-20
   ============================================================ */

session_start();
require_once '../config.php';
require_once '../includes/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();

/* ============================================================
   [PARAMETER-VALIDATION] - Get Parameters
   ============================================================ */
$applicationId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$newStatus = isset($_GET['status']) ? $_GET['status'] : '';

// Valid statuses
$validStatuses = ['new', 'screening', 'shortlisted', 'interviewed', 'offered', 'hired', 'rejected', 'withdrawn'];

if ($applicationId <= 0 || !in_array($newStatus, $validStatuses)) {
    setFlashMessage('error', 'Invalid parameters');
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? SITE_URL . '/jobs/'));
    exit();
}

/* ============================================================
   [UPDATE-STATUS] - Update Application Status
   ============================================================ */
try {
    // Get application details for logging
    $stmt = $db->prepare("
        SELECT a.*, c.first_name, c.last_name, j.job_title
        FROM applications a
        LEFT JOIN candidates c ON a.candidate_id = c.candidate_id
        LEFT JOIN jobs j ON a.job_id = j.job_id
        WHERE a.application_id = ?
    ");
    $stmt->execute([$applicationId]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$application) {
        setFlashMessage('error', 'Application not found');
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? SITE_URL . '/jobs/'));
        exit();
    }
    
    $oldStatus = $application['status'];
    
    // Update status
    $stmt = $db->prepare("UPDATE applications SET status = ? WHERE application_id = ?");
    $stmt->execute([$newStatus, $applicationId]);
    
    // Log activity
    logActivity(
        $db, 
        $_SESSION['user_id'], 
        'update', 
        'application', 
        $applicationId, 
        "Changed application status from '{$oldStatus}' to '{$newStatus}' for {$application['first_name']} {$application['last_name']}"
    );
    
    setFlashMessage('success', "Application status updated to '" . ucfirst($newStatus) . "'");
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Database error: ' . $e->getMessage());
}

/* ============================================================
   [REDIRECT] - Return to Previous Page
   ============================================================ */
header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? SITE_URL . '/jobs/'));
exit();
?>
