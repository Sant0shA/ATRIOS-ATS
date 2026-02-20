<?php
ob_start(); // Buffer output to prevent header warnings
/* ============================================================
   FILE: users/edit.php
   PURPOSE: Edit existing user details
   ACCESS: Admin only
   
   SECTIONS:
   - [AUTH-CHECK] Verify admin access
   - [FETCH-USER] Get user data from database
   - [FORM-PROCESSING] Handle form submission
   - [VALIDATION] Validate user input
   - [DATABASE-UPDATE] Update user record
   - [HTML-FORM] Display edit user form
   
   LAST MODIFIED: 2026-02-20
   ============================================================ */

$pageTitle = 'Edit User';
require_once '../includes/header.php';

/* ============================================================
   [AUTH-CHECK] - Require Admin Role
   ============================================================ */
requireRole('admin');

$errors = [];
$user = null;

/* ============================================================
   [FETCH-USER] - Get User ID and Fetch Data
   ============================================================ */
$userId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($userId <= 0) {
    setFlashMessage('error', 'Invalid user ID');
    header('Location: index.php');
    exit();
}

try {
    $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        setFlashMessage('error', 'User not found');
        header('Location: index.php');
        exit();
    }
} catch (PDOException $e) {
    setFlashMessage('error', 'Database error: ' . $e->getMessage());
    header('Location: index.php');
    exit();
}

