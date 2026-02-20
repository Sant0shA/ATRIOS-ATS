<?php
ob_start();
/* ============================================================
   FILE: jobs/view.php
   PURPOSE: View job details, public apply link, and applications
   ACCESS: All users (based on assignment/client ownership)
   
   UPDATED: Added Accept/Reject/Status buttons for applications
   
   LAST MODIFIED: 2026-02-20
   ============================================================ */

$pageTitle = 'View Job';
require_once '../includes/header.php';

/* ============================================================
   [FETCH-JOB] - Get Job ID and Fetch Data
   ============================================================ */
$jobId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($jobId <= 0) {
    setFlashMessage('error', 'Invalid job ID');
    header('Location: index.php');
    exit();
}

try {
    $stmt = $db->prepare("
        SELECT j.*, 
               c.company_name, c.client_id,
               u.full_name as created_by_name
        FROM jobs j
        LEFT JOIN clients c ON j.client_id = c.client_id
        LEFT JOIN users u ON j.created_by = u.user_id
        WHERE j.job_id = ?
    ");
    $stmt->execute([$jobId]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$job) {
        setFlashMessage('error', 'Job not found');
        header('Location: index.php');
        exit();
    }
    
    $pageTitle = $job['job_title'];
    
    // Parse assigned recruiters JSON
    $assignedRecruiters = json_decode($job['assigned_to'], true) ?? [];
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Database error: ' . $e->getMessage());
    header('Location: index.php');
    exit();
}

/* ============================================================
   [FETCH-APPLICATIONS] - Get Applications for This Job
   ============================================================ */
try {
    $appsStmt = $db->prepare("
        SELECT a.*, 
               c.first_name, c.last_name, c.email, c.phone,
               u.full_name as assigned_recruiter_name
        FROM applications a
        LEFT JOIN candidates c ON a.candidate_id = c.candidate_id
        LEFT JOIN users u ON a.assigned_recruiter = u.user_id
        WHERE a.job_id = ?
        ORDER BY a.applied_at DESC
    ");
    $appsStmt->execute([$jobId]);
    $applications = $appsStmt->fetchAll();
} catch (PDOException $e) {
    $applications = [];
}

// Get assigned recruiter names
$recruiterNames = [];
if (!empty($assignedRecruiters)) {
    try {
        $placeholders = implode(',', array_fill(0, count($assignedRecruiters), '?'));
        $recStmt = $db->prepare("SELECT user_id, full_name FROM users WHERE user_id IN ($placeholders)");
        $recStmt->execute($assignedRecruiters);
        $recruiters = $recStmt->fetchAll();
        foreach ($recruiters as $rec) {
            $recruiterNames[$rec['user_id']] = $rec['full_name'];
        }
    } catch (PDOException $e) {
        // Ignore
    }
}

// Generate public apply URL
$applyUrl = SITE_URL . '/apply.php?token=' . $job['apply_link_token'];
?>

<!-- ============================================================
     [HTML-OUTPUT] - Job View Interface
     ============================================================ -->

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="index.php" class="btn btn-secondary btn-sm mb-2">
            <i class="fas fa-arrow-left"></i> Back to Jobs
        </a>
        <h2><?php echo htmlspecialchars($job['job_title']); ?></h2>
        <p class="text-secondary mb-0">
            <a href="../clients/view.php?id=<?php echo $job['client_id']; ?>">
                <?php echo htmlspecialchars($job['company_name']); ?>
            </a>
        </p>
    </div>
    
    <?php if (hasRole(['admin', 'manager'])): ?>
        <a href="edit.php?id=<?php echo $jobId; ?>" class="btn btn-primary">
            <i class="fas fa-edit"></i> Edit Job
        </a>
    <?php endif; ?>
</div>

<div class="row">
    <!-- Job Details -->
    <div class="col-lg-8">
        
        <!-- Public Apply Link Card -->
        <div class="card mb-4 border-primary">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-link"></i> Public Apply Link</h5>
            </div>
            <div class="card-body">
                <p class="mb-2">Share this link with candidates to apply:</p>
                <div class="input-group">
                    <input type="text" 
                           class="form-control" 
                           value="<?php echo $applyUrl; ?>" 
                           id="applyUrlInput"
                           readonly>
                    <button class="btn btn-outline" 
                            onclick="copyApplyLink()"
                            type="button">
                        <i class="fas fa-copy"></i> Copy
                    </button>
                </div>
                <small class="text-secondary">
                    Candidates will answer screening questions when applying through this link
                </small>
            </div>
        </div>
        
        <!-- Job Description -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>Job Description</h5>
            </div>
            <div class="card-body">
                <?php if ($job['job_description']): ?>
                    <p style="white-space: pre-wrap;"><?php echo htmlspecialchars($job['job_description']); ?></p>
                <?php else: ?>
                    <p class="text-tertiary">No description provided</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Requirements -->
        <?php if ($job['requirements']): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5>Requirements</h5>
            </div>
            <div class="card-body">
                <p style="white-space: pre-wrap;"><?php echo htmlspecialchars($job['requirements']); ?></p>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Screening Questions -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>Screening Questions for Applicants</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Question 1:</strong><br>
                    <?php echo htmlspecialchars($job['screening_question_1']); ?>
                </div>
                <div class="mb-0">
                    <strong>Question 2:</strong><br>
                    <?php echo htmlspecialchars($job['screening_question_2']); ?>
                </div>
            </div>
        </div>
        
        <!-- Applications List -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-file-alt"></i> Applications (<?php echo count($applications); ?>)</h5>
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
                                    <th>Candidate</th>
                                    <th>Status</th>
                                    <th>Assigned To</th>
                                    <th>Applied</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($applications as $app): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></strong>
                                        <br><small class="text-secondary"><?php echo htmlspecialchars($app['email']); ?></small>
                                    </td>
                                    <td>
                                        <?php
                                        $appStatusColors = [
                                            'new' => 'primary',
                                            'screening' => 'info',
                                            'shortlisted' => 'success',
                                            'interviewed' => 'warning',
                                            'offered' => 'success',
                                            'hired' => 'success',
                                            'rejected' => 'danger',
                                            'withdrawn' => 'secondary'
                                        ];
                                        $appColor = $appStatusColors[$app['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge badge-<?php echo $appColor; ?>">
                                            <?php echo ucfirst($app['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($app['assigned_recruiter_name']): ?>
                                            <?php echo htmlspecialchars($app['assigned_recruiter_name']); ?>
                                        <?php else: ?>
                                            <span class="text-tertiary">Unassigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?php echo timeAgo($app['applied_at']); ?></small>
                                    </td>
                                    <td>
                                        <!-- ✅ UPDATED ACTIONS COLUMN -->
                                        <div class="btn-group btn-group-sm" role="group">
                                            <?php if ($app['status'] === 'new' || $app['status'] === 'screening'): ?>
                                                <!-- Accept Button (opens in new tab) -->
                                                <a href="../applications/accept.php?id=<?php echo $app['application_id']; ?>" 
                                                   target="_blank"
                                                   class="btn btn-sm btn-success"
                                                   title="Accept & Enhance Profile">
                                                    <i class="fas fa-check"></i> Accept
                                                </a>
                                                
                                                <!-- Reject Button -->
                                                <a href="../applications/reject.php?id=<?php echo $app['application_id']; ?>" 
                                                   class="btn btn-sm btn-danger"
                                                   title="Reject Application">
                                                    <i class="fas fa-times"></i> Reject
                                                </a>
                                            <?php endif; ?>
                                            
                                            <!-- Status Dropdown -->
                                            <select class="form-select form-select-sm" 
                                                    style="width: auto; min-width: 120px;"
                                                    onchange="if(confirm('Change status to ' + this.options[this.selectedIndex].text + '?')) { window.location.href='../applications/status.php?id=<?php echo $app['application_id']; ?>&status=' + this.value; } else { this.value='<?php echo $app['status']; ?>'; }">
                                                <option value="new" <?php echo $app['status'] === 'new' ? 'selected' : ''; ?>>New</option>
                                                <option value="screening" <?php echo $app['status'] === 'screening' ? 'selected' : ''; ?>>Screening</option>
                                                <option value="shortlisted" <?php echo $app['status'] === 'shortlisted' ? 'selected' : ''; ?>>Shortlisted</option>
                                                <option value="interviewed" <?php echo $app['status'] === 'interviewed' ? 'selected' : ''; ?>>Interviewed</option>
                                                <option value="offered" <?php echo $app['status'] === 'offered' ? 'selected' : ''; ?>>Offered</option>
                                                <option value="hired" <?php echo $app['status'] === 'hired' ? 'selected' : ''; ?>>Hired</option>
                                                <option value="rejected" <?php echo $app['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                                <option value="withdrawn" <?php echo $app['status'] === 'withdrawn' ? 'selected' : ''; ?>>Withdrawn</option>
                                            </select>
                                        </div>
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
    
    <!-- Job Info Sidebar -->
    <div class="col-lg-4">
        
        <!-- Status Card -->
        <div class="card mb-3">
            <div class="card-body">
                <div class="mb-3">
                    <strong>Status</strong><br>
                    <?php
                    $statusColors = [
                        'draft' => 'secondary',
                        'active' => 'success',
                        'on-hold' => 'warning',
                        'closed' => 'danger',
                        'filled' => 'info'
                    ];
                    $color = $statusColors[$job['status']] ?? 'secondary';
                    ?>
                    <span class="badge badge-<?php echo $color; ?>">
                        <?php echo ucwords(str_replace('-', ' ', $job['status'])); ?>
                    </span>
                </div>
                
                <div class="mb-3">
                    <strong>Priority</strong><br>
                    <?php
                    $priorityColors = [
                        'low' => 'secondary',
                        'medium' => 'primary',
                        'high' => 'warning',
                        'urgent' => 'danger'
                    ];
                    $pColor = $priorityColors[$job['priority']] ?? 'secondary';
                    ?>
                    <span class="badge badge-<?php echo $pColor; ?>">
                        <?php echo ucfirst($job['priority']); ?>
                    </span>
                </div>
                
                <?php if ($job['deadline']): ?>
                <div class="mb-0">
                    <strong>Application Deadline</strong><br>
                    <?php echo formatDate($job['deadline']); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Job Details Card -->
        <div class="card mb-3">
            <div class="card-header">
                <h5>Job Details</h5>
            </div>
            <div class="card-body">
                
                <!-- Location -->
                <div class="mb-3">
                    <strong>Location</strong><br>
                    <?php 
                    $locations = $job['location'] ? explode(',', $job['location']) : [];
                    foreach ($locations as $loc):
                        $loc = trim($loc);
                        $badgeClass = (strtolower($loc) === 'remote' || strtolower($loc) === 'hybrid') ? 'badge-primary' : 'badge-secondary';
                    ?>
                        <span class="badge <?php echo $badgeClass; ?> me-1">
                            <?php echo htmlspecialchars($loc); ?>
                        </span>
                    <?php endforeach; ?>
                </div>
                
                <!-- Employment Type -->
                <div class="mb-3">
                    <strong>Employment Type</strong><br>
                    <?php echo ucwords(str_replace('-', ' ', $job['employment_type'])); ?>
                </div>
                
                <!-- Experience -->
                <?php if ($job['experience_min'] > 0 || $job['experience_max'] > 0): ?>
                <div class="mb-3">
                    <strong>Experience Required</strong><br>
                    <?php 
                    if ($job['experience_max'] > 0) {
                        echo $job['experience_min'] . ' - ' . $job['experience_max'] . ' years';
                    } else {
                        echo $job['experience_min'] . '+ years';
                    }
                    ?>
                </div>
                <?php endif; ?>
                
                <!-- Salary -->
                <?php if ($job['salary_min'] > 0 || $job['salary_max'] > 0): ?>
                <div class="mb-3">
                    <strong>Salary Range</strong><br>
                    <?php 
                    if ($job['salary_max'] > 0) {
                        echo formatCurrency($job['salary_min']) . ' - ' . formatCurrency($job['salary_max']);
                    } else {
                        echo formatCurrency($job['salary_min']) . '+';
                    }
                    ?>
                    <br><small class="text-tertiary">per annum</small>
                </div>
                <?php endif; ?>
                
                <!-- Skills -->
                <?php if ($job['skills_required']): ?>
                <div class="mb-3">
                    <strong>Skills Required</strong><br>
                    <?php echo htmlspecialchars($job['skills_required']); ?>
                </div>
                <?php endif; ?>
                
                <!-- Education -->
                <?php if ($job['education_required']): ?>
                <div class="mb-3">
                    <strong>Education</strong><br>
                    <?php echo htmlspecialchars($job['education_required']); ?>
                </div>
                <?php endif; ?>
                
                <!-- Positions -->
                <div class="mb-0">
                    <strong>Positions Available</strong><br>
                    <?php echo $job['positions_available']; ?>
                </div>
            </div>
        </div>
        
        <!-- Assigned Recruiters -->
        <?php if (!empty($recruiterNames)): ?>
        <div class="card mb-3">
            <div class="card-header">
                <h5>Assigned Team</h5>
            </div>
            <div class="card-body">
                <?php foreach ($recruiterNames as $recId => $recName): ?>
                    <span class="badge badge-primary me-1 mb-1">
                        <?php echo htmlspecialchars($recName); ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Created Info -->
        <div class="card">
            <div class="card-body">
                <p class="small text-secondary mb-2">
                    <strong>Posted:</strong><br>
                    <?php echo formatDateTime($job['created_at']); ?>
                </p>
                <?php if ($job['created_by_name']): ?>
                <p class="small text-secondary mb-0">
                    <strong>Posted By:</strong><br>
                    <?php echo htmlspecialchars($job['created_by_name']); ?>
                </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function copyApplyLink() {
    const input = document.getElementById('applyUrlInput');
    input.select();
    document.execCommand('copy');
    
    // Show feedback
    const btn = event.target.closest('button');
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
    btn.classList.add('btn-success');
    btn.classList.remove('btn-outline');
    
    setTimeout(() => {
        btn.innerHTML = originalHTML;
        btn.classList.remove('btn-success');
        btn.classList.add('btn-outline');
    }, 2000);
}
</script>

<?php require_once '../includes/footer.php'; ?>
