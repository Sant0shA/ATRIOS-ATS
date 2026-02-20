<?php
ob_start();
/* ============================================================
   FILE: applications/accept.php
   PURPOSE: Accept application and convert to candidate (opens in NEW TAB)
   ACCESS: Admin, Manager, assigned recruiters
   
   LAST MODIFIED: 2026-02-20
   ============================================================ */

session_start();
require_once '../config.php';
require_once '../includes/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$database = new Database();
$db = $database->getConnection();

$errors = [];
$application = null;

// Standardized Skills List (same as candidates/add.php)
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

/* ============================================================
   [FETCH-APPLICATION] - Get Application Data
   ============================================================ */
$applicationId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($applicationId <= 0) {
    die('Invalid application ID');
}

try {
    $stmt = $db->prepare("
        SELECT a.*, c.first_name, c.last_name, c.email, c.phone, c.current_location, c.cv_path
        FROM applications a
        LEFT JOIN candidates c ON a.candidate_id = c.candidate_id
        WHERE a.application_id = ?
    ");
    $stmt->execute([$applicationId]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$application) {
        die('Application not found');
    }
} catch (PDOException $e) {
    die('Database error');
}

/* ============================================================
   [FORM-PROCESSING] - Handle Form Submission
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $formData = [
        'skills' => isset($_POST['skills']) ? implode(', ', $_POST['skills']) : '',
        'experience_years' => floatval($_POST['experience_years'] ?? 0),
        'education' => trim($_POST['education'] ?? ''),
        'current_company' => trim($_POST['current_company'] ?? ''),
        'current_designation' => trim($_POST['current_designation'] ?? ''),
        'expected_salary' => floatval($_POST['expected_salary'] ?? 0),
        'notice_period' => trim($_POST['notice_period'] ?? ''),
        'linkedin_url' => trim($_POST['linkedin_url'] ?? ''),
        'notes' => trim($_POST['notes'] ?? '')
    ];
    
    try {
        $db->beginTransaction();
        
        // Check if candidate already exists
        $stmt = $db->prepare("SELECT candidate_id FROM candidates WHERE email = ? OR phone = ?");
        $stmt->execute([$application['email'], $application['phone']]);
        $existingCandidate = $stmt->fetch();
        
        if ($existingCandidate) {
            // Update existing candidate
            $candidateId = $existingCandidate['candidate_id'];
            
            $stmt = $db->prepare("
                UPDATE candidates 
                SET skills = ?, experience_years = ?, education = ?,
                    current_company = ?, current_designation = ?, expected_salary = ?,
                    notice_period = ?, linkedin_url = ?, notes = ?,
                    assigned_to = ?, updated_at = NOW()
                WHERE candidate_id = ?
            ");
            
            $stmt->execute([
                $formData['skills'],
                $formData['experience_years'],
                $formData['education'],
                $formData['current_company'],
                $formData['current_designation'],
                $formData['expected_salary'],
                $formData['notice_period'],
                $formData['linkedin_url'],
                $formData['notes'],
                $_SESSION['user_id'], // Assign to current user
                $candidateId
            ]);
            
            $action = 'updated';
            
        } else {
            // Create new candidate (basic info already exists from application)
            $candidateId = $application['candidate_id'];
            
            $stmt = $db->prepare("
                UPDATE candidates 
                SET skills = ?, experience_years = ?, education = ?,
                    current_company = ?, current_designation = ?, expected_salary = ?,
                    notice_period = ?, linkedin_url = ?, notes = ?,
                    assigned_to = ?, updated_at = NOW()
                WHERE candidate_id = ?
            ");
            
            $stmt->execute([
                $formData['skills'],
                $formData['experience_years'],
                $formData['education'],
                $formData['current_company'],
                $formData['current_designation'],
                $formData['expected_salary'],
                $formData['notice_period'],
                $formData['linkedin_url'],
                $formData['notes'],
                $_SESSION['user_id'], // Assign to current user
                $candidateId
            ]);
            
            $action = 'enhanced';
        }
        
        // Update application status to shortlisted
        $stmt = $db->prepare("UPDATE applications SET status = 'shortlisted' WHERE application_id = ?");
        $stmt->execute([$applicationId]);
        
        // Log activity
        logActivity($db, $_SESSION['user_id'], 'accept', 'application', $applicationId, 
                   "Accepted application and {$action} candidate profile");
        
        $db->commit();
        
        // Show success popup with close tab button
        echo "<!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
            <link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap' rel='stylesheet'>
            <style>
                body { 
                    font-family: 'Inter', sans-serif;
                    margin: 0; padding: 0;
                    display: flex; align-items: center; justify-content: center;
                    min-height: 100vh;
                    background: rgba(0, 0, 0, 0.15);
                }
                .success-modal {
                    background: white; border-radius: 16px;
                    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                    max-width: 420px; width: 90%;
                    padding: 48px 40px 40px; text-align: center;
                }
                .success-icon {
                    width: 80px; height: 80px;
                    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                    border-radius: 50%;
                    display: flex; align-items: center; justify-content: center;
                    margin: 0 auto 24px;
                }
                .checkmark {
                    width: 32px; height: 18px;
                    border-left: 4px solid white;
                    border-bottom: 4px solid white;
                    transform: rotate(-45deg);
                }
                .success-title {
                    font-size: 28px; font-weight: 600;
                    color: #1a1a1a; margin: 0 0 12px;
                }
                .success-name {
                    font-size: 20px; color: #10b981;
                    font-weight: 500; margin: 0 0 8px;
                }
                .success-message {
                    font-size: 15px; color: #6b7280;
                    margin: 0 0 32px;
                }
                .btn-ok {
                    background: linear-gradient(135deg, #F16136 0%, #FF8A65 100%);
                    color: white; border: none;
                    padding: 14px 48px; border-radius: 10px;
                    font-size: 16px; font-weight: 600;
                    cursor: pointer; transition: all 0.2s ease;
                    box-shadow: 0 4px 12px rgba(241, 97, 54, 0.3);
                }
                .btn-ok:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 6px 20px rgba(241, 97, 54, 0.4);
                }
            </style>
        </head>
        <body>
            <div class='success-modal'>
                <div class='success-icon'>
                    <div class='checkmark'></div>
                </div>
                <h2 class='success-title'>Application Accepted!</h2>
                <p class='success-name'>{$application['first_name']} {$application['last_name']}</p>
                <p class='success-message'>Candidate profile has been enhanced and assigned to you.</p>
                <button class='btn-ok' onclick='window.close()'>
                    Close This Tab
                </button>
            </div>
        </body>
        </html>";
        exit();
        
    } catch (PDOException $e) {
        $db->rollBack();
        $errors['general'] = 'Error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accept Application</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="<?php echo SITE_URL; ?>/assets/css/style.css" rel="stylesheet">
</head>
<body style="background: #f9fafb; padding: 40px 20px;">

<div class="container" style="max-width: 900px;">
    
    <div class="card">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">
                <i class="fas fa-check-circle"></i> Accept Application & Enhance Candidate Profile
            </h5>
        </div>
        <div class="card-body">
            
            <!-- Application Info -->
            <div class="alert alert-info">
                <strong>Candidate:</strong> <?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?><br>
                <strong>Email:</strong> <?php echo htmlspecialchars($application['email']); ?><br>
                <strong>Phone:</strong> <?php echo htmlspecialchars($application['phone']); ?><br>
                <strong>Location:</strong> <?php echo htmlspecialchars($application['current_location']); ?>
            </div>
            
            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-danger"><?php echo $errors['general']; ?></div>
            <?php endif; ?>
            
            <p class="text-secondary mb-4">
                <i class="fas fa-info-circle"></i>
                Basic information has been captured from the application. Please add additional details to complete the candidate profile.
            </p>
            
            <form method="POST" action="">
                
                <h6 class="mb-3">Professional Details</h6>
                
                <!-- Skills -->
                <div class="mb-3">
                    <label class="form-label">Skills</label>
                    <select name="skills[]" class="form-control" multiple size="8" style="height: 200px;">
                        <?php foreach ($standardizedSkills as $skill): ?>
                            <option value="<?php echo htmlspecialchars($skill); ?>">
                                <?php echo htmlspecialchars($skill); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-tertiary">Hold Ctrl/Cmd to select multiple</small>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Experience (Years)</label>
                            <input type="number" name="experience_years" class="form-control"
                                   min="0" max="50" step="0.5" placeholder="e.g., 5">
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
                            <input type="text" name="current_designation" class="form-control"
                                   placeholder="e.g., Senior Developer">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Current Company</label>
                            <input type="text" name="current_company" class="form-control"
                                   placeholder="e.g., Tech Corp">
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Education</label>
                    <input type="text" name="education" class="form-control"
                           placeholder="e.g., B.Tech Computer Science">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Expected Salary (INR per annum)</label>
                    <input type="number" name="expected_salary" class="form-control"
                           min="0" step="1000" placeholder="e.g., 800000">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">LinkedIn Profile</label>
                    <input type="url" name="linkedin_url" class="form-control"
                           placeholder="https://linkedin.com/in/username">
                </div>
                
                <div class="mb-4">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="3"
                              placeholder="Any additional notes about this candidate"></textarea>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="fas fa-check"></i> Accept & Save
                    </button>
                    <button type="button" class="btn btn-secondary btn-lg" onclick="window.close()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
                
            </form>
            
        </div>
    </div>
    
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
