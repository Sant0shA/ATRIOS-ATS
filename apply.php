<?php
ob_start();
session_start();
/* ============================================================
   FILE: apply.php
   PURPOSE: Public job application form for external candidates
   ACCESS: Public (no login required)
   
   SECTIONS:
   - [TOKEN-VALIDATION] Verify apply link token
   - [FETCH-JOB] Get job details
   - [FORM-PROCESSING] Handle application submission
   - [FILE-UPLOAD] Handle resume upload
   - [CANDIDATE-CREATION] Create candidate if not exists
   - [APPLICATION-CREATION] Create application record
   - [HTML-FORM] Display public application form
   
   LAST MODIFIED: 2026-02-20
   ============================================================ */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';

$database = new Database();
$db = $database->getConnection();

$errors = [];
$formData = [];
$success = false;

/* ============================================================
   [TOKEN-VALIDATION] - Verify Apply Link Token
   ============================================================ */
$token = $_GET['token'] ?? '';

if (empty($token)) {
    die('<h3>Invalid Application Link</h3><p>No token provided in URL.</p>');
}

/* ============================================================
   [FETCH-JOB] - Get Job Details by Token
   ============================================================ */
try {
    $stmt = $db->prepare("
        SELECT j.*, c.company_name 
        FROM jobs j
        LEFT JOIN clients c ON j.client_id = c.client_id
        WHERE j.apply_link_token = ? AND j.status = 'active'
    ");
    $stmt->execute([$token]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$job) {
        die('<h3>Job Not Available</h3><p>This job posting is no longer accepting applications or does not exist.</p><p><small>Token: ' . htmlspecialchars($token) . '</small></p>');
    }
} catch (PDOException $e) {
    die('<h3>Database Error</h3><p>' . htmlspecialchars($e->getMessage()) . '</p>');
}

// India cities list
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
    
    // Get form data
    $formData = [
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name' => trim($_POST['last_name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'current_location' => $_POST['current_location'] ?? '',
        'linkedin_url' => trim($_POST['linkedin_url'] ?? ''),
        'screening_answer_1' => trim($_POST['screening_answer_1'] ?? ''),
        'screening_answer_2' => trim($_POST['screening_answer_2'] ?? ''),
        'cover_note' => trim($_POST['cover_note'] ?? '')
    ];
    
    /* ============================================================
       [VALIDATION] - Validate Input
       ============================================================ */
    
    // Name validation
    if (empty($formData['first_name'])) {
        $errors['first_name'] = 'First name is required';
    }
    
    if (empty($formData['last_name'])) {
        $errors['last_name'] = 'Last name is required';
    }
    
    // Email validation
    if (empty($formData['email'])) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    }
    
    // Phone validation
    if (empty($formData['phone'])) {
        $errors['phone'] = 'Phone number is required';
    } else {
        $phone = preg_replace('/[^0-9]/', '', $formData['phone']);
        if (strlen($phone) != 10) {
            $errors['phone'] = 'Phone number must be 10 digits';
        }
    }
    
    // Location validation
    if (empty($formData['current_location'])) {
        $errors['current_location'] = 'Current location is required';
    }
    
    // Resume validation
    if (!isset($_FILES['resume']) || $_FILES['resume']['error'] !== UPLOAD_ERR_OK) {
        $errors['resume'] = 'Resume is required';
    }
    
    // Screening answers validation
    if (empty($formData['screening_answer_1'])) {
        $errors['screening_answer_1'] = 'This question is required';
    } elseif (strlen($formData['screening_answer_1']) < 2) {
        $errors['screening_answer_1'] = 'Please provide at least 2 characters';
    }
    
    if (empty($formData['screening_answer_2'])) {
        $errors['screening_answer_2'] = 'This question is required';
    } elseif (strlen($formData['screening_answer_2']) < 2) {
        $errors['screening_answer_2'] = 'Please provide at least 2 characters';
    }
    
    // LinkedIn URL validation (optional but validated if provided)
    if (!empty($formData['linkedin_url'])) {
        if (!filter_var($formData['linkedin_url'], FILTER_VALIDATE_URL)) {
            $errors['linkedin_url'] = 'Invalid LinkedIn URL';
        }
    }
    
    /* ============================================================
       [FILE-UPLOAD] - Handle Resume Upload
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
       [DATABASE-INSERT] - Create Candidate and Application
       ============================================================ */
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Check if candidate already exists by email
            $stmt = $db->prepare("SELECT candidate_id FROM candidates WHERE email = ?");
            $stmt->execute([$formData['email']]);
            $existingCandidate = $stmt->fetch();
            
            if ($existingCandidate) {
                $candidateId = $existingCandidate['candidate_id'];
                
                // Update resume if new one uploaded
                if ($resumePath) {
                    $stmt = $db->prepare("UPDATE candidates SET cv_path = ?, updated_at = NOW() WHERE candidate_id = ?");
                    $stmt->execute([$resumePath, $candidateId]);
                }
            } else {
                // Create new candidate (no added_by since it's public application)
                $stmt = $db->prepare("
                    INSERT INTO candidates (
                        first_name, last_name, email, phone, current_location,
                        cv_path, linkedin_url, source, status, created_at
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'Public Apply Link', 'active', NOW())
                ");
                
                $stmt->execute([
                    $formData['first_name'],
                    $formData['last_name'],
                    $formData['email'],
                    $formData['phone'],
                    $formData['current_location'],
                    $resumePath,
                    $formData['linkedin_url']
                ]);
                
                $candidateId = $db->lastInsertId();
            }
            
            // Check if already applied to this job
            $stmt = $db->prepare("SELECT application_id FROM applications WHERE job_id = ? AND candidate_id = ?");
            $stmt->execute([$job['job_id'], $candidateId]);
            
            if ($stmt->fetch()) {
                $errors['general'] = 'You have already applied to this position';
                $db->rollBack();
            } else {
                // Create application
                $stmt = $db->prepare("
                    INSERT INTO applications (
                        job_id, candidate_id, status, applied_at,
                        screening_answer_1, screening_answer_2,
                        current_location, screening_notes
                    )
                    VALUES (?, ?, 'new', NOW(), ?, ?, ?, ?)
                ");
                
                $coverNote = !empty($formData['cover_note']) ? "Cover Note:\n" . $formData['cover_note'] : '';
                
                $stmt->execute([
                    $job['job_id'],
                    $candidateId,
                    $formData['screening_answer_1'],
                    $formData['screening_answer_2'],
                    $formData['current_location'],
                    $coverNote
                ]);
                
                $db->commit();
                $success = true;
            }
            
        } catch (PDOException $e) {
            $db->rollBack();
            $errors['general'] = 'Application submission failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply - <?php echo htmlspecialchars($job['job_title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
</head>
<body style="background: #f9fafb;">
    
    <div class="container" style="max-width: 800px; padding: 40px 20px;">
        
        <!-- Atrios Branding Header -->
        <div class="text-center mb-4">
            <div style="display: inline-flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #F16136 0%, #FF8A65 100%); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-briefcase" style="color: white; font-size: 20px;"></i>
                </div>
                <h3 style="margin: 0; color: #1a1a1a; font-weight: 600;">Atrios ATS</h3>
            </div>
            <p style="color: #6b7280; margin: 0; font-size: 14px;">Recruitment Management System</p>
        </div>
        
        <?php if ($success): ?>
            <!-- Success Message -->
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-check-circle text-success" style="font-size: 64px;"></i>
                    <h2 class="mt-4">Application Submitted!</h2>
                    <p class="lead text-secondary">Thank you for applying to this position.</p>
                    <p>We have received your application and will review it shortly. If your profile matches our requirements, we'll contact you at <strong><?php echo htmlspecialchars($formData['email']); ?></strong></p>
                    <hr class="my-4">
                    <p class="text-secondary mb-0">You can close this window now.</p>
                </div>
            </div>
        <?php else: ?>
            <!-- Application Form -->
            <div class="card">
                <div class="card-body p-4">
                    
                    <!-- Job Header -->
                    <div class="mb-4">
                        <h3><?php echo htmlspecialchars($job['job_title']); ?></h3>
                        <p class="text-secondary mb-2"><?php echo htmlspecialchars($job['company_name']); ?></p>
                        <?php if ($job['location']): ?>
                            <p class="mb-0">
                                <i class="fas fa-map-marker-alt text-secondary"></i>
                                <?php echo htmlspecialchars($job['location']); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <hr>
                    
                    <?php if (!empty($errors['general'])): ?>
                        <div class="alert alert-danger">
                            <?php echo htmlspecialchars($errors['general']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" enctype="multipart/form-data">
                        
                        <h5 class="mb-3">Personal Information</h5>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">First Name *</label>
                                    <input type="text" 
                                           name="first_name" 
                                           class="form-control <?php echo isset($errors['first_name']) ? 'is-invalid' : ''; ?>"
                                           value="<?php echo htmlspecialchars($formData['first_name'] ?? ''); ?>"
                                           required>
                                    <?php if (isset($errors['first_name'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['first_name']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Last Name *</label>
                                    <input type="text" 
                                           name="last_name" 
                                           class="form-control <?php echo isset($errors['last_name']) ? 'is-invalid' : ''; ?>"
                                           value="<?php echo htmlspecialchars($formData['last_name'] ?? ''); ?>"
                                           required>
                                    <?php if (isset($errors['last_name'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['last_name']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email *</label>
                                    <input type="email" 
                                           name="email" 
                                           class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>"
                                           value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>"
                                           required>
                                    <?php if (isset($errors['email'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Phone *</label>
                                    <input type="tel" 
                                           name="phone" 
                                           class="form-control <?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>"
                                           value="<?php echo htmlspecialchars($formData['phone'] ?? ''); ?>"
                                           placeholder="10-digit mobile number"
                                           required>
                                    <?php if (isset($errors['phone'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['phone']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
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
                                <div class="invalid-feedback"><?php echo $errors['current_location']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <h5 class="mb-3 mt-4">Professional Details</h5>
                        
                        <div class="mb-3">
                            <label class="form-label">Resume * (PDF, DOC, or DOCX)</label>
                            <input type="file" 
                                   name="resume" 
                                   class="form-control <?php echo isset($errors['resume']) ? 'is-invalid' : ''; ?>"
                                   accept=".pdf,.doc,.docx"
                                   required>
                            <small class="text-tertiary">Max 10MB</small>
                            <?php if (isset($errors['resume'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['resume']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">LinkedIn Profile (Optional)</label>
                            <input type="url" 
                                   name="linkedin_url" 
                                   class="form-control <?php echo isset($errors['linkedin_url']) ? 'is-invalid' : ''; ?>"
                                   value="<?php echo htmlspecialchars($formData['linkedin_url'] ?? ''); ?>"
                                   placeholder="https://linkedin.com/in/yourname">
                            <?php if (isset($errors['linkedin_url'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['linkedin_url']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <h5 class="mb-3 mt-4">Screening Questions</h5>
                        
                        <div class="mb-3">
                            <label class="form-label">1. <?php echo htmlspecialchars($job['screening_question_1']); ?> *</label>
                            <textarea name="screening_answer_1" 
                                      class="form-control <?php echo isset($errors['screening_answer_1']) ? 'is-invalid' : ''; ?>"
                                      rows="3"
                                      required><?php echo htmlspecialchars($formData['screening_answer_1'] ?? ''); ?></textarea>
                            <small class="text-tertiary">Minimum 20 characters</small>
                            <?php if (isset($errors['screening_answer_1'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['screening_answer_1']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">2. <?php echo htmlspecialchars($job['screening_question_2']); ?> *</label>
                            <textarea name="screening_answer_2" 
                                      class="form-control <?php echo isset($errors['screening_answer_2']) ? 'is-invalid' : ''; ?>"
                                      rows="3"
                                      required><?php echo htmlspecialchars($formData['screening_answer_2'] ?? ''); ?></textarea>
                            <small class="text-tertiary">Minimum 20 characters</small>
                            <?php if (isset($errors['screening_answer_2'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['screening_answer_2']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <h5 class="mb-3 mt-4">Additional Information</h5>
                        
                        <div class="mb-4">
                            <label class="form-label">Cover Note (Optional)</label>
                            <textarea name="cover_note" 
                                      class="form-control"
                                      rows="4"
                                      placeholder="Why are you interested in this position?"><?php echo htmlspecialchars($formData['cover_note'] ?? ''); ?></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="fas fa-paper-plane"></i> Submit Application
                        </button>
                        
                    </form>
                </div>
            </div>
        <?php endif; ?>
        
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
