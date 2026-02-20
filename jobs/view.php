<?php
ob_start();
/* ============================================================
   FILE: jobs/view.php - REDESIGNED APPLICANT REVIEW UI
   PURPOSE: Card-based applications with inline screening answers
   
   NEW FEATURES:
   - Card layout with screening Q&A visible
   - Location prominently displayed
   - Pagination (10/25/50 per page)
   - Modal for reject (no page navigation)
   - Full profile modal view
   
   LAST MODIFIED: 2026-02-20
   ============================================================ */

$pageTitle = 'View Job';
require_once '../includes/header.php';

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
    $assignedRecruiters = json_decode($job['assigned_to'], true) ?? [];
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Database error');
    header('Location: index.php');
    exit();
}

/* ============================================================
   [PAGINATION] - Handle Pagination
   ============================================================ */
$perPage = isset($_GET['per_page']) ? intval($_GET['per_page']) : 10;
$perPage = in_array($perPage, [10, 25, 50]) ? $perPage : 10;
$currentPage = isset($_GET['page']) ? intval($_GET['page']) : 1;
$currentPage = max(1, $currentPage);
$offset = ($currentPage - 1) * $perPage;

/* ============================================================
   [FETCH-APPLICATIONS] - Get Applications with Pagination
   ============================================================ */
try {
    // Get total count
    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM applications WHERE job_id = ?");
    $countStmt->execute([$jobId]);
    $totalApplications = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalApplications / $perPage);
    
    // Get paginated applications
    $appsStmt = $db->prepare("
        SELECT a.*, 
               c.first_name, c.last_name, c.email, c.phone, c.current_location, c.cv_path, c.linkedin_url,
               u.full_name as assigned_recruiter_name
        FROM applications a
        LEFT JOIN candidates c ON a.candidate_id = c.candidate_id
        LEFT JOIN users u ON a.assigned_recruiter = u.user_id
        WHERE a.job_id = ?
        ORDER BY a.applied_at DESC
        LIMIT ? OFFSET ?
    ");
    $appsStmt->execute([$jobId, $perPage, $offset]);
    $applications = $appsStmt->fetchAll();
} catch (PDOException $e) {
    $applications = [];
    $totalApplications = 0;
    $totalPages = 0;
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
    } catch (PDOException $e) {}
}

$applyUrl = SITE_URL . '/apply.php?token=' . $job['apply_link_token'];
?>

