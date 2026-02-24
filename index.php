<?php
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('dashboard.php');
}

// Reset forgot password session
if (isset($_GET['reset_forgot'])) {
    unset($_SESSION['reset_email']);
    unset($_SESSION['reset_security_question']);
    unset($_SESSION['verified_email']);
    unset($_SESSION['forgot_step']);
    exit();
}

$error = '';
$success = '';
$active_tab = isset($_GET['signup']) ? 'signup' : 'login';
$forgot_step = $_SESSION['forgot_step'] ?? 1; // 1 = email, 2 = security question, 3 = password reset

// If there's an error from forgot password forms, keep the tab visible
if (($error && (isset($_POST['forgot_password']) || isset($_POST['verify_security']) || isset($_POST['reset_password']))) && $forgot_step == 1) {
    // Stay on step 1 with error
}

// Show success messages if redirected from signup or reset
if (isset($_GET['signup_done'])) {
    $success = 'Account created successfully! Please login with your credentials.';
    $active_tab = 'login';
}
if (isset($_GET['reset_done'])) {
    $success = 'Reset password successful. Now login with your new password.';
    $active_tab = 'login';
}

// Show step-specific success messages
if ($forgot_step == 2 && !isset($_POST['verify_security']) && !isset($_POST['forgot_password'])) {
    $success = 'Please answer your security question to proceed.';
}
if ($forgot_step == 3 && !isset($_POST['reset_password']) && !isset($_POST['verify_security'])) {
    $success = 'Reset your password.';
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);

    if (empty($email) || empty($password)) {
        $error = 'Please fill all fields';
    } else {
        $query = "SELECT * FROM users WHERE email = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($user = mysqli_fetch_assoc($result)) {
            if (password_verify($password, $user['password'])) {
                if ($user['status'] !== 'approved') {
                    if ($user['status'] === 'pending') {
                        $error = 'Your account is pending for admin approval. Please wait for approval.';
                    } elseif ($user['status'] === 'rejected') {
                        $error = 'Your account has been rejected by admin.';
                    } else {
                        $error = 'Your account is not active.';
                    }
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['user_course'] = $user['course'] ?? null;
                    $_SESSION['last_activity'] = time();
                    
                    // Remember me functionality
                    if ($remember) {
                        $token = bin2hex(random_bytes(32));
                        setcookie('remember_token', $token, time() + (86400 * 30), '/');
                        $query = "UPDATE users SET remember_token = ? WHERE id = ?";
                        $stmt = mysqli_prepare($conn, $query);
                        mysqli_stmt_bind_param($stmt, 'si', $token, $user['id']);
                        mysqli_stmt_execute($stmt);
                    }
                    
                    logActivity($user['id'], 'Login', 'User logged in successfully');
                    redirect('dashboard.php');
                }
            } else {
                $error = 'Invalid email or password';
                logActivity($user['id'], 'Failed Login', 'Invalid password attempt');
            }
        } else {
            $error = 'Invalid email or password';
        }
    }
}

