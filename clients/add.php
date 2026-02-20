<?php
ob_start();
/* ============================================================
   FILE: clients/add.php
   PURPOSE: Add new client with agreement upload
   ACCESS: Admin, Manager only
   
   SECTIONS:
   - [AUTH-CHECK] Verify admin/manager access
   - [FORM-PROCESSING] Handle form submission
   - [FILE-UPLOAD] Handle agreement document upload
   - [VALIDATION] Validate client data
   - [DATABASE-INSERT] Create new client
   - [HTML-FORM] Display add client form
   
   LAST MODIFIED: 2026-02-20
   ============================================================ */

$pageTitle = 'Add New Client';
require_once '../includes/header.php';

/* ============================================================
   [AUTH-CHECK] - Require Admin or Manager Role
   ============================================================ */
requireRole(['admin', 'manager']);

$errors = [];
$formData = [];

/* ============================================================
   [FORM-PROCESSING] - Handle Form Submission
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Get form data
    $formData = [
        'company_name' => trim($_POST['company_name'] ?? ''),
        'industry' => trim($_POST['industry'] ?? ''),
        'contact_person' => trim($_POST['contact_person'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'city' => trim($_POST['city'] ?? ''),
        'state' => trim($_POST['state'] ?? ''),
        'country' => $_POST['country'] ?? 'India',
        'postal_code' => trim($_POST['postal_code'] ?? ''),
        'website' => trim($_POST['website'] ?? ''),
        'assigned_to' => intval($_POST['assigned_to'] ?? 0),
        'status' => $_POST['status'] ?? 'active'
    ];
    
    /* ============================================================
       [VALIDATION] - Validate Input
       ============================================================ */
    
    // Company name validation
    if (empty($formData['company_name'])) {
        $errors['company_name'] = 'Company name is required';
    } elseif (strlen($formData['company_name']) < 2) {
        $errors['company_name'] = 'Company name must be at least 2 characters';
    }
    
    // Email validation (optional but validated if provided)
    if (!empty($formData['email']) && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    }
    
    // Phone validation (optional but validated if provided)
    if (!empty($formData['phone'])) {
        $phone = preg_replace('/[^0-9]/', '', $formData['phone']);
        if (strlen($phone) < 10) {
            $errors['phone'] = 'Phone number must be at least 10 digits';
        }
    }
    
    // Website validation (optional but validated if provided)
    if (!empty($formData['website'])) {
        if (!filter_var($formData['website'], FILTER_VALIDATE_URL)) {
            $errors['website'] = 'Invalid website URL';
        }
    }
    
    // Assigned to validation
    if ($formData['assigned_to'] <= 0) {
        $errors['assigned_to'] = 'Please assign this client to a user';
    } else {
        // Verify user exists
        $stmt = $db->prepare("SELECT user_id FROM users WHERE user_id = ? AND status = 'active'");
        $stmt->execute([$formData['assigned_to']]);
        if (!$stmt->fetch()) {
            $errors['assigned_to'] = 'Selected user not found or inactive';
        }
    }
    
    /* ============================================================
       [FILE-UPLOAD] - Handle Agreement Upload
       ============================================================ */
    $agreementPath = null;
    if (isset($_FILES['agreement']) && $_FILES['agreement']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = uploadFile($_FILES['agreement'], 'agreements', ['pdf', 'doc', 'docx']);
        
        if ($uploadResult['success']) {
            $agreementPath = $uploadResult['path'];
        } else {
            $errors['agreement'] = $uploadResult['error'];
        }
    }
    
    /* ============================================================
       [DATABASE-INSERT] - Create Client if No Errors
       ============================================================ */
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                INSERT INTO clients (
                    company_name, industry, contact_person, email, phone,
                    address, city, state, country, postal_code, website,
                    agreement_path, assigned_to, status, created_by, created_at
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $formData['company_name'],
                $formData['industry'],
                $formData['contact_person'],
                $formData['email'],
                $formData['phone'],
                $formData['address'],
                $formData['city'],
                $formData['state'],
                $formData['country'],
                $formData['postal_code'],
                $formData['website'],
                $agreementPath,
                $formData['assigned_to'],
                $formData['status'],
                $_SESSION['user_id']
            ]);
            
            $newClientId = $db->lastInsertId();
            
            // Log activity
            logActivity($db, $_SESSION['user_id'], 'create', 'client', $newClientId, 
                       "Created new client: {$formData['company_name']}");
            
            setFlashMessage('success', "Client '{$formData['company_name']}' created successfully!");
            header('Location: index.php');
            exit();
            
        } catch (PDOException $e) {
            $errors['general'] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Get active users for assignment dropdown
try {
    $usersStmt = $db->query("SELECT user_id, full_name, role FROM users WHERE status = 'active' ORDER BY full_name");
    $users = $usersStmt->fetchAll();
} catch (PDOException $e) {
    $users = [];
}
?>

<!-- ============================================================
     [HTML-FORM] - Add Client Form
     ============================================================ -->

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5>Add New Client</h5>
            </div>
            <div class="card-body">
                
                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($errors['general']); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" enctype="multipart/form-data" class="needs-validation" novalidate>
                    
                    <h6 class="mb-3">Company Information</h6>
                    
                    <!-- Company Name -->
                    <div class="mb-3">
                        <label class="form-label">Company Name *</label>
                        <input type="text" 
                               name="company_name" 
                               class="form-control <?php echo isset($errors['company_name']) ? 'is-invalid' : ''; ?>"
                               value="<?php echo htmlspecialchars($formData['company_name'] ?? ''); ?>"
                               required>
                        <?php if (isset($errors['company_name'])): ?>
                            <div class="invalid-feedback d-block"><?php echo $errors['company_name']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Industry -->
                    <div class="mb-3">
                        <label class="form-label">Industry</label>
                        <input type="text" 
                               name="industry" 
                               class="form-control"
                               value="<?php echo htmlspecialchars($formData['industry'] ?? ''); ?>"
                               placeholder="e.g., IT Services, Manufacturing, Healthcare">
                    </div>
                    
                    <hr class="my-4">
                    <h6 class="mb-3">Contact Information</h6>
                    
                    <!-- Contact Person -->
                    <div class="mb-3">
                        <label class="form-label">Contact Person</label>
                        <input type="text" 
                               name="contact_person" 
                               class="form-control"
                               value="<?php echo htmlspecialchars($formData['contact_person'] ?? ''); ?>"
                               placeholder="Primary contact name">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <!-- Email -->
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" 
                                       name="email" 
                                       class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>"
                                       value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>"
                                       placeholder="contact@company.com">
                                <?php if (isset($errors['email'])): ?>
                                    <div class="invalid-feedback d-block"><?php echo $errors['email']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <!-- Phone -->
                            <div class="mb-3">
                                <label class="form-label">Phone</label>
                                <input type="tel" 
                                       name="phone" 
                                       class="form-control <?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>"
                                       value="<?php echo htmlspecialchars($formData['phone'] ?? ''); ?>"
                                       placeholder="+91-XXXXX-XXXXX">
                                <?php if (isset($errors['phone'])): ?>
                                    <div class="invalid-feedback d-block"><?php echo $errors['phone']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Website -->
                    <div class="mb-3">
                        <label class="form-label">Website</label>
                        <input type="url" 
                               name="website" 
                               class="form-control <?php echo isset($errors['website']) ? 'is-invalid' : ''; ?>"
                               value="<?php echo htmlspecialchars($formData['website'] ?? ''); ?>"
                               placeholder="https://www.company.com">
                        <?php if (isset($errors['website'])): ?>
                            <div class="invalid-feedback d-block"><?php echo $errors['website']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <hr class="my-4">
                    <h6 class="mb-3">Address</h6>
                    
                    <!-- Address -->
                    <div class="mb-3">
                        <label class="form-label">Street Address</label>
                        <textarea name="address" 
                                  class="form-control" 
                                  rows="2"
                                  placeholder="Building, street, area"><?php echo htmlspecialchars($formData['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <!-- City -->
                            <div class="mb-3">
                                <label class="form-label">City</label>
                                <input type="text" 
                                       name="city" 
                                       class="form-control"
                                       value="<?php echo htmlspecialchars($formData['city'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <!-- State -->
                            <div class="mb-3">
                                <label class="form-label">State</label>
                                <input type="text" 
                                       name="state" 
                                       class="form-control"
                                       value="<?php echo htmlspecialchars($formData['state'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <!-- Country -->
                            <div class="mb-3">
                                <label class="form-label">Country</label>
                                <input type="text" 
                                       name="country" 
                                       class="form-control"
                                       value="<?php echo htmlspecialchars($formData['country'] ?? 'India'); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <!-- Postal Code -->
                            <div class="mb-3">
                                <label class="form-label">Postal Code</label>
                                <input type="text" 
                                       name="postal_code" 
                                       class="form-control"
                                       value="<?php echo htmlspecialchars($formData['postal_code'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    <h6 class="mb-3">Assignment & Documents</h6>
                    
                    <!-- Assigned To -->
                    <div class="mb-3">
                        <label class="form-label">Assign To (Client Owner) *</label>
                        <select name="assigned_to" 
                                class="form-control <?php echo isset($errors['assigned_to']) ? 'is-invalid' : ''; ?>"
                                required>
                            <option value="">-- Select User --</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['user_id']; ?>"
                                        <?php echo ($formData['assigned_to'] ?? 0) == $user['user_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['full_name']); ?> 
                                    (<?php echo ucfirst($user['role']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-tertiary">Client owner will see all jobs for this client</small>
                        <?php if (isset($errors['assigned_to'])): ?>
                            <div class="invalid-feedback d-block"><?php echo $errors['assigned_to']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Agreement Upload -->
                    <div class="mb-3">
                        <label class="form-label">Client Agreement (Optional)</label>
                        <input type="file" 
                               name="agreement" 
                               class="form-control <?php echo isset($errors['agreement']) ? 'is-invalid' : ''; ?>"
                               accept=".pdf,.doc,.docx">
                        <small class="text-tertiary">PDF, DOC, or DOCX format. Max 10MB</small>
                        <?php if (isset($errors['agreement'])): ?>
                            <div class="invalid-feedback d-block"><?php echo $errors['agreement']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Status -->
                    <div class="mb-4">
                        <label class="form-label">Status *</label>
                        <select name="status" class="form-control" required>
                            <option value="active" <?php echo ($formData['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>
                                Active
                            </option>
                            <option value="on-hold" <?php echo ($formData['status'] ?? '') === 'on-hold' ? 'selected' : ''; ?>>
                                On Hold
                            </option>
                            <option value="inactive" <?php echo ($formData['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>
                                Inactive
                            </option>
                        </select>
                    </div>
                    
                    <!-- Submit Buttons -->
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Create Client
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
                <h5><i class="fas fa-info-circle"></i> Quick Tips</h5>
            </div>
            <div class="card-body">
                <p class="small mb-2"><strong>Required Fields:</strong></p>
                <ul class="small text-secondary mb-3">
                    <li>Company Name</li>
                    <li>Assign To (Client Owner)</li>
                    <li>Status</li>
                </ul>
                
                <p class="small mb-2"><strong>Client Owner:</strong></p>
                <p class="small text-secondary mb-3">
                    The assigned user will see all jobs for this client, even if not directly assigned to those jobs.
                </p>
                
                <p class="small mb-2"><strong>Agreement Document:</strong></p>
                <p class="small text-secondary mb-0">
                    Upload the signed client agreement for reference. This can be updated later.
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
