<?php
ob_start();
/* ============================================================
   FILE: settings.php
   PURPOSE: System settings and user profile management
   ACCESS: All users (admin sees additional settings)
   
   SECTIONS:
   - Company Settings (Admin only)
   - My Profile (All users)
   - Change Password (All users)
   
   LAST MODIFIED: 2026-02-20
   ============================================================ */

$pageTitle = 'Settings';
require_once 'includes/header.php';

$errors = [];
$success = '';

/* ============================================================
   [FORM-PROCESSING] - Handle Form Submissions
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Company Settings (Admin only)
    if (isset($_POST['action']) && $_POST['action'] === 'update_company' && hasRole('admin')) {
        try {
            $companyName = trim($_POST['company_name']);
            $companyEmail = trim($_POST['company_email']);
            $enforcePasswordPolicy = isset($_POST['enforce_password_policy']) ? 1 : 0;
            
            // Validate
            if (empty($companyName)) {
                $errors['company_name'] = 'Company name is required';
            }
            
            if (!empty($companyEmail) && !filter_var($companyEmail, FILTER_VALIDATE_EMAIL)) {
                $errors['company_email'] = 'Invalid email address';
            }
            
            if (empty($errors)) {
                // Update or insert settings
                $settings = [
                    'company_name' => $companyName,
                    'company_email' => $companyEmail,
                    'enforce_password_policy' => $enforcePasswordPolicy
                ];
                
                foreach ($settings as $key => $value) {
                    $stmt = $db->prepare("
                        INSERT INTO settings (setting_key, setting_value, updated_at) 
                        VALUES (?, ?, NOW())
                        ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()
                    ");
                    $stmt->execute([$key, $value, $value]);
                }
                
                logActivity($db, $_SESSION['user_id'], 'update', 'settings', 0, 'Updated company settings');
                
                $success = 'Company settings updated successfully';
            }
        } catch (PDOException $e) {
            $errors['general'] = 'Database error: ' . $e->getMessage();
        }
    }
    
    // Update Profile (All users)
    if (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
        try {
            $fullName = trim($_POST['full_name']);
            $email = trim($_POST['email']);
            $phone = trim($_POST['phone']);
            
            // Validate
            if (empty($fullName)) {
                $errors['full_name'] = 'Full name is required';
            }
            
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Valid email is required';
            }
            
            // Check if email is taken by another user
            if (!isset($errors['email'])) {
                $stmt = $db->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
                $stmt->execute([$email, $_SESSION['user_id']]);
                if ($stmt->fetch()) {
                    $errors['email'] = 'Email already in use by another user';
                }
            }
            
            if (empty($errors)) {
                $stmt = $db->prepare("
                    UPDATE users 
                    SET full_name = ?, email = ?, phone = ?, updated_at = NOW()
                    WHERE user_id = ?
                ");
                $stmt->execute([$fullName, $email, $phone, $_SESSION['user_id']]);
                
                // Update session
                $_SESSION['user_name'] = $fullName;
                $_SESSION['user_email'] = $email;
                
                logActivity($db, $_SESSION['user_id'], 'update', 'user', $_SESSION['user_id'], 'Updated profile information');
                
                $success = 'Profile updated successfully';
            }
        } catch (PDOException $e) {
            $errors['general'] = 'Database error: ' . $e->getMessage();
        }
    }
    
    // Change Password (All users)
    if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
        try {
            $currentPassword = $_POST['current_password'];
            $newPassword = $_POST['new_password'];
            $confirmPassword = $_POST['confirm_password'];
            
            // Get current user password
            $stmt = $db->prepare("SELECT password_hash FROM users WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Validate current password
            if (!password_verify($currentPassword, $user['password_hash'])) {
                $errors['current_password'] = 'Current password is incorrect';
            }
            
            // Validate new password
            if (empty($newPassword)) {
                $errors['new_password'] = 'New password is required';
            } elseif (strlen($newPassword) < 8) {
                $errors['new_password'] = 'Password must be at least 8 characters';
            }
            
            // Check password policy if enabled
            $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'enforce_password_policy'");
            $stmt->execute();
            $policySetting = $stmt->fetch();
            $enforcePolicy = $policySetting && $policySetting['setting_value'] == 1;
            
            if ($enforcePolicy && !isset($errors['new_password'])) {
                if (!preg_match('/[A-Z]/', $newPassword)) {
                    $errors['new_password'] = 'Password must contain at least 1 uppercase letter';
                } elseif (!preg_match('/[a-z]/', $newPassword)) {
                    $errors['new_password'] = 'Password must contain at least 1 lowercase letter';
                } elseif (!preg_match('/[0-9]/', $newPassword)) {
                    $errors['new_password'] = 'Password must contain at least 1 number';
                } elseif (!preg_match('/[^A-Za-z0-9]/', $newPassword)) {
                    $errors['new_password'] = 'Password must contain at least 1 special character';
                }
            }
            
            // Validate confirmation
            if ($newPassword !== $confirmPassword) {
                $errors['confirm_password'] = 'Passwords do not match';
            }
            
            if (empty($errors)) {
                $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                
                $stmt = $db->prepare("
                    UPDATE users 
                    SET password_hash = ?, updated_at = NOW()
                    WHERE user_id = ?
                ");
                $stmt->execute([$passwordHash, $_SESSION['user_id']]);
                
                logActivity($db, $_SESSION['user_id'], 'update', 'user', $_SESSION['user_id'], 'Changed password');
                
                $success = 'Password changed successfully';
            }
        } catch (PDOException $e) {
            $errors['general'] = 'Database error: ' . $e->getMessage();
        }
    }
}

/* ============================================================
   [LOAD-SETTINGS] - Load Current Settings
   ============================================================ */
