<?php
/* ============================================================
   FILE: users/index.php
   PURPOSE: List all users with search and filtering
   ACCESS: Admin only
   
   SECTIONS:
   - [AUTH-CHECK] Verify admin access
   - [SEARCH-FILTER] Handle search and filter parameters
   - [DATABASE-QUERY] Fetch users from database
   - [HTML-OUTPUT] Display user table with actions
   
   LAST MODIFIED: 2026-02-20
   ============================================================ */

$pageTitle = 'Users Management';
require_once '../includes/header.php';

/* ============================================================
   [AUTH-CHECK] - Require Admin Role
   ============================================================ */
requireRole('admin');

/* ============================================================
   [SEARCH-FILTER] - Get Search and Filter Parameters
   ============================================================ */
$search = $_GET['search'] ?? '';
$roleFilter = $_GET['role'] ?? '';
$statusFilter = $_GET['status'] ?? '';

/* ============================================================
   [DATABASE-QUERY] - Build and Execute Query
   ============================================================ */
try {
    $query = "SELECT user_id, username, email, full_name, phone, role, status, last_login, created_at 
              FROM users 
              WHERE 1=1";
    $params = [];
    
    // Apply search filter
    if ($search) {
        $query .= " AND (full_name LIKE ? OR email LIKE ? OR username LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    // Apply role filter
    if ($roleFilter) {
        $query .= " AND role = ?";
        $params[] = $roleFilter;
    }
    
    // Apply status filter
    if ($statusFilter) {
        $query .= " AND status = ?";
        $params[] = $statusFilter;
    }
    
    $query .= " ORDER BY created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
} catch (PDOException $e) {
    setFlashMessage('error', 'Error loading users: ' . $e->getMessage());
    $users = [];
}
?>

<!-- ============================================================
     [HTML-OUTPUT] - Users List Interface
     ============================================================ -->

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Users Management</h2>
    <a href="add.php" class="btn btn-primary">
        <i class="fas fa-plus"></i> Add New User
    </a>
</div>

<!-- Search and Filter Form -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" 
                       name="search" 
                       class="form-control" 
                       placeholder="Name, email, or username"
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Role</label>
                <select name="role" class="form-control">
                    <option value="">All Roles</option>
                    <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="manager" <?php echo $roleFilter === 'manager' ? 'selected' : ''; ?>>Manager</option>
                    <option value="recruiter" <?php echo $roleFilter === 'recruiter' ? 'selected' : ''; ?>>Recruiter</option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
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

<!-- Users Table -->
<div class="card">
    <div class="card-body p-0">
        <?php if (empty($users)): ?>
            <div class="p-4 text-center text-secondary">
                <i class="fas fa-users fa-3x mb-3"></i>
                <p>No users found</p>
                <?php if ($search || $roleFilter || $statusFilter): ?>
                    <a href="index.php" class="btn btn-sm btn-secondary">Clear Filters</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                <?php if ($user['phone']): ?>
                                    <br><small class="text-secondary"><?php echo htmlspecialchars($user['phone']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <code><?php echo htmlspecialchars($user['username']); ?></code>
                            </td>
                            <td>
                                <?php
                                $roleColors = [
                                    'admin' => 'danger',
                                    'manager' => 'warning',
                                    'recruiter' => 'primary'
                                ];
                                $color = $roleColors[$user['role']] ?? 'secondary';
                                ?>
                                <span class="badge badge-<?php echo $color; ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($user['status'] === 'active'): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                if ($user['last_login']) {
                                    echo timeAgo($user['last_login']);
                                } else {
                                    echo '<span class="text-tertiary">Never</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="edit.php?id=<?php echo $user['user_id']; ?>" 
                                       class="btn btn-outline btn-sm"
                                       title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    
                                    <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                        <?php if ($user['status'] === 'active'): ?>
                                            <a href="delete.php?id=<?php echo $user['user_id']; ?>&action=deactivate" 
                                               class="btn btn-outline btn-sm"
                                               data-confirm-delete="Are you sure you want to deactivate this user?"
                                               title="Deactivate">
                                                <i class="fas fa-ban"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="delete.php?id=<?php echo $user['user_id']; ?>&action=activate" 
                                               class="btn btn-outline btn-sm"
                                               title="Activate">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <button class="btn btn-outline btn-sm" disabled title="Cannot modify yourself">
                                            <i class="fas fa-lock"></i>
                                        </button>
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
        Showing <?php echo count($users); ?> user(s)
    </small>
</div>

<?php require_once '../includes/footer.php'; ?>
