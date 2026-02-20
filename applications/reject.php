<?php
ob_start();
/* ============================================================
   FILE: applications/reject.php
   PURPOSE: Reject application with optional reason
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

if ($applicationId <= 0) {
    setFlashMessage('error', 'Invalid application ID');
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? SITE_URL . '/jobs/'));
    exit();
}

/* ============================================================
   [FORM-PROCESSING] - Handle Rejection
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $reason = trim($_POST['reason'] ?? '');
    
    try {
        // Get application details for logging
        $stmt = $db->prepare("
            SELECT a.*, c.first_name, c.last_name 
            FROM applications a
            LEFT JOIN candidates c ON a.candidate_id = c.candidate_id
            WHERE a.application_id = ?
        ");
        $stmt->execute([$applicationId]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$application) {
            setFlashMessage('error', 'Application not found');
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? SITE_URL . '/jobs/'));
            exit();
        }
        
        // Update application status to rejected
        $stmt = $db->prepare("
            UPDATE applications 
            SET status = 'rejected', 
                screening_notes = CONCAT(COALESCE(screening_notes, ''), '\n\nRejection Reason: ', ?)
            WHERE application_id = ?
        ");
        $stmt->execute([
            $reason ?: 'No reason provided',
            $applicationId
        ]);
        
        // Log activity
        $logMessage = "Rejected application from {$application['first_name']} {$application['last_name']}";
        if ($reason) {
            $logMessage .= " - Reason: " . substr($reason, 0, 100);
        }
        
        logActivity($db, $_SESSION['user_id'], 'reject', 'application', $applicationId, $logMessage);
        
        setFlashMessage('success', 'Application rejected successfully');
        
    } catch (PDOException $e) {
        setFlashMessage('error', 'Database error: ' . $e->getMessage());
    }
    
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? SITE_URL . '/jobs/'));
    exit();
}

// If GET request, show confirmation form
$pageTitle = 'Reject Application';
require_once '../includes/header.php';

// Get application details
try {
    $stmt = $db->prepare("
        SELECT a.*, c.first_name, c.last_name, c.email, c.phone, j.job_title
        FROM applications a
        LEFT JOIN candidates c ON a.candidate_id = c.candidate_id
        LEFT JOIN jobs j ON a.job_id = j.job_id
        WHERE a.application_id = ?
    ");
    $stmt->execute([$applicationId]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$application) {
        echo '<div class="alert alert-danger">Application not found</div>';
        require_once '../includes/footer.php';
        exit();
    }
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Database error</div>';
    require_once '../includes/footer.php';
    exit();
}
?>

<div class="row">
    <div class="col-lg-6 mx-auto">
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">
                    <i class="fas fa-times-circle"></i> Reject Application
                </h5>
            </div>
            <div class="card-body">
                
                <div class="alert alert-warning">
                    <strong>Candidate:</strong> <?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?><br>
                    <strong>Email:</strong> <?php echo htmlspecialchars($application['email']); ?><br>
                    <strong>Job:</strong> <?php echo htmlspecialchars($application['job_title']); ?>
                </div>
                
                <p class="text-secondary mb-4">
                    Are you sure you want to reject this application? This action will mark the application as rejected.
                </p>
                
                <form method="POST" action="">
                    
                    <div class="mb-4">
                        <label class="form-label">Rejection Reason (Optional)</label>
                        <textarea name="reason" 
                                  class="form-control" 
                                  rows="4"
                                  placeholder="Provide a reason for rejection (optional but recommended)"></textarea>
                        <small class="text-tertiary">This will be logged for reference</small>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times"></i> Confirm Rejection
                        </button>
                        <a href="<?php echo $_SERVER['HTTP_REFERER'] ?? SITE_URL . '/jobs/'; ?>" 
                           class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Cancel
                        </a>
                    </div>
                    
                </form>
                
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