// Handle signup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signup'])) {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = sanitize($_POST['role']);

    // Role-specific fields
    $course = '';
    $subject = '';
    $qualification = '';
    $experience = '';
    $security_question = sanitize($_POST['security_question'] ?? '');
    $security_answer = sanitize($_POST['security_answer'] ?? '');

    if ($role === 'Student') {
        $course = sanitize($_POST['course'] ?? '');
        $rollno = sanitize($_POST['rollno'] ?? '');
        $age = intval($_POST['age'] ?? 0);
        $course_year = sanitize($_POST['course_year'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
    } elseif ($role === 'Teacher') {
        $subject = sanitize($_POST['subject']);
        $qualification = sanitize($_POST['qualification']);
        $experience = intval($_POST['experience']);
    }

    // Validation
    if (empty($name) || empty($email) || empty($password) || empty($role)) {
        $error = 'Please fill all required fields';
    } elseif (empty($security_question) || empty($security_answer)) {
        $error = 'Please select a security question and provide an answer';
    } elseif ($role === 'Student' && empty($course)) {
        $error = 'Please select your course';
    } elseif ($role === 'Student' && (empty($rollno) || empty($age) || empty($course_year))) {
        $error = 'Please provide Roll No, Age and Course Year for student accounts';
    } elseif ($role === 'Teacher' && (empty($subject) || empty($qualification))) {
        $error = 'Please fill all teacher-specific fields';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif (!in_array($role, ['Student', 'Teacher'])) {
        $error = 'Invalid role selected';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        // Check if email already exists
        $query = "SELECT id FROM users WHERE email = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) > 0) {
            $error = 'Email already registered';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Set status based on role
            $status = ($role === 'Teacher') ? 'pending' : 'approved';

            if ($role === 'Student') {
                // Ensure rollno unique within course
                $check_q = mysqli_prepare($conn, "SELECT id FROM users WHERE rollno = ? AND course = ?");
                mysqli_stmt_bind_param($check_q, 'ss', $rollno, $course);
                mysqli_stmt_execute($check_q);
                $check_res = mysqli_stmt_get_result($check_q);
                if (mysqli_num_rows($check_res) > 0) {
                    $error = 'Roll Number already exists for selected course';
                } else {
                    $query = "INSERT INTO users (name, email, password, role, course, rollno, age, course_year, phone, address, security_question, security_answer, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $query);
                    if (!$stmt) {
                        $error = 'Database error: ' . mysqli_error($conn);
                    } else {
                        mysqli_stmt_bind_param($stmt, 'ssssssissssss', $name, $email, $hashed_password, $role, $course, $rollno, $age, $course_year, $phone, $address, $security_question, $security_answer, $status);
                        if (mysqli_stmt_execute($stmt)) {
                            $new_user_id = mysqli_insert_id($conn);
                            try {
                                logActivity($new_user_id, 'User Registration', 'New student account created - Status: ' . $status);
                            } catch (Exception $e) {
                                // Continue even if logging fails
                            }
                            $_SESSION['signup_success'] = true;
                            header('Location: index.php?signup_done=1');
                            exit();
                        } else {
                            $error = 'Registration failed: ' . mysqli_error($conn);
                        }
                    }
                }
            } else {
                $query = "INSERT INTO users (name, email, password, role, subject, qualification, experience, security_question, security_answer, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $query);
                if (!$stmt) {
                    $error = 'Database error: ' . mysqli_error($conn);
                } else {
                    mysqli_stmt_bind_param($stmt, 'ssssssisss', $name, $email, $hashed_password, $role, $subject, $qualification, $experience, $security_question, $security_answer, $status);
                    if (mysqli_stmt_execute($stmt)) {
                        $new_user_id = mysqli_insert_id($conn);
                        try {
                            logActivity($new_user_id, 'User Registration', 'New teacher account created - Status: ' . $status);
                        } catch (Exception $e) {
                            // Continue even if logging fails
                        }
                        $_SESSION['signup_success'] = true;
                        header('Location: index.php?signup_done=1');
                        exit();
                    } else {
                        $error = 'Registration failed: ' . mysqli_error($conn);
                    }
                }
            }
        }
    }
}

// Handle forgot password - Step 1: Verify email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forgot_password'])) {
    $email = sanitize($_POST['email']);
    
    if (empty($email)) {
        $error = 'Please enter your email address';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        $query = "SELECT id, security_question FROM users WHERE email = ?";
        $stmt = mysqli_prepare($conn, $query);
        if (!$stmt) {
            $error = 'Database error. Please try again later.';
        } else {
            mysqli_stmt_bind_param($stmt, 's', $email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if ($user_found = mysqli_fetch_assoc($result)) {
                $_SESSION['reset_email'] = $email;
                $_SESSION['reset_security_question'] = $user_found['security_question'];
                $_SESSION['forgot_step'] = 2;
                // Redirect to clear POST data
                header('Location: index.php');
                exit();
            } else {
                $error = 'No account found with this email address';
            }
        }
    }
}

// Handle security question verification - Step 2: Verify security answer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_security'])) {
    $security_answer = sanitize($_POST['security_answer']);
    $reset_email = $_SESSION['reset_email'] ?? null;
    
    if (empty($security_answer)) {
        $error = 'Please provide an answer to the security question';
    } elseif (!$reset_email) {
        $error = 'Please start the password reset process again';
    } else {
        $query = "SELECT id FROM users WHERE email = ? AND security_answer = ?";
        $stmt = mysqli_prepare($conn, $query);
        if (!$stmt) {
            $error = 'Database error. Please try again later.';
        } else {
            mysqli_stmt_bind_param($stmt, 'ss', $reset_email, $security_answer);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if ($verify_user = mysqli_fetch_assoc($result)) {
                $_SESSION['verified_email'] = $reset_email;
                $_SESSION['forgot_step'] = 3;
                // Redirect to clear POST data
                header('Location: index.php');
                exit();
            } else {
                $error = 'Incorrect answer to the security question. Please try again.';
            }
        }
    }
}

