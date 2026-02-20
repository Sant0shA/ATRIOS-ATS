<?php
$pageTitle = 'Add Client';
require_once '../includes/header.php';

requireRole(['admin', 'manager']);

$errors = [];
$client = ['company_name' => '', 'contact_person' => '', 'email' => '', 'phone' => '', 'address' => '', 'status' => 'active'];
$isEdit = false;

// Check if editing
if (isset($_GET['id']) && $_GET['id']) {
    $isEdit = true;
    $client_id = intval($_GET['id']);
    $pageTitle = 'Edit Client';
    
    try {
        $stmt = $db->prepare("SELECT * FROM clients WHERE client_id = ?");
        $stmt->execute([$client_id]);
        $client = $stmt->fetch();
        
        if (!$client) {
            setFlashMessage('error', 'Client not found');
            header('Location: index.php');
            exit();
        }
    } catch (PDOException $e) {
        setFlashMessage('error', 'Error fetching client data');
        header('Location: index.php');
        exit();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = sanitizeInput($_POST['company_name'] ?? '');
    $contact_person = sanitizeInput($_POST['contact_person'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $address = sanitizeInput($_POST['address'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    // Validation
    if (empty($company_name)) $errors['company_name'] = 'Company name is required';
    if (empty($contact_person)) $errors['contact_person'] = 'Contact person is required';
    if (empty($email)) {
        $errors['email'] = 'Email is required';
    } elseif (!isValidEmail($email)) {
        $errors['email'] = 'Invalid email format';
    }
    
    // Check email uniqueness
    if (empty($errors['email'])) {
        $checkQuery = "SELECT client_id FROM clients WHERE email = ?";
        if ($isEdit) {
            $checkQuery .= " AND client_id != ?";
            $stmt = $db->prepare($checkQuery);
            $stmt->execute([$email, $client_id]);
        } else {
            $stmt = $db->prepare($checkQuery);
            $stmt->execute([$email]);
        }
        
        if ($stmt->fetch()) {
            $errors['email'] = 'Email already exists';
        }
    }
    
    if (empty($errors)) {
        try {
            if ($isEdit) {
                $stmt = $db->prepare("UPDATE clients SET company_name = ?, contact_person = ?, email = ?, phone = ?, address = ?, status = ? WHERE client_id = ?");
                $stmt->execute([$company_name, $contact_person, $email, $phone, $address, $status, $client_id]);
                logActivity($db, $_SESSION['user_id'], 'update', 'client', $client_id, "Updated client: $company_name");
                setFlashMessage('success', 'Client updated successfully');
            } else {
                $stmt = $db->prepare("INSERT INTO clients (company_name, contact_person, email, phone, address, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$company_name, $contact_person, $email, $phone, $address, $status]);
                $client_id = $db->lastInsertId();
                logActivity($db, $_SESSION['user_id'], 'create', 'client', $client_id, "Created client: $company_name");
                setFlashMessage('success', 'Client added successfully');
            }
            
            header('Location: index.php');
            exit();
        } catch (PDOException $e) {
            $errors['general'] = 'Database error: ' . $e->getMessage();
        }
    }
    
    // Preserve form data
    $client = array_merge($client, $_POST);
}
?>

<div class="mb-4">
    <a href="index.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Clients
    </a>
</div>

<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-building"></i> <?php echo $isEdit ? 'Edit' : 'Add New'; ?> Client
                </h5>
            </div>
            <div class="card-body">
                <?php if (isset($errors['general'])): ?>
                    <div class="alert alert-danger">
                        <?php echo $errors['general']; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label">Company Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?php echo isset($errors['company_name']) ? 'is-invalid' : ''; ?>" 
                               name="company_name" value="<?php echo htmlspecialchars($client['company_name']); ?>" required>
                        <?php if (isset($errors['company_name'])): ?>
                            <div class="invalid-feedback"><?php echo $errors['company_name']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Contact Person <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?php echo isset($errors['contact_person']) ? 'is-invalid' : ''; ?>" 
                               name="contact_person" value="<?php echo htmlspecialchars($client['contact_person']); ?>" required>
                        <?php if (isset($errors['contact_person'])): ?>
                            <div class="invalid-feedback"><?php echo $errors['contact_person']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                                   name="email" value="<?php echo htmlspecialchars($client['email']); ?>" required>
                            <?php if (isset($errors['email'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control" name="phone" 
                                   value="<?php echo htmlspecialchars($client['phone'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="address" rows="3"><?php echo htmlspecialchars($client['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="active" <?php echo ($client['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($client['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo $isEdit ? 'Update' : 'Add'; ?> Client
                        </button>
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