<style>
.application-card {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 16px;
    background: white;
    transition: box-shadow 0.2s;
}
.application-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}
.candidate-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 16px;
    padding-bottom: 16px;
    border-bottom: 1px solid #f3f4f6;
}
.candidate-info h5 {
    margin: 0 0 4px 0;
    font-size: 18px;
    color: #1a1a1a;
}
.candidate-meta {
    color: #6b7280;
    font-size: 14px;
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
}
.screening-section {
    background: #f9fafb;
    padding: 16px;
    border-radius: 6px;
    margin-bottom: 16px;
}
.screening-question {
    margin-bottom: 12px;
}
.screening-question:last-child {
    margin-bottom: 0;
}
.screening-question strong {
    display: block;
    color: #374151;
    margin-bottom: 4px;
    font-size: 14px;
}
.screening-answer {
    color: #1f2937;
    font-size: 14px;
    line-height: 1.6;
    padding-left: 12px;
    border-left: 3px solid #F16136;
}
.action-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.pagination-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 24px;
    padding: 16px 0;
    border-top: 1px solid #e5e7eb;
}
.pagination-info {
    color: #6b7280;
    font-size: 14px;
}
.pagination-buttons {
    display: flex;
    gap: 4px;
}
.pagination-buttons .btn {
    min-width: 40px;
}
</style>

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
    <!-- Main Content -->
    <div class="col-lg-8">
        
        <!-- Public Apply Link Card -->
        <div class="card mb-4 border-primary">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-link"></i> Public Apply Link</h5>
            </div>
            <div class="card-body">
                <p class="mb-2">Share this link with candidates to apply:</p>
                <div class="input-group">
                    <input type="text" class="form-control" value="<?php echo $applyUrl; ?>" id="applyUrlInput" readonly>
                    <button class="btn btn-outline" onclick="copyApplyLink()" type="button">
                        <i class="fas fa-copy"></i> Copy
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Applications Section -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-file-alt"></i> Applications (<?php echo $totalApplications; ?>)
                </h5>
                <?php if ($totalApplications > 0): ?>
                <div>
                    <label class="small me-2">Per page:</label>
                    <select class="form-select form-select-sm d-inline-block" style="width: auto;" 
                            onchange="window.location.href='?id=<?php echo $jobId; ?>&per_page='+this.value">
                        <option value="10" <?php echo $perPage == 10 ? 'selected' : ''; ?>>10</option>
                        <option value="25" <?php echo $perPage == 25 ? 'selected' : ''; ?>>25</option>
                        <option value="50" <?php echo $perPage == 50 ? 'selected' : ''; ?>>50</option>
                    </select>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-body">
                
                <?php if (empty($applications)): ?>
                    <div class="text-center py-5 text-secondary">
                        <i class="fas fa-inbox fa-3x mb-3"></i>
                        <p>No applications yet</p>
                    </div>
                <?php else: ?>
                    
                    <!-- Application Cards -->
                    <?php foreach ($applications as $app): 
                        $statusColors = [
                            'new' => 'primary',
                            'screening' => 'info',
                            'shortlisted' => 'success',
                            'interviewed' => 'warning',
                            'offered' => 'success',
                            'hired' => 'success',
                            'rejected' => 'danger',
                            'withdrawn' => 'secondary'
                        ];
                        $statusColor = $statusColors[$app['status']] ?? 'secondary';
                    ?>
                    
                    <div class="application-card">
                        <!-- Candidate Header -->
                        <div class="candidate-header">
                            <div class="candidate-info">
                                <h5><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></h5>
                                <div class="candidate-meta">
                                    <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($app['email']); ?></span>
                                    <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($app['phone']); ?></span>
                                    <span><i class="fas fa-map-marker-alt"></i> <strong><?php echo htmlspecialchars($app['current_location']); ?></strong></span>
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="badge badge-<?php echo $statusColor; ?> mb-2">
                                    <?php echo ucfirst($app['status']); ?>
                                </span>
                                <div class="small text-secondary">
                                    <?php echo timeAgo($app['applied_at']); ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Screening Answers -->
                        <div class="screening-section">
                            <div class="screening-question">
                                <strong>Q1: <?php echo htmlspecialchars($job['screening_question_1']); ?></strong>
                                <div class="screening-answer">
                                    <?php echo htmlspecialchars($app['screening_answer_1'] ?: 'No answer provided'); ?>
                                </div>
                            </div>
                            <div class="screening-question">
                                <strong>Q2: <?php echo htmlspecialchars($job['screening_question_2']); ?></strong>
                                <div class="screening-answer">
                                    <?php echo htmlspecialchars($app['screening_answer_2'] ?: 'No answer provided'); ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="action-buttons">
                            <button class="btn btn-outline btn-sm" onclick="viewFullProfile(<?php echo $app['application_id']; ?>)">
                                <i class="fas fa-eye"></i> View Full Profile
                            </button>
                            
                            <?php if ($app['status'] === 'new' || $app['status'] === 'screening'): ?>
                                <a href="../applications/accept.php?id=<?php echo $app['application_id']; ?>" 
                                   target="_blank"
                                   class="btn btn-success btn-sm">
                                    <i class="fas fa-check"></i> Accept
                                </a>
                                
                                <button class="btn btn-danger btn-sm" 
                                        onclick="openRejectModal(<?php echo $app['application_id']; ?>, '<?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name'], ENT_QUOTES); ?>')">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            <?php else: ?>
                                <select class="form-select form-select-sm" style="width: auto; min-width: 120px;"
                                        onchange="changeStatus(<?php echo $app['application_id']; ?>, this.value, '<?php echo $app['status']; ?>')">
                                    <option value="new" <?php echo $app['status'] === 'new' ? 'selected' : ''; ?>>New</option>
                                    <option value="screening" <?php echo $app['status'] === 'screening' ? 'selected' : ''; ?>>Screening</option>
                                    <option value="shortlisted" <?php echo $app['status'] === 'shortlisted' ? 'selected' : ''; ?>>Shortlisted</option>
                                    <option value="interviewed" <?php echo $app['status'] === 'interviewed' ? 'selected' : ''; ?>>Interviewed</option>
                                    <option value="offered" <?php echo $app['status'] === 'offered' ? 'selected' : ''; ?>>Offered</option>
                                    <option value="hired" <?php echo $app['status'] === 'hired' ? 'selected' : ''; ?>>Hired</option>
                                    <option value="rejected" <?php echo $app['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                    <option value="withdrawn" <?php echo $app['status'] === 'withdrawn' ? 'selected' : ''; ?>>Withdrawn</option>
                                </select>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Hidden data for modal -->
                        <div id="fullProfile<?php echo $app['application_id']; ?>" style="display:none;">
                            <?php echo json_encode([
                                'name' => $app['first_name'] . ' ' . $app['last_name'],
                                'email' => $app['email'],
                                'phone' => $app['phone'],
                                'location' => $app['current_location'],
                                'cv_path' => $app['cv_path'],
                                'linkedin_url' => $app['linkedin_url'],
                                'screening_answer_1' => $app['screening_answer_1'],
                                'screening_answer_2' => $app['screening_answer_2'],
                                'cover_note' => $app['cover_note'] ?? '',
                                'applied_at' => $app['applied_at']
                            ]); ?>
                        </div>
                    </div>
                    
                    <?php endforeach; ?>
                    
                    <!-- Pagination Controls -->
                    <?php if ($totalPages > 1): ?>
                    <div class="pagination-controls">
                        <div class="pagination-info">
                            Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $perPage, $totalApplications); ?> of <?php echo $totalApplications; ?>
                        </div>
                        <div class="pagination-buttons">
                            <?php if ($currentPage > 1): ?>
                                <a href="?id=<?php echo $jobId; ?>&page=<?php echo $currentPage - 1; ?>&per_page=<?php echo $perPage; ?>" 
                                   class="btn btn-sm btn-outline">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            <?php endif; ?>
                            
                            <?php
                            $startPage = max(1, $currentPage - 2);
                            $endPage = min($totalPages, $currentPage + 2);
                            
                            for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                                <a href="?id=<?php echo $jobId; ?>&page=<?php echo $i; ?>&per_page=<?php echo $perPage; ?>" 
                                   class="btn btn-sm <?php echo $i == $currentPage ? 'btn-primary' : 'btn-outline'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($currentPage < $totalPages): ?>
                                <a href="?id=<?php echo $jobId; ?>&page=<?php echo $currentPage + 1; ?>&per_page=<?php echo $perPage; ?>" 
                                   class="btn btn-sm btn-outline">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Sidebar (Job Details) - Keep existing sidebar code -->
    <div class="col-lg-4">
        <!-- [Previous sidebar code remains the same] -->
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
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-times-circle"></i> Reject Application
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to reject this application? This action will mark the application as rejected.</p>
                <p class="mb-3"><strong id="rejectCandidateName"></strong></p>
                
                <div class="mb-3">
                    <label class="form-label">Rejection Reason (Optional)</label>
                    <textarea id="rejectReason" class="form-control" rows="3" 
                              placeholder="Provide a reason for rejection (optional but recommended)"></textarea>
                    <small class="text-tertiary">This will be logged for reference</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-arrow-left"></i> Cancel
                </button>
                <button type="button" class="btn btn-danger" onclick="confirmReject()">
                    <i class="fas fa-times"></i> Confirm Rejection
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Full Profile Modal -->
<div class="modal fade" id="profileModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user"></i> Candidate Profile
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="profileModalBody">
                <!-- Content loaded dynamically -->
            </div>
        </div>
    </div>
