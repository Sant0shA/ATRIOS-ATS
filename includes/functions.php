<?php
// Helper Functions

// Flash Messages
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash;
    }
    return null;
}

// Status Badge
function statusBadge($status) {
    $badges = [
        'new' => 'bg-primary',
        'screening' => 'bg-info',
        'shortlisted' => 'bg-warning',
        'interviewed' => 'bg-secondary',
        'offered' => 'bg-success',
        'hired' => 'bg-success',
        'rejected' => 'bg-danger',
        'withdrawn' => 'bg-dark',
        'active' => 'bg-success',
        'inactive' => 'bg-secondary',
        'on-hold' => 'bg-warning',
        'closed' => 'bg-dark',
        'filled' => 'bg-success',
        'draft' => 'bg-secondary'
    ];
    
    $class = $badges[$status] ?? 'bg-secondary';
    $label = ucwords(str_replace('-', ' ', $status));
    
    return "<span class='badge {$class}'>{$label}</span>";
}

// Time Ago Function
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 2592000) {
        $weeks = floor($diff / 604800);
        return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
    } else {
        return date('M d, Y', $timestamp);
    }
}

// Format Date
function formatDate($date) {
    if (empty($date)) return 'N/A';
    return date('M d, Y', strtotime($date));
}

// Format DateTime
function formatDateTime($datetime) {
    if (empty($datetime)) return 'N/A';
    return date('M d, Y h:i A', strtotime($datetime));
}

// Sanitize Input
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Log Activity
function logActivity($db, $user_id, $action, $entity_type, $entity_id, $description) {
    try {
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description, ip_address) 
                              VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $entity_type, $entity_id, $description, $_SERVER['REMOTE_ADDR']]);
    } catch (PDOException $e) {
        // Fail silently
    }
}

// File Upload Handler
function uploadFile($file, $upload_dir, $allowed_types) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'No file uploaded or upload error'];
    }
    
    $file_size = $file['size'];
    $file_tmp = $file['tmp_name'];
    $file_name = $file['name'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    if (!in_array($file_ext, $allowed_types)) {
        return ['success' => false, 'message' => 'Invalid file type'];
    }
    
    if ($file_size > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'File too large'];
    }
    
    $new_filename = uniqid() . '_' . time() . '.' . $file_ext;
    $destination = $upload_dir . $new_filename;
    
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    if (move_uploaded_file($file_tmp, $destination)) {
        return ['success' => true, 'filename' => $new_filename, 'path' => $destination];
    }
    
    return ['success' => false, 'message' => 'Failed to move uploaded file'];
}
?>
