<?php
ob_start();
/* ============================================================
   FILE: jobs/delete.php
   PURPOSE: Close job posting (soft delete)
   ACCESS: Admin, Manager only
   
   SECTIONS:
   - [AUTH-CHECK] Verify admin/manager access
   - [PARAMETER-VALIDATION] Validate job ID
   - [DATABASE-UPDATE] Update job status to closed
   - [REDIRECT] Return to jobs list
   
   LAST MODIFIED: 2026-02-20
   ============================================================ */

session_start();
require_once '../config.php';
require_once '../includes/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

/* ============================================================
   [AUTH-CHECK] - Require Admin or Manager Role
   ============================================================ */
requireRole(['admin', 'manager']);

$database = new Database();
$db = $database->getConnection();

/* ============================================================
   [PARAMETER-VALIDATION] - Get and Validate Parameters
   ============================================================ */
$jobId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($jobId <= 0) {
    setFlashMessage('error', 'Invalid job ID');
    header('Location: index.php');
    exit();
}

/* ============================================================
   [DATABASE-UPDATE] - Close Job
   ============================================================ */
try {
    // First, check if job exists
    $stmt = $db->prepare("SELECT job_id, job_title, status FROM jobs WHERE job_id = ?");
    $stmt->execute([$jobId]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$job) {
        setFlashMessage('error', 'Job not found');
        header('Location: index.php');
        exit();
    }
    
    // Check if job is already closed
    if ($job['status'] === 'closed') {
        setFlashMessage('warning', "Job '{$job['job_title']}' is already closed");
        header('Location: view.php?id=' . $jobId);
        exit();
    }
    
    // Get application count
    $appStmt = $db->prepare("SELECT COUNT(*) as app_count FROM applications WHERE job_id = ?");
    $appStmt->execute([$jobId]);
    $appCount = $appStmt->fetch(PDO::FETCH_ASSOC)['app_count'];
    
    // Update job status to closed
    $stmt = $db->prepare("UPDATE jobs SET status = 'closed', updated_at = NOW() WHERE job_id = ?");
    $stmt->execute([$jobId]);
    
    // Log activity
    logActivity($db, $_SESSION['user_id'], 'delete', 'job', $jobId, 
               "Closed job: {$job['job_title']} ({$appCount} applications)");
    
    setFlashMessage('success', "Job '{$job['job_title']}' has been closed successfully");
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Database error: ' . $e->getMessage());
}

/* ============================================================
   [REDIRECT] - Return to Jobs List
   ============================================================ */
header('Location: index.php');
exit();
?>
