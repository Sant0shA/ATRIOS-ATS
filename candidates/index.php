<?php
ob_start();
/* ============================================================
   FILE: candidates/index.php
   PURPOSE: List all candidates with advanced ATS filtering
   ACCESS: All users (filtered by assignment)
   
   SECTIONS:
   - [AUTH-CHECK] Verify user access
   - [SEARCH-FILTER] Handle search and advanced filters
   - [DATABASE-QUERY] Fetch candidates based on role
   - [HTML-OUTPUT] Display candidates with intuitive ATS UI
   
   LAST MODIFIED: 2026-02-20
   ============================================================ */

$pageTitle = 'Candidates';
require_once '../includes/header.php';

/* ============================================================
   [SEARCH-FILTER] - Get Search and Filter Parameters
   ============================================================ */
$search = $_GET['search'] ?? '';
$skillFilter = $_GET['skill'] ?? '';
$locationFilter = $_GET['location'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$assignedFilter = $_GET['assigned_to'] ?? '';
$experienceFilter = $_GET['experience'] ?? '';

/* ============================================================
   [DATABASE-QUERY] - Build and Execute Query Based on Role
   ============================================================ */
try {
    $query = "SELECT c.*, 
              u1.full_name as added_by_name,
              u2.full_name as assigned_to_name,
              (SELECT COUNT(*) FROM applications WHERE candidate_id = c.candidate_id) as application_count
              FROM candidates c
              LEFT JOIN users u1 ON c.added_by = u1.user_id
              LEFT JOIN users u2 ON c.assigned_to = u2.user_id
              WHERE 1=1";
    $params = [];
    
    // Apply role-based filtering
    if (hasRole('recruiter')) {
        // Recruiters see: their own candidates OR shared candidates
        $query .= " AND (c.assigned_to = ? OR c.added_by = ?)";
        $params[] = $_SESSION['user_id'];
        $params[] = $_SESSION['user_id'];
    }
    // Admin and Manager see all candidates
    
    // Apply search filter
    if ($search) {
        $query .= " AND (c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    // Apply skill filter
    if ($skillFilter) {
        $query .= " AND c.skills LIKE ?";
        $params[] = "%$skillFilter%";
    }
    
    // Apply location filter
    if ($locationFilter) {
        $query .= " AND c.current_location = ?";
        $params[] = $locationFilter;
    }
    
    // Apply status filter
    if ($statusFilter === 'blacklisted') {
        $query .= " AND c.blacklisted = 1";
    } elseif ($statusFilter) {
        $query .= " AND c.status = ? AND c.blacklisted = 0";
        $params[] = $statusFilter;
    } else {
        // By default, don't show blacklisted
        $query .= " AND c.blacklisted = 0";
    }
    
    // Apply assigned filter
    if ($assignedFilter) {
        $query .= " AND c.assigned_to = ?";
        $params[] = $assignedFilter;
    }
    
    // Apply experience filter
    if ($experienceFilter) {
        if ($experienceFilter === '0-2') {
            $query .= " AND c.experience_years >= 0 AND c.experience_years <= 2";
        } elseif ($experienceFilter === '3-5') {
            $query .= " AND c.experience_years >= 3 AND c.experience_years <= 5";
        } elseif ($experienceFilter === '6-10') {
            $query .= " AND c.experience_years >= 6 AND c.experience_years <= 10";
        } elseif ($experienceFilter === '10+') {
            $query .= " AND c.experience_years >= 10";
        }
    }
    
    $query .= " ORDER BY c.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $candidates = $stmt->fetchAll();
    
    // Get users for assignment filter
    $usersStmt = $db->query("SELECT user_id, full_name FROM users WHERE status = 'active' ORDER BY full_name");
    $users = $usersStmt->fetchAll();
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Error loading candidates: ' . $e->getMessage());
    $candidates = [];
    $users = [];
}
?>

<!-- ============================================================
     [HTML-OUTPUT] - Candidates List Interface
     ============================================================ -->

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Candidates</h2>
    <a href="add.php" class="btn btn-primary">
        <i class="fas fa-plus"></i> Add Candidate
    </a>
</div>

<!-- Advanced Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <input type="text" 
                       name="search" 
                       class="form-control" 
                       placeholder="Name, email, or phone"
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="col-md-2">
                <label class="form-label">Location</label>
                <input type="text" 
                       name="location" 
                       class="form-control" 
                       placeholder="City"
                       value="<?php echo htmlspecialchars($locationFilter); ?>">
            </div>
            
            <div class="col-md-2">
                <label class="form-label">Experience</label>
                <select name="experience" class="form-control">
                    <option value="">All</option>
                    <option value="0-2" <?php echo $experienceFilter === '0-2' ? 'selected' : ''; ?>>0-2 years</option>
                    <option value="3-5" <?php echo $experienceFilter === '3-5' ? 'selected' : ''; ?>>3-5 years</option>
                    <option value="6-10" <?php echo $experienceFilter === '6-10' ? 'selected' : ''; ?>>6-10 years</option>
                    <option value="10+" <?php echo $experienceFilter === '10+' ? 'selected' : ''; ?>>10+ years</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="">Active</option>
                    <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="placed" <?php echo $statusFilter === 'placed' ? 'selected' : ''; ?>>Placed</option>
                    <option value="blacklisted" <?php echo $statusFilter === 'blacklisted' ? 'selected' : ''; ?>>Blacklisted</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">Assigned To</label>
                <select name="assigned_to" class="form-control">
                    <option value="">All</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['user_id']; ?>" 
                                <?php echo $assignedFilter == $user['user_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-1">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Candidates Grid/Table -->
<div class="card">
    <div class="card-body p-0">
        <?php if (empty($candidates)): ?>
            <div class="p-4 text-center text-secondary">
                <i class="fas fa-user-tie fa-3x mb-3"></i>
                <p>No candidates found</p>
                <?php if ($search || $skillFilter || $locationFilter || $statusFilter || $assignedFilter): ?>
                    <a href="index.php" class="btn btn-sm btn-secondary">Clear Filters</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Candidate</th>
                            <th>Location</th>
                            <th>Experience</th>
                            <th>Current Role</th>
                            <th>Skills</th>
                            <th>Assigned To</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($candidates as $candidate): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']); ?></strong>
                                <br><small class="text-secondary"><?php echo htmlspecialchars($candidate['email']); ?></small>
                                <?php if ($candidate['phone']): ?>
                                    <br><small class="text-secondary"><?php echo htmlspecialchars($candidate['phone']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($candidate['current_location'] ?? '—'); ?></td>
                            <td>
                                <?php if ($candidate['experience_years']): ?>
                                    <?php echo $candidate['experience_years']; ?> years
                                <?php else: ?>
                                    <span class="text-tertiary">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($candidate['current_designation']): ?>
                                    <?php echo htmlspecialchars($candidate['current_designation']); ?>
                                    <?php if ($candidate['current_company']): ?>
                                        <br><small class="text-secondary">@ <?php echo htmlspecialchars($candidate['current_company']); ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-tertiary">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                if ($candidate['skills']):
                                    $skills = explode(',', $candidate['skills']);
                                    $displaySkills = array_slice($skills, 0, 2);
                                    foreach ($displaySkills as $skill):
                                        $skill = trim($skill);
                                ?>
                                    <span class="badge badge-secondary me-1"><?php echo htmlspecialchars($skill); ?></span>
                                <?php 
                                    endforeach;
                                    if (count($skills) > 2):
                                ?>
                                    <span class="badge badge-secondary">+<?php echo count($skills) - 2; ?></span>
                                <?php 
                                    endif;
                                else:
                                    echo '<span class="text-tertiary">—</span>';
                                endif;
                                ?>
                            </td>
                            <td>
                                <?php if ($candidate['assigned_to_name']): ?>
                                    <span class="badge badge-primary">
                                        <?php echo htmlspecialchars($candidate['assigned_to_name']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-tertiary">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($candidate['blacklisted']): ?>
                                    <span class="badge badge-danger">Blacklisted</span>
                                <?php else: ?>
                                    <span class="badge badge-success">
                                        <?php echo ucfirst($candidate['status']); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="view.php?id=<?php echo $candidate['candidate_id']; ?>" 
                                       class="btn btn-outline btn-sm"
                                       title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    <?php if (hasRole(['admin', 'manager']) || $candidate['added_by'] == $_SESSION['user_id']): ?>
                                        <a href="edit.php?id=<?php echo $candidate['candidate_id']; ?>" 
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
        Showing <?php echo count($candidates); ?> candidate(s)
        <?php if ($statusFilter !== 'blacklisted'): ?>
            <span class="text-tertiary">(excluding blacklisted)</span>
        <?php endif; ?>
    </small>
</div>

<?php require_once '../includes/footer.php'; ?>
