<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('index.php');
}

$user = getCurrentUser();
$response = array('success' => false, 'message' => '');

// Create upload directories if they don't exist
$upload_dirs = ['uploads/notes', 'uploads/assignments', 'uploads/locker', 'uploads/profile', 'uploads/payments', 'uploads/qr'];
foreach ($upload_dirs as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
}

// DIGITAL LOCKER - Upload file
if (isset($_POST['upload_locker'])) {
    if (isset($_FILES['file']) && $_FILES['file']['error'] === 0) {
        $file = $_FILES['file'];
        $subject = sanitize($_POST['subject']);
        
        if (!isAllowedFile($file['name'])) {
            $_SESSION['error'] = 'Invalid file type. Allowed: PDF, DOC, DOCX, JPG, PNG';
        } elseif ($file['size'] > 10485760) { // 10MB
            $_SESSION['error'] = 'File size must be less than 10MB';
        } else {
            $filename = time() . '_' . basename($file['name']);
            $filepath = 'uploads/locker/' . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $query = "INSERT INTO digital_locker (user_id, file_name, file_path, file_size, subject) VALUES (?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, 'issis', $user['id'], $file['name'], $filepath, $file['size'], $subject);
                
                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['success'] = 'File uploaded successfully';
                } else {
                    $_SESSION['error'] = 'Failed to save file information';
                }
            } else {
                $_SESSION['error'] = 'Failed to upload file';
            }
        }
    }
    redirect('dashboard.php?section=locker');
}

// DIGITAL LOCKER - Delete file
if (isset($_GET['delete_locker'])) {
    $file_id = intval($_GET['delete_locker']);
    $query = "SELECT * FROM digital_locker WHERE id = ? AND user_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'ii', $file_id, $user['id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($file = mysqli_fetch_assoc($result)) {
        if (file_exists($file['file_path'])) {
            unlink($file['file_path']);
        }
        $query = "DELETE FROM digital_locker WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'i', $file_id);
        mysqli_stmt_execute($stmt);
        $_SESSION['success'] = 'File deleted successfully';
    }
    redirect('dashboard.php?section=locker');
}

// NOTES - Upload (Teacher only)
if (isset($_POST['upload_note']) && hasRole('Teacher')) {
    if (isset($_FILES['file']) && $_FILES['file']['error'] === 0) {
        $file = $_FILES['file'];
        $title = sanitize($_POST['title']);
        $subject = sanitize($_POST['subject']);
        $course = sanitize($_POST['course']);
        
        if (!isAllowedFile($file['name'])) {
            $_SESSION['error'] = 'Invalid file type';
        } else {
            $filename = time() . '_' . basename($file['name']);
            $filepath = 'uploads/notes/' . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $query = "INSERT INTO notes (title, subject, course, file_name, file_path, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, 'sssssi', $title, $subject, $course, $file['name'], $filepath, $user['id']);
                mysqli_stmt_execute($stmt);
                $_SESSION['success'] = 'Note uploaded successfully';                // Notify students in the stream
                if ($course === 'All') {
                    $students = mysqli_query($conn, "SELECT id FROM users WHERE role = 'Student'");
                } else {
                    $safe_stream = mysqli_real_escape_string($conn, $course);
                    $students = mysqli_query($conn, "SELECT id FROM users WHERE role = 'Student' AND course = '$safe_stream'");
                }
                while ($s = mysqli_fetch_assoc($students)) {
                    createNotification($s['id'], 'New Note: ' . $title, "A new note has been uploaded for $subject: $title", 'info', 'dashboard.php?section=notes');
                }            }
        }
    }
    redirect('dashboard.php?section=notes');
}

// NOTES - Delete (Teacher only)
if (isset($_GET['delete_note']) && hasRole('Teacher')) {
    $note_id = intval($_GET['delete_note']);
    $query = "SELECT * FROM notes WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $note_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($note = mysqli_fetch_assoc($result)) {
        if (file_exists($note['file_path'])) {
            unlink($note['file_path']);
        }
        $query = "DELETE FROM notes WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'i', $note_id);
        mysqli_stmt_execute($stmt);
        $_SESSION['success'] = 'Note deleted successfully';
    }
    redirect('dashboard.php?section=notes');
}

// ASSIGNMENT - Create (Teacher only)
if (isset($_POST['create_assignment']) && hasRole('Teacher')) {
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    $subject = sanitize($_POST['subject']);
    $course = sanitize($_POST['course']);
    $due_date = $_POST['due_date'];
    
    $query = "INSERT INTO assignments (title, description, subject, course, due_date, created_by) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'sssssi', $title, $description, $subject, $course, $due_date, $user['id']);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = 'Assignment created successfully';
        // Notify students in the target stream
        if ($course === 'All') {
            $students = mysqli_query($conn, "SELECT id FROM users WHERE role = 'Student'");
        } else {
            $safe_stream = mysqli_real_escape_string($conn, $course);
            $students = mysqli_query($conn, "SELECT id FROM users WHERE role = 'Student' AND course = '$safe_stream'");
        }
        while ($s = mysqli_fetch_assoc($students)) {
            createNotification($s['id'], 'New Assignment: ' . $title, "Assignment: $title for $subject is posted. Due: $due_date", 'info', 'dashboard.php?section=assignments');
        }
    } else {
        $_SESSION['error'] = 'Failed to create assignment';
    }
    redirect('dashboard.php?section=assignments');
}

// ASSIGNMENT - Submit (Student only)
if (isset($_POST['submit_assignment']) && hasRole('Student')) {
    if (isset($_FILES['file']) && $_FILES['file']['error'] === 0) {
        $file = $_FILES['file'];
        $assignment_id = intval($_POST['assignment_id']);
        
        // Check if assignment exists and get due date
        $query = "SELECT * FROM assignments WHERE id = ? AND course = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'is', $assignment_id, $user['course']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($assignment = mysqli_fetch_assoc($result)) {
            $is_late = (strtotime($assignment['due_date']) < time()) ? 1 : 0;
            
            if (!isAllowedFile($file['name'])) {
                $_SESSION['error'] = 'Invalid file type';
            } else {
                $filename = time() . '_' . $user['id'] . '_' . basename($file['name']);
                $filepath = 'uploads/assignments/' . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    // Check if already submitted
                    $query = "SELECT id FROM assignment_submissions WHERE assignment_id = ? AND student_id = ?";
                    $stmt = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmt, 'ii', $assignment_id, $user['id']);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    
                    if (mysqli_num_rows($result) > 0) {
                        // Update existing submission (only if not late)
                        if (!$is_late) {
                            $existing = mysqli_fetch_assoc($result);
                            if (file_exists($existing['file_path'])) {
                                unlink($existing['file_path']);
                            }
                            $query = "UPDATE assignment_submissions SET file_name = ?, file_path = ?, submitted_at = NOW() WHERE assignment_id = ? AND student_id = ?";
                            $stmt = mysqli_prepare($conn, $query);
                            mysqli_stmt_bind_param($stmt, 'ssii', $file['name'], $filepath, $assignment_id, $user['id']);
                            mysqli_stmt_execute($stmt);
                            $_SESSION['success'] = 'Assignment re-submitted successfully';
                        } else {
                            $_SESSION['error'] = 'Cannot re-submit after due date';
                        }
                    } else {
                        // New submission
                        $query = "INSERT INTO assignment_submissions (assignment_id, student_id, file_name, file_path, is_late) VALUES (?, ?, ?, ?, ?)";
                        $stmt = mysqli_prepare($conn, $query);
                        mysqli_stmt_bind_param($stmt, 'iissi', $assignment_id, $user['id'], $file['name'], $filepath, $is_late);
                        mysqli_stmt_execute($stmt);
                        $_SESSION['success'] = $is_late ? 'Assignment submitted (Late)' : 'Assignment submitted successfully';
                    }
                }
            }
        }
    }
    redirect('dashboard.php?section=assignments');
}

// ASSIGNMENT - Delete (Teacher only)
if (isset($_GET['delete_assignment']) && hasRole('Teacher')) {
    $assignment_id = intval($_GET['delete_assignment']);
    $query = "DELETE FROM assignments WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $assignment_id);
    mysqli_stmt_execute($stmt);
    $_SESSION['success'] = 'Assignment deleted successfully';
    redirect('dashboard.php?section=assignments');
}

