<?php
ob_start();
/* ============================================================
   FILE: clients/view.php
   PURPOSE: View client details and associated jobs
   ACCESS: All users (view assigned clients)
   
   SECTIONS:
   - [AUTH-CHECK] Verify user access
   - [FETCH-CLIENT] Get client data from database
   - [FETCH-JOBS] Get jobs for this client
   - [HTML-OUTPUT] Display client details and jobs
   
   LAST MODIFIED: 2026-02-20
   ============================================================ */

$pageTitle = 'View Client';
require_once '../includes/header.php';

/* ============================================================
   [FETCH-CLIENT] - Get Client ID and Fetch Data
   ============================================================ */
$clientId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($clientId <= 0) {
    setFlashMessage('error', 'Invalid client ID');
    header('Location: index.php');
    exit();
}

try {
    $stmt = $db->prepare("
        SELECT c.*, 
               u1.full_name as created_by_name,
               u2.full_name as assigned_to_name,
               u2.email as assigned_to_email
        FROM clients c
        LEFT JOIN users u1 ON c.created_by = u1.user_id
        LEFT JOIN users u2 ON c.assigned_to = u2.user_id
        WHERE c.client_id = ?
    ");
    $stmt->execute([$clientId]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$client) {
        setFlashMessage('error', 'Client not found');
        header('Location: index.php');
        exit();
    }
    
    $pageTitle = $client['company_name'];
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Database error: ' . $e->getMessage());
    header('Location: index.php');
    exit();
}

/* ============================================================
   [FETCH-JOBS] - Get Jobs for This Client
   ============================================================ */
try {
    $jobsStmt = $db->prepare("
        SELECT j.*, 
               u.full_name as created_by_name,
               (SELECT COUNT(*) FROM applications WHERE job_id = j.job_id) as application_count
        FROM jobs j
        LEFT JOIN users u ON j.created_by = u.user_id
        WHERE j.client_id = ?
        ORDER BY j.created_at DESC
    ");
    $jobsStmt->execute([$clientId]);
    $jobs = $jobsStmt->fetchAll();
} catch (PDOException $e) {
    $jobs = [];
}
?>

<!-- ============================================================
     [HTML-OUTPUT] - Client View Interface
     ============================================================ -->

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="index.php" class="btn btn-secondary btn-sm mb-2">
            <i class="fas fa-arrow-left"></i> Back to Clients
        </a>
        <h2><?php echo htmlspecialchars($client['company_name']); ?></h2>
        <?php if ($client['industry']): ?>
            <p class="text-secondary mb-0"><?php echo htmlspecialchars($client['industry']); ?></p>
        <?php endif; ?>
    </div>
    
    <?php if (hasRole(['admin', 'manager'])): ?>
        <a href="edit.php?id=<?php echo $clientId; ?>" class="btn btn-primary">
            <i class="fas fa-edit"></i> Edit Client
        </a>
    <?php endif; ?>
</div>

<div class="row">
    <!-- Client Information -->
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="fas fa-building"></i> Client Information</h5>
            </div>
            <div class="card-body">
                
                <!-- Company Name -->
                <div class="mb-3">
                    <strong>Company Name</strong><br>
                    <span><?php echo htmlspecialchars($client['company_name']); ?></span>
                </div>
                
                <!-- Industry -->
                <?php if ($client['industry']): ?>
                <div class="mb-3">
                    <strong>Industry</strong><br>
                    <span><?php echo htmlspecialchars($client['industry']); ?></span>
                </div>
                <?php endif; ?>
                
                <!-- Contact Person -->
                <?php if ($client['contact_person']): ?>
                <div class="mb-3">
                    <strong>Contact Person</strong><br>
                    <span><?php echo htmlspecialchars($client['contact_person']); ?></span>
                </div>
                <?php endif; ?>
                
                <!-- Email -->
                <?php if ($client['email']): ?>
                <div class="mb-3">
                    <strong>Email</strong><br>
                    <a href="mailto:<?php echo htmlspecialchars($client['email']); ?>">
                        <?php echo htmlspecialchars($client['email']); ?>
                    </a>
                </div>
                <?php endif; ?>
                
                <!-- Phone -->
                <?php if ($client['phone']): ?>
                <div class="mb-3">
                    <strong>Phone</strong><br>
                    <a href="tel:<?php echo htmlspecialchars($client['phone']); ?>">
                        <?php echo htmlspecialchars($client['phone']); ?>
                    </a>
                </div>
                <?php endif; ?>
                
                <!-- Website -->
                <?php if ($client['website']): ?>
                <div class="mb-3">
                    <strong>Website</strong><br>
                    <a href="<?php echo htmlspecialchars($client['website']); ?>" target="_blank">
                        <?php echo htmlspecialchars($client['website']); ?>
                        <i class="fas fa-external-link-alt fa-xs"></i>
                    </a>
                </div>
                <?php endif; ?>
                
                <!-- Address -->
                <?php if ($client['address'] || $client['city'] || $client['state']): ?>
                <div class="mb-3">
                    <strong>Address</strong><br>
                    <?php if ($client['address']): ?>
                        <?php echo nl2br(htmlspecialchars($client['address'])); ?><br>
                    <?php endif; ?>
                    <?php if ($client['city']): ?>
                        <?php echo htmlspecialchars($client['city']); ?>
                    <?php endif; ?>
                    <?php if ($client['state']): ?>
                        , <?php echo htmlspecialchars($client['state']); ?>
                    <?php endif; ?>
                    <?php if ($client['postal_code']): ?>
                        - <?php echo htmlspecialchars($client['postal_code']); ?>
                    <?php endif; ?>
                    <?php if ($client['country']): ?>
                        <br><?php echo htmlspecialchars($client['country']); ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <!-- Status -->
                <div class="mb-3">
                    <strong>Status</strong><br>
                    <?php
                    $statusColors = [
                        'active' => 'success',
                        'inactive' => 'secondary',
                        'on-hold' => 'warning'
                    ];
                    $color = $statusColors[$client['status']] ?? 'secondary';
                    ?>
                    <span class="badge badge-<?php echo $color; ?>">
                        <?php echo ucwords(str_replace('-', ' ', $client['status'])); ?>
                    </span>
                </div>
                
                <!-- Agreement Document -->
                <div class="mb-3">
                    <strong>Client Agreement</strong><br>
                    <?php if ($client['agreement_path']): ?>
                        <a href="<?php echo SITE_URL . '/' . htmlspecialchars($client['agreement_path']); ?>" 
                           target="_blank"
                           class="btn btn-sm btn-outline">
                            <i class="fas fa-file-contract"></i> View Agreement
                        </a>
                    <?php else: ?>
                        <span class="text-tertiary">No agreement uploaded</span>
                    <?php endif; ?>
                </div>
                
                <!-- Assigned To -->
                <div class="mb-3">
                    <strong>Assigned To (Client Owner)</strong><br>
                    <?php if ($client['assigned_to_name']): ?>
                        <span class="badge badge-primary">
                            <?php echo htmlspecialchars($client['assigned_to_name']); ?>
                        </span>
                        <?php if ($client['assigned_to_email']): ?>
                            <br><small>
                                <a href="mailto:<?php echo htmlspecialchars($client['assigned_to_email']); ?>">
                                    <?php echo htmlspecialchars($client['assigned_to_email']); ?>
                                </a>
                            </small>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-tertiary">Unassigned</span>
                    <?php endif; ?>
                </div>
                
                <hr>
                
                <!-- Created By -->
                <div class="mb-2">
                    <small class="text-secondary">
                        <strong>Added On:</strong> <?php echo formatDateTime($client['created_at']); ?>
                    </small>
                </div>
                
                <?php if ($client['created_by_name']): ?>
                <div class="mb-0">
                    <small class="text-secondary">
                        <strong>Added By:</strong> <?php echo htmlspecialchars($client['created_by_name']); ?>
                    </small>
                </div>
                <?php endif; ?>
                
            </div>
        </div>
    </div>
    
    <!-- Jobs List -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-briefcase"></i> Jobs (<?php echo count($jobs); ?>)</h5>
                <?php if (hasRole(['admin', 'manager'])): ?>
                    <a href="../jobs/add.php?client_id=<?php echo $clientId; ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Post New Job
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (empty($jobs)): ?>
                    <div class="p-4 text-center text-secondary">
                        <i class="fas fa-briefcase fa-3x mb-3"></i>
                        <p>No jobs posted for this client yet</p>
                        <?php if (hasRole(['admin', 'manager'])): ?>
                            <a href="../jobs/add.php?client_id=<?php echo $clientId; ?>" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Post New Job
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Job Title</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                    <th>Applications</th>
                                    <th>Posted</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($jobs as $job): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($job['job_title']); ?></strong>
                                        <?php if ($job['employment_type']): ?>
                                            <br><small class="text-secondary">
                                                <?php echo ucwords(str_replace('-', ' ', $job['employment_type'])); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($job['location'] ?? '—'); ?></td>
                                    <td>
                                        <?php
                                        $jobStatusColors = [
                                            'draft' => 'secondary',
                                            'active' => 'success',
                                            'on-hold' => 'warning',
                                            'closed' => 'danger',
                                            'filled' => 'info'
                                        ];
                                        $jobColor = $jobStatusColors[$job['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge badge-<?php echo $jobColor; ?>">
                                            <?php echo ucwords($job['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-secondary">
                                            <?php echo $job['application_count']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small><?php echo timeAgo($job['created_at']); ?></small>
                                    </td>
                                    <td>
                                        <a href="../jobs/view.php?id=<?php echo $job['job_id']; ?>" 
                                           class="btn btn-outline btn-sm"
                                           title="View Job">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
