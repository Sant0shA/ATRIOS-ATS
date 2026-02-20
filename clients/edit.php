<?php
ob_start();
/* ============================================================
   FILE: clients/edit.php
   PURPOSE: Edit existing client details
   ACCESS: Admin, Manager only
   
   SECTIONS:
   - [AUTH-CHECK] Verify admin/manager access
   - [FETCH-CLIENT] Get client data
   - [FORM-PROCESSING] Handle form submission
   - [FILE-UPLOAD] Handle agreement replacement
   - [VALIDATION] Validate client data
   - [DATABASE-UPDATE] Update client record
   - [HTML-FORM] Display edit client form
   
   LAST MODIFIED: 2026-02-20
   ============================================================ */

$pageTitle = 'Edit Client';
require_once '../includes/header.php';

/* ============================================================
   [AUTH-CHECK] - Require Admin or Manager Role
   ============================================================ */
requireRole(['admin', 'manager']);

$errors = [];
$client = null;

/* ============================================================
   [FETCH-CLIENT] - Get Client ID and Fetch Data
   ============================================================ */
$clientId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($clientId <= 0) {
    setFlashMessage('error', 'Invalid client ID');
    header('Location: index.php');
    exit();
}

try {
    $stmt = $db->prepare("SELECT * FROM clients WHERE client_id = ?");
    $stmt->execute([$clientId]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$client) {
        setFlashMessage('error', 'Client not found');
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
       [FILE-UPLOAD] - Handle Agreement Replacement
       ============================================================ */
    $agreementPath = $client['agreement_path']; // Keep existing by default
    
    if (isset($_FILES['agreement']) && $_FILES['agreement']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = uploadFile($_FILES['agreement'], 'agreements', ['pdf', 'doc', 'docx']);
        
        if ($uploadResult['success']) {
            // Delete old agreement if exists
            if ($client['agreement_path']) {
                deleteFile($client['agreement_path']);
            }
            $agreementPath = $uploadResult['path'];
        } else {
            $errors['agreement'] = $uploadResult['error'];
        }
    }
    
    /* ============================================================
       [DATABASE-UPDATE] - Update Client if No Errors
       ============================================================ */
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                UPDATE clients 
                SET company_name = ?, industry = ?, contact_person = ?, email = ?, phone = ?,
                    address = ?, city = ?, state = ?, country = ?, postal_code = ?, website = ?,
                    agreement_path = ?, assigned_to = ?, status = ?, updated_at = NOW()
                WHERE client_id = ?
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
                $clientId
            ]);
            
            // Log activity
            logActivity($db, $_SESSION['user_id'], 'update', 'client', $clientId, 
                       "Updated client: {$formData['company_name']}");
            
            setFlashMessage('success', "Client '{$formData['company_name']}' updated successfully!");
            header('Location: view.php?id=' . $clientId);
            exit();
            
        } catch (PDOException $e) {
            $errors['general'] = 'Database error: ' . $e->getMessage();
        }
    } else {
        // Update client array with form data for display
        $client = array_merge($client, $formData);
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
     [HTML-FORM] - Edit Client Form
     ============================================================ -->

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5>Edit Client: <?php echo htmlspecialchars($client['company_name']); ?></h5>
            </div>
            <div class="card-body">
                
                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($errors['general']); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" enctype="multipart/form-data">
                    
                    <h6 class="mb-3">Company Information</h6>
                    
                    <!-- Company Name -->
                    <div class="mb-3">
                        <label class="form-label">Company Name *</label>
                        <input type="text" 
                               name="company_name" 
                               class="form-control <?php echo isset($errors['company_name']) ? 'is-invalid' : ''; ?>"
                               value="<?php echo htmlspecialchars($client['company_name']); ?>"
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
                               value="<?php echo htmlspecialchars($client['industry']); ?>"
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
                               value="<?php echo htmlspecialchars($client['contact_person']); ?>">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <!-- Email -->
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" 
                                       name="email" 
                                       class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>"
                                       value="<?php echo htmlspecialchars($client['email']); ?>">
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
                                       value="<?php echo htmlspecialchars($client['phone']); ?>">
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
                               value="<?php echo htmlspecialchars($client['website']); ?>">
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
                                  rows="2"><?php echo htmlspecialchars($client['address']); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">City</label>
                                <input type="text" 
                                       name="city" 
                                       class="form-control"
                                       value="<?php echo htmlspecialchars($client['city']); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">State</label>
                                <input type="text" 
                                       name="state" 
                                       class="form-control"
                                       value="<?php echo htmlspecialchars($client['state']); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Country</label>
                                <input type="text" 
                                       name="country" 
                                       class="form-control"
                                       value="<?php echo htmlspecialchars($client['country']); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Postal Code</label>
                                <input type="text" 
                                       name="postal_code" 
                                       class="form-control"
                                       value="<?php echo htmlspecialchars($client['postal_code']); ?>">
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
                                        <?php echo $client['assigned_to'] == $user['user_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['full_name']); ?> 
                                    (<?php echo ucfirst($user['role']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['assigned_to'])): ?>
                            <div class="invalid-feedback d-block"><?php echo $errors['assigned_to']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Current Agreement -->
                    <?php if ($client['agreement_path']): ?>
                    <div class="mb-3">
                        <label class="form-label">Current Agreement</label><br>
                        <a href="<?php echo SITE_URL . '/' . htmlspecialchars($client['agreement_path']); ?>" 
                           target="_blank"
                           class="btn btn-sm btn-outline">
                            <i class="fas fa-file-contract"></i> View Current Agreement
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Replace Agreement -->
                    <div class="mb-3">
                        <label class="form-label">
                            <?php echo $client['agreement_path'] ? 'Replace Agreement (Optional)' : 'Upload Agreement (Optional)'; ?>
                        </label>
                        <input type="file" 
                               name="agreement" 
                               class="form-control <?php echo isset($errors['agreement']) ? 'is-invalid' : ''; ?>"
                               accept=".pdf,.doc,.docx">
                        <small class="text-tertiary">
                            <?php echo $client['agreement_path'] ? 'Leave empty to keep current agreement. ' : ''; ?>
                            PDF, DOC, or DOCX format. Max 10MB
                        </small>
                        <?php if (isset($errors['agreement'])): ?>
                            <div class="invalid-feedback d-block"><?php echo $errors['agreement']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Status -->
                    <div class="mb-4">
                        <label class="form-label">Status *</label>
                        <select name="status" class="form-control" required>
                            <option value="active" <?php echo $client['status'] === 'active' ? 'selected' : ''; ?>>
                                Active
                            </option>
                            <option value="on-hold" <?php echo $client['status'] === 'on-hold' ? 'selected' : ''; ?>>
                                On Hold
                            </option>
                            <option value="inactive" <?php echo $client['status'] === 'inactive' ? 'selected' : ''; ?>>
                                Inactive
                            </option>
                        </select>
                    </div>
                    
                    <!-- Submit Buttons -->
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Client
                        </button>
                        <a href="view.php?id=<?php echo $clientId; ?>" class="btn btn-secondary">
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
                <h5>Client Information</h5>
            </div>
            <div class="card-body">
                <p class="small text-secondary mb-2">
                    <strong>Created:</strong><br>
                    <?php echo formatDateTime($client['created_at']); ?>
                </p>
                <p class="small text-secondary mb-0">
                    <strong>Client ID:</strong> #<?php echo $client['client_id']; ?>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