// TEST - Create (Teacher only)
if (isset($_POST['create_test']) && hasRole('Teacher')) {
    $title = sanitize($_POST['title']);
    $subject = sanitize($_POST['subject']);
    $course = sanitize($_POST['course']);
    $time_limit = intval($_POST['time_limit']);
    $pass_percentage = intval($_POST['pass_percentage']);
    $allow_multiple = isset($_POST['allow_multiple']) ? 1 : 0;
    $status = sanitize($_POST['test_status']);
    
    // Insert test
    $query = "INSERT INTO tests (title, subject, course, time_limit, total_marks, pass_marks, allow_multiple_attempts, status, created_by) VALUES (?, ?, ?, ?, 0, 0, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'sssiisi', $title, $subject, $course, $time_limit, $allow_multiple, $status, $user['id']);
    if (mysqli_stmt_execute($stmt)) {
        $test_id = mysqli_insert_id($conn);
        // Notify students in stream about new test
        if ($course === 'All') {
            $students = mysqli_query($conn, "SELECT id FROM users WHERE role = 'Student'");
        } else {
            $safe_stream = mysqli_real_escape_string($conn, $course);
            $students = mysqli_query($conn, "SELECT id FROM users WHERE role = 'Student' AND course = '$safe_stream'");
        }
        while ($s = mysqli_fetch_assoc($students)) {
            createNotification($s['id'], 'New Test: ' . $title, "A new test '$title' has been scheduled. Please check the Tests section.", 'info', 'dashboard.php?section=tests');
        }
        
        // Insert questions
        $total_marks = 0;
        if (isset($_POST['questions']) && is_array($_POST['questions'])) {
            foreach ($_POST['questions'] as $q) {
                $question = sanitize($q['question']);
                $option_a = sanitize($q['option_a']);
                $option_b = sanitize($q['option_b']);
                $option_c = sanitize($q['option_c']);
                $option_d = sanitize($q['option_d']);
                $correct = sanitize($q['correct']);
                $marks = intval($q['marks']);
                $total_marks += $marks;
                
                $query = "INSERT INTO test_questions (test_id, question, option_a, option_b, option_c, option_d, correct_answer, marks) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, 'issssssi', $test_id, $question, $option_a, $option_b, $option_c, $option_d, $correct, $marks);
                mysqli_stmt_execute($stmt);
            }
        }
        
        // Update total marks and pass marks
        $pass_marks = ($total_marks * $pass_percentage) / 100;
        $query = "UPDATE tests SET total_marks = ?, pass_marks = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'iii', $total_marks, $pass_marks, $test_id);
        mysqli_stmt_execute($stmt);
        
        $_SESSION['success'] = 'Test ' . ($status === 'draft' ? 'saved as draft' : 'published') . ' successfully';
    } else {
        $_SESSION['error'] = 'Failed to create test';
    }
    redirect('dashboard.php?section=tests');
}



// TEST - Update Status (Teacher only)
if (isset($_POST['update_test_status']) && hasRole('Teacher')) {
    $test_id = intval($_POST['test_id']);
    $status = sanitize($_POST['test_status']);
    
    $query = "UPDATE tests SET status = ?, scheduled_date = NULL WHERE id = ? AND created_by = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'sii', $status, $test_id, $user['id']);
    mysqli_stmt_execute($stmt);
    
    $_SESSION['success'] = 'Test ' . ($status === 'published' ? 'published' : 'saved as draft') . ' successfully';
    redirect('dashboard.php?section=tests');
}

// TEST - Delete (Teacher only)
if (isset($_GET['delete_test']) && hasRole('Teacher')) {
    $test_id = intval($_GET['delete_test']);
    $query = "DELETE FROM tests WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $test_id);
    mysqli_stmt_execute($stmt);
    $_SESSION['success'] = 'Test deleted successfully';
    redirect('dashboard.php?section=tests');
}

// NOTICE - Create (Admin/Teacher only)
if (isset($_POST['create_notice']) && (hasRole('Admin') || hasRole('Teacher'))) {
    $title = sanitize($_POST['title']);
    $content = sanitize($_POST['content']);
    $course = sanitize($_POST['course']);
    $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;
    
    $query = "INSERT INTO notices (title, content, course, posted_by, is_pinned) VALUES (?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'sssii', $title, $content, $course, $user['id'], $is_pinned);
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = 'Notice posted successfully';
    } else {
        $_SESSION['error'] = 'Failed to post notice: ' . mysqli_error($conn);
    }
    redirect('dashboard.php?section=notices');
}

// NOTICE - Delete (Admin/Teacher only)
if (isset($_GET['delete_notice']) && (hasRole('Admin') || hasRole('Teacher'))) {
    $notice_id = intval($_GET['delete_notice']);
    $query = "DELETE FROM notices WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $notice_id);
    mysqli_stmt_execute($stmt);
    $_SESSION['success'] = 'Notice deleted successfully';
    redirect('dashboard.php?section=notices');
}

// ADMIN - Add User
if (isset($_POST['add_user']) && hasRole('Admin')) {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = sanitize($_POST['role']);
    $course = ($role === 'Student') ? sanitize($_POST['course']) : NULL;
    
    $query = "INSERT INTO users (name, email, password, role, course, status) VALUES (?, ?, ?, ?, ?, 'approved')";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'sssss', $name, $email, $password, $role, $course);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = 'User added successfully';
    } else {
        $_SESSION['error'] = 'Email already exists';
    }
    redirect('dashboard.php?section=users');
}

// ADMIN - Delete User
if (isset($_GET['delete_user']) && hasRole('Admin')) {
    $user_id = intval($_GET['delete_user']);
    if ($user_id !== $user['id']) {
        $query = "DELETE FROM users WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        $_SESSION['success'] = 'User deleted successfully';
    }
    redirect('dashboard.php?section=users');
}

// ADMIN - Update Role
if (isset($_POST['update_role']) && hasRole('Admin')) {
    $user_id = intval($_POST['user_id']);
    $new_role = sanitize($_POST['role']);
    
    $query = "UPDATE users SET role = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'si', $new_role, $user_id);
    mysqli_stmt_execute($stmt);
    $_SESSION['success'] = 'Role updated successfully';
    redirect('dashboard.php?section=users');
}

// ADMIN - Approve User
if (isset($_POST['approve_user']) && hasRole('Admin')) {
    $user_id = intval($_POST['user_id']);
    
    $query = "UPDATE users SET status = 'approved' WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $_SESSION['success'] = 'User approved successfully';
    redirect('dashboard.php?section=users');
}

// ADMIN - Reject User
if (isset($_POST['reject_user']) && hasRole('Admin')) {
    $user_id = intval($_POST['user_id']);
    
    $query = "UPDATE users SET status = 'rejected' WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $_SESSION['success'] = 'User rejected successfully';
    redirect('dashboard.php?section=users');
}

// CHANGE PASSWORD
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $_SESSION['error'] = 'All fields are required';
    } elseif ($new_password !== $confirm_password) {
        $_SESSION['error'] = 'New passwords do not match';
    } elseif (strlen($new_password) < 6) {
        $_SESSION['error'] = 'New password must be at least 6 characters';
    } elseif (!password_verify($current_password, $user['password'])) {
        $_SESSION['error'] = 'Current password is incorrect';
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $query = "UPDATE users SET password = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'si', $hashed_password, $user['id']);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = 'Password changed successfully';
        } else {
            $_SESSION['error'] = 'Failed to change password';
        }
    }
    redirect('dashboard.php?section=change_password');
}

// LOGOUT
if (isset($_GET['logout'])) {
    session_destroy();
    redirect('index.php');
}

