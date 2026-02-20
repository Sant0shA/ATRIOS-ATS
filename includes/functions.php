<?php
/* ============================================================
   FILE: functions.php  
   PURPOSE: Helper functions for the application
   
   SECTIONS:
   - [FLASH-MESSAGES] Session-based notifications
   - [STATUS-BADGES] UI status indicators
   - [DATE-FORMATTING] Date/time display functions
   - [SANITIZATION] Input cleaning
   - [ACTIVITY-LOGGING] Audit trail
   - [FILE-UPLOAD] File upload handling
   
   LAST MODIFIED: 2026-02-20
   ============================================================ */

/* ============================================================
   [FLASH-MESSAGES] - Session Flash Messages
   ============================================================ */

function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

/* ============================================================
   [STATUS-BADGES] - Status Badge HTML Generator
   ============================================================ */

function statusBadge($status) {
    $badges = [
        'active' => '<span class="badge badge-success">Active</span>',
        'inactive' => '<span class="badge badge-secondary">Inactive</span>',
        'on-hold' => '<span class="badge badge-warning">On Hold</span>',
        'pending' => '<span class="badge badge-warning">Pending</span>',
        'completed' => '<span class="badge badge-success">Completed</span>',
        'cancelled' => '<span class="badge badge-danger">Cancelled</span>'
    ];
    
    return $badges[$status] ?? '<span class="badge badge-secondary">' . htmlspecialchars($status) . '</span>';
}

/* ============================================================
   [DATE-FORMATTING] - Date and Time Formatting
   ============================================================ */

function timeAgo($datetime) {
    if (!$datetime) return 'Never';
    
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
    } else {
        return date('M j, Y', $timestamp);
    }
}

function formatDate($date) {
    if (!$date) return '';
    return date('M j, Y', strtotime($date));
}

function formatDateTime($datetime) {
    if (!$datetime) return '';
    return date('M j, Y g:i A', strtotime($datetime));
}

/* ============================================================
   [SANITIZATION] - Input Sanitization
   ============================================================ */

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/* ============================================================
   [ACTIVITY-LOGGING] - Audit Trail Logger
   ============================================================ */

function logActivity($db, $userId, $action, $entityType, $entityId, $description) {
    try {
        $stmt = $db->prepare("
            INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description, ip_address, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        
        $stmt->execute([
            $userId,
            $action,
            $entityType,
            $entityId,
            $description,
            $ipAddress
        ]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Activity log error: " . $e->getMessage());
        return false;
    }
}

/* ============================================================
   [FILE-UPLOAD] - File Upload Handler
   ============================================================ */

/**
 * Upload a file to the specified directory
 * 
 * @param array $file The $_FILES array element
 * @param string $folder Subfolder in uploads/ (e.g., 'cvs', 'agreements', 'logos')
 * @param array $allowedExtensions Allowed file extensions (e.g., ['pdf', 'doc', 'docx'])
 * @param int $maxSize Maximum file size in bytes (default: 10MB)
 * @return array ['success' => bool, 'path' => string|null, 'error' => string|null]
 */
function uploadFile($file, $folder, $allowedExtensions = ['pdf', 'doc', 'docx'], $maxSize = 10485760) {
    // Check if file was uploaded
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return [
            'success' => false,
            'path' => null,
            'error' => 'No file uploaded or upload error occurred'
        ];
    }
    
    // Check file size
    if ($file['size'] > $maxSize) {
        $maxSizeMB = $maxSize / 1048576;
        return [
            'success' => false,
            'path' => null,
            'error' => "File size exceeds maximum allowed size of {$maxSizeMB}MB"
        ];
    }
    
    // Get file extension
    $originalName = $file['name'];
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    
    // Check file extension
    if (!in_array($extension, $allowedExtensions)) {
        $allowed = implode(', ', $allowedExtensions);
        return [
            'success' => false,
            'path' => null,
            'error' => "File type not allowed. Allowed types: {$allowed}"
        ];
    }
    
    // Create upload directory if it doesn't exist
    $uploadDir = __DIR__ . "/../uploads/{$folder}/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $uniqueName = uniqid() . '_' . time() . '.' . $extension;
    $uploadPath = $uploadDir . $uniqueName;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        // Return relative path from site root
        $relativePath = "uploads/{$folder}/{$uniqueName}";
        return [
            'success' => true,
            'path' => $relativePath,
            'error' => null
        ];
    } else {
        return [
            'success' => false,
            'path' => null,
            'error' => 'Failed to move uploaded file'
        ];
    }
}

/**
 * Delete a file from uploads directory
 * 
 * @param string $filePath Relative path to file (e.g., 'uploads/cvs/file.pdf')
 * @return bool Success status
 */
function deleteFile($filePath) {
    if (empty($filePath)) {
        return false;
    }
    
    $fullPath = __DIR__ . "/../{$filePath}";
    
    if (file_exists($fullPath)) {
        return unlink($fullPath);
    }
    
    return false;
}

/* ============================================================
   [UTILITY-FUNCTIONS] - Miscellaneous Helpers
   ============================================================ */

/**
 * Format currency (Indian Rupees)
 * 
 * @param float $amount Amount in rupees
 * @return string Formatted currency string
 */
function formatCurrency($amount) {
    if (empty($amount)) return '—';
    return '₹' . number_format($amount, 2);
}

/**
 * Generate a random token
 * 
 * @param int $length Token length (default: 32)
 * @return string Random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}
?>
