<?php
// Utility Helper Functions

// Sanitize input data
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Validate email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Validate phone number (basic)
function isValidPhone($phone) {
    return preg_match('/^[0-9+\-\s()]{10,20}$/', $phone);
}

// Format date for display
function formatDate($date, $format = 'd M Y') {
    if (empty($date)) return '-';
    return date($format, strtotime($date));
}

// Format datetime for display
function formatDateTime($datetime, $format = 'd M Y h:i A') {
    if (empty($datetime)) return '-';
    return date($format, strtotime($datetime));
}

// Get time ago string
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    if ($diff < 2592000) return floor($diff / 604800) . ' weeks ago';
    
    return formatDate($datetime);
}

// Handle file upload
function uploadFile($file, $allowedTypes = null, $maxSize = null, $targetDir = null) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'No file uploaded or upload error'];
    }
    
    $allowedTypes = $allowedTypes ?? ALLOWED_EXTENSIONS;
    $maxSize = $maxSize ?? MAX_FILE_SIZE;
    $targetDir = $targetDir ?? UPLOAD_DIR;
    
    // Create upload directory if it doesn't exist
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    
    // Validate file size
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'File size exceeds maximum allowed size'];
    }
    
    // Get file extension
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Validate file type
    if (!in_array($fileExt, $allowedTypes)) {
        return ['success' => false, 'message' => 'File type not allowed'];
    }
    
    // Generate unique filename
    $newFileName = uniqid() . '_' . time() . '.' . $fileExt;
    $targetPath = $targetDir . $newFileName;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return [
            'success' => true,
            'message' => 'File uploaded successfully',
            'file_name' => $file['name'],
            'file_path' => $newFileName,
            'file_size' => $file['size']
        ];
    } else {
        return ['success' => false, 'message' => 'Failed to move uploaded file'];
    }
}

// Delete file
function deleteFile($filePath) {
    $fullPath = UPLOAD_DIR . $filePath;
    if (file_exists($fullPath)) {
        return unlink($fullPath);
    }
    return false;
}

// Generate random password
function generateRandomPassword($length = 10) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
    return substr(str_shuffle(str_repeat($chars, $length)), 0, $length);
}

// Send email (basic)
function sendEmail($to, $subject, $body, $from = null) {
    $from = $from ?? ADMIN_EMAIL;
    $headers = "From: " . $from . "\r\n";
    $headers .= "Reply-To: " . $from . "\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    return mail($to, $subject, $body, $headers);
}

// Pagination helper
function getPaginationData($total, $currentPage = 1, $perPage = null) {
    $perPage = $perPage ?? RECORDS_PER_PAGE;
    $totalPages = ceil($total / $perPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;
    
    return [
        'total' => $total,
        'per_page' => $perPage,
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'has_prev' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages
    ];
}

// Convert status to badge HTML
function statusBadge($status) {
    $badges = [
        'active' => 'success',
        'inactive' => 'secondary',
        'draft' => 'secondary',
        'on-hold' => 'warning',
        'closed' => 'danger',
        'filled' => 'info',
        'new' => 'primary',
        'screening' => 'info',
        'shortlisted' => 'success',
        'interview-scheduled' => 'warning',
        'interviewed' => 'info',
        'offered' => 'success',
        'rejected' => 'danger',
        'hired' => 'success',
        'withdrawn' => 'secondary',
        'scheduled' => 'warning',
        'completed' => 'success',
        'cancelled' => 'danger',
        'no-show' => 'danger'
    ];
    
    $class = $badges[$status] ?? 'secondary';
    $label = ucwords(str_replace('-', ' ', $status));
    
    return "<span class='badge badge-$class'>$label</span>";
}

// Get file icon based on extension
function getFileIcon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $icons = [
        'pdf' => 'fa-file-pdf text-danger',
        'doc' => 'fa-file-word text-primary',
        'docx' => 'fa-file-word text-primary',
        'txt' => 'fa-file-alt text-secondary'
    ];
    
    return $icons[$ext] ?? 'fa-file text-secondary';
}

// Validate required fields
function validateRequired($fields, $data) {
    $errors = [];
    foreach ($fields as $field => $label) {
        if (empty($data[$field])) {
            $errors[$field] = "$label is required";
        }
    }
    return $errors;
}

// Create slug from string
function createSlug($string) {
    $string = strtolower(trim($string));
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
}

// Get initials from name
function getInitials($name) {
    $words = explode(' ', $name);
    if (count($words) >= 2) {
        return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}

// Flash message functions
function setFlashMessage($type, $message) {
    initSession();
    $_SESSION['flash_message'] = ['type' => $type, 'message' => $message];
}

function getFlashMessage() {
    initSession();
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash;
    }
    return null;
}
?>
