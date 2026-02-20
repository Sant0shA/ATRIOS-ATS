<?php
ob_start(); // Buffer output to prevent header warnings
/* ============================================================
   FILE: users/add.php
   PURPOSE: Add new user to the system
   ACCESS: Admin only
   
   SECTIONS:
   - [AUTH-CHECK] Verify admin access
   - [FORM-PROCESSING] Handle form submission
   - [VALIDATION] Validate user input
   - [DATABASE-INSERT] Create new user
   - [HTML-FORM] Display add user form
   
   LAST MODIFIED: 2026-02-20
   ============================================================ */

$pageTitle = 'Add New User';
require_once '../includes/header.php';

/* ============================================================
   [AUTH-CHECK] - Require Admin Role
   ============================================================ */
requireRole('admin');

$errors = [];
$formData = [];

/* ============================================================
   [FORM-PROCESSING] - Handle Form Submission
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Get form data
    $formData = [
        'username' => trim($_POST['username'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'full_name' => trim($_POST['full_name'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'role' => $_POST['role'] ?? 'recruiter',
        'password' => $_POST['password'] ?? '',
        'password_confirm' => $_POST['password_confirm'] ?? ''
    ];
    
    /* ============================================================
       [VALIDATION] - Validate Input
       ============================================================ */
    
    // Username validation
    if (empty($formData['username'])) {
        $errors['username'] = 'Username is required';
    } elseif (strlen($formData['username']) < 3) {
        $errors['username'] = 'Username must be at least 3 characters';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $formData['username'])) {
        $errors['username'] = 'Username can only contain letters, numbers, and underscores';
    } else {
        // Check username uniqueness
        $stmt = $db->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt->execute([$formData['username']]);
        if ($stmt->fetch()) {
            $errors['username'] = 'Username already exists';
        }
    }
    
    // Email validation
    if (empty($formData['email'])) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    } else {
        // Check email uniqueness
        $stmt = $db->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$formData['email']]);
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
    
    // Password validation
    if (empty($formData['password'])) {
        $errors['password'] = 'Password is required';
    } elseif (strlen($formData['password']) < 6) {
        $errors['password'] = 'Password must be at least 6 characters';
    }
    
    // Password confirmation
    if ($formData['password'] !== $formData['password_confirm']) {
        $errors['password_confirm'] = 'Passwords do not match';
    }
    
    /* ============================================================
       [DATABASE-INSERT] - Create User if No Errors
       ============================================================ */
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                INSERT INTO users (username, email, password_hash, full_name, phone, role, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())
            ");
            
            $passwordHash = password_hash($formData['password'], PASSWORD_DEFAULT);
            
            $stmt->execute([
                $formData['username'],
                $formData['email'],
                $passwordHash,
                $formData['full_name'],
                $formData['phone'],
                $formData['role']
            ]);
            
            $newUserId = $db->lastInsertId();
            
            // Log activity
            logActivity($db, $_SESSION['user_id'], 'create', 'user', $newUserId, 
                       "Created new user: {$formData['username']} ({$formData['role']})");
            
            setFlashMessage('success', "User '{$formData['full_name']}' created successfully!");
            header('Location: index.php');
            exit();
            
        } catch (PDOException $e) {
            $errors['general'] = 'Database error: ' . $e->getMessage();
        }
    }
}
?>

