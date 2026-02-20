<?php
ob_start();
/* ============================================================
   FILE: clients/delete.php
   PURPOSE: Soft delete client (mark as inactive)
   ACCESS: Admin, Manager only
   
   SECTIONS:
   - [AUTH-CHECK] Verify admin/manager access
   - [PARAMETER-VALIDATION] Validate client ID
   - [DATABASE-UPDATE] Update client status to inactive
   - [REDIRECT] Return to client list
   
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
$clientId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($clientId <= 0) {
    setFlashMessage('error', 'Invalid client ID');
    header('Location: index.php');
    exit();
}

/* ============================================================
   [DATABASE-UPDATE] - Mark Client as Inactive
   ============================================================ */
try {
    // First, check if client exists
    $stmt = $db->prepare("SELECT client_id, company_name FROM clients WHERE client_id = ?");
    $stmt->execute([$clientId]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$client) {
        setFlashMessage('error', 'Client not found');
        header('Location: index.php');
        exit();
    }
    
    // Check if client has active jobs
    $jobsStmt = $db->prepare("SELECT COUNT(*) as job_count FROM jobs WHERE client_id = ? AND status IN ('active', 'draft')");
    $jobsStmt->execute([$clientId]);
    $jobCount = $jobsStmt->fetch(PDO::FETCH_ASSOC)['job_count'];
    
    if ($jobCount > 0) {
        setFlashMessage('warning', "Cannot deactivate client '{$client['company_name']}' - they have {$jobCount} active job(s). Please close those jobs first.");
        header('Location: view.php?id=' . $clientId);
        exit();
    }
    
    // Update client status to inactive
    $stmt = $db->prepare("UPDATE clients SET status = 'inactive', updated_at = NOW() WHERE client_id = ?");
    $stmt->execute([$clientId]);
    
    // Log activity
    logActivity($db, $_SESSION['user_id'], 'delete', 'client', $clientId, 
               "Deactivated client: {$client['company_name']}");
    
    setFlashMessage('success', "Client '{$client['company_name']}' has been deactivated successfully");
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Database error: ' . $e->getMessage());
}

/* ============================================================
   [REDIRECT] - Return to Clients List
   ============================================================ */
header('Location: index.php');
exit();
?>
