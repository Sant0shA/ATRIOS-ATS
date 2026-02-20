<?php
ob_start();
/* ============================================================
   FILE: candidates/view.php
   PURPOSE: View complete candidate profile and application history
   ACCESS: All users (filtered by assignment)
   
   LAST MODIFIED: 2026-02-20
   ============================================================ */

$pageTitle = 'View Candidate';
require_once '../includes/header.php';

$candidateId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($candidateId <= 0) {
    setFlashMessage('error', 'Invalid candidate ID');
    header('Location: index.php');
    exit();
}

try {
    $stmt = $db->prepare("
        SELECT c.*, 
               u1.full_name as added_by_name,
               u2.full_name as assigned_to_name
        FROM candidates c
        LEFT JOIN users u1 ON c.added_by = u1.user_id
        LEFT JOIN users u2 ON c.assigned_to = u2.user_id
        WHERE c.candidate_id = ?
    ");
    $stmt->execute([$candidateId]);
    $candidate = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$candidate) {
        setFlashMessage('error', 'Candidate not found');
        header('Location: index.php');
        exit();
    }
    
    $pageTitle = $candidate['first_name'] . ' ' . $candidate['last_name'];
    
    // Get applications
    $appsStmt = $db->prepare("
        SELECT a.*, j.job_title, c.company_name
        FROM applications a
        LEFT JOIN jobs j ON a.job_id = j.job_id
        LEFT JOIN clients c ON j.client_id = c.client_id
        WHERE a.candidate_id = ?
        ORDER BY a.applied_at DESC
    ");
    $appsStmt->execute([$candidateId]);
    $applications = $appsStmt->fetchAll();
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Database error: ' . $e->getMessage());
    header('Location: index.php');
    exit();
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="index.php" class="btn btn-secondary btn-sm mb-2">
            <i class="fas fa-arrow-left"></i> Back to Candidates
        </a>
        <h2><?php echo htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']); ?></h2>
    </div>
    
    <div class="d-flex gap-2">
        <?php if (hasRole(['admin', 'manager']) || $candidate['added_by'] == $_SESSION['user_id']): ?>
            <a href="edit.php?id=<?php echo $candidateId; ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> Edit
            </a>
        <?php endif; ?>
        
        <?php if (hasRole(['admin', 'manager']) && !$candidate['blacklisted']): ?>
            <a href="blacklist.php?id=<?php echo $candidateId; ?>" 
               class="btn btn-outline-danger"
               onclick="return confirm('Are you sure you want to blacklist this candidate?')">
                <i class="fas fa-ban"></i> Blacklist
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <!-- Candidate Info -->
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5>Contact Information</h5>
            </div>
            <div class="card-body">
                <?php if ($candidate['blacklisted']): ?>
                    <div class="alert alert-danger mb-3">
                        <strong><i class="fas fa-ban"></i> BLACKLISTED</strong>
                        <?php if ($candidate['blacklist_reason']): ?>
                            <p class="mb-0 mt-2 small"><?php echo nl2br(htmlspecialchars($candidate['blacklist_reason'])); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <div class="mb-3">
                    <strong>Email:</strong><br>
                    <a href="mailto:<?php echo htmlspecialchars($candidate['email']); ?>">
                        <?php echo htmlspecialchars($candidate['email']); ?>
                    </a>
                </div>
                
                <div class="mb-3">
                    <strong>Phone:</strong><br>
                    <a href="tel:<?php echo htmlspecialchars($candidate['phone']); ?>">
                        <?php echo htmlspecialchars($candidate['phone']); ?>
                    </a>
                </div>
                
                <div class="mb-3">
                    <strong>Location:</strong><br>
                    <?php echo htmlspecialchars($candidate['current_location'] ?? '—'); ?>
                </div>
                
                <?php if ($candidate['linkedin_url']): ?>
                <div class="mb-3">
                    <strong>LinkedIn:</strong><br>
                    <a href="<?php echo htmlspecialchars($candidate['linkedin_url']); ?>" target="_blank">
                        View Profile <i class="fas fa-external-link-alt fa-xs"></i>
                    </a>
                </div>
                <?php endif; ?>
                
                <?php if ($candidate['cv_path']): ?>
                <div class="mb-3">
                    <strong>Resume:</strong><br>
                    <a href="<?php echo SITE_URL . '/' . htmlspecialchars($candidate['cv_path']); ?>" 
                       target="_blank"
                       class="btn btn-sm btn-outline">
                        <i class="fas fa-file-pdf"></i> View Resume
                    </a>
                </div>
                <?php endif; ?>
                
                <hr>
                
                <div class="mb-2">
                    <strong>Assigned To:</strong><br>
                    <?php if ($candidate['assigned_to_name']): ?>
                        <span class="badge badge-primary"><?php echo htmlspecialchars($candidate['assigned_to_name']); ?></span>
                    <?php else: ?>
                        <span class="text-tertiary">Unassigned</span>
                    <?php endif; ?>
                </div>
                
                <div class="mb-2">
                    <strong>Source:</strong><br>
                    <?php echo htmlspecialchars($candidate['source'] ?? '—'); ?>
                </div>
                
                <div class="mb-2">
                    <small class="text-secondary">
                        <strong>Added:</strong> <?php echo formatDateTime($candidate['created_at']); ?>
                        <?php if ($candidate['added_by_name']): ?>
                            <br>by <?php echo htmlspecialchars($candidate['added_by_name']); ?>
                        <?php endif; ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Professional Details & Applications -->
    <div class="col-lg-8">
        <!-- Professional Details -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>Professional Details</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Experience:</strong><br>
                        <?php echo $candidate['experience_years'] ? $candidate['experience_years'] . ' years' : '—'; ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Notice Period:</strong><br>
                        <?php echo htmlspecialchars($candidate['notice_period'] ?? '—'); ?>
                    </div>
                </div>
                
                <?php if ($candidate['current_designation'] || $candidate['current_company']): ?>
                <div class="mb-3">
                    <strong>Current Role:</strong><br>
                    <?php if ($candidate['current_designation']): ?>
                        <?php echo htmlspecialchars($candidate['current_designation']); ?>
                    <?php endif; ?>
                    <?php if ($candidate['current_company']): ?>
                        @ <?php echo htmlspecialchars($candidate['current_company']); ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($candidate['education']): ?>
                <div class="mb-3">
                    <strong>Education:</strong><br>
                    <?php echo htmlspecialchars($candidate['education']); ?>
                </div>
                <?php endif; ?>
                
                <?php if ($candidate['expected_salary']): ?>
                <div class="mb-3">
                    <strong>Expected Salary:</strong><br>
                    <?php echo formatCurrency($candidate['expected_salary']); ?> per annum
                </div>
                <?php endif; ?>
                
                <?php if ($candidate['skills']): ?>
                <div class="mb-3">
                    <strong>Skills:</strong><br>
                    <?php 
                    $skills = explode(',', $candidate['skills']);
                    foreach ($skills as $skill):
                        $skill = trim($skill);
                    ?>
                        <span class="badge badge-secondary me-1 mb-1"><?php echo htmlspecialchars($skill); ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($candidate['notes']): ?>
                <div>
                    <strong>Notes:</strong><br>
                    <p class="text-secondary mb-0"><?php echo nl2br(htmlspecialchars($candidate['notes'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Applications -->
        <div class="card">
            <div class="card-header">
                <h5>Application History (<?php echo count($applications); ?>)</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($applications)): ?>
                    <div class="p-4 text-center text-secondary">
                        <i class="fas fa-inbox fa-3x mb-3"></i>
                        <p>No applications yet</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Job Title</th>
                                    <th>Company</th>
                                    <th>Status</th>
                                    <th>Applied</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($applications as $app): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($app['job_title']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($app['company_name']); ?></td>
                                    <td>
                                        <?php
                                        $statusColors = [
                                            'new' => 'primary',
                                            'screening' => 'info',
                                            'shortlisted' => 'success',
                                            'interviewed' => 'warning',
                                            'offered' => 'success',
                                            'hired' => 'success',
                                            'rejected' => 'danger'
                                        ];
                                        $color = $statusColors[$app['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge badge-<?php echo $color; ?>">
                                            <?php echo ucfirst($app['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small><?php echo timeAgo($app['applied_at']); ?></small>
                                    </td>
                                    <td>
                                        <a href="../applications/view.php?id=<?php echo $app['application_id']; ?>" 
                                           class="btn btn-outline btn-sm">
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