</div>

<script>
let currentRejectId = null;

function copyApplyLink() {
    const input = document.getElementById('applyUrlInput');
    input.select();
    document.execCommand('copy');
    
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

function openRejectModal(appId, candidateName) {
    currentRejectId = appId;
    document.getElementById('rejectCandidateName').textContent = candidateName;
    document.getElementById('rejectReason').value = '';
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

function confirmReject() {
    const reason = document.getElementById('rejectReason').value;
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '../applications/reject.php?id=' + currentRejectId;
    
    const reasonInput = document.createElement('input');
    reasonInput.type = 'hidden';
    reasonInput.name = 'reason';
    reasonInput.value = reason;
    form.appendChild(reasonInput);
    
    document.body.appendChild(form);
    form.submit();
}

function changeStatus(appId, newStatus, currentStatus) {
    if (newStatus === currentStatus) return;
    
    if (confirm('Change status to ' + newStatus + '?')) {
        window.location.href = '../applications/status.php?id=' + appId + '&status=' + newStatus;
    } else {
        event.target.value = currentStatus;
    }
}

function viewFullProfile(appId) {
    const data = JSON.parse(document.getElementById('fullProfile' + appId).textContent);
    
    const html = `
        <div class="row">
            <div class="col-md-6">
                <p><strong>Name:</strong><br>${data.name}</p>
                <p><strong>Email:</strong><br>${data.email}</p>
                <p><strong>Phone:</strong><br>${data.phone}</p>
                <p><strong>Location:</strong><br>${data.location}</p>
            </div>
            <div class="col-md-6">
                ${data.cv_path ? `<p><strong>Resume:</strong><br><a href="${data.cv_path}" target="_blank" class="btn btn-sm btn-outline"><i class="fas fa-download"></i> Download CV</a></p>` : ''}
                ${data.linkedin_url ? `<p><strong>LinkedIn:</strong><br><a href="${data.linkedin_url}" target="_blank">${data.linkedin_url}</a></p>` : ''}
                <p><strong>Applied:</strong><br>${data.applied_at}</p>
            </div>
        </div>
        <hr>
        <h6>Screening Answers:</h6>
        <div class="mb-3">
            <strong>Q1:</strong>
            <p class="ms-3">${data.screening_answer_1 || 'No answer provided'}</p>
        </div>
        <div class="mb-3">
            <strong>Q2:</strong>
            <p class="ms-3">${data.screening_answer_2 || 'No answer provided'}</p>
        </div>
        ${data.cover_note ? `<hr><h6>Cover Note:</h6><p>${data.cover_note}</p>` : ''}
    `;
    
    document.getElementById('profileModalBody').innerHTML = html;
    new bootstrap.Modal(document.getElementById('profileModal')).show();
}
</script>

<?php require_once '../includes/footer.php'; ?>
