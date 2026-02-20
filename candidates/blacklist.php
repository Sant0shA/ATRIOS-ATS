<?php
ob_start();
/* ============================================================
   FILE: candidates/blacklist.php
   PURPOSE: Blacklist or unblacklist candidate with reason
   ACCESS: Admin, Manager only
   
   SECTIONS:
   - [AUTH-CHECK] Require Admin or Manager
   - [FETCH-CANDIDATE] Get candidate data
   - [FORM-PROCESSING] Handle blacklist action
   - [DATABASE-UPDATE] Update blacklist status
   - [HTML-FORM] Display blacklist confirmation form
   
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

$errors = [];
$candidate = null;

/* ============================================================
   [FETCH-CANDIDATE] - Get Candidate Data
   ============================================================ */
$candidateId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$action = isset($_GET['action']) ? $_GET['action'] : 'blacklist'; // blacklist or unblacklist

if ($candidateId <= 0) {
    setFlashMessage('error', 'Invalid candidate ID');
    header('Location: index.php');
    exit();
}

try {
    $stmt = $db->prepare("SELECT * FROM candidates WHERE candidate_id = ?");
    $stmt->execute([$candidateId]);
    $candidate = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$candidate) {
        setFlashMessage('error', 'Candidate not found');
        header('Location: index.php');
        exit();
    }
} catch (PDOException $e) {
    setFlashMessage('error', 'Database error');
    header('Location: index.php');
    exit();
}