// UPDATE PROFILE
if (isset($_POST['update_profile'])) {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');

    // Load latest user data
    $u_stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
    mysqli_stmt_bind_param($u_stmt, 'i', $user['id']);
    mysqli_stmt_execute($u_stmt);
    $u_res = mysqli_stmt_get_result($u_stmt);
    $current = mysqli_fetch_assoc($u_res);

    // Check if email is already taken by another user
    $query = "SELECT id FROM users WHERE email = ? AND id != ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'si', $email, $user['id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        $_SESSION['error'] = 'Email already taken by another user';
    } else {
        $errors = [];
        $updates = [];
        $types = '';
        $params = [];

        // Name: cannot be changed once filled
        if (!empty($current['name']) && $current['name'] !== $name) {
            $errors[] = 'Name cannot be changed once set';
        } elseif (empty($current['name']) && !empty($name)) {
            $updates[] = 'name = ?'; $types .= 's'; $params[] = $name;
        }

        // Email can be changed
        if ($current['email'] !== $email) {
            $updates[] = 'email = ?'; $types .= 's'; $params[] = $email;
        }

        if ($user['role'] === 'Student') {
            $course = sanitize($_POST['course'] ?? '');
            $rollno = sanitize($_POST['rollno'] ?? '');
            $age = intval($_POST['age'] ?? 0);
            $course_year = sanitize($_POST['course_year'] ?? '');
            $phone = sanitize($_POST['phone'] ?? '');
            $address = sanitize($_POST['address'] ?? '');

            // course/rollno/age/course_year immutability
            if (!empty($current['course']) && $current['course'] !== $course) {
                $errors[] = 'Course cannot be changed once set';
            } elseif (empty($current['course']) && !empty($course)) {
                $updates[] = 'course = ?'; $types .= 's'; $params[] = $course;
            }
            if (!empty($current['rollno']) && $current['rollno'] !== $rollno) {
                $errors[] = 'Roll Number cannot be changed once set';
            } elseif (empty($current['rollno']) && !empty($rollno)) {
                // ensure unique
                $chk = mysqli_prepare($conn, "SELECT id FROM users WHERE rollno = ? AND course = ? AND id != ?");
                mysqli_stmt_bind_param($chk, 'ssi', $rollno, $course, $user['id']);
                mysqli_stmt_execute($chk);
                $chkres = mysqli_stmt_get_result($chk);
                if (mysqli_num_rows($chkres) > 0) {
                    $errors[] = 'Roll Number already exists for this course';
                } else {
                    $updates[] = 'rollno = ?'; $types .= 's'; $params[] = $rollno;
                }
            }
            if (!empty($current['age']) && intval($current['age']) !== $age) {
                $errors[] = 'Age cannot be changed once set';
            } elseif (empty($current['age']) && $age > 0) {
                $updates[] = 'age = ?'; $types .= 'i'; $params[] = $age;
            }
            if (!empty($current['course_year']) && $current['course_year'] !== $course_year) {
                $errors[] = 'Course year cannot be changed once set';
            } elseif (empty($current['course_year']) && !empty($course_year)) {
                $updates[] = 'course_year = ?'; $types .= 's'; $params[] = $course_year;
            }

            // Phone: allow up to 4 changes
            if ($phone !== $current['phone']) {
                $phone_changes = intval($current['phone_changes'] ?? 0);
                if ($phone_changes >= 4) {
                    $errors[] = 'Phone number change limit reached (4)';
                } else {
                    $updates[] = 'phone = ?'; $types .= 's'; $params[] = $phone;
                    $updates[] = 'phone_changes = phone_changes + 1';
                }
            }

            // Address: allow up to 4 changes
            if ($address !== $current['address']) {
                $address_changes = intval($current['address_changes'] ?? 0);
                if ($address_changes >= 4) {
                    $errors[] = 'Address change limit reached (4)';
                } else {
                    $updates[] = 'address = ?'; $types .= 's'; $params[] = $address;
                    $updates[] = 'address_changes = address_changes + 1';
                }
            }
        } elseif ($user['role'] === 'Teacher') {
            $subject = sanitize($_POST['subject'] ?? '');
            $qualification = sanitize($_POST['qualification'] ?? '');
            $experience = intval($_POST['experience'] ?? 0);
            $updates[] = 'subject = ?'; $types .= 's'; $params[] = $subject;
            $updates[] = 'qualification = ?'; $types .= 's'; $params[] = $qualification;
            $updates[] = 'experience = ?'; $types .= 'i'; $params[] = $experience;
        }

        if (!empty($errors)) {
            $_SESSION['error'] = implode(' | ', $errors);
        } elseif (!empty($updates)) {
            $sql = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?';
            $params[] = $user['id'];
            $types .= 'i';
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, $types, ...$params);
                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['success'] = 'Profile updated successfully';
                    unset($_SESSION['user_data']); unset($_SESSION['user_cache_time']);
                } else {
                    $_SESSION['error'] = 'Failed to update profile';
                }
            } else {
                $_SESSION['error'] = 'Database error: ' . mysqli_error($conn);
            }
        } else {
            $_SESSION['success'] = 'No changes detected';
        }
    }
    redirect('dashboard.php');
}

// PROFILE PHOTO - Upload
if (isset($_POST['upload_profile'])) {
    if (!isset($_FILES['profile_photo'])) {
        $_SESSION['error'] = 'No file selected';
    } elseif ($_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE => 'File is too large (server limit)',
            UPLOAD_ERR_FORM_SIZE => 'File is too large (form limit)',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was selected',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        $_SESSION['error'] = $upload_errors[$_FILES['profile_photo']['error']] ?? 'Unknown upload error';
    } else {
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profile_photo']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed_types)) {
            $_SESSION['error'] = 'Invalid file type. Only JPG, PNG, GIF allowed';
        } else if ($_FILES['profile_photo']['size'] > 5 * 1024 * 1024) {
            $_SESSION['error'] = 'File is too large (max 5MB)';
        } else {
            $new_filename = 'profile_' . $user['id'] . '_' . time() . '.' . $ext;
            $target_path = 'uploads/profile/' . $new_filename;
            
            // Ensure directory exists
            if (!is_dir('uploads/profile')) {
                @mkdir('uploads/profile', 0755, true);
            }
            
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target_path)) {
                // Get old profile photo to delete it
                $old_photo_query = "SELECT profile_photo FROM users WHERE id = ?";
                $stmt = mysqli_prepare($conn, $old_photo_query);
                mysqli_stmt_bind_param($stmt, 'i', $user['id']);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $old_user = mysqli_fetch_assoc($result);
                
                // Update user profile_photo
                $query = "UPDATE users SET profile_photo = ? WHERE id = ?";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, 'si', $new_filename, $user['id']);
                
                if (mysqli_stmt_execute($stmt)) {
                    // Delete old profile photo if it exists
                    if ($old_user['profile_photo']) {
                        @unlink('uploads/profile/' . $old_user['profile_photo']);
                    }
                    // Clear session cache to force refresh
                    unset($_SESSION['user_data']);
                    unset($_SESSION['user_cache_time']);
                    $_SESSION['success'] = 'Profile photo updated successfully';
                } else {
                    $_SESSION['error'] = 'Failed to update profile photo in database: ' . mysqli_error($conn);
                }
            } else {
                $_SESSION['error'] = 'Failed to upload file to server. Check permissions on uploads/profile directory';
            }
        }
    }
    redirect('dashboard.php');
}