$companySettings = [];
if (hasRole('admin')) {
    try {
        $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('company_name', 'company_email', 'enforce_password_policy')");
        while ($row = $stmt->fetch()) {
            $companySettings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (PDOException $e) {
        // Use defaults
    }
}

// Set defaults if not set
$companySettings['company_name'] = $companySettings['company_name'] ?? 'Atrios';
$companySettings['company_email'] = $companySettings['company_email'] ?? '';
$companySettings['enforce_password_policy'] = $companySettings['enforce_password_policy'] ?? 0;

// Load current user profile
try {
    $stmt = $db->prepare("SELECT full_name, email, phone FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userProfile = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $userProfile = ['full_name' => '', 'email' => '', 'phone' => ''];
}
?>

<style>
.settings-nav {
    background: white;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    overflow: hidden;
}
.settings-nav-item {
    padding: 16px 20px;
    border-bottom: 1px solid #f3f4f6;
    cursor: pointer;
    transition: background 0.2s;
    display: flex;
    align-items: center;
    gap: 12px;
}
.settings-nav-item:last-child {
    border-bottom: none;
}
.settings-nav-item:hover {
    background: #f9fafb;
}
.settings-nav-item.active {
    background: #FEF3E2;
    border-left: 3px solid #F16136;
}
.settings-nav-item i {
    width: 20px;
    color: #6b7280;
}
.settings-nav-item.active i {
    color: #F16136;
}
.settings-content {
    background: white;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    padding: 32px;
}
.settings-section {
    display: none;
}
.settings-section.active {
    display: block;
}
.form-section-title {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 2px solid #f3f4f6;
}
.alert-custom {
    padding: 16px;
    border-radius: 8px;
    margin-bottom: 24px;
}
</style>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($errors['general'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errors['general']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Left Navigation -->
    <div class="col-lg-3">
        <div class="settings-nav">
            <?php if (hasRole('admin')): ?>
            <div class="settings-nav-item active" onclick="showSection('company')">
                <i class="fas fa-building"></i>
                <span>Company Settings</span>
            </div>
            <?php endif; ?>
            
            <div class="settings-nav-item <?php echo !hasRole('admin') ? 'active' : ''; ?>" onclick="showSection('profile')">
                <i class="fas fa-user"></i>
                <span>My Profile</span>
            </div>
            
            <div class="settings-nav-item" onclick="showSection('password')">
                <i class="fas fa-lock"></i>
                <span>Change Password</span>
            </div>
        </div>
    </div>
    
    <!-- Right Content -->
    <div class="col-lg-9">
        
        <!-- Company Settings (Admin Only) -->
        <?php if (hasRole('admin')): ?>
        <div class="settings-content settings-section active" id="section-company">
            <h4 class="form-section-title"><i class="fas fa-building"></i> Company Settings</h4>
            
            <form method="POST">
                <input type="hidden" name="action" value="update_company">
                
                <div class="mb-4">
                    <label class="form-label">Company Name *</label>
                    <input type="text" 
                           name="company_name" 
                           class="form-control <?php echo isset($errors['company_name']) ? 'is-invalid' : ''; ?>"
                           value="<?php echo htmlspecialchars($companySettings['company_name']); ?>"
                           required>
                    <?php if (isset($errors['company_name'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['company_name']; ?></div>
                    <?php endif; ?>
                    <small class="text-tertiary">Displayed on public application forms</small>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">Company Email</label>
                    <input type="email" 
                           name="company_email" 
                           class="form-control <?php echo isset($errors['company_email']) ? 'is-invalid' : ''; ?>"
                           value="<?php echo htmlspecialchars($companySettings['company_email']); ?>"
                           placeholder="contact@company.com">
                    <?php if (isset($errors['company_email'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['company_email']; ?></div>
                    <?php endif; ?>
                    <small class="text-tertiary">Used for system notifications</small>
                </div>
                
                <div class="mb-4">
                    <h6 class="mb-3">Security Settings</h6>
                    
                    <div class="form-check form-switch">
                        <input class="form-check-input" 
                               type="checkbox" 
                               name="enforce_password_policy" 
                               id="enforcePasswordPolicy"
                               <?php echo $companySettings['enforce_password_policy'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="enforcePasswordPolicy">
                            <strong>Enforce Strong Password Policy</strong>
                        </label>
                    </div>
                    <small class="text-tertiary d-block mt-2">
                        When enabled, passwords must be 8+ characters with uppercase, lowercase, number, and special character
                    </small>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Company Settings
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>
        
        <!-- My Profile -->
        <div class="settings-content settings-section <?php echo !hasRole('admin') ? 'active' : ''; ?>" id="section-profile">
            <h4 class="form-section-title"><i class="fas fa-user"></i> My Profile</h4>
            
            <form method="POST">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="mb-4">
                    <label class="form-label">Full Name *</label>
                    <input type="text" 
                           name="full_name" 
                           class="form-control <?php echo isset($errors['full_name']) ? 'is-invalid' : ''; ?>"
                           value="<?php echo htmlspecialchars($userProfile['full_name']); ?>"
                           required>
                    <?php if (isset($errors['full_name'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['full_name']; ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">Email Address *</label>
                    <input type="email" 
                           name="email" 
                           class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>"
                           value="<?php echo htmlspecialchars($userProfile['email']); ?>"
                           required>
                    <?php if (isset($errors['email'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" 
                           name="phone" 
                           class="form-control"
                           value="<?php echo htmlspecialchars($userProfile['phone'] ?? ''); ?>"
                           placeholder="+91 98765 43210">
                </div>
                
                <div class="alert alert-info alert-custom">
                    <i class="fas fa-info-circle"></i>
                    <strong>Note:</strong> Your username and role cannot be changed. Contact an administrator if you need these updated.
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Change Password -->
        <div class="settings-content settings-section" id="section-password">
            <h4 class="form-section-title"><i class="fas fa-lock"></i> Change Password</h4>
            
            <form method="POST">
                <input type="hidden" name="action" value="change_password">
                
                <div class="mb-4">
                    <label class="form-label">Current Password *</label>
                    <input type="password" 
                           name="current_password" 
                           class="form-control <?php echo isset($errors['current_password']) ? 'is-invalid' : ''; ?>"
                           required>
                    <?php if (isset($errors['current_password'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['current_password']; ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">New Password *</label>
                    <input type="password" 
                           name="new_password" 
                           class="form-control <?php echo isset($errors['new_password']) ? 'is-invalid' : ''; ?>"
                           required>
                    <?php if (isset($errors['new_password'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['new_password']; ?></div>
                    <?php else: ?>
                        <small class="text-tertiary">
                            <?php if ($companySettings['enforce_password_policy']): ?>
                                Must be 8+ characters with uppercase, lowercase, number, and special character
                            <?php else: ?>
                                Minimum 8 characters recommended
                            <?php endif; ?>
                        </small>
                    <?php endif; ?>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">Confirm New Password *</label>
                    <input type="password" 
                           name="confirm_password" 
                           class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>"
                           required>
                    <?php if (isset($errors['confirm_password'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['confirm_password']; ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="alert alert-warning alert-custom">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Security Note:</strong> Choose a strong, unique password. Never share your password with anyone.
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                </div>
            </form>
        </div>
        
    </div>
</div>

<script>
function showSection(sectionId) {
    // Hide all sections
    document.querySelectorAll('.settings-section').forEach(section => {
        section.classList.remove('active');
    });
    
    // Remove active from all nav items
    document.querySelectorAll('.settings-nav-item').forEach(item => {
        item.classList.remove('active');
    });
    
    // Show selected section
    document.getElementById('section-' + sectionId).classList.add('active');
    
    // Add active to clicked nav item
    event.currentTarget.classList.add('active');
}
</script>

<?php require_once 'includes/footer.php'; ?>