/* ============================================================
   [FORM-PROCESSING] - Handle Form Submission
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $reason = trim($_POST['reason'] ?? '');
    
    // Validate reason for blacklisting
    if ($action === 'blacklist' && empty($reason)) {
        $errors['reason'] = 'Please provide a reason for blacklisting this candidate';
    } elseif ($action === 'blacklist' && strlen($reason) < 10) {
        $errors['reason'] = 'Reason must be at least 10 characters';
    }
    
    if (empty($errors)) {
        try {
            if ($action === 'blacklist') {
                // Blacklist the candidate
                $stmt = $db->prepare("
                    UPDATE candidates 
                    SET blacklisted = 1, blacklist_reason = ?, updated_at = NOW()
                    WHERE candidate_id = ?
                ");
                $stmt->execute([$reason, $candidateId]);
                
                logActivity($db, $_SESSION['user_id'], 'blacklist', 'candidate', $candidateId, 
                           "Blacklisted candidate: {$candidate['first_name']} {$candidate['last_name']} - Reason: {$reason}");
                
                $actionText = 'Blacklisted';
                $statusColor = 'danger';
            } else {
                // Unblacklist the candidate
                $stmt = $db->prepare("
                    UPDATE candidates 
                    SET blacklisted = 0, blacklist_reason = NULL, updated_at = NOW()
                    WHERE candidate_id = ?
                ");
                $stmt->execute([$candidateId]);
                
                logActivity($db, $_SESSION['user_id'], 'unblacklist', 'candidate', $candidateId, 
                           "Removed blacklist from: {$candidate['first_name']} {$candidate['last_name']}");
                
                $actionText = 'Unblacklisted';
                $statusColor = 'success';
            }
            
            // Show success popup
            echo "<!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
                <link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap' rel='stylesheet'>
                <style>
                    body { 
                        font-family: 'Inter', sans-serif;
                        margin: 0; padding: 0;
                        display: flex; align-items: center; justify-content: center;
                        min-height: 100vh;
                        background: rgba(0, 0, 0, 0.15);
                        animation: fadeIn 0.3s ease;
                    }
                    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
                    @keyframes slideUp {
                        from { opacity: 0; transform: translateY(20px) scale(0.95); }
                        to { opacity: 1; transform: translateY(0) scale(1); }
                    }
                    .success-modal {
                        background: white; border-radius: 16px;
                        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                        max-width: 420px; width: 90%;
                        padding: 48px 40px 40px; text-align: center;
                        animation: slideUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
                    }
                    .success-icon {
                        width: 80px; height: 80px;
                        background: linear-gradient(135deg, " . ($action === 'blacklist' ? '#ef4444 0%, #dc2626 100%' : '#10b981 0%, #059669 100%') . ");
                        border-radius: 50%;
                        display: flex; align-items: center; justify-content: center;
                        margin: 0 auto 24px; position: relative;
                    }
                    .success-icon::before {
                        content: ''; position: absolute;
                        width: 100%; height: 100%;
                        background: " . ($action === 'blacklist' ? 'rgba(239, 68, 68, 0.2)' : 'rgba(16, 185, 129, 0.2)') . ";
                        border-radius: 50%;
                        animation: pulse 2s infinite;
                    }
                    @keyframes pulse {
                        0%, 100% { transform: scale(1); opacity: 1; }
                        50% { transform: scale(1.1); opacity: 0.5; }
                    }
                    .icon-symbol {
                        color: white;
                        font-size: 40px;
                        font-weight: bold;
                    }
                    .success-title {
                        font-size: 28px; font-weight: 600;
                        color: #1a1a1a; margin: 0 0 12px;
                    }
                    .success-name {
                        font-size: 20px; 
                        color: " . ($action === 'blacklist' ? '#ef4444' : '#10b981') . ";
                        font-weight: 500; margin: 0 0 8px;
                    }
                    .success-message {
                        font-size: 15px; color: #6b7280;
                        margin: 0 0 32px;
                    }
                    .btn-ok {
                        background: linear-gradient(135deg, #F16136 0%, #FF8A65 100%);
                        color: white; border: none;
                        padding: 14px 48px; border-radius: 10px;
                        font-size: 16px; font-weight: 600;
                        cursor: pointer; transition: all 0.2s ease;
                        box-shadow: 0 4px 12px rgba(241, 97, 54, 0.3);
                    }
                    .btn-ok:hover {
                        transform: translateY(-2px);
                        box-shadow: 0 6px 20px rgba(241, 97, 54, 0.4);
                    }
                </style>
            </head>
            <body>
                <div class='success-modal'>
                    <div class='success-icon'>
                        <div class='icon-symbol'>" . ($action === 'blacklist' ? '!' : '✓') . "</div>
                    </div>
                    <h2 class='success-title'>Candidate {$actionText}!</h2>
                    <p class='success-name'>{$candidate['first_name']} {$candidate['last_name']}</p>
                    <p class='success-message'>" . 
                        ($action === 'blacklist' ? 
                            'This candidate has been blacklisted and will not appear in active searches.' : 
                            'Blacklist has been removed. Candidate is now active again.') . 
                    "</p>
                    <button class='btn-ok' onclick='window.location.href=\"index.php\"'>
                        OK, Got it!
                    </button>
                </div>
            </body>
            </html>";
            exit();
            
        } catch (PDOException $e) {
            $errors['general'] = 'Database error: ' . $e->getMessage();
        }
    }
}

$pageTitle = ($action === 'blacklist' ? 'Blacklist' : 'Remove Blacklist') . ' Candidate';
require_once '../includes/header.php';
?>

<!-- ============================================================
     [HTML-FORM] - Blacklist Confirmation Form
     ============================================================ -->

<div class="row">
    <div class="col-lg-6 mx-auto">
        <div class="card">
            <div class="card-header bg-<?php echo $action === 'blacklist' ? 'danger' : 'success'; ?> text-white">
                <h5 class="mb-0">
                    <i class="fas fa-<?php echo $action === 'blacklist' ? 'ban' : 'check-circle'; ?>"></i>
                    <?php echo $action === 'blacklist' ? 'Blacklist' : 'Remove Blacklist'; ?> Candidate
                </h5>
            </div>
            <div class="card-body">
                
                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-danger"><?php echo $errors['general']; ?></div>
                <?php endif; ?>
                
                <div class="alert alert-<?php echo $action === 'blacklist' ? 'warning' : 'info'; ?>">
                    <strong>Candidate:</strong> <?php echo htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']); ?><br>
                    <strong>Email:</strong> <?php echo htmlspecialchars($candidate['email']); ?><br>
                    <strong>Phone:</strong> <?php echo htmlspecialchars($candidate['phone']); ?>
                </div>
                
                <?php if ($action === 'blacklist'): ?>
                    
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Warning:</strong> Blacklisting will:
                        <ul class="mb-0 mt-2">
                            <li>Hide this candidate from active searches</li>
                            <li>Prevent them from being assigned to new jobs</li>
                            <li>Mark them as unavailable for placement</li>
                        </ul>
                    </div>
                    
                    <form method="POST" action="">
                        <div class="mb-4">
                            <label class="form-label">Reason for Blacklisting *</label>
                            <textarea name="reason" 
                                      class="form-control <?php echo isset($errors['reason']) ? 'is-invalid' : ''; ?>"
                                      rows="4"
                                      placeholder="Provide a detailed reason (minimum 10 characters)"
                                      required><?php echo htmlspecialchars($_POST['reason'] ?? ''); ?></textarea>
                            <small class="text-tertiary">This reason will be logged for audit purposes</small>
                            <?php if (isset($errors['reason'])): ?>
                                <div class="invalid-feedback d-block"><?php echo $errors['reason']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-ban"></i> Confirm Blacklist
                            </button>
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                    
                <?php else: ?>
                    
                    <?php if ($candidate['blacklist_reason']): ?>
                        <div class="alert alert-secondary">
                            <strong>Previous Blacklist Reason:</strong><br>
                            <?php echo nl2br(htmlspecialchars($candidate['blacklist_reason'])); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        Removing blacklist will make this candidate active again and visible in searches.
                    </div>
                    
                    <form method="POST" action="">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-check"></i> Confirm Remove Blacklist
                            </button>
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                    
                <?php endif; ?>
                
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
