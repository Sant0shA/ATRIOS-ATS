<?php
ob_start();
/* ============================================================
   FILE: candidates/add.php
   PURPOSE: Add new candidate with standardized skills
   ACCESS: All users
   
   SECTIONS:
   - [AUTH-CHECK] Verify user access
   - [FORM-PROCESSING] Handle form submission
   - [DUPLICATE-CHECK] Check for existing candidate
   - [VALIDATION] Validate candidate data
   - [FILE-UPLOAD] Handle resume upload
   - [DATABASE-INSERT] Create new candidate
   - [HTML-FORM] Display add candidate form
   
   LAST MODIFIED: 2026-02-20
   ============================================================ */

$pageTitle = 'Add Candidate';
require_once '../includes/header.php';

$errors = [];
$formData = [];

// Standardized Skills List
$standardizedSkills = [
    'Programme Management', 'Project Implementation', 'Project Planning', 'Budget Management',
    'Grant Management', 'Monitoring & Evaluation (M&E)', 'Impact Assessment', 'MEL Framework Design',
    'Data Analysis for Programs', 'MIS & Dashboarding', 'Proposal / Grant Writing', 'Fundraising Strategy',
    'Donor Management & Reporting', 'CSR Partnerships', 'Community Mobilisation', 'Capacity Building & Training',
    'Strategic Planning', 'Organisation Development', 'Change Management', 'Programme Scaling',
    'Partnership Development', 'Talent Acquisition', 'HR Business Partnering', 'Performance Management',
    'Learning & Development', 'POSH & Safeguarding', 'Diversity & Inclusion', 'Finance',
    'FP&A', 'Financial Modelling', 'Business Finance', 'Corporate Finance',
    'Marketing', 'Performance Marketing', 'GTM Strategy', 'Product Marketing', 'Marketing Analytics',
    'Full Stack Development', 'Backend Development', 'Frontend Development', 'Cloud Computing',
    'DevOps', 'Cybersecurity', 'Data Engineering', 'AI / Machine Learning',
    'Data Analysis', 'Business Intelligence', 'Data Visualization', 'SQL',
    'Python for Data', 'Big Data', 'Operations Management', 'Process Improvement',
    'Supply Chain Management', 'Vendor Management', 'Procurement', 'Quality Management',
    'Product Management', 'Product Strategy', 'Roadmap Planning', 'User Research',
    'Agile / Scrum', 'Product Analytics', 'B2B Sales', 'B2C Sales',
    'Key Account Management', 'Business Development', 'Sales Strategy', 'Revenue Operations',
    'Business Strategy', 'Management Consulting', 'Transformation Programs', 'Stakeholder Management',
    'Program Management', 'UI/UX Design', 'Product Design', 'Interaction Design',
    'Design Systems', 'User Testing', 'Visual Design', 'Contract Management',
    'Corporate Law', 'Regulatory Compliance', 'Legal Research', 'Litigation Management',
    'Risk & Governance', 'Office Administration', 'Facilities Management', 'Travel & Vendor Coordination',
    'MIS & Reporting', 'Executive Assistance', 'P&L Management', 'Cross-functional Leadership',
    'Organizational Development', 'Board / Investor Management', 'Scaling Operations'
];

// India Cities
$indiaCities = [
    'Mumbai', 'Delhi', 'Bangalore', 'Hyderabad', 'Chennai', 'Kolkata',
    'Pune', 'Ahmedabad', 'Gurgaon', 'Noida', 'Jaipur', 'Chandigarh',
    'Kochi', 'Indore', 'Coimbatore', 'Surat', 'Vadodara', 'Nagpur',
    'Lucknow', 'Bhopal', 'Visakhapatnam', 'Other'
];

