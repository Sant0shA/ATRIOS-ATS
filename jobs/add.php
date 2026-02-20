<?php
ob_start();
/* ============================================================
   FILE: jobs/add.php
   PURPOSE: Create new job posting with screening questions
   ACCESS: Admin, Manager only
   
   SECTIONS:
   - [AUTH-CHECK] Verify admin/manager access
   - [FORM-PROCESSING] Handle form submission
   - [VALIDATION] Validate job data
   - [TOKEN-GENERATION] Generate unique apply link token
   - [DATABASE-INSERT] Create new job
   - [HTML-FORM] Display add job form with multi-select locations
   
   LAST MODIFIED: 2026-02-20
   ============================================================ */

$pageTitle = 'Post New Job';
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
        'client_id' => intval($_POST['client_id'] ?? 0),
        'job_title' => trim($_POST['job_title'] ?? ''),
        'job_description' => trim($_POST['job_description'] ?? ''),
        'requirements' => trim($_POST['requirements'] ?? ''),
        'location' => isset($_POST['location']) ? implode(', ', $_POST['location']) : '',
        'employment_type' => $_POST['employment_type'] ?? 'full-time',
        'experience_min' => intval($_POST['experience_min'] ?? 0),
        'experience_max' => intval($_POST['experience_max'] ?? 0),
        'salary_min' => floatval($_POST['salary_min'] ?? 0),
        'salary_max' => floatval($_POST['salary_max'] ?? 0),
        'salary_currency' => 'INR',
        'skills_required' => trim($_POST['skills_required'] ?? ''),
        'education_required' => trim($_POST['education_required'] ?? ''),
        'positions_available' => intval($_POST['positions_available'] ?? 1),
        'priority' => $_POST['priority'] ?? 'medium',
        'deadline' => $_POST['deadline'] ?? null,
        'screening_question_1' => trim($_POST['screening_question_1'] ?? ''),
        'screening_question_2' => trim($_POST['screening_question_2'] ?? ''),
        'assigned_to' => isset($_POST['assigned_to']) ? json_encode($_POST['assigned_to']) : '[]',
        'status' => $_POST['status'] ?? 'draft'
    ];
    
    /* ============================================================
       [VALIDATION] - Validate Input
       ============================================================ */
    
    // Client validation
    if ($formData['client_id'] <= 0) {
        $errors['client_id'] = 'Please select a client';
    }
    
    // Job title validation
    if (empty($formData['job_title'])) {
        $errors['job_title'] = 'Job title is required';
    }
    
    // Location validation
    if (empty($formData['location'])) {
        $errors['location'] = 'Please select at least one location';
    }
    
    // Screening questions validation
    if (empty($formData['screening_question_1'])) {
        $errors['screening_question_1'] = 'Screening Question 1 is required';
    }
    
    if (empty($formData['screening_question_2'])) {
        $errors['screening_question_2'] = 'Screening Question 2 is required';
    }
    
    // Experience validation
    if ($formData['experience_max'] > 0 && $formData['experience_max'] < $formData['experience_min']) {
        $errors['experience_max'] = 'Maximum experience must be greater than minimum';
    }
    
    // Salary validation
    if ($formData['salary_max'] > 0 && $formData['salary_max'] < $formData['salary_min']) {
        $errors['salary_max'] = 'Maximum salary must be greater than minimum';
    }
    
    /* ============================================================
       [DATABASE-INSERT] - Create Job if No Errors
       ============================================================ */
    if (empty($errors)) {
        try {
            // Generate unique apply link token
            $applyLinkToken = bin2hex(random_bytes(16));
            
            $stmt = $db->prepare("
                INSERT INTO jobs (
                    client_id, job_title, job_description, requirements, location,
                    employment_type, experience_min, experience_max,
                    salary_min, salary_max, salary_currency,
                    skills_required, education_required, positions_available,
                    status, priority, deadline,
                    screening_question_1, screening_question_2,
                    apply_link_token, assigned_to, created_by, created_at
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $formData['client_id'],
                $formData['job_title'],
                $formData['job_description'],
                $formData['requirements'],
                $formData['location'],
                $formData['employment_type'],
                $formData['experience_min'],
                $formData['experience_max'],
                $formData['salary_min'],
                $formData['salary_max'],
                $formData['salary_currency'],
                $formData['skills_required'],
                $formData['education_required'],
                $formData['positions_available'],
                $formData['status'],
                $formData['priority'],
                $formData['deadline'],
                $formData['screening_question_1'],
                $formData['screening_question_2'],
                $applyLinkToken,
                $formData['assigned_to'],
                $_SESSION['user_id']
            ]);
            
            $newJobId = $db->lastInsertId();
            
            // Log activity
            logActivity($db, $_SESSION['user_id'], 'create', 'job', $newJobId, 
                       "Created new job: {$formData['job_title']}");
            
            setFlashMessage('success', "Job '{$formData['job_title']}' posted successfully!");
            header('Location: view.php?id=' . $newJobId);
            exit();
            
        } catch (PDOException $e) {
            $errors['general'] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Get active clients for dropdown
try {
    $clientsStmt = $db->query("SELECT client_id, company_name FROM clients WHERE status = 'active' ORDER BY company_name");
    $clients = $clientsStmt->fetchAll();
} catch (PDOException $e) {
    $clients = [];
}

// Get active users for assignment
try {
    $usersStmt = $db->query("SELECT user_id, full_name, role FROM users WHERE status = 'active' ORDER BY full_name");
    $users = $usersStmt->fetchAll();
} catch (PDOException $e) {
    $users = [];
}

// Pre-select client if passed in URL
$preselectedClient = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;

// Location options (Remote/Hybrid first, then major cities)
$locationOptions = [
    'Remote',
    'Hybrid',
    '---', // Separator
    'Mumbai',
    'Delhi',
    'Bangalore',
    'Hyderabad',
    'Chennai',
    'Kolkata',
    'Pune',
    'Ahmedabad',
    'Gurgaon',
    'Noida',
    'Jaipur',
    'Chandigarh',
    'Kochi',
    'Indore',
    'Coimbatore'
];
?>

<!-- ============================================================
     [HTML-FORM] - Add Job Form
     ============================================================ -->

<div class="row">
    <div class="col-lg-9">
        <div class="card">
            <div class="card-header">
                <h5>Post New Job</h5>
            </div>
            <div class="card-body">
                
                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($errors['general']); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    
                    <!-- Client -->
                    <div class="mb-3">
                        <label class="form-label">Client *</label>
                        <select name="client_id" 
                                class="form-control <?php echo isset($errors['client_id']) ? 'is-invalid' : ''; ?>"
                                required>
                            <option value="">-- Select Client --</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?php echo $client['client_id']; ?>"
                                        <?php echo ($formData['client_id'] ?? $preselectedClient) == $client['client_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($client['company_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['client_id'])): ?>
                            <div class="invalid-feedback d-block"><?php echo $errors['client_id']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Job Title -->
                    <div class="mb-3">
                        <label class="form-label">Job Title *</label>
                        <input type="text" 
                               name="job_title" 
                               class="form-control <?php echo isset($errors['job_title']) ? 'is-invalid' : ''; ?>"
                               value="<?php echo htmlspecialchars($formData['job_title'] ?? ''); ?>"
                               placeholder="e.g., Senior React Developer"
                               required>
                        <?php if (isset($errors['job_title'])): ?>
                            <div class="invalid-feedback d-block"><?php echo $errors['job_title']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Location (Multi-select with Remote/Hybrid first) -->
                    <div class="mb-3">
                        <label class="form-label">Location *</label>
                        <div class="border rounded p-3" style="max-height: 200px; overflow-y: auto;">
                            <?php 
                            $selectedLocations = isset($formData['location']) ? explode(', ', $formData['location']) : [];
                            $isFirst = true;
                            foreach ($locationOptions as $location): 
                                if ($location === '---'): 
                            ?>
                                    <hr class="my-2">
                            <?php 
                                    continue;
                                endif;
                                $isChecked = in_array($location, $selectedLocations);
                                $inputId = 'loc_' . str_replace(' ', '_', strtolower($location));
                            ?>
                                <div class="form-check">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           name="location[]" 
                                           value="<?php echo htmlspecialchars($location); ?>"
                                           id="<?php echo $inputId; ?>"
                                           <?php echo $isChecked ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="<?php echo $inputId; ?>">
                                        <?php echo htmlspecialchars($location); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <small class="text-tertiary">Select one or more locations. Remote/Hybrid options at top.</small>
                        <?php if (isset($errors['location'])): ?>
                            <div class="invalid-feedback d-block"><?php echo $errors['location']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <!-- Employment Type -->
                            <div class="mb-3">
                                <label class="form-label">Employment Type</label>
                                <select name="employment_type" class="form-control">
                                    <option value="full-time" <?php echo ($formData['employment_type'] ?? 'full-time') === 'full-time' ? 'selected' : ''; ?>>
                                        Full Time
                                    </option>
                                    <option value="part-time" <?php echo ($formData['employment_type'] ?? '') === 'part-time' ? 'selected' : ''; ?>>
                                        Part Time
                                    </option>
                                    <option value="contract" <?php echo ($formData['employment_type'] ?? '') === 'contract' ? 'selected' : ''; ?>>
                                        Contract
                                    </option>
                                    <option value="temporary" <?php echo ($formData['employment_type'] ?? '') === 'temporary' ? 'selected' : ''; ?>>
                                        Temporary
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <!-- Positions Available -->
                            <div class="mb-3">
                                <label class="form-label">Positions Available</label>
                                <input type="number" 
                                       name="positions_available" 
                                       class="form-control"
                                       value="<?php echo htmlspecialchars($formData['positions_available'] ?? '1'); ?>"
                                       min="1">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Experience Range -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Minimum Experience (years)</label>
                                <input type="number" 
                                       name="experience_min" 
                                       class="form-control"
                                       value="<?php echo htmlspecialchars($formData['experience_min'] ?? '0'); ?>"
                                       min="0"
                                       step="0.5">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Maximum Experience (years)</label>
                                <input type="number" 
                                       name="experience_max" 
                                       class="form-control <?php echo isset($errors['experience_max']) ? 'is-invalid' : ''; ?>"
                                       value="<?php echo htmlspecialchars($formData['experience_max'] ?? ''); ?>"
                                       min="0"
                                       step="0.5">
                                <?php if (isset($errors['experience_max'])): ?>
                                    <div class="invalid-feedback d-block"><?php echo $errors['experience_max']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Salary Range -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Minimum Salary (INR per annum)</label>
                                <input type="number" 
                                       name="salary_min" 
                                       class="form-control"
                                       value="<?php echo htmlspecialchars($formData['salary_min'] ?? ''); ?>"
                                       min="0"
                                       step="1000"
                                       placeholder="e.g., 500000">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Maximum Salary (INR per annum)</label>
                                <input type="number" 
                                       name="salary_max" 
                                       class="form-control <?php echo isset($errors['salary_max']) ? 'is-invalid' : ''; ?>"
                                       value="<?php echo htmlspecialchars($formData['salary_max'] ?? ''); ?>"
                                       min="0"
                                       step="1000"
                                       placeholder="e.g., 800000">
                                <?php if (isset($errors['salary_max'])): ?>
                                    <div class="invalid-feedback d-block"><?php echo $errors['salary_max']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Job Description -->
                    <div class="mb-3">
                        <label class="form-label">Job Description</label>
                        <textarea name="job_description" 
                                  class="form-control" 
                                  rows="5"
                                  placeholder="Describe the role, responsibilities, and what the candidate will do..."><?php echo htmlspecialchars($formData['job_description'] ?? ''); ?></textarea>
                    </div>
                    
                    <!-- Requirements -->
                    <div class="mb-3">
                        <label class="form-label">Requirements</label>
                        <textarea name="requirements" 
                                  class="form-control" 
                                  rows="4"
                                  placeholder="List the qualifications, skills, and experience required..."><?php echo htmlspecialchars($formData['requirements'] ?? ''); ?></textarea>
                    </div>
                    
                    <!-- Skills Required -->
                    <div class="mb-3">
                        <label class="form-label">Skills Required</label>
                        <input type="text" 
                               name="skills_required" 
                               class="form-control"
                               value="<?php echo htmlspecialchars($formData['skills_required'] ?? ''); ?>"
                               placeholder="e.g., React, Node.js, MongoDB">
                        <small class="text-tertiary">Comma-separated</small>
                    </div>
                    
                    <!-- Education Required -->
                    <div class="mb-3">
                        <label class="form-label">Education Required</label>
                        <input type="text" 
                               name="education_required" 
                               class="form-control"
                               value="<?php echo htmlspecialchars($formData['education_required'] ?? ''); ?>"
                               placeholder="e.g., Bachelor's in Computer Science or equivalent">
                    </div>
                    
                    <hr class="my-4">
                    <h6 class="mb-3">Screening Questions for Applicants</h6>
                    <p class="text-secondary small">These questions will be asked to all external applicants through the public apply link</p>
                    
                    <!-- Screening Question 1 -->
                    <div class="mb-3">
                        <label class="form-label">Screening Question 1 *</label>
                        <input type="text" 
                               name="screening_question_1" 
                               class="form-control <?php echo isset($errors['screening_question_1']) ? 'is-invalid' : ''; ?>"
                               value="<?php echo htmlspecialchars($formData['screening_question_1'] ?? ''); ?>"
                               placeholder="e.g., Do you have 3+ years of React experience?"
                               required>
                        <?php if (isset($errors['screening_question_1'])): ?>
                            <div class="invalid-feedback d-block"><?php echo $errors['screening_question_1']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Screening Question 2 -->
                    <div class="mb-3">
                        <label class="form-label">Screening Question 2 *</label>
                        <input type="text" 
                               name="screening_question_2" 
                               class="form-control <?php echo isset($errors['screening_question_2']) ? 'is-invalid' : ''; ?>"
                               value="<?php echo htmlspecialchars($formData['screening_question_2'] ?? ''); ?>"
                               placeholder="e.g., Can you join within 30 days?"
                               required>
                        <?php if (isset($errors['screening_question_2'])): ?>
                            <div class="invalid-feedback d-block"><?php echo $errors['screening_question_2']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <hr class="my-4">
                    <h6 class="mb-3">Assignment & Priority</h6>
                    
                    <!-- Assign Recruiters (Multi-select) -->
                    <div class="mb-3">
                        <label class="form-label">Assign Recruiters (Team)</label>
                        <select name="assigned_to[]" 
                                class="form-control" 
                                multiple 
                                size="5">
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['user_id']; ?>">
                                    <?php echo htmlspecialchars($user['full_name']); ?> 
                                    (<?php echo ucfirst($user['role']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-tertiary">Hold Ctrl/Cmd to select multiple recruiters</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <!-- Priority -->
                            <div class="mb-3">
                                <label class="form-label">Priority</label>
                                <select name="priority" class="form-control">
                                    <option value="low" <?php echo ($formData['priority'] ?? 'medium') === 'low' ? 'selected' : ''; ?>>Low</option>
                                    <option value="medium" <?php echo ($formData['priority'] ?? 'medium') === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                    <option value="high" <?php echo ($formData['priority'] ?? '') === 'high' ? 'selected' : ''; ?>>High</option>
                                    <option value="urgent" <?php echo ($formData['priority'] ?? '') === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <!-- Deadline -->
                            <div class="mb-3">
                                <label class="form-label">Application Deadline</label>
                                <input type="date" 
                                       name="deadline" 
                                       class="form-control"
                                       value="<?php echo htmlspecialchars($formData['deadline'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Status -->
                    <div class="mb-4">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="draft" <?php echo ($formData['status'] ?? 'draft') === 'draft' ? 'selected' : ''; ?>>
                                Draft (not visible publicly)
                            </option>
                            <option value="active" <?php echo ($formData['status'] ?? '') === 'active' ? 'selected' : ''; ?>>
                                Active (accepting applications)
                            </option>
                            <option value="on-hold" <?php echo ($formData['status'] ?? '') === 'on-hold' ? 'selected' : ''; ?>>
                                On Hold
                            </option>
                        </select>
                    </div>
                    
                    <!-- Submit Buttons -->
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Post Job
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
                <p class="small mb-2"><strong>Required Fields:</strong></p>
                <ul class="small text-secondary mb-3">
                    <li>Client</li>
                    <li>Job Title</li>
                    <li>Location</li>
                    <li>Both Screening Questions</li>
                </ul>
                
                <p class="small mb-2"><strong>Public Apply Link:</strong></p>
                <p class="small text-secondary mb-3">
                    A unique link will be generated automatically for external candidates to apply.
                </p>
                
                <p class="small mb-2"><strong>Team Assignment:</strong></p>
                <p class="small text-secondary mb-0">
                    Multiple recruiters can work on the same job. Assigned recruiters will see this job in their dashboard.
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
