<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'u248088683_atrios_ats');
define('DB_USER', 'u248088683_ats_admin');
define('DB_PASS', 'Terminate@123!');

// Site Configuration
define('SITE_URL', 'https://atrios.in/recruitment-ats');
define('SITE_NAME', 'Atrios ATS');

// File Upload Configuration
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('CV_UPLOAD_DIR', __DIR__ . '/uploads/cvs/');
define('LOGO_UPLOAD_DIR', __DIR__ . '/uploads/logos/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB

// Allowed file types
define('ALLOWED_CV_TYPES', ['pdf', 'doc', 'docx']);
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'svg', 'webp']);

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Error Reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>