// PROFILE PHOTO - Remove
if (isset($_POST['remove_profile'])) {
    // Get the old photo to delete it
    $old_photo_query = "SELECT profile_photo FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $old_photo_query);
    mysqli_stmt_bind_param($stmt, 'i', $user['id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $old_user = mysqli_fetch_assoc($result);
    
    $query = "UPDATE users SET profile_photo = NULL WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $user['id']);
    
    if (mysqli_stmt_execute($stmt)) {
        // Delete the physical file
        if ($old_user && $old_user['profile_photo']) {
            @unlink('uploads/profile/' . $old_user['profile_photo']);
        }
        // Clear session cache to force refresh
        unset($_SESSION['user_data']);
        unset($_SESSION['user_cache_time']);
        $_SESSION['success'] = 'Profile photo removed successfully';
    } else {
        $_SESSION['error'] = 'Failed to remove profile photo';
    }
    redirect('dashboard.php');
}

// ATTENDANCE - Removed (feature disabled by admin)
// Former handler removed to disable attendance marking.

// GRADES - Removed (feature disabled by admin)
// Grade add/delete handlers removed.

// TIMETABLE - Add (Admin/Teacher only)
if (isset($_POST['add_timetable']) && (hasRole('Admin') || hasRole('Teacher'))) {
    $day = sanitize($_POST['day']);
    $time_slot = sanitize($_POST['time_slot']);
    $subject = sanitize($_POST['subject']);
    $teacher_id = intval($_POST['teacher_id']);
    $course = sanitize($_POST['course']);
    $room_number = sanitize($_POST['room_number']);
    
    $query = "INSERT INTO timetable (day, time_slot, subject, teacher_id, course, room_number) 
              VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'sssiss', $day, $time_slot, $subject, $teacher_id, $course, $room_number);
    
    if (mysqli_stmt_execute($stmt)) {
        logActivity($user['id'], 'Add Timetable', "Added timetable entry for $subject");
        $_SESSION['success'] = 'Timetable entry added successfully';
    } else {
        $_SESSION['error'] = 'Failed to add timetable entry';
    }
    redirect('dashboard.php?section=timetable');
}

// TIMETABLE - Delete (Admin/Teacher only)
if (isset($_GET['delete_timetable']) && (hasRole('Admin') || hasRole('Teacher'))) {
    $timetable_id = intval($_GET['delete_timetable']);
    $query = "DELETE FROM timetable WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $timetable_id);
    mysqli_stmt_execute($stmt);
    logActivity($user['id'], 'Delete Timetable', "Deleted timetable entry ID: $timetable_id");
    $_SESSION['success'] = 'Timetable entry deleted successfully';
    redirect('dashboard.php?section=timetable');
}

// MESSAGES - Send
if (isset($_POST['send_message'])) {
    $receiver_id = intval($_POST['receiver_id']);
    $message = sanitize($_POST['message']);
    
    if (empty($message)) {
        $_SESSION['error'] = 'Message cannot be empty';
    } else {
        $query = "INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'iis', $user['id'], $receiver_id, $message);
        
        if (mysqli_stmt_execute($stmt)) {
            logActivity($user['id'], 'Send Message', "Sent message to user ID: $receiver_id");
            $_SESSION['success'] = 'Message sent successfully';
        } else {
            $_SESSION['error'] = 'Failed to send message';
        }
    }
    redirect('dashboard.php?section=messages');
}

// MESSAGES - Mark as read
if (isset($_POST['mark_read'])) {
    $message_id = intval($_POST['message_id']);
    $query = "UPDATE messages SET is_read = 1 WHERE id = ? AND receiver_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'ii', $message_id, $user['id']);
    mysqli_stmt_execute($stmt);
    echo json_encode(['success' => true]);
    exit;
}

// MESSAGES - Delete
if (isset($_GET['delete_message'])) {
    $message_id = intval($_GET['delete_message']);
    $query = "DELETE FROM messages WHERE id = ? AND (sender_id = ? OR receiver_id = ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'iii', $message_id, $user['id'], $user['id']);
    mysqli_stmt_execute($stmt);
    $_SESSION['success'] = 'Message deleted successfully';
    redirect('dashboard.php?section=messages');
}

// RESOURCES - Add (Teacher/Admin only)
if (isset($_POST['add_resource']) && (hasRole('Teacher') || hasRole('Admin'))) {
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    $url = sanitize($_POST['url']);
    $type = sanitize($_POST['type']);
    $subject = sanitize($_POST['subject']);
    $course = sanitize($_POST['course']);
    
    $query = "INSERT INTO resources (title, description, url, type, subject, course, uploaded_by) 
              VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'ssssssi', $title, $description, $url, $type, $subject, $course, $user['id']);
    
    if (mysqli_stmt_execute($stmt)) {
        logActivity($user['id'], 'Add Resource', "Added resource: $title");
        $_SESSION['success'] = 'Resource added successfully';
        // Notify students in the stream
        if ($course === 'All') {
            $students = mysqli_query($conn, "SELECT id FROM users WHERE role = 'Student'");
        } else {
            $safe_stream = mysqli_real_escape_string($conn, $course);
            $students = mysqli_query($conn, "SELECT id FROM users WHERE role = 'Student' AND course = '$safe_stream'");
        }
        while ($s = mysqli_fetch_assoc($students)) {
            createNotification($s['id'], 'New Resource: ' . $title, "$title has been added to resources.", 'info', 'dashboard.php?section=resources');
        }
    } else {
        $_SESSION['error'] = 'Failed to add resource';
    }
    redirect('dashboard.php?section=resources');
}

// RESOURCES - Delete (Teacher/Admin only)
if (isset($_GET['delete_resource']) && (hasRole('Teacher') || hasRole('Admin'))) {
    $resource_id = intval($_GET['delete_resource']);
    $query = "DELETE FROM resources WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $resource_id);
    mysqli_stmt_execute($stmt);
    logActivity($user['id'], 'Delete Resource', "Deleted resource ID: $resource_id");
    $_SESSION['success'] = 'Resource deleted successfully';
    redirect('dashboard.php?section=resources');
}

// NOTIFICATIONS - Mark all as read
if (isset($_POST['mark_all_read'])) {
    mysqli_query($conn, "UPDATE notifications SET is_read = 1 WHERE user_id = {$user['id']}");
    $_SESSION['success'] = 'All notifications marked as read';
    redirect('dashboard.php?section=notifications');
}

// NOTIFICATIONS - Mark single as read
if (isset($_POST['mark_notif_read'])) {
    $notif_id = intval($_POST['notif_id']);
    mysqli_query($conn, "UPDATE notifications SET is_read = 1 WHERE id = $notif_id AND user_id = {$user['id']}");
    $_SESSION['success'] = 'Notification marked as read';
    redirect('dashboard.php?section=notifications');
}

// AJAX: Fetch latest notifications (returns JSON)
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_notifications') {
    $notes = array();
    $res = mysqli_query($conn, "SELECT * FROM notifications WHERE user_id = {$user['id']} ORDER BY created_at DESC LIMIT 10");
    while ($row = mysqli_fetch_assoc($res)) {
        $row['time_ago'] = timeAgo($row['created_at']);
        $notes[] = $row;
    }
    $cnt = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM notifications WHERE user_id = {$user['id']} AND is_read = 0"));
    header('Content-Type: application/json');
    echo json_encode(array('notifications' => $notes, 'unread' => intval($cnt['cnt'])));
    exit;
}

// AJAX: Mark single notification as read
if (isset($_POST['ajax_mark_notif_read'])) {
    $notif_id = intval($_POST['notif_id']);
    $stmt = mysqli_prepare($conn, "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $notif_id, $user['id']);
    mysqli_stmt_execute($stmt);
    header('Content-Type: application/json');
    echo json_encode(array('success' => true));
    exit;
}

// AJAX: Mark all notifications as read
if (isset($_POST['ajax_mark_all_read'])) {
    mysqli_query($conn, "UPDATE notifications SET is_read = 1 WHERE user_id = {$user['id']}");
    header('Content-Type: application/json');
    echo json_encode(array('success' => true));
    exit;
}

// AJAX: Delete a notification
if (isset($_POST['ajax_delete_notif'])) {
    $notif_id = intval($_POST['notif_id']);
    $stmt = mysqli_prepare($conn, "DELETE FROM notifications WHERE id = ? AND user_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $notif_id, $user['id']);
    mysqli_stmt_execute($stmt);
    header('Content-Type: application/json');
    echo json_encode(array('success' => true));
    exit;
}

// FORUM - Removed (feature disabled by admin)
// Forum create/reply/solution handlers removed.

// STUDY GROUPS - Removed (feature disabled by admin)
// Study group create/join/leave handlers removed.

// LIBRARY - Removed (feature disabled by admin)
if (isset($_POST['issue_book']) && $user['role'] === 'Student') {
    $_SESSION['error'] = 'Library feature has been disabled by the administrator';
    redirect('dashboard.php');
}

// LIBRARY - Removed (feature disabled by admin)
if (isset($_POST['return_book']) && $user['role'] === 'Student') {
    $_SESSION['error'] = 'Library feature has been disabled by the administrator';
    redirect('dashboard.php');
}

// ATTENDANCE MANAGEMENT (Teacher) - Removed (feature disabled by admin)
// Former handler removed to disable attendance marking.

// GRADES MANAGEMENT - Removed (feature disabled by admin)
// Grade management handler removed.

// ==== ADMIN CALENDAR MANAGEMENT ====
// Only Admin can add/edit/delete events

// CALENDAR - Add Event (Admin only)
if (isset($_POST['add_event']) && hasRole('Admin')) {
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    $event_type = sanitize($_POST['event_type']);
    $event_date = sanitize($_POST['event_date']);
    $start_time = !empty($_POST['start_time']) ? sanitize($_POST['start_time']) : NULL;
    $end_time = !empty($_POST['end_time']) ? sanitize($_POST['end_time']) : NULL;
    $location = sanitize($_POST['location']);
    $course = sanitize($_POST['course']);
    
    $query = "INSERT INTO events (title, description, event_type, event_date, start_time, end_time, location, course, created_by) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'ssssssssi', $title, $description, $event_type, $event_date, $start_time, $end_time, $location, $course, $user['id']);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = 'Event added successfully';
        logActivity($user['id'], 'Add Event', "Added event: $title");
        // Notify students in the targeted stream
        if ($course === 'All') {
            $students = mysqli_query($conn, "SELECT id FROM users WHERE role = 'Student'");
        } else {
            $safe_stream = mysqli_real_escape_string($conn, $course);
            $students = mysqli_query($conn, "SELECT id FROM users WHERE role = 'Student' AND course = '$safe_stream'");
        }
        while ($s = mysqli_fetch_assoc($students)) {
            createNotification($s['id'], 'New Event: ' . $title, "$title on $event_date - $event_type", 'info', 'dashboard.php?section=calendar');
        }
    } else {
        $_SESSION['error'] = 'Failed to add event';
    }
    redirect('dashboard.php?section=calendar_mgmt');
}

// CALENDAR - Edit Event (Admin only)
if (isset($_POST['edit_event']) && hasRole('Admin')) {
    $event_id = intval($_POST['event_id']);
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    $event_type = sanitize($_POST['event_type']);
    $event_date = sanitize($_POST['event_date']);
    $start_time = !empty($_POST['start_time']) ? sanitize($_POST['start_time']) : NULL;
    $end_time = !empty($_POST['end_time']) ? sanitize($_POST['end_time']) : NULL;
    $location = sanitize($_POST['location']);
    $course = sanitize($_POST['course']);
    
    $query = "UPDATE events SET title = ?, description = ?, event_type = ?, event_date = ?, start_time = ?, end_time = ?, location = ?, course = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'ssssssssi', $title, $description, $event_type, $event_date, $start_time, $end_time, $location, $course, $event_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = 'Event updated successfully';
        logActivity($user['id'], 'Edit Event', "Updated event ID: $event_id");
    } else {
        $_SESSION['error'] = 'Failed to update event';
    }
    redirect('dashboard.php?section=calendar_mgmt');
}