/* ============================================================
   [FORM-PROCESSING] - Handle Form Submission
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Get form data
    $formData = [
        'email' => trim($_POST['email'] ?? ''),
        'full_name' => trim($_POST['full_name'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'role' => $_POST['role'] ?? 'recruiter',
        'status' => $_POST['status'] ?? 'active',
        'password' => $_POST['password'] ?? '',
        'password_confirm' => $_POST['password_confirm'] ?? ''
    ];
    
    /* ============================================================
       [VALIDATION] - Validate Input
       ============================================================ */
    
    // Email validation
    if (empty($formData['email'])) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    } else {
        // Check email uniqueness (excluding current user)
        $stmt = $db->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $stmt->execute([$formData['email'], $userId]);
        if ($stmt->fetch()) {
            $errors['email'] = 'Email already exists';
        }
    }
    
    // Full name validation
    if (empty($formData['full_name'])) {
        $errors['full_name'] = 'Full name is required';
    } elseif (strlen($formData['full_name']) < 2) {
        $errors['full_name'] = 'Full name must be at least 2 characters';
    }
    
    // Phone validation (optional but validated if provided)
    if (!empty($formData['phone'])) {
        $phone = preg_replace('/[^0-9]/', '', $formData['phone']);
        if (strlen($phone) != 10) {
            $errors['phone'] = 'Phone number must be 10 digits';
        }
    }
    
    // Role validation
    if (!in_array($formData['role'], ['admin', 'manager', 'recruiter'])) {
        $errors['role'] = 'Invalid role selected';
    }
    
    // Prevent user from demoting themselves from admin
    if ($userId == $_SESSION['user_id'] && $formData['role'] !== 'admin') {
        $errors['role'] = 'You cannot change your own role';
    }
    
    // Status validation
    if (!in_array($formData['status'], ['active', 'inactive'])) {
        $errors['status'] = 'Invalid status selected';
    }
    
    // Password validation (only if provided)
    if (!empty($formData['password'])) {
        if (strlen($formData['password']) < 6) {
            $errors['password'] = 'Password must be at least 6 characters';
        }
        
        if ($formData['password'] !== $formData['password_confirm']) {
            $errors['password_confirm'] = 'Passwords do not match';
        }
    }
    
    /* ============================================================
       [DATABASE-UPDATE] - Update User if No Errors
       ============================================================ */
    if (empty($errors)) {
        try {
            // Build update query based on whether password is being changed
            if (!empty($formData['password'])) {
                $stmt = $db->prepare("
                    UPDATE users 
                    SET email = ?, password_hash = ?, full_name = ?, phone = ?, role = ?, status = ?, updated_at = NOW()
                    WHERE user_id = ?
                ");
                
                $passwordHash = password_hash($formData['password'], PASSWORD_DEFAULT);
                
                $stmt->execute([
                    $formData['email'],
                    $passwordHash,
                    $formData['full_name'],
                    $formData['phone'],
                    $formData['role'],
                    $formData['status'],
                    $userId
                ]);
            } else {
                $stmt = $db->prepare("
                    UPDATE users 
                    SET email = ?, full_name = ?, phone = ?, role = ?, status = ?, updated_at = NOW()
                    WHERE user_id = ?
                ");
                
                $stmt->execute([
                    $formData['email'],
                    $formData['full_name'],
                    $formData['phone'],
                    $formData['role'],
                    $formData['status'],
                    $userId
                ]);
            }
            
            // Log activity
            logActivity($db, $_SESSION['user_id'], 'update', 'user', $userId, 
                       "Updated user: {$formData['full_name']} ({$formData['role']})");
            
            setFlashMessage('success', "User '{$formData['full_name']}' updated successfully!");
            header('Location: index.php');
            exit();
            
        } catch (PDOException $e) {
            $errors['general'] = 'Database error: ' . $e->getMessage();
        }
    } else {
        // Update user array with form data for display
        $user = array_merge($user, $formData);
    }
}
?>

<!-- ============================================================
     [HTML-FORM] - Edit User Form
     ============================================================ -->

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5>Edit User: <?php echo htmlspecialchars($user['username']); ?></h5>
            </div>
            <div class="card-body">
                
                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($errors['general']); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    
                    <!-- Username (Read-only) -->
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($user['username']); ?>"
                               disabled>
                        <small class="text-tertiary">Username cannot be changed</small>
                    </div>
                    
                    <!-- Email -->
                    <div class="mb-3">
                        <label class="form-label">Email Address *</label>
                        <input type="email" 
                               name="email" 
                               class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>"
                               value="<?php echo htmlspecialchars($user['email']); ?>"
                               required>
                        <?php if (isset($errors['email'])): ?>
                            <div class="invalid-feedback d-block"><?php echo $errors['email']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Full Name -->
                    <div class="mb-3">
                        <label class="form-label">Full Name *</label>
                        <input type="text" 
                               name="full_name" 
                               class="form-control <?php echo isset($errors['full_name']) ? 'is-invalid' : ''; ?>"
                               value="<?php echo htmlspecialchars($user['full_name']); ?>"
                               required>
                        <?php if (isset($errors['full_name'])): ?>
                            <div class="invalid-feedback d-block"><?php echo $errors['full_name']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Phone -->
                    <div class="mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" 
                               name="phone" 
                               class="form-control <?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>"
                               value="<?php echo htmlspecialchars($user['phone']); ?>"
                               placeholder="10-digit mobile number">
                        <?php if (isset($errors['phone'])): ?>
                            <div class="invalid-feedback d-block"><?php echo $errors['phone']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Role -->
                    <div class="mb-3">
                        <label class="form-label">Role *</label>
                        <select name="role" 
                                class="form-control <?php echo isset($errors['role']) ? 'is-invalid' : ''; ?>"
                                <?php echo ($userId == $_SESSION['user_id']) ? 'disabled' : ''; ?>
                                required>
                            <option value="recruiter" <?php echo $user['role'] === 'recruiter' ? 'selected' : ''; ?>>
                                Recruiter
                            </option>
                            <option value="manager" <?php echo $user['role'] === 'manager' ? 'selected' : ''; ?>>
                                Manager
                            </option>
                            <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>
                                Admin
                            </option>
                        </select>
                        <?php if ($userId == $_SESSION['user_id']): ?>
                            <small class="text-tertiary">You cannot change your own role</small>
                            <input type="hidden" name="role" value="<?php echo $user['role']; ?>">
                        <?php endif; ?>
                        <?php if (isset($errors['role'])): ?>
                            <div class="invalid-feedback d-block"><?php echo $errors['role']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Status -->
                    <div class="mb-3">
                        <label class="form-label">Status *</label>
                        <select name="status" 
                                class="form-control"
                                <?php echo ($userId == $_SESSION['user_id']) ? 'disabled' : ''; ?>
                                required>
                            <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>
                                Active
                            </option>
                            <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>
                                Inactive
                            </option>
                        </select>
                        <?php if ($userId == $_SESSION['user_id']): ?>
                            <small class="text-tertiary">You cannot deactivate yourself</small>
                            <input type="hidden" name="status" value="<?php echo $user['status']; ?>">
                        <?php endif; ?>
                    </div>
                    
                    <hr class="my-4">
                    
                    <h6 class="mb-3">Change Password (Optional)</h6>
                    <p class="text-secondary small">Leave blank to keep current password</p>
                    
                    <!-- New Password -->
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" 
                               name="password" 
                               class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>"
                               placeholder="Leave blank to keep current password">
                        <small class="text-tertiary">Minimum 6 characters</small>
                        <?php if (isset($errors['password'])): ?>
                            <div class="invalid-feedback d-block"><?php echo $errors['password']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Confirm New Password -->
                    <div class="mb-4">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" 
                               name="password_confirm" 
                               id="password_confirm"
                               class="form-control <?php echo isset($errors['password_confirm']) ? 'is-invalid' : ''; ?>"
                               placeholder="Confirm new password">
                        <div id="password-match-indicator" class="mt-2" style="display: none;">
                            <small class="text-success" id="match-success" style="display: none;">
                                <i class="fas fa-check-circle"></i> Passwords match!
                            </small>
                            <small class="text-danger" id="match-error" style="display: none;">
                                <i class="fas fa-times-circle"></i> Passwords do not match
                            </small>
                        </div>
                        <?php if (isset($errors['password_confirm'])): ?>
                            <div class="invalid-feedback d-block"><?php echo $errors['password_confirm']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <script>
                    // Real-time password match validation
                    document.addEventListener('DOMContentLoaded', function() {
                        const password = document.querySelector('input[name="password"]');
                        const confirmPassword = document.getElementById('password_confirm');
                        const indicator = document.getElementById('password-match-indicator');
                        const matchSuccess = document.getElementById('match-success');
                        const matchError = document.getElementById('match-error');
                        
                        function checkPasswordMatch() {
                            const pass1 = password.value;
                            const pass2 = confirmPassword.value;
                            
                            // If both fields are empty, hide indicator
                            if (pass1.length === 0 && pass2.length === 0) {
                                indicator.style.display = 'none';
                                confirmPassword.classList.remove('is-valid', 'is-invalid');
                                return;
                            }
                            
                            // If confirm password is empty but password has value
                            if (pass2.length === 0 && pass1.length > 0) {
                                indicator.style.display = 'none';
                                confirmPassword.classList.remove('is-valid', 'is-invalid');
                                return;
                            }
                            
                            // If confirm password has value, show indicator
                            if (pass2.length > 0) {
                                indicator.style.display = 'block';
                                
                                if (pass1 === pass2 && pass1.length > 0) {
                                    matchSuccess.style.display = 'block';
                                    matchError.style.display = 'none';
                                    confirmPassword.classList.add('is-valid');
                                    confirmPassword.classList.remove('is-invalid');
                                } else {
                                    matchSuccess.style.display = 'none';
                                    matchError.style.display = 'block';
                                    confirmPassword.classList.add('is-invalid');
                                    confirmPassword.classList.remove('is-valid');
                                }
                            }
                        }
                        
                        password.addEventListener('input', checkPasswordMatch);
                        confirmPassword.addEventListener('input', checkPasswordMatch);
                    });
                    </script>
                    
                    <!-- Submit Buttons -->
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update User
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                    
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5>User Information</h5>
            </div>
            <div class="card-body">
                <p class="small text-secondary mb-2">
                    <strong>Created:</strong><br>
                    <?php echo formatDateTime($user['created_at']); ?>
                </p>
                <?php if ($user['last_login']): ?>
                    <p class="small text-secondary mb-2">
                        <strong>Last Login:</strong><br>
                        <?php echo timeAgo($user['last_login']); ?>
                    </p>
                <?php endif; ?>
                <p class="small text-secondary mb-0">
                    <strong>User ID:</strong> #<?php echo $user['user_id']; ?>
                </p>
            </div>
        </div>
        
        <?php if ($userId != $_SESSION['user_id']): ?>
        <div class="card mt-3">
            <div class="card-body text-center">
                <?php if ($user['status'] === 'active'): ?>
                    <a href="delete.php?id=<?php echo $userId; ?>&action=deactivate" 
                       class="btn btn-danger w-100"
                       data-confirm-delete="Are you sure you want to deactivate this user? They will not be able to log in.">
                        <i class="fas fa-ban"></i> Deactivate User
                    </a>
                <?php else: ?>
                    <a href="delete.php?id=<?php echo $userId; ?>&action=activate" 
                       class="btn btn-success w-100">
                        <i class="fas fa-check"></i> Activate User
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