/* ============================================================
   [FORM-PROCESSING] - Handle Form Submission
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $formData = [
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name' => trim($_POST['last_name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'current_location' => $_POST['current_location'] ?? '',
        'skills' => isset($_POST['skills']) ? implode(', ', $_POST['skills']) : '',
        'experience_years' => floatval($_POST['experience_years'] ?? 0),
        'education' => trim($_POST['education'] ?? ''),
        'current_company' => trim($_POST['current_company'] ?? ''),
        'current_designation' => trim($_POST['current_designation'] ?? ''),
        'expected_salary' => floatval($_POST['expected_salary'] ?? 0),
        'notice_period' => trim($_POST['notice_period'] ?? ''),
        'linkedin_url' => trim($_POST['linkedin_url'] ?? ''),
        'source' => $_POST['source'] ?? 'Manual Entry',
        'assigned_to' => $_POST['assigned_to'] ?? null,
        'notes' => trim($_POST['notes'] ?? '')
    ];
    
    /* ============================================================
       [VALIDATION] - Validate Mandatory Fields Only
       ============================================================ */
    
    if (empty($formData['first_name'])) {
        $errors['first_name'] = 'First name is required';
    }
    
    if (empty($formData['last_name'])) {
        $errors['last_name'] = 'Last name is required';
    }
    
    if (empty($formData['email'])) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    }
    
    if (empty($formData['phone'])) {
        $errors['phone'] = 'Phone number is required';
    } else {
        $phone = preg_replace('/[^0-9]/', '', $formData['phone']);
        if (strlen($phone) != 10) {
            $errors['phone'] = 'Phone number must be 10 digits';
        }
    }
    
    if (empty($formData['current_location'])) {
        $errors['current_location'] = 'Current location is required';
    }
    
    /* ============================================================
       [DUPLICATE-CHECK] - Check for Existing Candidate
       ============================================================ */
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("SELECT candidate_id, first_name, last_name FROM candidates WHERE email = ? OR phone = ?");
            $stmt->execute([$formData['email'], $formData['phone']]);
            $existingCandidate = $stmt->fetch();
            
            if ($existingCandidate) {
                $errors['general'] = "Candidate already exists: " . htmlspecialchars($existingCandidate['first_name'] . ' ' . $existingCandidate['last_name']) . 
                                    ". <a href='view.php?id={$existingCandidate['candidate_id']}'>View Profile</a>";
            }
        } catch (PDOException $e) {
            $errors['general'] = 'Database error during duplicate check';
        }
    }
    
    /* ============================================================
       [FILE-UPLOAD] - Handle Resume Upload (Optional)
       ============================================================ */
    $resumePath = null;
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = uploadFile($_FILES['resume'], 'cvs', ['pdf', 'doc', 'docx']);
        
        if ($uploadResult['success']) {
            $resumePath = $uploadResult['path'];
        } else {
            $errors['resume'] = $uploadResult['error'];
        }
    }
    
    /* ============================================================
       [DATABASE-INSERT] - Create Candidate if No Errors
       ============================================================ */
    if (empty($errors)) {
        try {
            $assignedTo = $formData['assigned_to'] === 'self' ? $_SESSION['user_id'] : $formData['assigned_to'];
            
            $stmt = $db->prepare("
                INSERT INTO candidates (
                    first_name, last_name, email, phone, current_location,
                    cv_path, linkedin_url, skills, experience_years, education,
                    current_company, current_designation, expected_salary, notice_period,
                    source, status, assigned_to, added_by, notes, created_at
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $formData['first_name'],
                $formData['last_name'],
                $formData['email'],
                $formData['phone'],
                $formData['current_location'],
                $resumePath,
                $formData['linkedin_url'],
                $formData['skills'],
                $formData['experience_years'],
                $formData['education'],
                $formData['current_company'],
                $formData['current_designation'],
                $formData['expected_salary'],
                $formData['notice_period'],
                $formData['source'],
                $assignedTo,
                $_SESSION['user_id'],
                $formData['notes']
            ]);
            
            $newCandidateId = $db->lastInsertId();
            
            logActivity($db, $_SESSION['user_id'], 'create', 'candidate', $newCandidateId, 
                       "Created candidate: {$formData['first_name']} {$formData['last_name']}");
            
            // Show beautiful success popup
            echo "<!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
                <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>
                <link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap' rel='stylesheet'>
                <style>
                    body { 
                        font-family: 'Inter', sans-serif;
                        margin: 0;
                        padding: 0;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        min-height: 100vh;
                        background: rgba(0, 0, 0, 0.15);
                        animation: fadeIn 0.3s ease;
                    }
                    @keyframes fadeIn {
                        from { opacity: 0; }
                        to { opacity: 1; }
                    }
                    @keyframes slideUp {
                        from { 
                            opacity: 0;
                            transform: translateY(20px) scale(0.95);
                        }
                        to { 
                            opacity: 1;
                            transform: translateY(0) scale(1);
                        }
                    }
                    @keyframes checkmark {
                        0% { transform: scale(0) rotate(-45deg); }
                        50% { transform: scale(1.2) rotate(-45deg); }
                        100% { transform: scale(1) rotate(-45deg); }
                    }
                    .success-modal {
                        background: white;
                        border-radius: 16px;
                        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                        max-width: 420px;
                        width: 90%;
                        padding: 48px 40px 40px;
                        text-align: center;
                        animation: slideUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
                    }
                    .success-icon {
                        width: 80px;
                        height: 80px;
                        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                        border-radius: 50%;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        margin: 0 auto 24px;
                        position: relative;
                    }
                    .success-icon::before {
                        content: '';
                        position: absolute;
                        width: 100%;
                        height: 100%;
                        background: rgba(16, 185, 129, 0.2);
                        border-radius: 50%;
                        animation: pulse 2s infinite;
                    }
                    @keyframes pulse {
                        0%, 100% { transform: scale(1); opacity: 1; }
                        50% { transform: scale(1.1); opacity: 0.5; }
                    }
                    .checkmark {
                        width: 32px;
                        height: 18px;
                        border-left: 4px solid white;
                        border-bottom: 4px solid white;
                        transform: rotate(-45deg);
                        animation: checkmark 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) 0.2s forwards;
                        transform-origin: center;
                    }
                    .success-title {
                        font-size: 28px;
                        font-weight: 600;
                        color: #1a1a1a;
                        margin: 0 0 12px;
                        line-height: 1.2;
                    }
                    .success-name {
                        font-size: 20px;
                        color: #10b981;
                        font-weight: 500;
                        margin: 0 0 8px;
                    }
                    .success-message {
                        font-size: 15px;
                        color: #6b7280;
                        margin: 0 0 32px;
                        line-height: 1.5;
                    }
                    .btn-ok {
                        background: linear-gradient(135deg, #F16136 0%, #FF8A65 100%);
                        color: white;
                        border: none;
                        padding: 14px 48px;
                        border-radius: 10px;
                        font-size: 16px;
                        font-weight: 600;
                        cursor: pointer;
                        transition: all 0.2s ease;
                        box-shadow: 0 4px 12px rgba(241, 97, 54, 0.3);
                    }
                    .btn-ok:hover {
                        transform: translateY(-2px);
                        box-shadow: 0 6px 20px rgba(241, 97, 54, 0.4);
                    }
                    .btn-ok:active {
                        transform: translateY(0);
                    }
                </style>
            </head>
            <body>
                <div class='success-modal'>
                    <div class='success-icon'>
                        <div class='checkmark'></div>
                    </div>
                    <h2 class='success-title'>Candidate Added!</h2>
                    <p class='success-name'>{$formData['first_name']} {$formData['last_name']}</p>
                    <p class='success-message'>The candidate has been successfully added to your database.</p>
                    <button class='btn-ok' onclick='window.location.href=\"index.php\"'>
                        OK, Got it!
                    </button>
                </div>
            </body>
            </html>";
            exit();
            
        } catch (PDOException $e) {
            $errors['general'] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Get users for assignment
try {
    $usersStmt = $db->query("SELECT user_id, full_name FROM users WHERE status = 'active' ORDER BY full_name");
    $users = $usersStmt->fetchAll();
} catch (PDOException $e) {
    $users = [];
}
?>

<!-- ============================================================
     [HTML-FORM] - Add Candidate Form
     ============================================================ -->

<div class="row">
    <div class="col-lg-9">
        <div class="card">
            <div class="card-header">
                <h5>Add New Candidate</h5>
            </div>
            <div class="card-body">
                
                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-danger">
                        <?php echo $errors['general']; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" enctype="multipart/form-data">
                    
                    <h6 class="mb-3">Basic Information *</h6>
                    <p class="text-secondary small mb-3">All fields in this section are mandatory</p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">First Name *</label>
                                <input type="text" name="first_name" 
                                       class="form-control <?php echo isset($errors['first_name']) ? 'is-invalid' : ''; ?>"
                                       value="<?php echo htmlspecialchars($formData['first_name'] ?? ''); ?>" required>
                                <?php if (isset($errors['first_name'])): ?>
                                    <div class="invalid-feedback d-block"><?php echo $errors['first_name']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Last Name *</label>
                                <input type="text" name="last_name" 
                                       class="form-control <?php echo isset($errors['last_name']) ? 'is-invalid' : ''; ?>"
                                       value="<?php echo htmlspecialchars($formData['last_name'] ?? ''); ?>" required>
                                <?php if (isset($errors['last_name'])): ?>
                                    <div class="invalid-feedback d-block"><?php echo $errors['last_name']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" name="email" 
                                       class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>"
                                       value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>" required>
                                <?php if (isset($errors['email'])): ?>
                                    <div class="invalid-feedback d-block"><?php echo $errors['email']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Phone *</label>
                                <input type="tel" name="phone" 
                                       class="form-control <?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>"
                                       value="<?php echo htmlspecialchars($formData['phone'] ?? ''); ?>"
                                       placeholder="10-digit mobile number" required>
                                <?php if (isset($errors['phone'])): ?>
                                    <div class="invalid-feedback d-block"><?php echo $errors['phone']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Current Location *</label>
                        <select name="current_location" 
                                class="form-control <?php echo isset($errors['current_location']) ? 'is-invalid' : ''; ?>"
                                required>
                            <option value="">-- Select City --</option>
                            <?php foreach ($indiaCities as $city): ?>
                                <option value="<?php echo $city; ?>"
                                        <?php echo ($formData['current_location'] ?? '') === $city ? 'selected' : ''; ?>>
                                    <?php echo $city; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['current_location'])): ?>
                            <div class="invalid-feedback d-block"><?php echo $errors['current_location']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <hr class="my-4">
                    <h6 class="mb-3">Professional Details (Optional)</h6>
                    
                    <!-- Skills Multi-Select -->
                    <div class="mb-3">
                        <label class="form-label">Skills</label>
                        <select name="skills[]" 
                                class="form-control" 
                                multiple 
                                size="8"
                                style="height: 200px;">
                            <?php 
                            $selectedSkills = isset($formData['skills']) ? explode(', ', $formData['skills']) : [];
                            foreach ($standardizedSkills as $skill): 
                            ?>
                                <option value="<?php echo htmlspecialchars($skill); ?>"
                                        <?php echo in_array($skill, $selectedSkills) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($skill); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-tertiary">Hold Ctrl/Cmd to select multiple skills</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Experience (Years)</label>
                                <input type="number" name="experience_years" 
                                       class="form-control"
                                       value="<?php echo htmlspecialchars($formData['experience_years'] ?? ''); ?>"
                                       min="0" max="50" step="0.5">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Notice Period</label>
                                <select name="notice_period" class="form-control">
                                    <option value="">-- Select --</option>
                                    <option value="Immediate">Immediate</option>
                                    <option value="15 days">15 days</option>
                                    <option value="1 month">1 month</option>
                                    <option value="2 months">2 months</option>
                                    <option value="3 months">3 months</option>
                                    <option value="Serving notice">Serving notice</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Current Designation</label>
                                <input type="text" name="current_designation" 
                                       class="form-control"
                                       value="<?php echo htmlspecialchars($formData['current_designation'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Current Company</label>
                                <input type="text" name="current_company" 
                                       class="form-control"
                                       value="<?php echo htmlspecialchars($formData['current_company'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Education</label>
                        <input type="text" name="education" 
                               class="form-control"
                               value="<?php echo htmlspecialchars($formData['education'] ?? ''); ?>"
                               placeholder="e.g., MBA, B.Tech Computer Science">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Expected Salary (INR per annum)</label>
                        <input type="number" name="expected_salary" 
                               class="form-control"
                               value="<?php echo htmlspecialchars($formData['expected_salary'] ?? ''); ?>"
                               min="0" step="1000">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Resume / CV (Optional)</label>
                        <input type="file" name="resume" 
                               class="form-control <?php echo isset($errors['resume']) ? 'is-invalid' : ''; ?>"
                               accept=".pdf,.doc,.docx">
                        <small class="text-tertiary">PDF, DOC, or DOCX. Max 10MB</small>
                        <?php if (isset($errors['resume'])): ?>
                            <div class="invalid-feedback d-block"><?php echo $errors['resume']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">LinkedIn Profile</label>
                        <input type="url" name="linkedin_url" 
                               class="form-control"
                               value="<?php echo htmlspecialchars($formData['linkedin_url'] ?? ''); ?>"
                               placeholder="https://linkedin.com/in/username">
                    </div>
                    
                    <hr class="my-4">
                    <h6 class="mb-3">Assignment & Source</h6>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Assign To</label>
                                <select name="assigned_to" class="form-control">
                                    <option value="">Unassigned</option>
                                    <option value="self">Self</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['user_id']; ?>">
                                            <?php echo htmlspecialchars($user['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Source</label>
                                <select name="source" class="form-control">
                                    <option value="Manual Entry">Manual Entry</option>
                                    <option value="LinkedIn">LinkedIn</option>
                                    <option value="Naukri">Naukri</option>
                                    <option value="Referral">Referral</option>
                                    <option value="Public Apply Link">Public Apply Link</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3"
                                  placeholder="Any additional notes about this candidate"><?php echo htmlspecialchars($formData['notes'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Add Candidate
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                    
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-info-circle"></i> Tips</h5>
            </div>
            <div class="card-body">
                <p class="small mb-2"><strong>Mandatory Fields:</strong></p>
                <ul class="small text-secondary mb-3">
                    <li>First & Last Name</li>
                    <li>Email</li>
                    <li>Phone (10 digits)</li>
                    <li>Current Location</li>
                </ul>
                
                <p class="small mb-2"><strong>Duplicate Check:</strong></p>
                <p class="small text-secondary mb-3">
                    System checks for existing candidates by email and phone to prevent duplicates.
                </p>
                
                <p class="small mb-2"><strong>Skills:</strong></p>
                <p class="small text-secondary mb-0">
                    Select multiple skills from the standardized list. This helps in matching candidates to jobs.
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