// CALENDAR - Delete Event (Admin only)
if (isset($_GET['delete_event']) && hasRole('Admin')) {
    $event_id = intval($_GET['delete_event']);
    $query = "DELETE FROM events WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $event_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = 'Event deleted successfully';
        logActivity($user['id'], 'Delete Event', "Deleted event ID: $event_id");
    } else {
        $_SESSION['error'] = 'Failed to delete event';
    }
    redirect('dashboard.php?section=calendar_mgmt');
}

// ==== TEACHER FEE MANAGEMENT ====
// Teachers can view and update fee records

// FEE - Update Payment (Teacher only)
if (isset($_POST['update_fee']) && hasRole('Teacher')) {
    $fee_id = intval($_POST['fee_id']);
    $paid_amount = floatval($_POST['paid_amount']);
    $payment_date = !empty($_POST['payment_date']) ? sanitize($_POST['payment_date']) : NULL;
    $transaction_id = sanitize($_POST['transaction_id']);
    $remarks = sanitize($_POST['remarks']);
    
    // Get current fee details
    $fee_query = mysqli_query($conn, "SELECT student_id, amount, paid_amount FROM fees WHERE id = $fee_id");
    $fee = mysqli_fetch_assoc($fee_query);
    
    if ($fee) {
        $student_to_notify = $fee['student_id'];
        $total_amount = $fee['amount'];
        $new_paid = $paid_amount;
        
        // Determine status
        if ($new_paid >= $total_amount) {
            $status = 'paid';
        } elseif ($new_paid > 0) {
            $status = 'partial';
        } else {
            $status = 'pending';
        }
        
        $query = "UPDATE fees SET paid_amount = ?, payment_date = ?, transaction_id = ?, status = ?, remarks = ?, updated_by = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'dssssii', $new_paid, $payment_date, $transaction_id, $status, $remarks, $user['id'], $fee_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = 'Fee record updated successfully';
            logActivity($user['id'], 'Update Fee', "Updated fee ID: $fee_id");
            // Notify student about fee update
            $status_msg = ucfirst($status);
            createNotification($student_to_notify, 'Fee Updated', "Your fee record (ID: $fee_id) status: $status_msg. Paid: ₹$new_paid", 'info', 'dashboard.php?section=fees');
        } else {
            $_SESSION['error'] = 'Failed to update fee record';
        }
    }
    redirect('dashboard.php?section=fees_mgmt');
}

// FEE - Add New Record (Teacher only)
if (isset($_POST['add_fee']) && hasRole('Teacher')) {
    $student_id = intval($_POST['student_id']);
    $fee_type = sanitize($_POST['fee_type']);
    $amount = floatval($_POST['amount']);
    $due_date = sanitize($_POST['due_date']);
    $remarks = sanitize($_POST['remarks']);
    
    $query = "INSERT INTO fees (student_id, fee_type, amount, due_date, remarks, updated_by) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'isdssi', $student_id, $fee_type, $amount, $due_date, $remarks, $user['id']);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = 'Fee record added successfully';
        logActivity($user['id'], 'Add Fee', "Added fee for student ID: $student_id");
        // Notify student about new fee
        createNotification($student_id, 'New Fee Assigned', "A new fee has been assigned: $fee_type - ₹$amount due on $due_date", 'warning', 'dashboard.php?section=fees');
    } else {
        $_SESSION['error'] = 'Failed to add fee record';
    }
    redirect('dashboard.php?section=fees_mgmt');
}

// FEE - Direct Cash Payment Entry (Teacher/Admin only)
if (isset($_POST['direct_cash_payment']) && hasAnyRole(['Teacher', 'Admin'])) {
    $student_id = intval($_POST['student_id']);
    $fee_id = isset($_POST['fee_id']) ? intval($_POST['fee_id']) : null;
    $fee_type = sanitize($_POST['fee_type']);
    $amount = floatval($_POST['amount']);
    $payment_date = sanitize($_POST['payment_date']);
    $receipt_number = sanitize($_POST['receipt_number'] ?? 'CASH-' . time());
    $remarks = sanitize($_POST['remarks'] ?? 'Cash payment received in office');
    
    // If fee_id is provided, update existing fee
    if ($fee_id) {
        // Get current fee details
        $fee_query = mysqli_query($conn, "SELECT * FROM fees WHERE id = $fee_id AND student_id = $student_id");
        $fee = mysqli_fetch_assoc($fee_query);
        
        if ($fee) {
            $new_paid = $fee['paid_amount'] + $amount;
            $status = ($new_paid >= $fee['amount']) ? 'paid' : 'partial';
            
            $query = "UPDATE fees SET paid_amount = ?, payment_date = ?, transaction_id = ?, status = ?, remarks = ?, updated_by = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'dssssii', $new_paid, $payment_date, $receipt_number, $status, $remarks, $user['id'], $fee_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success'] = "✓ Cash payment recorded successfully! Receipt: $receipt_number";
                logActivity($user['id'], 'Direct Cash Payment', "Recorded cash payment of ₹$amount for fee ID: $fee_id");
                createNotification($student_id, 'Fee Payment Received', "Your cash payment of ₹$amount has been recorded. Receipt: $receipt_number", 'success', 'dashboard.php?section=fees');
            } else {
                $_SESSION['error'] = 'Failed to record payment';
            }
        }
    } else {
        // Create new fee record with payment
        $query = "INSERT INTO fees (student_id, fee_type, amount, paid_amount, payment_date, transaction_id, status, remarks, updated_by) VALUES (?, ?, ?, ?, ?, ?, 'paid', ?, ?)";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'isddsssi', $student_id, $fee_type, $amount, $amount, $payment_date, $receipt_number, $remarks, $user['id']);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "✓ Cash payment recorded successfully! Receipt: $receipt_number";
            logActivity($user['id'], 'Direct Cash Payment', "Recorded cash payment of ₹$amount for student ID: $student_id");
            createNotification($student_id, 'Fee Payment Received', "Your cash payment of ₹$amount has been recorded. Receipt: $receipt_number", 'success', 'dashboard.php?section=fees');
        } else {
            $_SESSION['error'] = 'Failed to record payment';
        }
    }
    
    redirect('dashboard.php?section=fees_mgmt');
}