<!-- ============================================================
     [HTML-FORM] - Add User Form
     ============================================================ -->

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5>Add New User</h5>
            </div>
            <div class="card-body">
                
                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($errors['general']); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" class="needs-validation" novalidate>
                    
                    <!-- Username -->
                    <div class="mb-3">
                        <label class="form-label">Username *</label>
                        <input type="text" 
                               name="username" 
                               class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>"
                               value="<?php echo htmlspecialchars($formData['username'] ?? ''); ?>"
                               required>
                        <small class="text-tertiary">Letters, numbers, and underscores only. Min 3 characters.</small>
                        <?php if (isset($errors['username'])): ?>
                            <div class="invalid-feedback d-block"><?php echo $errors['username']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Email -->
                    <div class="mb-3">
                        <label class="form-label">Email Address *</label>
                        <input type="email" 
                               name="email" 
                               class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>"
                               value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>"
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
                               value="<?php echo htmlspecialchars($formData['full_name'] ?? ''); ?>"
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
                               value="<?php echo htmlspecialchars($formData['phone'] ?? ''); ?>"
                               placeholder="10-digit mobile number">
                        <small class="text-tertiary">Optional. Enter 10-digit mobile number.</small>
                        <?php if (isset($errors['phone'])): ?>
                            <div class="invalid-feedback d-block"><?php echo $errors['phone']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Role -->
                    <div class="mb-3">
                        <label class="form-label">Role *</label>
                        <select name="role" 
                                class="form-control <?php echo isset($errors['role']) ? 'is-invalid' : ''; ?>"
                                required>
                            <option value="recruiter" <?php echo ($formData['role'] ?? '') === 'recruiter' ? 'selected' : ''; ?>>
                                Recruiter
                            </option>
                            <option value="manager" <?php echo ($formData['role'] ?? '') === 'manager' ? 'selected' : ''; ?>>
                                Manager
                            </option>
                            <option value="admin" <?php echo ($formData['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>
                                Admin
                            </option>
                        </select>
                        <?php if (isset($errors['role'])): ?>
                            <div class="invalid-feedback d-block"><?php echo $errors['role']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <hr class="my-4">
                    
                    <!-- Password -->
                    <div class="mb-3">
                        <label class="form-label">Password *</label>
                        <input type="password" 
                               name="password" 
                               id="password"
                               class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>"
                               required>
                        <small class="text-tertiary">Minimum 6 characters.</small>
                        <div id="password-strength" class="mt-2" style="display: none;">
                            <div class="progress" style="height: 4px;">
                                <div id="strength-bar" class="progress-bar" role="progressbar" style="width: 0%"></div>
                            </div>
                            <small id="strength-text" class="text-tertiary"></small>
                        </div>
                        <?php if (isset($errors['password'])): ?>
                            <div class="invalid-feedback d-block"><?php echo $errors['password']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <script>
                    // Password strength indicator
                    document.addEventListener('DOMContentLoaded', function() {
                        const passwordInput = document.getElementById('password');
                        const strengthDiv = document.getElementById('password-strength');
                        const strengthBar = document.getElementById('strength-bar');
                        const strengthText = document.getElementById('strength-text');
                        
                        passwordInput.addEventListener('input', function() {
                            const password = this.value;
                            
                            if (password.length === 0) {
                                strengthDiv.style.display = 'none';
                                return;
                            }
                            
                            strengthDiv.style.display = 'block';
                            
                            let strength = 0;
                            let tips = [];
                            
                            // Length check
                            if (password.length >= 6) strength += 20;
                            if (password.length >= 8) strength += 10;
                            if (password.length >= 12) strength += 10;
                            
                            // Character variety
                            if (/[a-z]/.test(password)) strength += 15;
                            if (/[A-Z]/.test(password)) strength += 15;
                            if (/[0-9]/.test(password)) strength += 15;
                            if (/[^a-zA-Z0-9]/.test(password)) strength += 15;
                            
                            // Update bar
                            strengthBar.style.width = strength + '%';
                            
                            // Update color and text
                            if (strength < 40) {
                                strengthBar.className = 'progress-bar bg-danger';
                                strengthText.textContent = 'Weak password';
                                strengthText.className = 'text-danger small';
                            } else if (strength < 70) {
                                strengthBar.className = 'progress-bar bg-warning';
                                strengthText.textContent = 'Medium strength';
                                strengthText.className = 'text-warning small';
                            } else {
                                strengthBar.className = 'progress-bar bg-success';
                                strengthText.textContent = 'Strong password!';
                                strengthText.className = 'text-success small';
                            }
                        });
                    });
                    </script>
                    
                    <!-- Confirm Password -->
                    <div class="mb-4">
                        <label class="form-label">Confirm Password *</label>
                        <input type="password" 
                               name="password_confirm" 
                               id="password_confirm"
                               class="form-control <?php echo isset($errors['password_confirm']) ? 'is-invalid' : ''; ?>"
                               required>
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
                            
                            if (pass2.length === 0) {
                                indicator.style.display = 'none';
                                confirmPassword.classList.remove('is-valid', 'is-invalid');
                                return;
                            }
                            
                            indicator.style.display = 'block';
                            
                            if (pass1 === pass2) {
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
                        
                        password.addEventListener('input', checkPasswordMatch);
                        confirmPassword.addEventListener('input', checkPasswordMatch);
                    });
                    </script>
                    
                    <!-- Submit Buttons -->
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Create User
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
                <h5>Role Permissions</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong class="text-danger">Admin</strong>
                    <p class="small text-secondary mb-0">Full system access. Can manage users, all clients, jobs, and settings.</p>
                </div>
                <hr>
                <div class="mb-3">
                    <strong class="text-warning">Manager</strong>
                    <p class="small text-secondary mb-0">Can manage clients, jobs, candidates, and applications. Cannot manage users.</p>
                </div>
                <hr>
                <div>
                    <strong class="text-primary">Recruiter</strong>
                    <p class="small text-secondary mb-0">Can manage assigned jobs, own candidates, and assigned applications.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