// Handle password reset - Step 3: Reset password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $verified_email = $_SESSION['verified_email'] ?? null;
    
    if (empty($new_password) || empty($confirm_password)) {
        $error = 'Please fill in all password fields';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($new_password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif (!$verified_email) {
        $error = 'Please verify your security question first';
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $query = "UPDATE users SET password = ? WHERE email = ?";
        $stmt = mysqli_prepare($conn, $query);
        if (!$stmt) {
            $error = 'Database error. Please try again later.';
        } elseif (mysqli_stmt_bind_param($stmt, 'ss', $hashed_password, $verified_email) && mysqli_stmt_execute($stmt)) {
            unset($_SESSION['reset_email']);
            unset($_SESSION['verified_email']);
            unset($_SESSION['show_security_question']);
            unset($_SESSION['show_password_reset']);
            unset($_SESSION['reset_security_question']);
            
            // Redirect to login page with success message
            header('Location: index.php?reset_done=1');
            exit();
        } else {
            $error = 'Failed to reset password. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SAI COLLEGE - Education Management System</title>
    <link rel="stylesheet" href="assets.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .auth-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        .auth-container::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: moveBackground 20s linear infinite;
        }

        @keyframes moveBackground {
            0% { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
        }

        .auth-box {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 480px;
            width: 100%;
            position: relative;
            z-index: 1;
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .auth-header {
            text-align: center;
            padding: 40px 40px 30px;
            border-bottom: 2px solid #f0f0f0;
        }

        .auth-logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .auth-logo i {
            font-size: 40px;
            color: white;
        }

        .auth-header h1 {
            font-size: 28px;
            font-weight: 800;
            color: #1a1a1a;
            margin-bottom: 8px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .auth-header p {
            color: #666;
            font-size: 15px;
            font-weight: 500;
        }

        .tab-buttons {
            display: flex;
            padding: 0 40px;
            gap: 15px;
            margin-top: 30px;
        }

        .tab-btn {
            flex: 1;
            padding: 14px 20px;
            border: none;
            background: #f5f5f5;
            color: #666;
            font-size: 15px;
            font-weight: 600;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .tab-btn:hover {
            background: #e8e8e8;
            transform: translateY(-2px);
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .tab-content {
            display: none;
            padding: 30px 40px 40px;
            animation: fadeIn 0.4s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .tab-content.active {
            display: block;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .form-group label i {
            margin-right: 8px;
            color: #667eea;
            width: 16px;
        }

        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: white;
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .role-selection {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 25px;
        }

        .role-card {
            position: relative;
            padding: 20px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            background: white;
        }

        .role-card:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.15);
        }

        .role-card.selected {
            border-color: #667eea;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.2);
        }

        .role-card input[type="radio"] {
            position: absolute;
            opacity: 0;
        }

        .role-card i {
            font-size: 32px;
            color: #667eea;
            margin-bottom: 10px;
        }

        .role-card span {
            display: block;
            font-weight: 600;
            color: #333;
            font-size: 15px;
        }

        .checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 20px 0;
        }

        .checkbox-wrapper input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .checkbox-wrapper label {
            margin: 0;
            font-size: 14px;
            color: #666;
            cursor: pointer;
        }

        .btn {
            width: 100%;
            padding: 16px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(102, 126, 234, 0.5);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .alert {
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.4s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-danger {
            background: #fee;
            color: #c33;
            border: 1px solid #fdd;
        }

        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #dfd;
        }

        .alert i {
            font-size: 18px;
        }

        .forgot-link {
            text-align: center;
            margin-top: 20px;
        }

        .forgot-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .forgot-link a:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        .role-fields {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease;
        }

        .role-fields.show {
            max-height: 500px;
        }

        .auth-footer {
            text-align: center;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 0 0 24px 24px;
            font-size: 13px;
            color: #666;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-top: 50px;
            padding: 0 40px;
        }

        .feature-card {
            text-align: center;
            color: white;
        }

        .feature-card i {
            font-size: 36px;
            margin-bottom: 15px;
            opacity: 0.9;
        }

        .feature-card h3 {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .feature-card p {
            font-size: 13px;
            opacity: 0.8;
            line-height: 1.5;
        }

        @media (max-width: 768px) {
            .auth-box {
                margin: 20px;
            }

            .auth-header, .tab-buttons, .tab-content {
                padding-left: 25px;
                padding-right: 25px;
            }

            .form-row, .role-selection {
                grid-template-columns: 1fr;
            }

            .features-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23667eea' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
            padding-right: 40px;
        }

        .password-toggle {
            position: relative;
        }

        .password-toggle input {
            padding-right: 45px;
        }

        .password-toggle-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #667eea;
            font-size: 18px;
            padding: 8px;
        }

        /* Forgot Password Modal Styles */
        .forgot-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            animation: fadeIn 0.3s ease-in;
        }

        .modal-content {
            background-color: white;
            border-radius: 12px;
            padding: 30px;
            max-width: 450px;
            width: 90%;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            position: relative;
            animation: slideUp 0.3s ease-out;
        }

        .modal-close {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #999;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s;
        }

        .modal-close:hover {
            color: #333;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        @keyframes slideUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .btn-block {
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-header">
                <div class="auth-logo">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <h1>SAI COLLEGE</h1>
                <p>Education Management System</p>
            </div>

            <div class="tab-buttons">
                <button class="tab-btn <?php echo $active_tab === 'login' ? 'active' : ''; ?>" onclick="switchTab('login')">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
                <button class="tab-btn <?php echo $active_tab === 'signup' ? 'active' : ''; ?>" onclick="switchTab('signup')">
                    <i class="fas fa-user-plus"></i> Sign Up
                </button>
            </div>

            <!-- Login Tab -->
            <div id="login-tab" class="tab-content <?php echo $active_tab === 'login' ? 'active' : ''; ?>" style="display: <?php echo $active_tab === 'login' ? 'block' : 'none'; ?>;">
                <?php if ($error && isset($_POST['login'])): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if ($success && $active_tab === 'login'): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="login-form">
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="Enter your email" required
                               value="<?php echo isset($_POST['email']) && isset($_POST['login']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Password</label>
                        <div class="password-toggle">
                            <input type="password" name="password" class="form-control" id="login-password" placeholder="Enter your password" required>
                            <button type="button" class="password-toggle-btn" onclick="togglePassword('login-password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="checkbox-wrapper">
                        <input type="checkbox" name="remember" id="remember">
                        <label for="remember">Remember me for 30 days</label>
                    </div>

                    <button type="submit" name="login" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i>
                        Login to Dashboard
                    </button>

                    <div class="forgot-link">
                        <a href="javascript:void(0)" onclick="showForgotPassword()">
                            <i class="fas fa-key"></i> Forgot Password?
                        </a>
                    </div>
                </form>
            </div>

            <!-- Signup Tab -->
            <div id="signup-tab" class="tab-content <?php echo $active_tab === 'signup' ? 'active' : ''; ?>" style="display: <?php echo $active_tab === 'signup' ? 'block' : 'none'; ?>;">
                <?php if ($error && $active_tab === 'signup'): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="signup-form">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Full Name</label>
                        <input type="text" name="name" class="form-control" placeholder="Enter your full name" required
                               value="<?php echo isset($_POST['name']) && isset($_POST['signup']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="Enter your email" required
                               value="<?php echo isset($_POST['email']) && isset($_POST['signup']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-user-tag"></i> I am a</label>
                        <div class="role-selection">
                            <label class="role-card <?php echo (isset($_POST['role']) && $_POST['role'] == 'Student') ? 'selected' : ''; ?>">
                                <input type="radio" name="role" value="Student" onchange="toggleRoleFields()" required
                                       <?php echo (isset($_POST['role']) && $_POST['role'] == 'Student') ? 'checked' : ''; ?>>
                                <i class="fas fa-user-graduate"></i>
                                <span>Student</span>
                            </label>
                            <label class="role-card <?php echo (isset($_POST['role']) && $_POST['role'] == 'Teacher') ? 'selected' : ''; ?>">
                                <input type="radio" name="role" value="Teacher" onchange="toggleRoleFields()" required
                                       <?php echo (isset($_POST['role']) && $_POST['role'] == 'Teacher') ? 'checked' : ''; ?>>
                                <i class="fas fa-chalkboard-teacher"></i>
                                <span>Teacher</span>
                            </label>
                        </div>
                    </div>

                    <!-- Student Fields -->
                    <div id="student-fields" class="role-fields <?php echo (isset($_POST['role']) && $_POST['role'] == 'Student') ? 'show' : ''; ?>">
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-graduation-cap"></i> Course</label>
                                <select name="course" class="form-control">
                                    <option value="">Select your course</option>
                                    <option value="B.Com" <?php echo (isset($_POST['course']) && $_POST['course'] == 'B.Com') ? 'selected' : ''; ?>>B.Com</option>
                                    <option value="M.Com" <?php echo (isset($_POST['course']) && $_POST['course'] == 'M.Com') ? 'selected' : ''; ?>>M.Com</option>
                                    <option value="BCA" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BCA') ? 'selected' : ''; ?>>BCA</option>
                                    <option value="BA" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BA') ? 'selected' : ''; ?>>BA</option>
                                    <option value="BBA" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BBA') ? 'selected' : ''; ?>>BBA</option>
                                    <option value="PGDCA" <?php echo (isset($_POST['course']) && $_POST['course'] == 'PGDCA') ? 'selected' : ''; ?>>PGDCA</option>
                                    <option value="DCA" <?php echo (isset($_POST['course']) && $_POST['course'] == 'DCA') ? 'selected' : ''; ?>>DCA</option>
                                    <option value="M.Sc" <?php echo (isset($_POST['course']) && $_POST['course'] == 'M.Sc') ? 'selected' : ''; ?>>M.Sc</option>
                                    <option value="MA" <?php echo (isset($_POST['course']) && $_POST['course'] == 'MA') ? 'selected' : ''; ?>>MA</option>
                                    <option value="B.Lib" <?php echo (isset($_POST['course']) && $_POST['course'] == 'B.Lib') ? 'selected' : ''; ?>>B.Lib</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label><i class="fas fa-id-badge"></i> Student Roll No</label>
                                <input type="text" name="rollno" class="form-control" placeholder="Enter roll number" required value="<?php echo isset($_POST['rollno']) ? htmlspecialchars($_POST['rollno']) : ''; ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-birthday-cake"></i> Age</label>
                                <input type="number" name="age" min="10" max="100" class="form-control" placeholder="Enter age" required value="<?php echo isset($_POST['age']) ? intval($_POST['age']) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-calendar-alt"></i> Course Year</label>
                                <input type="text" name="course_year" class="form-control" placeholder="e.g., 1, 2, 3" required value="<?php echo isset($_POST['course_year']) ? htmlspecialchars($_POST['course_year']) : ''; ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-phone"></i> Phone</label>
                                <input type="text" name="phone" class="form-control" placeholder="Mobile number" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-map-marker-alt"></i> Address</label>
                                <input type="text" name="address" class="form-control" placeholder="Residential address" value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>">
                            </div>
                        </div>
                        <small style="display:block; color:#666;">Note: Name, Age, Roll No, Course Year cannot be changed once filled. Phone & Address can be changed up to 4 times.</small>
                    </div>

                    <!-- Teacher Fields -->
                    <div id="teacher-fields" class="role-fields <?php echo (isset($_POST['role']) && $_POST['role'] == 'Teacher') ? 'show' : ''; ?>">
                        <div class="form-group">
                            <label><i class="fas fa-book"></i> Subject</label>
                            <select name="subject" class="form-control">
                                <option value="">Select subject you teach</option>
                                <option value="Physics" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Physics') ? 'selected' : ''; ?>>Physics</option>
                                <option value="Chemistry" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Chemistry') ? 'selected' : ''; ?>>Chemistry</option>
                                <option value="Mathematics" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Mathematics') ? 'selected' : ''; ?>>Mathematics</option>
                                <option value="Biology" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Biology') ? 'selected' : ''; ?>>Biology</option>
                                <option value="English" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'English') ? 'selected' : ''; ?>>English</option>
                                <option value="History" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'History') ? 'selected' : ''; ?>>History</option>
                                <option value="Geography" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Geography') ? 'selected' : ''; ?>>Geography</option>
                                <option value="Economics" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Economics') ? 'selected' : ''; ?>>Economics</option>
                                <option value="Accountancy" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Accountancy') ? 'selected' : ''; ?>>Accountancy</option>
                                <option value="Computer Science" <?php echo (isset($_POST['subject']) && $_POST['subject'] == 'Computer Science') ? 'selected' : ''; ?>>Computer Science</option>
                            </select>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-graduation-cap"></i> Qualification</label>
                                <select name="qualification" class="form-control">
                                    <option value="">Select qualification</option>
                                    <option value="B.Ed" <?php echo (isset($_POST['qualification']) && $_POST['qualification'] == 'B.Ed') ? 'selected' : ''; ?>>B.Ed</option>
                                    <option value="M.Ed" <?php echo (isset($_POST['qualification']) && $_POST['qualification'] == 'M.Ed') ? 'selected' : ''; ?>>M.Ed</option>
                                    <option value="M.Sc" <?php echo (isset($_POST['qualification']) && $_POST['qualification'] == 'M.Sc') ? 'selected' : ''; ?>>M.Sc</option>
                                    <option value="M.A" <?php echo (isset($_POST['qualification']) && $_POST['qualification'] == 'M.A') ? 'selected' : ''; ?>>M.A</option>
                                    <option value="Ph.D" <?php echo (isset($_POST['qualification']) && $_POST['qualification'] == 'Ph.D') ? 'selected' : ''; ?>>Ph.D</option>
                                    <option value="Other" <?php echo (isset($_POST['qualification']) && $_POST['qualification'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label><i class="fas fa-briefcase"></i> Experience</label>
                                <select name="experience" class="form-control">
                                    <option value="">Years of experience</option>
                                    <option value="0" <?php echo (isset($_POST['experience']) && $_POST['experience'] == '0') ? 'selected' : ''; ?>>Fresh Graduate</option>
                                    <option value="1" <?php echo (isset($_POST['experience']) && $_POST['experience'] == '1') ? 'selected' : ''; ?>>1 Year</option>
                                    <option value="2" <?php echo (isset($_POST['experience']) && $_POST['experience'] == '2') ? 'selected' : ''; ?>>2 Years</option>
                                    <option value="3" <?php echo (isset($_POST['experience']) && $_POST['experience'] == '3') ? 'selected' : ''; ?>>3 Years</option>
                                    <option value="5" <?php echo (isset($_POST['experience']) && $_POST['experience'] == '5') ? 'selected' : ''; ?>>5+ Years</option>
                                    <option value="10" <?php echo (isset($_POST['experience']) && $_POST['experience'] == '10') ? 'selected' : ''; ?>>10+ Years</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Security Question -->
                    <div class="form-group">
                        <label><i class="fas fa-shield-alt"></i> Security Question</label>
                        <select name="security_question" class="form-control" required>
                            <option value="">Select a security question</option>
                            <option value="What is your mother's maiden name?" <?php echo (isset($_POST['security_question']) && $_POST['security_question'] == "What is your mother's maiden name?") ? 'selected' : ''; ?>>What is your mother's maiden name?</option>
                            <option value="What was the name of your first pet?" <?php echo (isset($_POST['security_question']) && $_POST['security_question'] == "What was the name of your first pet?") ? 'selected' : ''; ?>>What was the name of your first pet?</option>
                            <option value="In what city were you born?" <?php echo (isset($_POST['security_question']) && $_POST['security_question'] == "In what city were you born?") ? 'selected' : ''; ?>>In what city were you born?</option>
                            <option value="What is your favorite movie?" <?php echo (isset($_POST['security_question']) && $_POST['security_question'] == "What is your favorite movie?") ? 'selected' : ''; ?>>What is your favorite movie?</option>
                            <option value="What is your favorite subject?" <?php echo (isset($_POST['security_question']) && $_POST['security_question'] == "What is your favorite subject?") ? 'selected' : ''; ?>>What is your favorite subject?</option>
                            <option value="What is your dream career?" <?php echo (isset($_POST['security_question']) && $_POST['security_question'] == "What is your dream career?") ? 'selected' : ''; ?>>What is your dream career?</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-answer"></i> Answer to Security Question</label>
                        <input type="text" name="security_answer" class="form-control" placeholder="Enter your answer" required
                               value="<?php echo isset($_POST['security_answer']) && isset($_POST['signup']) ? htmlspecialchars($_POST['security_answer']) : ''; ?>">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> Password</label>
                            <div class="password-toggle">
                                <input type="password" name="password" class="form-control" id="signup-password" placeholder="Min 6 characters" required>
                                <button type="button" class="password-toggle-btn" onclick="togglePassword('signup-password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> Confirm Password</label>
                            <div class="password-toggle">
                                <input type="password" name="confirm_password" class="form-control" id="confirm-password" placeholder="Confirm password" required>
                                <button type="button" class="password-toggle-btn" onclick="togglePassword('confirm-password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="signup" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i>
                        Create Account
                    </button>
                </form>
            </div>

            <!-- Forgot Password Modal -->
            <div id="forgot-modal" class="forgot-modal" style="display: <?php echo ($forgot_step > 1 || isset($_POST['forgot_password'])) ? 'flex' : 'none'; ?>;">
                <div class="modal-content">
                    <button class="modal-close" onclick="closeForgotModal()">&times;</button>
                    
                    <h2 style="text-align: center; margin-bottom: 20px; color: #333;">
                        <i class="fas fa-key"></i> Reset Password
                    </h2>

                    <?php if ($error && (isset($_POST['forgot_password']) || isset($_POST['verify_security']) || isset($_POST['reset_password']))): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($success && $forgot_step > 1): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <?php echo $success; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Step 1: Email Verification -->
                    <?php if ($forgot_step == 1): ?>
                    <form method="POST">
                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Email Address</label>
                            <input type="email" name="email" class="form-control" placeholder="Enter your registered email" required>
                        </div>

                        <button type="submit" name="forgot_password" class="btn btn-primary btn-block">
                            <i class="fas fa-arrow-right"></i>
                            Next
                        </button>

                        <div class="forgot-link" style="text-align: center; margin-top: 12px;">
                            <a href="javascript:void(0)" onclick="closeForgotModal()">
                                <i class="fas fa-times-circle"></i> Close
                            </a>
                        </div>
                    </form>
                    <?php endif; ?>

                    <!-- Step 2: Security Question Verification -->
                    <?php if ($forgot_step == 2): ?>
                    <form method="POST">
                        <div class="form-group">
                            <label><i class="fas fa-question-circle"></i> Security Question</label>
                            <p style="background: #f0f0f0; padding: 12px; border-radius: 8px; margin-bottom: 16px; color: #333; font-weight: 500;">
                                <?php echo htmlspecialchars($_SESSION['reset_security_question'] ?? ''); ?>
                            </p>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-answer"></i> Your Answer</label>
                            <input type="text" name="security_answer" class="form-control" placeholder="Enter your answer" required>
                        </div>

                        <button type="submit" name="verify_security" class="btn btn-primary btn-block">
                            <i class="fas fa-check"></i>
                            Verify Answer
                        </button>

                        <div class="forgot-link" style="text-align: center; margin-top: 12px;">
                            <a href="javascript:void(0)" onclick="resetForgotPassword()">
                                <i class="fas fa-arrow-left"></i> Back to Email
                            </a>
                        </div>
                    </form>
                    <?php endif; ?>

                    <!-- Step 3: Password Reset -->
                    <?php if ($forgot_step == 3): ?>
                    <form method="POST">
                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> New Password</label>
                            <div class="password-toggle">
                                <input type="password" name="new_password" class="form-control" id="reset-password" placeholder="Min 6 characters" required>
                                <button type="button" class="password-toggle-btn" onclick="togglePassword('reset-password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> Confirm New Password</label>
                            <div class="password-toggle">
                                <input type="password" name="confirm_password" class="form-control" id="reset-confirm-password" placeholder="Confirm your password" required>
                                <button type="button" class="password-toggle-btn" onclick="togglePassword('reset-confirm-password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" name="reset_password" class="btn btn-primary btn-block">
                            <i class="fas fa-shield-alt"></i>
                            Reset Password
                        </button>

                        <div class="forgot-link" style="text-align: center; margin-top: 12px;">
                            <a href="javascript:void(0)" onclick="closeForgotModal()">
                                <i class="fas fa-times-circle"></i> Close
                            </a>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            </div>
        </div>

        <div class="features-grid">
            <div class="feature-card">
                <i class="fas fa-book-reader"></i>
                <h3>Smart Learning</h3>
                <p>Access notes, assignments, and study materials anytime</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-chart-line"></i>
                <h3>Track Progress</h3>
                <p>Monitor attendance and performance metrics</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-users"></i>
                <h3>Collaborative</h3>
                <p>Connect with teachers and classmates seamlessly</p>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
                content.style.display = 'none';
            });

            document.querySelector(`[onclick="switchTab('${tabName}')"]`).classList.add('active');
            const activeTab = document.getElementById(`${tabName}-tab`);
            if (activeTab) {
                activeTab.classList.add('active');
                activeTab.style.display = 'block';
            }
        }
        
        function showForgotPassword() {
            document.getElementById('forgot-modal').style.display = 'flex';
        }
        
        function closeForgotModal() {
            document.getElementById('forgot-modal').style.display = 'none';
            // Reset forgot password session
            fetch('index.php?reset_forgot=1', {method: 'GET'}).then(() => {
                location.reload();
            });
        }
        
        function resetForgotPassword() {
            // Reset forgot password session variables via AJAX
            fetch('index.php?reset_forgot=1', {method: 'GET'}).then(() => {
                location.reload();
            });
        }

        function toggleRoleFields() {
            const studentFields = document.getElementById('student-fields');
            const teacherFields = document.getElementById('teacher-fields');
            const roleCards = document.querySelectorAll('.role-card');

            studentFields.classList.remove('show');
            teacherFields.classList.remove('show');
            roleCards.forEach(card => card.classList.remove('selected'));

            const selectedRole = document.querySelector('input[name="role"]:checked');
            if (!selectedRole) return;

            const role = selectedRole.value;
            selectedRole.closest('.role-card').classList.add('selected');

            if (role === 'Student') {
                studentFields.classList.add('show');
                const courseSel = document.querySelector('select[name="course"]'); if (courseSel) courseSel.setAttribute('required','required');
                const rollSel = document.querySelector('input[name="rollno"]'); if (rollSel) rollSel.setAttribute('required','required');
                const ageSel = document.querySelector('input[name="age"]'); if (ageSel) ageSel.setAttribute('required','required');
                const cySel = document.querySelector('input[name="course_year"]'); if (cySel) cySel.setAttribute('required','required');
                document.querySelector('select[name="subject"]').removeAttribute('required');
                document.querySelector('select[name="qualification"]').removeAttribute('required');
            } else if (role === 'Teacher') {
                teacherFields.classList.add('show');
                document.querySelector('select[name="subject"]').setAttribute('required', 'required');
                document.querySelector('select[name="qualification"]').setAttribute('required', 'required');
                const courseSel = document.querySelector('select[name="course"]'); if(courseSel) courseSel.removeAttribute('required');
                const rollSel = document.querySelector('input[name="rollno"]'); if(rollSel) rollSel.removeAttribute('required');
                const ageSel = document.querySelector('input[name="age"]'); if(ageSel) ageSel.removeAttribute('required');
                const cySel = document.querySelector('input[name="course_year"]'); if(cySel) cySel.removeAttribute('required');
            }
        }

        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const btn = input.nextElementSibling;
            const icon = btn.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const selectedRole = document.querySelector('input[name="role"]:checked');
            if (selectedRole) {
                toggleRoleFields();
            }
        });

        const signupForm = document.getElementById('signup-form');
        if (signupForm) {
            signupForm.addEventListener('submit', function(e) {
                const passwordField = signupForm.querySelector('input[name="password"]');
                const confirmPasswordField = signupForm.querySelector('input[name="confirm_password"]');
                
                if (!passwordField || !confirmPasswordField) {
                    return true;
                }
                
                const password = passwordField.value;
                const confirmPassword = confirmPasswordField.value;

                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('Passwords do not match!');
                    return false;
                }

                if (password.length < 6) {
                    e.preventDefault();
                    alert('Password must be at least 6 characters long!');
                    return false;
                }
            });
        }
    </script>
</body>
</html>