// STUDENT: Submit Fee Payment (upload screenshot/proof)
if (isset($_POST['submit_fee_payment']) && hasRole('Student')) {
    $fee_id = intval($_POST['fee_id']);
    $transaction_id = sanitize($_POST['transaction_id'] ?? '');

    // Verify fee belongs to student
    $fee_q = mysqli_prepare($conn, "SELECT * FROM fees WHERE id = ? AND student_id = ?");
    mysqli_stmt_bind_param($fee_q, 'ii', $fee_id, $user['id']);
    mysqli_stmt_execute($fee_q);
    $fee_res = mysqli_stmt_get_result($fee_q);

    if ($fee = mysqli_fetch_assoc($fee_res)) {
        if (isset($_FILES['payment_screenshot']) && $_FILES['payment_screenshot']['error'] === 0) {
            $file = $_FILES['payment_screenshot'];

            // allow images and pdf
            $allowed = ['jpg','jpeg','png','pdf'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed)) {
                $_SESSION['error'] = 'Invalid file type. Allowed: JPG, PNG, PDF';
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $_SESSION['error'] = 'File must be less than 5MB';
            } else {
                $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file['name']));
                $filepath = 'uploads/payments/' . $filename;

                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    // create payments table if not exists
                    $create_sql = "CREATE TABLE IF NOT EXISTS fee_payments (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        fee_id INT,
                        student_id INT,
                        file_path VARCHAR(255),
                        transaction_id VARCHAR(100),
                        status VARCHAR(50) DEFAULT 'pending',
                        uploaded_at DATETIME,
                        approved_by INT NULL,
                        approved_at DATETIME NULL,
                        rejection_reason TEXT NULL
                    )";
                    mysqli_query($conn, $create_sql);

                    $stmt = mysqli_prepare($conn, "INSERT INTO fee_payments (fee_id, student_id, file_path, transaction_id, status, uploaded_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
                    mysqli_stmt_bind_param($stmt, 'iiss', $fee_id, $user['id'], $filepath, $transaction_id);
                    if (mysqli_stmt_execute($stmt)) {
                        // mark fee as pending approval
                        $u = mysqli_prepare($conn, "UPDATE fees SET status = 'pending_approval' WHERE id = ?");
                        mysqli_stmt_bind_param($u, 'i', $fee_id);
                        mysqli_stmt_execute($u);

                        // notify admins
                        $admins = mysqli_query($conn, "SELECT id FROM users WHERE role = 'Admin'");
                        while ($a = mysqli_fetch_assoc($admins)) {
                            $fee_amount = '₹' . number_format($fee['amount'], 2);
                            createNotification($a['id'], 'Fee Payment Pending Approval', "Student {$user['name']} submitted payment proof ({$fee_amount}) for fee: {$fee['fee_type']}", 'warning', 'dashboard.php?section=fees_mgmt');
                        }

                        $_SESSION['success'] = '✓ Payment proof uploaded successfully! Admin will verify and approve within 24 hours.';
                    } else {
                        $_SESSION['error'] = 'Failed to save payment record: ' . mysqli_error($conn);
                    }
                } else {
                    $_SESSION['error'] = 'Failed to upload file';
                }
            }
        } else {
            $_SESSION['error'] = 'Please upload a payment screenshot';
        }
    } else {
        $_SESSION['error'] = 'Invalid fee record';
    }

    redirect('dashboard.php?section=fees');
}

// ADMIN: Approve Fee Payment
if (isset($_POST['approve_fee_payment']) && hasRole('Admin')) {
    $payment_id = intval($_POST['payment_id']);

    $pstmt = mysqli_prepare($conn, "SELECT * FROM fee_payments WHERE id = ? AND status = 'pending'");
    mysqli_stmt_bind_param($pstmt, 'i', $payment_id);
    mysqli_stmt_execute($pstmt);
    $pres = mysqli_stmt_get_result($pstmt);

    if ($payment = mysqli_fetch_assoc($pres)) {
        // fetch fee
        $fid = intval($payment['fee_id']);
        $fee_q = mysqli_query($conn, "SELECT * FROM fees WHERE id = $fid");
        $fee = mysqli_fetch_assoc($fee_q);

        if ($fee) {
            // mark payment approved
            $upd = mysqli_prepare($conn, "UPDATE fee_payments SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?");
            mysqli_stmt_bind_param($upd, 'ii', $user['id'], $payment_id);
            mysqli_stmt_execute($upd);

            // update fee as paid
            $u2 = mysqli_prepare($conn, "UPDATE fees SET paid_amount = amount, payment_date = NOW(), status = 'paid', updated_by = ? WHERE id = ?");
            mysqli_stmt_bind_param($u2, 'ii', $user['id'], $fid);
            mysqli_stmt_execute($u2);

            // notify student
            createNotification($payment['student_id'], 'Fee Payment Approved', "Your payment for fee ID: $fid has been approved.", 'success', 'dashboard.php?section=fees');

            $_SESSION['success'] = 'Payment approved and fee marked as PAID';
        } else {
            $_SESSION['error'] = 'Fee record not found';
        }
    } else {
        $_SESSION['error'] = 'Payment not found or already processed';
    }

    redirect('dashboard.php?section=fees_approval');
}

if (isset($_GET['approve_fee_payment']) && hasRole('Admin')) {
    $payment_id = intval($_GET['approve_fee_payment']);

    $pstmt = mysqli_prepare($conn, "SELECT * FROM fee_payments WHERE id = ? AND status = 'pending'");
    mysqli_stmt_bind_param($pstmt, 'i', $payment_id);
    mysqli_stmt_execute($pstmt);
    $pres = mysqli_stmt_get_result($pstmt);

    if ($payment = mysqli_fetch_assoc($pres)) {
        // fetch fee
        $fid = intval($payment['fee_id']);
        $fee_q = mysqli_query($conn, "SELECT * FROM fees WHERE id = $fid");
        $fee = mysqli_fetch_assoc($fee_q);

        if ($fee) {
            // mark payment approved
            $upd = mysqli_prepare($conn, "UPDATE fee_payments SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?");
            mysqli_stmt_bind_param($upd, 'ii', $user['id'], $payment_id);
            mysqli_stmt_execute($upd);

            // update fee as paid
            $u2 = mysqli_prepare($conn, "UPDATE fees SET paid_amount = amount, payment_date = NOW(), status = 'paid', updated_by = ? WHERE id = ?");
            mysqli_stmt_bind_param($u2, 'ii', $user['id'], $fid);
            mysqli_stmt_execute($u2);

            // notify student
            createNotification($payment['student_id'], 'Fee Payment Approved', "Your payment for fee ID: $fid has been approved.", 'success', 'dashboard.php?section=fees');

            $_SESSION['success'] = 'Payment approved and fee marked as PAID';
        } else {
            $_SESSION['error'] = 'Fee record not found';
        }
    } else {
        $_SESSION['error'] = 'Payment not found or already processed';
    }

    redirect('dashboard.php?section=fees_mgmt');
}

// ATTENDANCE - Mark Attendance (Teacher only)
if (isset($_POST['mark_attendance']) && hasRole('Teacher')) {
    $attendance_data = isset($_POST['attendance']) ? $_POST['attendance'] : [];
    $remarks_data = isset($_POST['remarks']) ? $_POST['remarks'] : [];
    $subject = sanitize($_POST['subject']);
    $attendance_date = sanitize($_POST['attendance_date']);
    
    if (empty($subject) || empty($attendance_date) || empty($attendance_data)) {
        $_SESSION['error'] = 'Please fill all required fields';
    } else {
        $success_count = 0;
        $error_count = 0;
        
        foreach ($attendance_data as $student_id => $status) {
            $student_id = intval($student_id);
            $status = sanitize($status);
            $remarks = isset($remarks_data[$student_id]) ? sanitize($remarks_data[$student_id]) : NULL;
            
            // Validate status
            if (!in_array($status, ['present', 'absent', 'late'])) {
                $error_count++;
                continue;
            }
            
            // Insert or update attendance
            $query = "INSERT INTO attendance (student_id, teacher_id, subject, attendance_date, status, remarks) 
                      VALUES (?, ?, ?, ?, ?, ?) 
                      ON DUPLICATE KEY UPDATE status = ?, remarks = ?, updated_at = NOW()";
            $stmt = mysqli_prepare($conn, $query);
            
            if (!$stmt) {
                $_SESSION['error'] = 'Database error: ' . mysqli_error($conn) . '. Please ensure the attendance table exists. Import education_system.sql first.';
                redirect('dashboard.php?section=mark_attendance');
            }
            
            mysqli_stmt_bind_param($stmt, 'iissssss', $student_id, $user['id'], $subject, $attendance_date, $status, $remarks, $status, $remarks);
            
            if (mysqli_stmt_execute($stmt)) {
                $success_count++;
                
                // Notify student
                $student = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM users WHERE id = $student_id"));
                createNotification($student_id, 'Attendance Marked', "Your attendance for $subject on " . date('d M Y', strtotime($attendance_date)) . " has been marked as " . strtoupper($status), 'info', 'dashboard.php?section=attendance');
            } else {
                $error_count++;
            }
        }
        
        if ($success_count > 0) {
            $_SESSION['success'] = "Attendance marked successfully for $success_count student(s)" . ($error_count > 0 ? " ($error_count failed)" : '');
        } else {
            $_SESSION['error'] = 'Failed to mark attendance';
        }
    }
    
    redirect('dashboard.php?section=attendance_mark');
}

