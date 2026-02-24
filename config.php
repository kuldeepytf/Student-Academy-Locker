<?php
/**
 * SAI COLLEGE - Education Management System
 * Enhanced Configuration File with Security Improvements
 * Version: 2.0
 */

// Start session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', 1);
    session_start();
}

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'education_system');

// Application settings
define('SITE_NAME', 'SAI COLLEGE');
define('SITE_URL', 'http://localhost/education_system');
define('ADMIN_EMAIL', 'admin@saicollege.edu');

// File upload settings
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'pptx', 'xlsx']);
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png']);
define('UPLOAD_PATH', __DIR__ . '/uploads/');

// Profile update limits
define('MAX_PHONE_CHANGES', 4);
define('MAX_ADDRESS_CHANGES', 4);

// Payment settings
define('PAYMENT_UPI_ID', 'college@upi');
define('PAYMENT_BANK_NAME', 'SAI COLLEGE BANK');
define('PAYMENT_ACCOUNT_NUMBER', '1234567890');
define('PAYMENT_IFSC', 'SBIN0000000');
define('PAYMENT_QR_IMAGE', 'uploads/qr/fee_qr.jpeg');

// Security settings
define('PASSWORD_MIN_LENGTH', 8);
define('SESSION_TIMEOUT', 1800); // 30 minutes
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// Create database connection with error handling
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Set charset
    $conn->set_charset('utf8mb4');
    
    // Set timezone
    $conn->query("SET time_zone = '+00:00'");
    date_default_timezone_set('UTC');
    
    // Add missing columns if they don't exist
    $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS phone_changes INT DEFAULT 0");
    $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS address_changes INT DEFAULT 0");
    
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("Database connection failed. Please contact administrator.");
}

// Create necessary directories
$directories = [
    'uploads/notes',
    'uploads/assignments',
    'uploads/locker',
    'uploads/profile',
    'uploads/payments',
    'uploads/qr',
    'logs'
];

foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

/**
 * Security Helper Functions
 */

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

// Get current user with caching
function getCurrentUser() {
    global $conn;
    
    if (!isLoggedIn()) {
        return null;
    }
    
    // Cache user data in session
    if (!isset($_SESSION['user_data']) || 
        !isset($_SESSION['user_cache_time']) || 
        (time() - $_SESSION['user_cache_time']) > 300) { // Cache for 5 minutes
        
        $user_id = (int)$_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND status = 'approved'");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            // Ensure course field is set (backward compatibility with stream)
            if (!isset($user['course']) && isset($user['stream'])) {
                $user['course'] = $user['stream'];
            }
            $_SESSION['user_data'] = $user;
            $_SESSION['user_cache_time'] = time();
        } else {
            // User no longer exists or not approved
            session_destroy();
            return null;
        }
        $stmt->close();
    }
    
    return $_SESSION['user_data'] ?? null;
}

// Check user role
function hasRole($role) {
    $user = getCurrentUser();
    return $user && $user['role'] === $role;
}

// Check if user has any of the specified roles
function hasAnyRole($roles) {
    $user = getCurrentUser();
    return $user && in_array($user['role'], $roles);
}

// Redirect helper
function redirect($url) {
    if (!headers_sent()) {
        header("Location: $url");
        exit();
    } else {
        echo "<script>window.location.href='$url';</script>";
        exit();
    }
}

// Enhanced sanitization
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Clean HTML input (for rich text editors)
function sanitizeHTML($html) {
    // Allow only safe HTML tags
    $allowed_tags = '<p><br><strong><em><u><ul><ol><li><h1><h2><h3><h4><a><img>';
    return strip_tags($html, $allowed_tags);
}

// Validate email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Validate password strength
function validatePassword($password) {
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        return "Password must be at least " . PASSWORD_MIN_LENGTH . " characters long.";
    }
    if (!preg_match("/[A-Z]/", $password)) {
        return "Password must contain at least one uppercase letter.";
    }
    if (!preg_match("/[a-z]/", $password)) {
        return "Password must contain at least one lowercase letter.";
    }
    if (!preg_match("/[0-9]/", $password)) {
        return "Password must contain at least one number.";
    }
    return true;
}

// Hash password securely
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * File Handling Functions
 */

// Check if file type is allowed
function isAllowedFile($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, ALLOWED_FILE_TYPES);
}

// Get file extension
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

// Format file size
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' bytes';
}

// Validate uploaded file
function validateUploadedFile($file, $maxSize = MAX_FILE_SIZE) {
    if (!isset($file['error']) || is_array($file['error'])) {
        return 'Invalid file upload.';
    }
    
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'File size exceeds limit.';
        case UPLOAD_ERR_NO_FILE:
            return 'No file uploaded.';
        default:
            return 'Unknown upload error.';
    }
    
    if ($file['size'] > $maxSize) {
        return 'File size exceeds ' . formatFileSize($maxSize) . '.';
    }
    
    if (!isAllowedFile($file['name'])) {
        return 'File type not allowed. Allowed types: ' . implode(', ', ALLOWED_FILE_TYPES);
    }
    
    // Verify MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowedMimes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'image/jpeg',
        'image/png',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ];
    
    if (!in_array($mimeType, $allowedMimes)) {
        return 'Invalid file type detected.';
    }
    
    return true;
}

// Generate unique filename
function generateUniqueFilename($originalName) {
    $ext = getFileExtension($originalName);
    return uniqid('file_', true) . '_' . time() . '.' . $ext;
}

/**
 * Date/Time Functions
 */

// Format date
function formatDate($date, $format = 'd M Y, h:i A') {
    if (!$date) return 'N/A';
    return date($format, strtotime($date));
}

