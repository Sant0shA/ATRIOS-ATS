<?php
ob_start();
/* ============================================================
   FILE: jobs/index.php
   PURPOSE: List all jobs with search and filtering
   ACCESS: All users (filtered by assignment/client ownership)
   
   SECTIONS:
   - [AUTH-CHECK] Verify user access
   - [SEARCH-FILTER] Handle search and filter parameters
   - [DATABASE-QUERY] Fetch jobs based on user role
   - [HTML-OUTPUT] Display job table with actions
   
   LAST MODIFIED: 2026-02-20
   ============================================================ */

$pageTitle = 'Jobs Management';
require_once '../includes/header.php';

/* ============================================================
   [SEARCH-FILTER] - Get Search and Filter Parameters
   ============================================================ */
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$clientFilter = $_GET['client_id'] ?? '';
$locationFilter = $_GET['location'] ?? '';

/* ============================================================
   [DATABASE-QUERY] - Build and Execute Query Based on Role
   ============================================================ */
try {
    $query = "SELECT j.*, 
              c.company_name,
              u.full_name as created_by_name,
              (SELECT COUNT(*) FROM applications WHERE job_id = j.job_id) as application_count
              FROM jobs j
              LEFT JOIN clients c ON j.client_id = c.client_id
              LEFT JOIN users u ON j.created_by = u.user_id
              WHERE 1=1";
    $params = [];
    
    // Apply role-based filtering
    if (hasRole('recruiter')) {
        // Recruiters see: jobs assigned to them OR jobs for clients they own
        $query .= " AND (j.assigned_to LIKE ? OR c.assigned_to = ?)";
        $params[] = '%"' . $_SESSION['user_id'] . '"%'; // JSON search for team assignment
        $params[] = $_SESSION['user_id']; // Client ownership
    }
    // Admin and Manager see all jobs
    
    // Apply search filter
    if ($search) {
        $query .= " AND (j.job_title LIKE ? OR c.company_name LIKE ? OR j.location LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    // Apply status filter
    if ($statusFilter) {
        $query .= " AND j.status = ?";
        $params[] = $statusFilter;
    }
    
    // Apply client filter
    if ($clientFilter) {
        $query .= " AND j.client_id = ?";
        $params[] = $clientFilter;
    }
    
    // Apply location filter
    if ($locationFilter) {
        $query .= " AND j.location LIKE ?";
        $params[] = "%$locationFilter%";
    }
    
    $query .= " ORDER BY j.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $jobs = $stmt->fetchAll();
    
    // Get clients for filter dropdown
    $clientsStmt = $db->query("SELECT client_id, company_name FROM clients WHERE status = 'active' ORDER BY company_name");
    $clients = $clientsStmt->fetchAll();
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Error loading jobs: ' . $e->getMessage());
    $jobs = [];
    $clients = [];
}
?>

<!-- ============================================================
     [HTML-OUTPUT] - Jobs List Interface
     ============================================================ -->

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Jobs Management</h2>
    <?php if (hasRole(['admin', 'manager'])): ?>
        <a href="add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Post New Job
        </a>
    <?php endif; ?>
</div>

<!-- Search and Filter Form -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <input type="text" 
                       name="search" 
                       class="form-control" 
                       placeholder="Job title or company"
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="">All Status</option>
                    <option value="draft" <?php echo $statusFilter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                    <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="on-hold" <?php echo $statusFilter === 'on-hold' ? 'selected' : ''; ?>>On Hold</option>
                    <option value="closed" <?php echo $statusFilter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                    <option value="filled" <?php echo $statusFilter === 'filled' ? 'selected' : ''; ?>>Filled</option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Client</label>
                <select name="client_id" class="form-control">
                    <option value="">All Clients</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?php echo $client['client_id']; ?>" 
                                <?php echo $clientFilter == $client['client_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($client['company_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">Location</label>
                <input type="text" 
                       name="location" 
                       class="form-control" 
                       placeholder="City or Remote"
                       value="<?php echo htmlspecialchars($locationFilter); ?>">
            </div>
            
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search"></i> Search
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Jobs Table -->
<div class="card">
    <div class="card-body p-0">
        <?php if (empty($jobs)): ?>
            <div class="p-4 text-center text-secondary">
                <i class="fas fa-briefcase fa-3x mb-3"></i>
                <p>No jobs found</p>
                <?php if ($search || $statusFilter || $clientFilter || $locationFilter): ?>
                    <a href="index.php" class="btn btn-sm btn-secondary">Clear Filters</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Job Title</th>
                            <th>Client</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Priority</th>
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
                            <td>
                                <a href="../clients/view.php?id=<?php echo $job['client_id']; ?>">
                                    <?php echo htmlspecialchars($job['company_name']); ?>
                                </a>
                            </td>
                            <td>
                                <?php 
                                $locations = $job['location'] ? explode(',', $job['location']) : [];
                                if (!empty($locations)):
                                    foreach (array_slice($locations, 0, 2) as $loc):
                                        $loc = trim($loc);
                                        $badgeClass = (strtolower($loc) === 'remote' || strtolower($loc) === 'hybrid') ? 'badge-primary' : 'badge-secondary';
                                ?>
                                    <span class="badge <?php echo $badgeClass; ?> me-1">
                                        <?php echo htmlspecialchars($loc); ?>
                                    </span>
                                <?php 
                                    endforeach;
                                    if (count($locations) > 2):
                                ?>
                                    <span class="badge badge-secondary">+<?php echo count($locations) - 2; ?></span>
                                <?php 
                                    endif;
                                else:
                                    echo '<span class="text-tertiary">—</span>';
                                endif;
                                ?>
                            </td>
                            <td>
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
                            </td>
                            <td>
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
                                <div class="btn-group btn-group-sm">
                                    <a href="view.php?id=<?php echo $job['job_id']; ?>" 
                                       class="btn btn-outline btn-sm"
                                       title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    <?php if (hasRole(['admin', 'manager'])): ?>
                                        <a href="edit.php?id=<?php echo $job['job_id']; ?>" 
                                           class="btn btn-outline btn-sm"
                                           title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    <?php endif; ?>
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

<div class="mt-3 text-secondary">
    <small>
        <i class="fas fa-info-circle"></i>
        Showing <?php echo count($jobs); ?> job(s)
    </small>
</div>

<?php require_once '../includes/footer.php'; ?>