// CASH PAYMENT - Mark fee as paid (Admin only)
// DIRECT CASH ENTRY - Create a paid fee record for a student (Admin/Teacher)
if (isset($_POST['direct_cash_payment']) && (hasRole('Admin') || hasRole('Teacher'))) {
    $identifier = sanitize($_POST['student_identifier'] ?? ''); // rollno or email
    $course = sanitize($_POST['course'] ?? '');
    $fee_type = sanitize($_POST['fee_type'] ?? 'Tuition');
    $amount = floatval($_POST['amount'] ?? 0);
    $notes = sanitize($_POST['notes'] ?? 'Cash payment');

    if (empty($identifier) || $amount <= 0) {
        $_SESSION['error'] = 'Student identifier and amount are required';
        redirect('dashboard.php?section=fees_approval');
    }

    // Try find by rollno+course first
    $student = null;
    if (!empty($course)) {
        $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE rollno = ? AND course = ? AND role = 'Student' LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'ss', $identifier, $course);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($s = mysqli_fetch_assoc($res)) $student = $s;
    }

    // If not found by rollno, try by email
    if (!$student) {
        $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE (email = ? OR rollno = ?) AND role = 'Student' LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'ss', $identifier, $identifier);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($s = mysqli_fetch_assoc($res)) $student = $s;
    }

    if (!$student) {
        $_SESSION['error'] = 'Student not found';
        redirect('dashboard.php?section=fees_approval');
    }

    $student_id = intval($student['id']);

    // Create fee record
    $insert_fee = mysqli_prepare($conn, "INSERT INTO fees (student_id, fee_type, amount, due_date, remarks, paid_amount, payment_date, status, updated_by) VALUES (?, ?, ?, NOW(), ?, ?, NOW(), 'paid', ?)");
    if (!$insert_fee) {
        $_SESSION['error'] = 'Database error: ' . mysqli_error($conn);
        redirect('dashboard.php?section=fees_approval');
    }
    mysqli_stmt_bind_param($insert_fee, 'isdisi', $student_id, $fee_type, $amount, $notes, $amount, $user['id']);
    if (!mysqli_stmt_execute($insert_fee)) {
        $_SESSION['error'] = 'Failed to create fee record: ' . mysqli_error($conn);
        redirect('dashboard.php?section=fees_approval');
    }

    $fee_id = mysqli_insert_id($conn);

    // Ensure fee_payments exists and has receipt_number
    $create_sql = "CREATE TABLE IF NOT EXISTS fee_payments (
        id INT PRIMARY KEY AUTO_INCREMENT,
        fee_id INT,
        student_id INT,
        file_path VARCHAR(255),
        transaction_id VARCHAR(100),
        status VARCHAR(50) DEFAULT 'approved',
        uploaded_at DATETIME,
        approved_by INT NULL,
        approved_at DATETIME NULL,
        rejection_reason TEXT NULL,
        receipt_number VARCHAR(120) DEFAULT NULL
    )";
    mysqli_query($conn, $create_sql);

    // Insert payment record
    $receipt = 'RCP-' . date('YmdHis') . '-' . $fee_id;
    $pstmt = mysqli_prepare($conn, "INSERT INTO fee_payments (fee_id, student_id, file_path, transaction_id, status, uploaded_at, approved_by, approved_at, receipt_number) VALUES (?, ?, NULL, 'CASH', 'approved', NOW(), ?, NOW(), ?)");
    mysqli_stmt_bind_param($pstmt, 'iiis', $fee_id, $student_id, $user['id'], $receipt);
    mysqli_stmt_execute($pstmt);

    // Notify student
    createNotification($student_id, 'Cash Payment Received', "Your payment of ₹" . number_format($amount, 2) . " has been recorded. Receipt: $receipt", 'success', 'dashboard.php?section=fees');
    $_SESSION['success'] = 'Direct cash payment recorded. Receipt: ' . $receipt;
    redirect('dashboard.php?section=fees_approval');
}

// DIRECT CASH PAYMENT ENTRY - Add new payment record with cash payment
if (isset($_POST['add_direct_cash_payment']) && hasAnyRole(['Teacher', 'Admin'])) {
    $student_id = intval($_POST['student_id']);
    $fee_type = sanitize($_POST['fee_type']);
    $amount = floatval($_POST['amount']);
    $paid_amount = isset($_POST['paid_amount']) ? floatval($_POST['paid_amount']) : $amount;
    $payment_method = sanitize($_POST['payment_method'] ?? 'cash');
    $due_date = sanitize($_POST['due_date'] ?? date('Y-m-d'));
    $remarks = sanitize($_POST['remarks'] ?? 'Cash payment received in office');
    $receipt_number = 'RCP-' . time() . '-' . $student_id;
    
    // Ensure payment_method column exists
    $col_check = mysqli_query($conn, "SHOW COLUMNS FROM fees LIKE 'payment_method'");
    if ($col_check && mysqli_num_rows($col_check) == 0) {
        mysqli_query($conn, "ALTER TABLE fees ADD COLUMN payment_method VARCHAR(50) DEFAULT NULL");
    }
    
    // Determine status
    if ($paid_amount >= $amount) {
        $status = 'paid';
    } elseif ($paid_amount > 0) {
        $status = 'partial';
    } else {
        $status = 'pending';
    }
    
    // Insert new fee record with payment
    $query = "INSERT INTO fees (student_id, fee_type, amount, paid_amount, payment_date, transaction_id, status, remarks, payment_method, due_date, updated_by) VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'isddsssssi', $student_id, $fee_type, $amount, $paid_amount, $receipt_number, $status, $remarks, $payment_method, $due_date, $user['id']);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "✓ Payment recorded successfully! Receipt: $receipt_number";
            logActivity($user['id'], 'Direct Cash Payment Entry', "Recorded payment of ₹$paid_amount for student ID: $student_id - $fee_type");
            
            // Notify student
            createNotification($student_id, 'Fee Payment Received', "Your payment of ₹$paid_amount has been recorded for $fee_type. Receipt: $receipt_number", 'success', 'dashboard.php?section=fees');
        } else {
            $_SESSION['error'] = 'Failed to record payment: ' . mysqli_error($conn);
        }
    } else {
        $_SESSION['error'] = 'Database error: ' . mysqli_error($conn);
    }
    
    redirect('dashboard.php?section=fees_mgmt');
}

if (isset($_POST['mark_cash_paid']) && hasRole('Admin')) {
    $fee_id = intval($_POST['fee_id']);
    $payment_method = isset($_POST['payment_method']) ? sanitize($_POST['payment_method']) : '';
    $notes = isset($_POST['notes']) ? sanitize($_POST['notes']) : '';
    $paid_amount = isset($_POST['paid_amount']) ? floatval($_POST['paid_amount']) : null;

    // Get fee details
    $fee_query = mysqli_query($conn, "SELECT * FROM fees WHERE id = $fee_id");
    $fee = mysqli_fetch_assoc($fee_query);

    if (!$fee) {
        $_SESSION['error'] = 'Fee not found';
        redirect('dashboard.php?section=fees_approval');
    }

    $student_id = $fee['student_id'];
    $total_amount = floatval($fee['amount']);

    // If admin didn't pass paid_amount, default to full amount
    if ($paid_amount === null) {
        $paid_amount = $total_amount;
    }

    // Prevent overpayment
    if ($paid_amount > $total_amount) {
        $paid_amount = $total_amount;
    }
    if ($paid_amount < 0) {
        $paid_amount = 0;
    }

    // Determine status
    if ($paid_amount >= $total_amount) {
        $status = 'paid';
    } elseif ($paid_amount > 0) {
        $status = 'partial';
    } else {
        $status = 'pending';
    }

    // Ensure `payment_method` column exists
    $col_check = mysqli_query($conn, "SHOW COLUMNS FROM fees LIKE 'payment_method'");
    if ($col_check && mysqli_num_rows($col_check) == 0) {
        $alter_sql = "ALTER TABLE fees ADD COLUMN payment_method VARCHAR(50) DEFAULT NULL";
        if (!mysqli_query($conn, $alter_sql)) {
            $_SESSION['error'] = 'Database error (add column): ' . mysqli_error($conn);
            redirect('dashboard.php?section=fees_approval');
        }
    }

    // Update fee record
    $update_query = "UPDATE fees SET status = ?, paid_amount = ?, payment_date = NOW(), payment_method = ?, updated_by = ? WHERE id = ?";
    $update_stmt = mysqli_prepare($conn, $update_query);
    if (!$update_stmt) {
        $_SESSION['error'] = 'Database error (prepare): ' . mysqli_error($conn);
        redirect('dashboard.php?section=fees_approval');
    }

    mysqli_stmt_bind_param($update_stmt, 'sdsii', $status, $paid_amount, $payment_method, $user['id'], $fee_id);

    if (mysqli_stmt_execute($update_stmt)) {
        // Generate receipt number (always create one — you can opt to only create when fully paid)
        $receipt_number = 'RCP-' . date('YmdHis') . '-' . $fee_id;

        // Create notification for the student
        $notif_msg = "Your fee has been updated. Amount recorded: ₹" . number_format($paid_amount, 2) . " | Status: " . strtoupper($status);
        if ($notes) {
            $notif_msg .= " | Notes: " . $notes;
        }
        createNotification($student_id, 'Fee Payment Recorded', $notif_msg . " Receipt: " . $receipt_number, 'success', 'dashboard.php?section=fees');

        $_SESSION['success'] = "Fee updated. Receipt: " . $receipt_number;
    } else {
        $_SESSION['error'] = 'Failed to update fee: ' . mysqli_error($conn);
    }

    redirect('dashboard.php?section=fees_approval');
}