// Time ago function
function timeAgo($timestamp) {
    if (!$timestamp) return 'Never';
    
    $time_ago = strtotime($timestamp);
    $current_time = time();
    $time_difference = $current_time - $time_ago;
    
    if ($time_difference < 1) return 'Just now';
    
    $seconds = $time_difference;
    $minutes = round($seconds / 60);
    $hours = round($seconds / 3600);
    $days = round($seconds / 86400);
    $weeks = round($seconds / 604800);
    $months = round($seconds / 2629440);
    $years = round($seconds / 31553280);
    
    if ($seconds <= 60) {
        return "Just now";
    } elseif ($minutes <= 60) {
        return $minutes == 1 ? "1 min ago" : "$minutes mins ago";
    } elseif ($hours <= 24) {
        return $hours == 1 ? "1 hour ago" : "$hours hours ago";
    } elseif ($days <= 7) {
        return $days == 1 ? "Yesterday" : "$days days ago";
    } elseif ($weeks <= 4) {
        return $weeks == 1 ? "1 week ago" : "$weeks weeks ago";
    } elseif ($months <= 12) {
        return $months == 1 ? "1 month ago" : "$months months ago";
    } else {
        return $years == 1 ? "1 year ago" : "$years years ago";
    }
}

/**
 * Activity & Logging Functions
 */

// Log activity with prepared statement
function logActivity($user_id, $action, $description = '') {
    global $conn;
    
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $stmt = $conn->prepare("INSERT INTO activity_log (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param('issss', $user_id, $action, $description, $ip_address, $user_agent);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Exception $e) {
        // Silently fail - don't let activity logging break the application
        error_log("Activity log error: " . $e->getMessage());
    }
}

/**
 * Notification Functions
 */

// Get unread notification count
function getUnreadNotificationsCount($user_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row['count'] ?? 0;
}

// Create notification
function createNotification($user_id, $title, $message, $type = 'info', $link = null) {
    global $conn;
    
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('issss', $user_id, $title, $message, $type, $link);
    $success = $stmt->execute();
    $stmt->close();
    
    return $success;
}

// Bulk create notifications
function createBulkNotifications($user_ids, $title, $message, $type = 'info', $link = null) {
    global $conn;
    
    if (empty($user_ids)) return false;
    
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($user_ids as $user_id) {
        $stmt->bind_param('issss', $user_id, $title, $message, $type, $link);
        $stmt->execute();
    }
    
    $stmt->close();
    return true;
}

/**
 * Message Functions
 */

// Get unread message count
function getUnreadMessageCount($user_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row['count'] ?? 0;
}

/**
 * Attendance Functions
 */

// Get attendance percentage
function getAttendancePercentage($student_id, $subject = null) {
    global $conn;
    
    if ($subject) {
        $stmt = $conn->prepare("SELECT COUNT(*) as total, 
                  SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present 
                  FROM attendance WHERE student_id = ? AND subject = ?");
        $stmt->bind_param('is', $student_id, $subject);
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) as total, 
                  SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present 
                  FROM attendance WHERE student_id = ?");
        $stmt->bind_param('i', $student_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if ($row['total'] == 0) return 0;
    return round(($row['present'] / $row['total']) * 100, 2);
}

/**
 * User Status Functions
 */

// Check if user is online (active in last 5 minutes)
function isUserOnline($user_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT last_seen FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if ($user && $user['last_seen']) {
        $last_seen = strtotime($user['last_seen']);
        $current_time = time();
        return ($current_time - $last_seen) < 300; // 5 minutes
    }
    
    return false;
}

// Update user last seen
function updateLastSeen($user_id) {
    global $conn;
    
    $stmt = $conn->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->close();
}

/**
 * Event Functions
 */

// Get upcoming events
function getUpcomingEvents($limit = 5) {
    global $conn;
    $user = getCurrentUser();
    $course = $user['course'] ?? 'All';

    $stmt = $conn->prepare("SELECT * FROM events 
              WHERE event_date >= CURDATE() 
              AND (course = ? OR course = 'All') 
              ORDER BY event_date ASC, start_time ASC 
              LIMIT ?");
    $stmt->bind_param('si', $course, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    return $result;
}

/**
 * CSRF Protection
 */

// Generate CSRF token
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Get CSRF input field
function csrfField() {
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Session Management
 */

// Auto logout after inactivity
if (isLoggedIn()) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        logActivity($_SESSION['user_id'], 'Auto Logout', 'Session timeout');
        session_unset();
        session_destroy();
        redirect('index.php?timeout=1');
    }
    
    $_SESSION['last_activity'] = time();
    
    // Update last seen
    if (!isset($_SESSION['last_seen_update']) || (time() - $_SESSION['last_seen_update']) > 60) {
        updateLastSeen($_SESSION['user_id']);
        $_SESSION['last_seen_update'] = time();
    }
}

/**
 * Response Helpers
 */

// JSON response
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

// Success response
function successResponse($message, $data = []) {
    jsonResponse(array_merge(['success' => true, 'message' => $message], $data));
}

// Error response
function errorResponse($message, $statusCode = 400) {
    jsonResponse(['success' => false, 'message' => $message], $statusCode);
}

/**
 * Validation Helpers
 */

// Validate required fields
function validateRequired($fields) {
    $errors = [];
    
    foreach ($fields as $field => $value) {
        if (empty($value)) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
        }
    }
    
    return $errors;
}

// Validate date
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Initialize application
 */

// Set error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Error [$errno]: $errstr in $errfile on line $errline");
    return false;
});

// Set exception handler (commented out for development - uncomment for production)
// set_exception_handler(function($exception) {
//     error_log("Exception: " . $exception->getMessage());
//     die("An error occurred. Please contact administrator.");
// });

// Initialize CSRF token
generateCSRFToken();