// UPDATE CASH PAYMENT - Update paid amount and method (Admin only)
if (isset($_POST['update_cash_payment']) && hasRole('Admin')) {
    $fee_id = intval($_POST['fee_id']);
    $paid_amount = floatval($_POST['paid_amount']);
    $payment_method = sanitize($_POST['payment_method']);
    $notes = isset($_POST['notes']) ? sanitize($_POST['notes']) : '';
    
    // Get fee details
    $fee_query = mysqli_query($conn, "SELECT * FROM fees WHERE id = $fee_id");
    $fee = mysqli_fetch_assoc($fee_query);
    
    if ($fee) {
        $total_amount = $fee['amount'];
        
        // Determine status based on paid amount
        if ($paid_amount >= $total_amount) {
            $status = 'paid';
            $paid_amount = $total_amount;
        } elseif ($paid_amount > 0) {
            $status = 'partial';
        } else {
            $status = 'pending';
        }
        
        // Ensure `payment_method` column exists
        $col_check = mysqli_query($conn, "SHOW COLUMNS FROM fees LIKE 'payment_method'");
        if ($col_check && mysqli_num_rows($col_check) == 0) {
            $alter_sql = "ALTER TABLE fees ADD COLUMN payment_method VARCHAR(50) DEFAULT NULL";
            if (!mysqli_query($conn, $alter_sql)) {
                $_SESSION['error'] = 'Database error (add column): ' . mysqli_error($conn);
                redirect('dashboard.php?section=fees_approval');
            }
        }

        // Update fee details
        $update_query = "UPDATE fees SET paid_amount = ?, status = ?, payment_method = ?, payment_date = NOW(), updated_by = ? WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_query);
        if (!$update_stmt) {
            $_SESSION['error'] = 'Database error (prepare): ' . mysqli_error($conn);
            redirect('dashboard.php?section=fees_approval');
        }

        // types: double, string, string, int, int
        mysqli_stmt_bind_param($update_stmt, 'dssii', $paid_amount, $status, $payment_method, $user['id'], $fee_id);

        if (mysqli_stmt_execute($update_stmt)) {
            // Create notification
            $notif_msg = "Your fee payment has been updated. Amount: ₹" . number_format($paid_amount, 2) . " | Status: " . strtoupper($status);
            if ($notes) {
                $notif_msg .= " | Notes: " . $notes;
            }
            createNotification($fee['student_id'], 'Fee Payment Updated', $notif_msg, 'info', 'dashboard.php?section=fees');
            
            $_SESSION['success'] = "Fee payment updated successfully";
        } else {
            $_SESSION['error'] = 'Failed to update payment: ' . mysqli_error($conn);
        }
    } else {
        $_SESSION['error'] = 'Fee not found';
    }
    
    redirect('dashboard.php?section=fees_approval');
}

// TEST - Submit (Student only) - improved handler
if (isset($_POST['submit_test']) && hasRole('Student')) {
    $test_id = intval($_POST['test_id']);
    $answers = $_POST['answers'] ?? [];
    
    // Log the submission attempt
    error_log("Test submission attempt - Test ID: $test_id, Student ID: {$user['id']}");
    
    // Validate test ID
    if ($test_id <= 0) {
        $_SESSION['error'] = 'Invalid test ID';
        redirect('dashboard.php?section=tests');
        exit;
    }
    
    // Check if test exists and is published
    $query = "SELECT * FROM tests WHERE id = ? AND status = 'published'";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $test_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$result || mysqli_num_rows($result) === 0) {
        $_SESSION['error'] = 'Test not found or not published';
        error_log("Test not found or not published - Test ID: $test_id");
        redirect('dashboard.php?section=tests');
        exit;
    }
    
    $test = mysqli_fetch_assoc($result);
    
    // Check if student's course matches test course
    if ($test['course'] !== 'All' && $test['course'] !== $user['course']) {
        $_SESSION['error'] = 'This test is not for your course';
        error_log("Course mismatch - Test: {$test['course']}, Student: {$user['course']}");
        redirect('dashboard.php?section=tests');
        exit;
    }
    
    // Check previous attempts
    $query = "SELECT COUNT(*) as attempts FROM test_results WHERE test_id = ? AND student_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'ii', $test_id, $user['id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $attempt = mysqli_fetch_assoc($result);
    
    if ($attempt['attempts'] > 0 && $test['allow_multiple_attempts'] == 0) {
        $_SESSION['error'] = 'You have already attempted this test';
        error_log("Multiple attempt blocked - Student ID: {$user['id']}, Test ID: $test_id");
        redirect('dashboard.php?section=results');
        exit;
    }
    
    // Get all questions for this test
    $query = "SELECT * FROM test_questions WHERE test_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $test_id);
    mysqli_stmt_execute($stmt);
    $questions = mysqli_stmt_get_result($stmt);
    
    if (!$questions || mysqli_num_rows($questions) === 0) {
        $_SESSION['error'] = 'No questions found for this test';
        error_log("No questions found - Test ID: $test_id");
        redirect('dashboard.php?section=tests');
        exit;
    }
    
    // Calculate marks
    $marks_obtained = 0;
    $total_questions = 0;
    $correct_answers = 0;
    
    while ($q = mysqli_fetch_assoc($questions)) {
        $total_questions++;
        if (isset($answers[$q['id']])) {
            if ($answers[$q['id']] === $q['correct_answer']) {
                $marks_obtained += $q['marks'];
                $correct_answers++;
            }
        }
    }
    
    // Calculate percentage
    $percentage = ($test['total_marks'] > 0) ? ($marks_obtained / $test['total_marks']) * 100 : 0;
    
    // Determine pass/fail status
    $status = ($marks_obtained >= $test['pass_marks']) ? 'Pass' : 'Fail';
    
    // Log calculation details
    error_log("Test calculation - Marks: $marks_obtained/{$test['total_marks']}, Percentage: $percentage%, Status: $status");
    
    // Insert result into database
    $query = "INSERT INTO test_results (test_id, student_id, marks_obtained, total_marks, percentage, status) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    
    if (!$stmt) {
        $_SESSION['error'] = 'Database error: ' . mysqli_error($conn);
        error_log("Failed to prepare statement: " . mysqli_error($conn));
        redirect('dashboard.php?section=tests');
        exit;
    }
    
    // types: test_id (i), student_id (i), marks_obtained (i), total_marks (i), percentage (d), status (s)
    mysqli_stmt_bind_param($stmt, 'iiiids', $test_id, $user['id'], $marks_obtained, $test['total_marks'], $percentage, $status);
    
    if (mysqli_stmt_execute($stmt)) {
        // Success! Create detailed success message
        $result_id = mysqli_insert_id($conn);
        $pass_msg = ($status === 'Pass') ? '✅ Congratulations! You passed!' : '❌ Keep trying! You can do better!';
        
        $_SESSION['success'] = "Test submitted successfully! $pass_msg<br>
                               Score: $marks_obtained / {$test['total_marks']} ({$percentage}%)<br>
                               Correct Answers: $correct_answers / $total_questions";
        
        // Create notification
        createNotification(
            $user['id'], 
            "Test Result: {$test['title']}", 
            "You scored $marks_obtained/{$test['total_marks']} ({$percentage}%) - Status: $status", 
            $status === 'Pass' ? 'success' : 'warning',
            'dashboard.php?section=results'
        );
        
        // Log activity
        logActivity($user['id'], 'Test Submission', "Submitted test: {$test['title']} - Score: $marks_obtained/{$test['total_marks']}");
        
        error_log("Test submitted successfully - Result ID: $result_id, Student: {$user['id']}, Test: $test_id");
        
    } else {
        $_SESSION['error'] = 'Failed to save test result. Please try again or contact administrator.<br>Error: ' . mysqli_error($conn);
        error_log("Failed to insert test result: " . mysqli_error($conn));
        redirect('dashboard.php?section=tests');
        exit;
    }
    
    // Redirect to results page
    redirect('dashboard.php?section=results');
    exit;
}
