<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$user = getCurrentUser();

// Ensure user data is valid
if (!$user) {
    session_destroy();
    redirect('index.php');
}

// Ensure all required keys exist with default values
$user['name'] = $user['name'] ?? 'User';
$user['email'] = $user['email'] ?? '';
$user['role'] = $user['role'] ?? 'Student';
$user['course'] = $user['course'] ?? ($user['course'] ?? null);
$user['course'] = $user['course']; // Backward compatibility
$user['rollno'] = $user['rollno'] ?? null;
$user['age'] = $user['age'] ?? null;
$user['course_year'] = $user['course_year'] ?? null;
$user['profile_photo'] = $user['profile_photo'] ?? null;

// AJAX endpoint for getting test data - must be before HTML output
if (isset($_GET['section']) && $_GET['section'] === 'get_test' && isset($_GET['id'])) {
    $test_id = intval($_GET['id']);
    $test = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM tests WHERE id = $test_id"));
    if ($test && ($test['course'] === $user['course'] || $test['course'] === 'All')) {
        // Check if student can attempt this test
        $user_id = $user['id'] ?? 0;
        $attempt_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM test_results WHERE test_id = $test_id AND student_id = {$user_id}");
        $attempt_row = $attempt_result ? mysqli_fetch_assoc($attempt_result) : null;
        $attempted = $attempt_row ? $attempt_row['count'] : 0;
        $can_attempt = ($attempted == 0 || $test['allow_multiple_attempts'] == 1);
        
        if ($can_attempt) {
            $questions = mysqli_query($conn, "SELECT * FROM test_questions WHERE test_id = $test_id");
            
            $test['questions'] = [];
            while ($q = mysqli_fetch_assoc($questions)) {
                $test['questions'][] = $q;
            }
            
            header('Content-Type: application/json');
            echo json_encode($test);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Test already attempted']);
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Test not found or not accessible']);
    }
    exit;
}

$section = $_GET['section'] ?? 'home';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo $user['name']; ?></title>
    <link rel="stylesheet" href="assets.css">
    <style>
        .checkbox-options {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 10px;
        }
        
        .option-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .option-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            margin: 0;
        }
        
        .option-item label {
            margin: 0;
            font-weight: 500;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>SAI COLLEGE</h2>
                <span class="role-badge"><?php echo $user['role']; ?></span>
            </div>
            
            <nav class="sidebar-nav">
                <a href="?section=home" class="<?php echo $section === 'home' ? 'active' : ''; ?>">
                    <span>🏠</span> Dashboard
                </a>
                
                <a href="?section=notices" class="<?php echo $section === 'notices' ? 'active' : ''; ?>">
                    <span>📢</span> Notices
                    <?php
                    $notice_query = "SELECT COUNT(*) as count FROM notices WHERE (course = ? OR course = 'All') AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)";
                    $stmt = mysqli_prepare($conn, $notice_query);
                    $course = $user['course'] ?? 'All';
                    mysqli_stmt_bind_param($stmt, 's', $course);
                    mysqli_stmt_execute($stmt);
                    $notice_result = mysqli_stmt_get_result($stmt);
                    $notice_row = $notice_result ? mysqli_fetch_assoc($notice_result) : null;
                    $notice_count = $notice_row ? $notice_row['count'] : 0;
                    if ($notice_count > 0) echo '<span class="notification-dot">' . $notice_count . '</span>';
                    ?>
                </a>
                
                <?php if ($user['role'] === 'Student'): ?>
                <a href="?section=notes" class="<?php echo $section === 'notes' ? 'active' : ''; ?>">
                    <span>📚</span> Notes
                </a>
                <a href="?section=assignments" class="<?php echo $section === 'assignments' ? 'active' : ''; ?>">
                    <span>📝</span> Assignments
                </a>
                <a href="?section=tests" class="<?php echo $section === 'tests' ? 'active' : ''; ?>">
                    <span>✏️</span> Tests
                </a>
                <a href="?section=results" class="<?php echo $section === 'results' ? 'active' : ''; ?>">
                    <span>📊</span> Results
                </a>
                <a href="?section=attendance" class="<?php echo $section === 'attendance' ? 'active' : ''; ?>">
                    <span>✓</span> Attendance
                </a>
                <a href="?section=calendar" class="<?php echo $section === 'calendar' ? 'active' : ''; ?>">
                    <span>🗓️</span> Calendar
                </a>
                <a href="?section=fees" class="<?php echo $section === 'fees' ? 'active' : ''; ?>">
                    <span>💳</span> Fees
                </a>
                <a href="?section=locker" class="<?php echo $section === 'locker' ? 'active' : ''; ?>">
                    <span>🗄️</span> Digital Locker
                </a>
                <?php endif; ?>
                
                <?php if ($user['role'] === 'Teacher'): ?>
                <a href="?section=notes" class="<?php echo $section === 'notes' ? 'active' : ''; ?>">
                    <span>📚</span> Manage Notes
                </a>
                <a href="?section=assignments" class="<?php echo $section === 'assignments' ? 'active' : ''; ?>">
                    <span>📝</span> Assignments
                </a>
                <a href="?section=tests" class="<?php echo $section === 'tests' ? 'active' : ''; ?>">
                    <span>✏️</span> Tests
                </a>
                <a href="?section=attendance_mark" class="<?php echo $section === 'attendance_mark' ? 'active' : ''; ?>">
                    <span>✓</span> Mark Attendance
                </a>
                <a href="?section=fees_mgmt" class="<?php echo $section === 'fees_mgmt' ? 'active' : ''; ?>">
                    <span>💳</span> Manage Fees
                </a>
                <a href="?section=locker" class="<?php echo $section === 'locker' ? 'active' : ''; ?>">
                    <span>🗄️</span> Digital Locker
                </a>
                <?php endif; ?>
                
                <?php if ($user['role'] === 'Admin'): ?>
                <a href="?section=users" class="<?php echo $section === 'users' ? 'active' : ''; ?>">
                    <span>👥</span> Manage Users
                </a>
                <a href="?section=fees_approval" class="<?php echo $section === 'fees_approval' ? 'active' : ''; ?>">
                    <span>💳</span> Fee Approvals
                </a>
                <a href="?section=calendar_mgmt" class="<?php echo $section === 'calendar_mgmt' ? 'active' : ''; ?>">
                    <span>🗓️</span> Manage Calendar
                </a>
                <a href="?section=overview" class="<?php echo $section === 'overview' ? 'active' : ''; ?>">
                    <span>📈</span> Overview
                </a>
                <?php endif; ?>
                
                <a href="?section=change_password" class="<?php echo $section === 'change_password' ? 'active' : ''; ?>">
                    <span>🔒</span> Change Password
                </a>
            </nav>
            
            <div class="sidebar-footer">
                <button id="darkModeToggle" class="btn btn-secondary btn-block">🌙 Dark Mode</button>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Navbar -->
            <div class="topbar">
                <div class="topbar-left">
                    <div class="profile-photo" onclick="openProfileModal()" style="cursor: pointer;" title="Click to edit profile">
                        <?php 
                        $profile_photo_path = $user['profile_photo'] ? 'uploads/profile/' . $user['profile_photo'] : null;
                        $placeholder = 'https://via.placeholder.com/40x40?text=' . substr($user['name'], 0, 1);
                        $img_src = ($profile_photo_path && file_exists($profile_photo_path)) ? $profile_photo_path : $placeholder;
                        ?>
                        <img src="<?php echo htmlspecialchars($img_src); ?>" alt="Profile" class="profile-img">
                    </div>
                    <button id="sidebarToggle" class="btn btn-icon">☰</button>
                    <h1><?php echo ucfirst($section); ?></h1>
                </div>
                <div class="topbar-right">
                    <?php if ($user['role'] === 'Student'): ?>
                    <div style="position: relative; margin-right: 20px;">
                        <button id="notifToggle" aria-haspopup="true" aria-expanded="false" onclick="toggleNotifications()" class="btn btn-icon" style="padding: 20px; font-size: 20px; color: var(--text-primary); position: relative;">
                            🔔
                            <span id="notifCount" class="notification-badge" style="<?php echo getUnreadNotificationsCount($user['id'])>0 ? '' : 'display:none;'; ?>"><?php echo getUnreadNotificationsCount($user['id']); ?></span>
                        </button>
                        <div id="notifDropdown" class="notif-dropdown" style="display:none; position:absolute; right:0; top:40px; width:340px; max-height:420px; overflow:auto; z-index:50; box-shadow: var(--shadow-lg);">
                            <div class="notif-header" style="display:flex; justify-content:space-between; align-items:center; padding:10px 15px; border-bottom:1px solid var(--border); background:var(--bg-secondary);">
                                <strong>Notifications</strong>
                                <button class="btn btn-sm" onclick="markAllRead()">Mark all read</button>
                            </div>
                            <div id="notifList" style="padding:10px;"></div>
                            <div id="notifFooter" style="padding:10px; text-align:center; border-top:1px solid var(--border); background:var(--bg-secondary);">
                                <a href="?section=notifications" class="btn btn-link">View all</a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="user-info">
                        <span><?php echo $user['name']; ?></span>
                        <?php if (!empty($user['course']) && $user['role'] === 'Student'): ?>
                            <small><?php echo $user['course']; ?> (<?php echo $user['rollno'] ?? '-'; ?>)</small>
                        <?php endif; ?>
                    </div>
                    <a href="actions.php?logout=1" class="btn btn-danger">Logout</a>
                </div>
            </div>
            
            <!-- Alerts -->
            <div class="content-area">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['auto_publish_notification'])): ?>
                    <div class="alert alert-success"><?php echo $_SESSION['auto_publish_notification']; unset($_SESSION['auto_publish_notification']); ?></div>
                <?php endif; ?>
                
                <!-- Section Content -->
                <?php
                // HOME SECTION
                if ($section === 'home'):
                ?>
                    <div class="welcome-section">
                        <h2>Welcome, <?php echo $user['name']; ?>!</h2>
                        <p>Role: <?php echo $user['role']; ?> <?php echo (!empty($user['course']) && $user['role'] === 'Student') ? '| Course: ' . $user['course'] : ''; ?></p>
                    </div>
                    
                    <div class="dashboard-cards">
                        <?php if ($user['role'] === 'Student'): ?>
                            <?php
                            $course = $user['course'] ?? '';
                            $user_id = $user['id'] ?? 0;
                            
                            $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM assignments WHERE course = '{$course}' AND due_date > NOW() AND id NOT IN (SELECT assignment_id FROM assignment_submissions WHERE student_id = {$user_id})");
                            $pending_row = $result ? mysqli_fetch_assoc($result) : null;
                            $pending_assignments = $pending_row ? $pending_row['count'] : 0;
                            
                            $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM test_results WHERE student_id = {$user_id}");
                            $completed_row = $result ? mysqli_fetch_assoc($result) : null;
                            $completed_tests = $completed_row ? $completed_row['count'] : 0;
                            
                            $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM digital_locker WHERE user_id = {$user_id}");
                            $locker_row = $result ? mysqli_fetch_assoc($result) : null;
                            $locker_files = $locker_row ? $locker_row['count'] : 0;
                            ?>
                            <div class="stat-card">
                                <h3><?php echo $pending_assignments; ?></h3>
                                <p>Pending Assignments</p>
                            </div>
                            <div class="stat-card">
                                <h3><?php echo $completed_tests; ?></h3>
                                <p>Tests Completed</p>
                            </div>
                            <div class="stat-card">
                                <h3><?php echo $locker_files; ?></h3>
                                <p>Files in Locker</p>
                            </div>
                        <?php elseif ($user['role'] === 'Teacher'): ?>
                            <?php
                            $user_id = $user['id'] ?? 0;
                            
                            $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM notes WHERE uploaded_by = {$user_id}");
                            $notes_row = $result ? mysqli_fetch_assoc($result) : null;
                            $total_notes = $notes_row ? $notes_row['count'] : 0;
                            
                            $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM assignments WHERE created_by = {$user_id}");
                            $assignments_row = $result ? mysqli_fetch_assoc($result) : null;
                            $total_assignments = $assignments_row ? $assignments_row['count'] : 0;
                            
                            $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM tests WHERE created_by = {$user_id}");
                            $tests_row = $result ? mysqli_fetch_assoc($result) : null;
                            $total_tests = $tests_row ? $tests_row['count'] : 0;
                            ?>
                            <div class="stat-card">
                                <h3><?php echo $total_notes; ?></h3>
                                <p>Notes Uploaded</p>
                            </div>
                            <div class="stat-card">
                                <h3><?php echo $total_assignments; ?></h3>
                                <p>Assignments Created</p>
                            </div>
                            <div class="stat-card">
                                <h3><?php echo $total_tests; ?></h3>
                                <p>Tests Created</p>
                            </div>
                        <?php elseif ($user['role'] === 'Admin'): ?>
                            <?php
                            $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM users");
                            $users_row = $result ? mysqli_fetch_assoc($result) : null;
                            $total_users = $users_row ? $users_row['count'] : 0;
                            
                            $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM notices");
                            $notices_row = $result ? mysqli_fetch_assoc($result) : null;
                            $total_notices = $notices_row ? $notices_row['count'] : 0;
                            
                            $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM digital_locker");
                            $files_row = $result ? mysqli_fetch_assoc($result) : null;
                            $total_files = $files_row ? $files_row['count'] : 0;
                            ?>
                            <div class="stat-card">
                                <h3><?php echo $total_users; ?></h3>
                                <p>Total Users</p>
                            </div>
                            <div class="stat-card">
                                <h3><?php echo $total_notices; ?></h3>
                                <p>Notices Posted</p>
                            </div>
                            <div class="stat-card">
                                <h3><?php echo $total_files; ?></h3>
                                <p>Total Files</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Recent Notices -->
                    <div class="section-box">
                        <h3>Recent Notices</h3>
                        <?php
                        $course_condition = $user['course'] ? "AND (n.stream = '" . mysqli_real_escape_string($conn, $user['course']) . "' OR n.stream = 'All')" : "AND (n.stream = 'All')";
                        $notices = mysqli_query($conn, "SELECT n.*, u.name as posted_by_name FROM notices n JOIN users u ON n.posted_by = u.id WHERE 1=1 $course_condition ORDER BY is_pinned DESC, created_at DESC LIMIT 5");
                        if ($notices) {
                            while ($notice = mysqli_fetch_assoc($notices)):
                        ?>
                            <div class="notice-item <?php echo $notice['is_pinned'] ? 'pinned' : ''; ?>">
                                <h4><?php echo $notice['title']; ?> <?php echo $notice['is_pinned'] ? '📌' : ''; ?></h4>
                                <p><?php echo $notice['content']; ?></p>
                                <small>By <?php echo $notice['posted_by_name']; ?> | <?php echo formatDate($notice['created_at']); ?></small>
                            </div>
                        <?php 
                            endwhile;
                        } else {
                            echo "<p>Unable to load notices: " . mysqli_error($conn) . "</p>";
                        }
                        ?>
                    </div>
                
                <?php
                // NOTICES SECTION
                elseif ($section === 'notices'):
                ?>
                    <?php if ($user['role'] === 'Admin' || $user['role'] === 'Teacher'): ?>
                    <div class="section-box">
                        <h3>Post New Notice</h3>
                        <form method="POST" action="actions.php" class="form-inline">
                            <div class="form-row">
                                <div class="form-group">
                                    <input type="text" name="title" placeholder="Notice Title" required>
                                </div>
                                <div class="form-group">
                                    <select name="course" required>
                                        <option value="All">All Courses</option>
                                        <option value="B.Com">B.Com</option>
                                        <option value="M.Com">M.Com</option>
                                        <option value="BCA">BCA</option>
                                        <option value="BA">BA</option>
                                        <option value="BBA">BBA</option>
                                        <option value="PGDCA">PGDCA</option>
                                        <option value="DCA">DCA</option>
                                        <option value="M.Sc">M.Sc</option>
                                        <option value="MA">MA</option>
                                        <option value="B.Lib">B.Lib</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <textarea name="content" placeholder="Notice Content" rows="3" required></textarea>
                            </div>
                            <div class="form-row">
                                <label><input type="checkbox" name="is_pinned"> Pin this notice</label>
                                <button type="submit" name="create_notice" class="btn btn-primary">Post Notice</button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>
                    
                    <div class="section-box">
                        <h3>All Notices</h3>
                        <?php
                        // Show administrative notices to all roles (Admin and Teacher see everything)
                        if ($user['role'] === 'Admin' || $user['role'] === 'Teacher') {
                            $course_condition = "";
                        } else {
                            // Students see their course or 'All' notices
                            $course_condition = $user['course'] ? "AND (n.stream = '" . mysqli_real_escape_string($conn, $user['course']) . "' OR n.stream = 'All')" : "AND (n.stream = 'All')";
                        }
                        $notices = mysqli_query($conn, "SELECT n.*, u.name as posted_by_name FROM notices n JOIN users u ON n.posted_by = u.id WHERE 1=1 $course_condition ORDER BY is_pinned DESC, created_at DESC");
                        if ($notices) {
                            while ($notice = mysqli_fetch_assoc($notices)):
                        ?>
                            <div class="notice-item <?php echo $notice['is_pinned'] ? 'pinned' : ''; ?>">
                                <div class="notice-header">
                                    <h4><?php echo $notice['title']; ?> <?php echo $notice['is_pinned'] ? '📌' : ''; ?></h4>
                                    <?php if ($user['role'] === 'Admin' || $user['role'] === 'Teacher'): ?>
                                        <a href="actions.php?delete_notice=<?php echo $notice['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this notice?')">Delete</a>
                                    <?php endif; ?>
                                </div>
                                <p><?php echo $notice['content']; ?></p>
                                <div class="notice-meta">
                                    <span class="badge"><?php echo $notice['course']; ?></span>
                                    <small>By <?php echo $notice['posted_by_name']; ?> | <?php echo formatDate($notice['created_at']); ?></small>
                                </div>
                            </div>
                        <?php 
                            endwhile;
                        } else {
                            echo "<p>Unable to load notices: " . mysqli_error($conn) . "</p>";
                        }
                        ?>
                    </div>
                
                <?php
                // ATTENDANCE MARKING - Teacher Section
                elseif ($section === 'attendance_mark' && $user['role'] === 'Teacher'):
                ?>
                    <div class="section-header">
                        <h2>✓ Mark Attendance</h2>
                    </div>
                    
                    <div class="section-box">
                        <h3 style="margin-bottom: 20px;">Mark Student Attendance</h3>
                        <form method="POST" action="actions.php">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Select Course</label>
                                    <select name="course" id="attendanceCourse" required onchange="loadStudentsForAttendance(this.value)">
                                        <option value="">Choose Course</option>
                                        <option value="B.Com">B.Com</option>
                                        <option value="M.Com">M.Com</option>
                                        <option value="BCA">BCA</option>
                                        <option value="BA">BA</option>
                                        <option value="BBA">BBA</option>
                                        <option value="PGDCA">PGDCA</option>
                                        <option value="DCA">DCA</option>
                                        <option value="M.Sc">M.Sc</option>
                                        <option value="MA">MA</option>
                                        <option value="B.Lib">B.Lib</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Subject</label>
                                    <input type="text" name="subject" placeholder="e.g., Mathematics, English" required>
                                </div>
                                <div class="form-group">
                                    <label>Date</label>
                                    <input type="date" name="attendance_date" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                            
                            <div style="border:1px solid #ddd; border-radius:8px; padding:15px; margin:20px 0; overflow-x:auto;">
                                <table class="data-table" style="width:100%; margin:0;">
                                    <thead>
                                        <tr style="background:#f5f5f5;">
                                            <th style="text-align:left; padding:10px;">#</th>
                                            <th style="text-align:left; padding:10px;">Student Name</th>
                                            <th style="text-align:center; padding:10px;">Present</th>
                                            <th style="text-align:center; padding:10px;">Absent</th>
                                            <th style="text-align:center; padding:10px;">Late</th>
                                            <th style="text-align:left; padding:10px;">Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody id="studentAttendanceList">
                                        <tr><td colspan="6" style="text-align:center; padding:20px; color:#999;">Select a course to load students</td></tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div style="text-align:right;">
                                <button type="submit" name="mark_attendance" class="btn btn-primary">Save Attendance</button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="section-box" style="margin-top:30px;">
                        <h3>Attendance History</h3>
                        <div style="display:flex; gap:10px; margin-bottom:15px; flex-wrap:wrap;">
                            <input type="text" id="searchAttendance" placeholder="Search student name..." style="padding:8px 12px; border:1px solid #ddd; border-radius:6px; flex:1; min-width:200px;" onkeyup="filterAttendanceTable()">
                        </div>
                        <div style="overflow-x:auto;">
                            <table class="data-table" id="attendanceHistoryTable" style="width:100%;">
                                <thead>
                                    <tr style="background:#f5f5f5;">
                                        <th style="padding:10px; text-align:left;">Student Name</th>
                                        <th style="padding:10px; text-align:left;">Subject</th>
                                        <th style="padding:10px; text-align:center;">Date</th>
                                        <th style="padding:10px; text-align:center;">Status</th>
                                        <th style="padding:10px; text-align:left;">Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $attendance_q = mysqli_query($conn, "SELECT a.*, u.name as student_name FROM attendance a JOIN users u ON a.student_id = u.id WHERE a.teacher_id = {$user['id']} ORDER BY a.attendance_date DESC LIMIT 100");
                                    if ($attendance_q && mysqli_num_rows($attendance_q) > 0):
                                        while ($att = mysqli_fetch_assoc($attendance_q)):
                                            $status_color = $att['status'] === 'present' ? '#28a745' : ($att['status'] === 'absent' ? '#dc3545' : '#ffc107');
                                    ?>
                                        <tr>
                                            <td style="padding:10px;"><?php echo htmlspecialchars($att['student_name']); ?></td>
                                            <td style="padding:10px;"><?php echo htmlspecialchars($att['subject']); ?></td>
                                            <td style="padding:10px; text-align:center;"><?php echo date('d M Y', strtotime($att['attendance_date'])); ?></td>
                                            <td style="padding:10px; text-align:center;">
                                                <span style="background:<?php echo $status_color; ?>; color:white; padding:4px 8px; border-radius:4px; font-weight:600;"><?php echo strtoupper($att['status']); ?></span>
                                            </td>
                                            <td style="padding:10px;"><?php echo $att['remarks'] ? htmlspecialchars($att['remarks']) : '-'; ?></td>
                                        </tr>
                                    <?php 
                                        endwhile;
                                    else:
                                    ?>
                                        <tr><td colspan="5" style="padding:20px; text-align:center; color:#999;">No attendance records yet</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                
                <?php
                // ATTENDANCE VIEW - Student Section
                elseif ($section === 'attendance' && $user['role'] === 'Student'):
                ?>
                    <div class="section-header">
                        <h2>✓ My Attendance</h2>
                    </div>
                    
                    <div class="section-box">
                        <h3>Attendance Records</h3>
                        <div style="overflow-x:auto;">
                            <table class="data-table" style="width:100%;">
                                <thead>
                                    <tr style="background:#f5f5f5;">
                                        <th style="padding:10px; text-align:left;">Subject</th>
                                        <th style="padding:10px; text-align:left;">Teacher</th>
                                        <th style="padding:10px; text-align:center;">Date</th>
                                        <th style="padding:10px; text-align:center;">Status</th>
                                        <th style="padding:10px; text-align:left;">Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Check attendance table exists before querying
                                    $att_table_check = mysqli_query($conn, "SHOW TABLES LIKE 'attendance'");
                                    if (!$att_table_check || mysqli_num_rows($att_table_check) === 0) {
                                        // Attendance feature/table not available
                                        echo '<tr><td colspan="5" style="padding:20px; text-align:center; color:#999;">Attendance data not available (feature disabled or table missing)</td></tr>';
                                    } else {
                                        $att_q = mysqli_query($conn, "SELECT a.*, u.name as teacher_name FROM attendance a JOIN users u ON a.teacher_id = u.id WHERE a.student_id = {$user['id']} ORDER BY a.attendance_date DESC");
                                        if ($att_q === false) {
                                            // Query failed — show error but avoid crash
                                            $err = mysqli_error($conn);
                                            echo '<tr><td colspan="5" style="padding:20px; text-align:center; color:#999;">Unable to load attendance (' . htmlspecialchars($err) . ')</td></tr>';
                                        } elseif (mysqli_num_rows($att_q) > 0) {
                                            while ($att = mysqli_fetch_assoc($att_q)):
                                                $status_color = $att['status'] === 'present' ? '#28a745' : ($att['status'] === 'absent' ? '#dc3545' : '#ffc107');
                                    ?>
                                        <tr>
                                            <td style="padding:10px;"><?php echo htmlspecialchars($att['subject']); ?></td>
                                            <td style="padding:10px;"><?php echo htmlspecialchars($att['teacher_name']); ?></td>
                                            <td style="padding:10px; text-align:center;"><?php echo date('d M Y', strtotime($att['attendance_date'])); ?></td>
                                            <td style="padding:10px; text-align:center;">
                                                <span style="background:<?php echo $status_color; ?>; color:white; padding:4px 8px; border-radius:4px; font-weight:600;"><?php echo strtoupper($att['status']); ?></span>
                                            </td>
                                            <td style="padding:10px;"><?php echo $att['remarks'] ? htmlspecialchars($att['remarks']) : '-'; ?></td>
                                        </tr>
                                    <?php 
                                            endwhile;
                                        } else {
                                            echo '<tr><td colspan="5" style="padding:20px; text-align:center; color:#999;">No attendance records yet</td></tr>';
                                        }
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="section-box" style="margin-top:30px; display:grid; grid-template-columns:repeat(auto-fit, minmax(250px, 1fr)); gap:20px;">
                        <h3 style="grid-column:1/-1;">Attendance Summary by Subject</h3>
                        <?php
                        // Simplified: only use brace-style PHP to avoid mixing alternative syntax
                        $att_table_check2 = mysqli_query($conn, "SHOW TABLES LIKE 'attendance'");
                        if ($att_table_check2 && mysqli_num_rows($att_table_check2) > 0) {
                            $summary_q = mysqli_query($conn, "SELECT subject,
                                COUNT(*) as total,
                                SUM(status='present') as present_count,
                                SUM(status='absent') as absent_count,
                                SUM(status='late') as late_count,
                                ROUND((SUM(status='present')/COUNT(*))*100, 1) as percentage
                                FROM attendance WHERE student_id = {$user['id']} GROUP BY subject");

                            if ($summary_q && mysqli_num_rows($summary_q) > 0) {
                                while ($summary = mysqli_fetch_assoc($summary_q)) {
                                    $percentage = $summary['percentage'] ?? 0;
                                    $percentage_color = $percentage >= 75 ? '#28a745' : ($percentage >= 50 ? '#ffc107' : '#dc3545');
                                    echo '<div style="border:1px solid #ddd; padding:15px; border-radius:8px; background:white;">';
                                    echo '<h4 style="margin-top:0; color:#333;">' . htmlspecialchars($summary['subject']) . '</h4>';
                                    echo '<div style="margin:10px 0;">';
                                    echo '<span style="display:inline-block; margin-right:15px;">✓ Present: <strong>' . intval($summary['present_count']) . '</strong></span>';
                                    echo '<span style="display:inline-block; margin-right:15px;">✗ Absent: <strong>' . intval($summary['absent_count']) . '</strong></span>';
                                    echo '<span style="display:inline-block;">⏱ Late: <strong>' . intval($summary['late_count']) . '</strong></span>';
                                    echo '</div>';
                                    echo '<div style="margin-top:10px;">';
                                    echo '<div style="background:#f0f0f0; border-radius:4px; height:8px; overflow:hidden;">';
                                    echo '<div style="background:' . $percentage_color . '; height:100%; width:' . $percentage . '%;"></div>';
                                    echo '</div>';
                                    echo '<p style="margin:5px 0 0 0; text-align:center; font-weight:600; color:' . $percentage_color . '; font-size:14px;">' . $percentage . '% Present</p>';
                                    echo '</div>';
                                    echo '<p style="margin:10px 0 0 0; font-size:12px; color:#666;">Total Classes: ' . intval($summary['total']) . '</p>';
                                    echo '</div>';
                                }
                            } else {
                                echo '<p style="grid-column:1/-1; color:#999; padding:10px;">No attendance summary data available</p>';
                            }
                        } else {
                            echo '<p style="grid-column:1/-1; color:#999; padding:10px;">Attendance summary not available (feature disabled)</p>';
                        }
                        ?>
                    </div>
                    
                    <?php
                    // Overall attendance report (only if attendance table exists)
                    $att_table_check3 = mysqli_query($conn, "SHOW TABLES LIKE 'attendance'");
                    if ($att_table_check3 && mysqli_num_rows($att_table_check3) > 0):
                        $allatt = mysqli_query($conn, "SELECT 
                            COUNT(*) as total_classes,
                            SUM(status='present') as total_present,
                            SUM(status='absent') as total_absent,
                            SUM(status='late') as total_late,
                            ROUND((SUM(status='present')/COUNT(*))*100, 1) as overall_percentage
                            FROM attendance WHERE student_id = {$user['id']}");
                        $overall = $allatt ? mysqli_fetch_assoc($allatt) : null;
                        if ($overall && $overall['total_classes'] > 0):
                        $op = $overall['overall_percentage'];
                        $status_msg = $op >= 75 ? '✓ Good Attendance' : ($op >= 50 ? '⚠ Fair Attendance - Needs Improvement' : '✗ Low Attendance - See Teacher');
                        $status_color = $op >= 75 ? '#28a745' : ($op >= 50 ? '#ffc107' : '#dc3545');
                    ?>
                        <div class="section-box" style="margin-top:30px; background:<?php echo $status_color; ?>0f; border-left:4px solid <?php echo $status_color; ?>;">
                            <h3 style="color:<?php echo $status_color; ?>; margin-top:0;">Overall Attendance Report</h3>
                            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:15px; margin:20px 0;">
                                <div style="text-align:center;">
                                    <p style="font-size:28px; font-weight:700; margin:0; color:<?php echo $status_color; ?>;"><?php echo $op; ?>%</p>
                                    <p style="margin:5px 0 0 0; color:#666; font-size:14px;">Overall Attendance</p>
                                </div>
                                <div style="text-align:center;">
                                    <p style="font-size:28px; font-weight:700; margin:0; color:#28a745;"><?php echo $overall['total_present']; ?></p>
                                    <p style="margin:5px 0 0 0; color:#666; font-size:14px;">Classes Present</p>
                                </div>
                                <div style="text-align:center;">
                                    <p style="font-size:28px; font-weight:700; margin:0; color:#dc3545;"><?php echo $overall['total_absent']; ?></p>
                                    <p style="margin:5px 0 0 0; color:#666; font-size:14px;">Classes Absent</p>
                                </div>
                                <div style="text-align:center;">
                                    <p style="font-size:28px; font-weight:700; margin:0; color:#ffc107;"><?php echo $overall['total_late']; ?></p>
                                    <p style="margin:5px 0 0 0; color:#666; font-size:14px;">Classes Late</p>
                                </div>
                            </div>
                            <p style="background:white; padding:10px; border-radius:6px; text-align:center; font-weight:600; color:<?php echo $status_color; ?>; margin:0;"><?php echo $status_msg; ?></p>
                        </div>
                    <?php endif; endif; ?>
                
                <?php
                // CHANGE PASSWORD SECTION
                elseif ($section === 'change_password'):
                ?>
                    <div class="section-box">
                        <h3>Change Password</h3>
                        <form method="POST" action="actions.php">
                            <div class="form-group">
                                <label>Current Password</label>
                                <input type="password" name="current_password" required>
                            </div>
                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password" name="new_password" required minlength="6">
                            </div>
                            <div class="form-group">
                                <label>Confirm New Password</label>
                                <input type="password" name="confirm_password" required>
                            </div>
                            <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                        </form>
                    </div>
                
                <?php
                // NOTES SECTION
                elseif ($section === 'notes'):
                ?>
                    <?php if ($user['role'] === 'Teacher'): ?>
                    <div class="section-box">
                        <h3>Upload New Note</h3>
                        <form method="POST" action="actions.php" enctype="multipart/form-data">
                            <div class="form-row">
                                <div class="form-group">
                                    <input type="text" name="title" placeholder="Note Title" required>
                                </div>
                                <div class="form-group">
                                    <input type="text" name="subject" placeholder="Subject" required>
                                </div>
                                <div class="form-group">
                                    <select name="course" required>
                                        <option value="">Select Course</option>
                                        <option value="All">All Courses</option>
                                        <option value="B.Com">B.Com</option>
                                        <option value="M.Com">M.Com</option>
                                        <option value="BCA">BCA</option>
                                        <option value="BA">BA</option>
                                        <option value="BBA">BBA</option>
                                        <option value="PGDCA">PGDCA</option>
                                        <option value="DCA">DCA</option>
                                        <option value="M.Sc">M.Sc</option>
                                        <option value="MA">MA</option>
                                        <option value="B.Lib">B.Lib</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <input type="file" name="file" required>
                                <small>Allowed: PDF, DOC, DOCX, JPG, PNG</small>
                            </div>
                            <button type="submit" name="upload_note" class="btn btn-primary">Upload Note</button>
                        </form>
                    </div>
                    <?php endif; ?>
                    
                    <div class="section-box">
                        <h3><?php echo $user['role'] === 'Teacher' ? 'Uploaded Notes' : 'Available Notes'; ?></h3>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Subject</th>
                                    <th>Course</th>
                                    <th>File</th>
                                    <th>Uploaded</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($user['role'] === 'Teacher') {
                                    $notes = mysqli_query($conn, "SELECT * FROM notes WHERE uploaded_by = {$user['id']} ORDER BY created_at DESC");
                                } else {
                                    $course = $user['course'] ?? '';
                                    $course_esc = mysqli_real_escape_string($conn, $course);
                                    $notes = mysqli_query($conn, "SELECT n.*, u.name as uploader_name FROM notes n JOIN users u ON n.uploaded_by = u.id WHERE (n.course = '" . $course_esc . "' OR n.course = 'All') ORDER BY n.created_at DESC");
                                }
                                ?>
                                <?php if ($notes && mysqli_num_rows($notes) > 0): while ($note = mysqli_fetch_assoc($notes)): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($note['title']); ?></td>
                                        <td><?php echo htmlspecialchars($note['subject']); ?></td>
                                        <td><span class="badge"><?php echo $note['course']; ?></span></td>
                                        <td><?php echo htmlspecialchars($note['file_name']); ?></td>
                                        <td><?php echo formatDate($note['created_at']); ?></td>
                                        <td>
                                            <a href="<?php echo htmlspecialchars($note['file_path']); ?>" download class="btn btn-sm btn-primary">⬇️ Download</a>
                                            <?php if ($user['role'] === 'Teacher'): ?>
                                                <a href="actions.php?delete_note=<?php echo $note['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this note?')">Delete</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; else: ?>
                                    <tr><td colspan="6" style="text-align:center; padding:20px; color:#999;">📚 No notes available for your course yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                
                <?php
                // ASSIGNMENTS SECTION
                elseif ($section === 'assignments'):
                ?>
                    <?php if ($user['role'] === 'Teacher'): ?>
                    <div class="section-box">
                        <h3>Create New Assignment</h3>
                        <form method="POST" action="actions.php">
                            <div class="form-row">
                                <div class="form-group">
                                    <input type="text" name="title" placeholder="Assignment Title" required>
                                </div>
                                <div class="form-group">
                                    <input type="text" name="subject" placeholder="Subject" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <select name="course" required>
                                        <option value="">Select Course</option>
                                        <option value="All">All Courses</option>
                                        <option value="B.Com">B.Com</option>
                                        <option value="M.Com">M.Com</option>
                                        <option value="BCA">BCA</option>
                                        <option value="BA">BA</option>
                                        <option value="BBA">BBA</option>
                                        <option value="PGDCA">PGDCA</option>
                                        <option value="DCA">DCA</option>
                                        <option value="M.Sc">M.Sc</option>
                                        <option value="MA">MA</option>
                                        <option value="B.Lib">B.Lib</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <input type="datetime-local" name="due_date" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <textarea name="description" placeholder="Assignment Description" rows="3" required></textarea>
                            </div>
                            <button type="submit" name="create_assignment" class="btn btn-primary">Create Assignment</button>
                        </form>
                    </div>
                    
                    <div class="section-box">
                        <h3>Manage Assignments</h3>
                        <?php
                        $assignments = mysqli_query($conn, "SELECT a.*, COUNT(s.id) as submissions FROM assignments a LEFT JOIN assignment_submissions s ON a.id = s.assignment_id WHERE a.created_by = {$user['id']} GROUP BY a.id ORDER BY a.created_at DESC");
                        while ($assignment = mysqli_fetch_assoc($assignments)):
                            $is_past_due = strtotime($assignment['due_date']) < time();
                        ?>
                            <div class="assignment-card">
                                <div class="assignment-header">
                                    <h4><?php echo $assignment['title']; ?></h4>
                                    <div>
                                        <span class="badge"><?php echo $assignment['course']; ?></span>
                                        <span class="badge <?php echo $is_past_due ? 'badge-danger' : 'badge-success'; ?>">
                                            <?php echo $is_past_due ? 'Closed' : 'Active'; ?>
                                        </span>
                                    </div>
                                </div>
                                <p><?php echo $assignment['description']; ?></p>
                                <div class="assignment-meta">
                                    <span>Subject: <?php echo $assignment['subject']; ?></span>
                                    <span>Due: <?php echo formatDate($assignment['due_date']); ?></span>
                                    <span>Submissions: <?php echo $assignment['submissions']; ?></span>
                                </div>
                                <div class="assignment-actions">
                                    <button onclick="viewSubmissions(<?php echo $assignment['id']; ?>)" class="btn btn-sm btn-primary">View Submissions</button>
                                    <a href="actions.php?delete_assignment=<?php echo $assignment['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this assignment?')">Delete</a>
                                </div>
                                <div id="submissions-<?php echo $assignment['id']; ?>" class="submissions-list" style="display:none;">
                                    <h5>Submissions</h5>
                                    <?php
                                    $submissions = mysqli_query($conn, "SELECT s.*, u.name as student_name FROM assignment_submissions s JOIN users u ON s.student_id = u.id WHERE s.assignment_id = {$assignment['id']}");
                                    if ($submissions && mysqli_num_rows($submissions) > 0):
                                        while ($sub = mysqli_fetch_assoc($submissions)):
                                    ?>
                                        <div class="submission-item">
                                            <span><?php echo $sub['student_name']; ?></span>
                                            <span><?php echo $sub['is_late'] ? '<span class="badge badge-danger">Late</span>' : '<span class="badge badge-success">On Time</span>'; ?></span>
                                            <span><?php echo formatDate($sub['submitted_at']); ?></span>
                                            <a href="<?php echo $sub['file_path']; ?>" download class="btn btn-sm btn-primary">Download</a>
                                        </div>
                                    <?php endwhile; else: ?>
                                        <p>No submissions yet.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                    
                    <?php else: // Student view ?>
                    <div class="section-box">
                        <h3>My Assignments</h3>
                        <?php
                        $course = $user['course'] ?? '';
                        $course_esc = mysqli_real_escape_string($conn, $course);
                        $assignments = mysqli_query($conn, "SELECT a.*, s.id as submitted_id, s.is_late, s.submitted_at FROM assignments a LEFT JOIN assignment_submissions s ON a.id = s.assignment_id AND s.student_id = {$user['id']} WHERE (a.course = '" . $course_esc . "' OR a.course = 'All') ORDER BY a.due_date ASC");
                        while ($assignment = mysqli_fetch_assoc($assignments)):
                            $is_past_due = strtotime($assignment['due_date']) < time();
                            $is_submitted = $assignment['submitted_id'] !== null;
                        ?>
                            <div class="assignment-card">
                                <div class="assignment-header">
                                    <h4><?php echo $assignment['title']; ?></h4>
                                    <div>
                                        <?php if ($is_submitted): ?>
                                            <span class="badge badge-success">Submitted</span>
                                            <?php if ($assignment['is_late']): ?>
                                                <span class="badge badge-danger">Late</span>
                                            <?php endif; ?>
                                        <?php elseif ($is_past_due): ?>
                                            <span class="badge badge-danger">Missed</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Pending</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <p><?php echo $assignment['description']; ?></p>
                                <div class="assignment-meta">
                                    <span>Subject: <?php echo $assignment['subject']; ?></span>
                                    <span>Due: <?php echo formatDate($assignment['due_date']); ?></span>
                                    <?php if ($is_submitted): ?>
                                        <span>Submitted: <?php echo formatDate($assignment['submitted_at']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!$is_past_due || (!$is_submitted && !$is_past_due)): ?>
                                <form method="POST" action="actions.php" enctype="multipart/form-data" class="mt-2">
                                    <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                                    <div class="form-row">
                                        <input type="file" name="file" required>
                                        <button type="submit" name="submit_assignment" class="btn btn-primary">
                                            <?php echo $is_submitted ? 'Re-submit' : 'Submit'; ?>
                                        </button>
                                    </div>
                                </form>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; 
                        if (!$assignments || mysqli_num_rows($assignments) === 0): ?>
                            <div class="empty-state" style="padding:30px; text-align:center; color:#999;">
                                <p>📝 No assignments found for your course yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                
                <?php
                // TESTS SECTION
                elseif ($section === 'tests'):
                ?>
                    <?php if ($user['role'] === 'Teacher'): ?>
                    <div class="section-box">
                        <h3>Create New Test</h3>
                        <form method="POST" action="actions.php" id="testForm">
                            <div class="form-row">
                                <div class="form-group">
                                    <input type="text" name="title" placeholder="Test Title" required>
                                </div>
                                <div class="form-group">
                                    <input type="text" name="subject" placeholder="Subject" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <select name="course" required>
                                        <option value="">Select Course</option>
                                        <option value="All">All Courses</option>
                                        <option value="B.Com">B.Com</option>
                                        <option value="M.Com">M.Com</option>
                                        <option value="BCA">BCA</option>
                                        <option value="BA">BA</option>
                                        <option value="BBA">BBA</option>
                                        <option value="PGDCA">PGDCA</option>
                                        <option value="DCA">DCA</option>
                                        <option value="M.Sc">M.Sc</option>
                                        <option value="MA">MA</option>
                                        <option value="B.Lib">B.Lib</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <input type="number" name="time_limit" placeholder="Time Limit (minutes)" required>
                                </div>
                                <div class="form-group">
                                    <input type="number" name="pass_percentage" placeholder="Pass %" min="0" max="100" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Test Options:</label>
                                <div class="checkbox-options">
                                    <div class="option-item">
                                        <input type="checkbox" name="allow_multiple" id="allow_multiple">
                                        <label for="allow_multiple">Allow Multiple Attempts</label>
                                    </div>
                                    <div class="option-item">
                                        <input type="checkbox" name="test_status" value="draft" id="draft" checked>
                                        <label for="draft">Save as Draft</label>
                                    </div>
                                    <div class="option-item">
                                        <input type="checkbox" name="test_status" value="published" id="published">
                                        <label for="published">Publish Now</label>
                                    </div>
                                </div>
                            </div>
                            
                            <h4>Questions</h4>
                            <div id="questionsContainer">
                                <div class="question-item">
                                    <input type="text" name="questions[0][question]" placeholder="Question" required>
                                    <div class="options-grid">
                                        <input type="text" name="questions[0][option_a]" placeholder="Option A" required>
                                        <input type="text" name="questions[0][option_b]" placeholder="Option B" required>
                                        <input type="text" name="questions[0][option_c]" placeholder="Option C" required>
                                        <input type="text" name="questions[0][option_d]" placeholder="Option D" required>
                                    </div>
                                    <div class="form-row">
                                        <select name="questions[0][correct]" required>
                                            <option value="">Correct Answer</option>
                                            <option value="A">A</option>
                                            <option value="B">B</option>
                                            <option value="C">C</option>
                                            <option value="D">D</option>
                                        </select>
                                        <input type="number" name="questions[0][marks]" value="1" min="1" placeholder="Marks" required>
                                    </div>
                                </div>
                            </div>
                            <button type="button" onclick="addQuestion()" class="btn btn-secondary">Add Question</button>
                            <button type="submit" name="create_test" class="btn btn-primary">Create Test</button>
                        </form>
                    </div>
                    
                    <div class="section-box">
                        <h3>My Tests</h3>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Subject</th>
                                    <th>Course</th>
                                    <th>Questions</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $tests = mysqli_query($conn, "SELECT t.*, COUNT(q.id) as question_count FROM tests t LEFT JOIN test_questions q ON t.id = q.test_id WHERE t.created_by = {$user['id']} GROUP BY t.id ORDER BY t.created_at DESC");
                                while ($test = mysqli_fetch_assoc($tests)):
                                ?>
                                    <tr>
                                        <td><?php echo $test['title']; ?></td>
                                        <td><?php echo $test['subject']; ?></td>
                                        <td><span class="badge"><?php echo $test['course']; ?></span></td>
                                        <td><?php echo $test['question_count']; ?></td>
                                        <td><?php echo $test['time_limit']; ?> min</td>
                                        <td>
                                            <span class="badge <?php echo $test['status'] === 'published' ? 'badge-success' : 'badge-warning'; ?>">
                                                <?php echo ucfirst($test['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo formatDate($test['created_at']); ?></td>
                                        <td>
                                            <?php if ($test['status'] === 'draft'): ?>
                                                <form method="POST" action="actions.php" style="display:inline;">
                                                    <input type="hidden" name="test_id" value="<?php echo $test['id']; ?>">
                                                    <input type="hidden" name="test_status" value="published">
                                                    <button type="submit" name="update_test_status" class="btn btn-sm btn-success" onclick="return confirm('Publish this test now?')">Publish</button>
                                                </form>
                                            <?php endif; ?>
                                            <a href="actions.php?delete_test=<?php echo $test['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this test?')">Delete</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php else: // Student view ?>
                    <div class="section-box">
                        <h3>Available Tests</h3>
                        <?php
                        $course = $user['course'] ?? '';
                        $course_esc = mysqli_real_escape_string($conn, $course);
                        $tests = mysqli_query($conn, "SELECT t.*, 
                            (SELECT COUNT(*) FROM test_results WHERE test_id = t.id AND student_id = {$user['id']}) as attempted
                            FROM tests t WHERE (t.course = '" . $course_esc . "' OR t.course = 'All')
                            AND t.status = 'published' 
                            ORDER BY t.created_at DESC");
                        if (!$tests || mysqli_num_rows($tests) === 0): ?>
                            <div class="empty-state" style="padding:30px; text-align:center; color:#999;">
                                <p>📋 No tests available for your course yet.</p>
                            </div>
                        <?php else: while ($test = mysqli_fetch_assoc($tests)):
                            $can_attempt = ($test['attempted'] == 0 || $test['allow_multiple_attempts'] == 1);
                        ?>
                            <div class="test-card">
                                <div class="test-header">
                                    <h4><?php echo $test['title']; ?></h4>
                                    <div>
                                        <span class="badge"><?php echo $test['subject']; ?></span>
                                        <?php if ($test['attempted'] > 0): ?>
                                            <span class="badge badge-success">Attempted</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="test-meta">
                                    <span>⏱️ <?php echo $test['time_limit']; ?> minutes</span>
                                    <span>📊 <?php echo $test['total_marks']; ?> marks</span>
                                    <span>✅ Pass: <?php echo $test['pass_marks']; ?> marks</span>
                                </div>
                                <?php if ($can_attempt): ?>
                                    <button onclick="startTest(<?php echo $test['id']; ?>)" class="btn btn-primary">Start Test</button>
                                <?php else: ?>
                                    <button class="btn btn-secondary" disabled>Already Attempted</button>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; endif; ?>
                    </div>
                    
                    <!-- Test Taking Modal -->
                    <div id="testModal" class="modal">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h3 id="testTitle"></h3>
                                <div class="timer" id="timer">00:00</div>
                            </div>
                            <form method="POST" action="actions.php" id="testSubmitForm">
                                <input type="hidden" name="test_id" id="testId">
                                <div id="questionsDisplay"></div>
                                <button type="submit" name="submit_test" class="btn btn-primary btn-block">Submit Test</button>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                
                <?php
                // RESULTS SECTION
                elseif ($section === 'results' && $user['role'] === 'Student'):
                ?>
                    <div class="section-box">
                        <h3>My Test Results</h3>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Test</th>
                                    <th>Subject</th>
                                    <th>Marks</th>
                                    <th>Percentage</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $results = mysqli_query($conn, "SELECT r.*, t.title, t.subject, t.pass_marks FROM test_results r JOIN tests t ON r.test_id = t.id WHERE r.student_id = {$user['id']} ORDER BY r.submitted_at DESC");
                                if ($results && mysqli_num_rows($results) > 0):
                                    while ($result = mysqli_fetch_assoc($results)):
                                ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($result['title']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($result['subject']); ?></td>
                                        <td>
                                            <strong><?php echo $result['marks_obtained']; ?></strong> / <?php echo $result['total_marks']; ?>
                                            <div style="width:100%; background:#eee; border-radius:4px; height:6px; margin-top:4px;">
                                                <div style="width:<?php echo min(100, $result['percentage']); ?>%; background:<?php echo $result['status']==='Pass' ? '#10b981' : '#ef4444'; ?>; height:6px; border-radius:4px;"></div>
                                            </div>
                                        </td>
                                        <td style="font-weight:600; color:<?php echo $result['percentage'] >= 70 ? '#10b981' : ($result['percentage'] >= 50 ? '#f59e0b' : '#ef4444'); ?>">
                                            <?php echo number_format($result['percentage'], 1); ?>%
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $result['status'] === 'Pass' ? 'badge-success' : 'badge-danger'; ?>">
                                                <?php echo $result['status'] === 'Pass' ? '✅ Pass' : '❌ Fail'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo formatDate($result['submitted_at']); ?></td>
                                    </tr>
                                <?php endwhile; else: ?>
                                    <tr><td colspan="6" style="text-align:center; padding:30px; color:#999;">📊 No test results yet. Take a test to see your results here.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                
                <?php
                // DIGITAL LOCKER SECTION
                elseif ($section === 'locker'):
                ?>
                    <div class="section-box">
                        <h3>Upload File</h3>
                        <form method="POST" action="actions.php" enctype="multipart/form-data">
                            <div class="form-row">
                                <div class="form-group">
                                    <input type="text" name="subject" placeholder="Subject/Tag" required>
                                </div>
                                <div class="form-group">
                                    <input type="file" name="file" required>
                                    <small>Max 10MB | PDF, DOC, DOCX, JPG, PNG</small>
                                </div>
                                <button type="submit" name="upload_locker" class="btn btn-primary">Upload</button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="section-box">
                        <h3>My Files</h3>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>File Name</th>
                                    <th>Subject</th>
                                    <th>Size</th>
                                    <th>Uploaded</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $files = mysqli_query($conn, "SELECT * FROM digital_locker WHERE user_id = {$user['id']} ORDER BY uploaded_at DESC");
                                while ($file = mysqli_fetch_assoc($files)):
                                ?>
                                    <tr>
                                        <td><?php echo $file['file_name']; ?></td>
                                        <td><span class="badge"><?php echo $file['subject']; ?></span></td>
                                        <td><?php echo formatFileSize($file['file_size']); ?></td>
                                        <td><?php echo formatDate($file['uploaded_at']); ?></td>
                                        <td>
                                            <a href="<?php echo $file['file_path']; ?>" download class="btn btn-sm btn-primary">Download</a>
                                            <a href="actions.php?delete_locker=<?php echo $file['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this file?')">Delete</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                
                <?php
                // ADMIN - USERS SECTION
                elseif ($section === 'users' && $user['role'] === 'Admin'):
                ?>
                    <div class="section-box">
                        <h3>Add New User</h3>
                        <form method="POST" action="actions.php">
                            <div class="form-row">
                                <div class="form-group">
                                    <input type="text" name="name" placeholder="Full Name" required>
                                </div>
                                <div class="form-group">
                                    <input type="email" name="email" placeholder="Email" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <input type="password" name="password" placeholder="Password" required>
                                </div>
                                <div class="form-group">
                                    <select name="role" id="roleSelect" required onchange="toggleCourse()">
                                        <option value="">Select Role</option>
                                        <option value="Student">Student</option>
                                        <option value="Teacher">Teacher</option>
                                        <option value="Admin">Admin</option>
                                    </select>
                                </div>
                                <div class="form-group" id="courseGroup" style="display:none;">
                                    <select name="course">
                                        <option value="">Select Course</option>
                                        <option value="B.Com">B.Com</option>
                                        <option value="M.Com">M.Com</option>
                                        <option value="BCA">BCA</option>
                                        <option value="BA">BA</option>
                                        <option value="BBA">BBA</option>
                                        <option value="PGDCA">PGDCA</option>
                                        <option value="DCA">DCA</option>
                                        <option value="M.Sc">M.Sc</option>
                                        <option value="MA">MA</option>
                                        <option value="B.Lib">B.Lib</option>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
                        </form>
                    </div>
                    
                    <div class="section-box">
                        <h3>Pending Approvals</h3>
                        <?php
                        $pending_users = mysqli_query($conn, "SELECT * FROM users WHERE status = 'pending' ORDER BY created_at DESC");
                        if ($pending_users && mysqli_num_rows($pending_users) > 0):
                        ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Details</th>
                                    <th>Applied</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($u = mysqli_fetch_assoc($pending_users)): ?>
                                    <tr>
                                        <td><?php echo $u['name']; ?></td>
                                        <td><?php echo $u['email']; ?></td>
                                        <td><?php echo $u['role']; ?></td>
                                        <td>
                                            <?php if ($u['role'] === 'Teacher'): ?>
                                                Subject: <?php echo $u['subject']; ?><br>
                                                Qualification: <?php echo $u['qualification']; ?><br>
                                                Experience: <?php echo $u['experience']; ?> years
                                            <?php elseif ($u['role'] === 'Student'): ?>
                                                Course: <?php echo $u['course'] ?? '-'; ?> | RollNo: <?php echo $u['rollno'] ?? '-'; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo formatDate($u['created_at']); ?></td>
                                        <td>
                                            <form method="POST" action="actions.php" style="display:inline;">
                                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                <button type="submit" name="approve_user" class="btn btn-sm btn-success">Approve</button>
                                                <button type="submit" name="reject_user" class="btn btn-sm btn-danger" onclick="return confirm('Reject this user?')">Reject</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <p>No pending approvals.</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="section-box">
                        <h3>Manage Users</h3>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Profile</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Course</th>
                                    <th>Status</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $users = mysqli_query($conn, "SELECT * FROM users ORDER BY created_at DESC");
                                while ($u = mysqli_fetch_assoc($users)):
                                ?>
                                    <tr>
                                        <td>
                                            <?php $u_photo_path = $u['profile_photo'] ? 'uploads/profile/' . $u['profile_photo'] : null; ?>
                                            <img src="<?php echo htmlspecialchars(($u_photo_path && file_exists($u_photo_path)) ? $u_photo_path : 'https://via.placeholder.com/40x40?text=' . substr($u['name'], 0, 1)); ?>" alt="Profile" class="profile-thumb">
                                        </td>
                                        <td><?php echo $u['name']; ?></td>
                                        <td><?php echo $u['email']; ?></td>
                                        <td>
                                            <form method="POST" action="actions.php" style="display:inline;">
                                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                <select name="role" onchange="this.form.submit()">
                                                    <option value="Student" <?php echo $u['role'] === 'Student' ? 'selected' : ''; ?>>Student</option>
                                                    <option value="Teacher" <?php echo $u['role'] === 'Teacher' ? 'selected' : ''; ?>>Teacher</option>
                                                    <option value="Admin" <?php echo $u['role'] === 'Admin' ? 'selected' : ''; ?>>Admin</option>
                                                </select>
                                                <button type="submit" name="update_role" style="display:none;"></button>
                                            </form>
                                        </td>
                                        <td><?php echo ($u['course'] ?? '-') . ' (' . ($u['rollno'] ?? '-') . ')'; ?></td>
                                        <td>
                                            <span class="badge <?php echo $u['status'] === 'approved' ? 'badge-success' : ($u['status'] === 'pending' ? 'badge-warning' : 'badge-danger'); ?>">
                                                <?php echo ucfirst($u['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo formatDate($u['created_at']); ?></td>
                                        <td>
                                            <?php if ($u['id'] !== $user['id']): ?>
                                                <a href="actions.php?delete_user=<?php echo $u['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this user?')">Delete</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                
                <?php
                // ADMIN - FEES APPROVAL SECTION
                elseif ($section === 'fees_approval' && $user['role'] === 'Admin'):
                ?>
                    <div class="section-header">
                        <h2>💳 Fee Payment Approvals</h2>
                    </div>

                    <!-- Fee Statistics Cards -->
                    <div class="dashboard-cards" style="margin-bottom:30px;">
                        <?php
                        $pending_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM fee_payments WHERE status = 'pending'"))['count'];
                        $approved_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM fee_payments WHERE status = 'approved'"))['count'];
                        $total_amount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(f.amount) as total FROM fee_payments fp JOIN fees f ON fp.fee_id = f.id WHERE fp.status = 'approved'"));
                        $total_approved = $total_amount['total'] ?? 0;
                        ?>
                        <div class="stat-card" style="border-left:5px solid #ffc107;">
                            <h3 style="color:#ff9800;"><?php echo $pending_count; ?></h3>
                            <p>⏳ Pending Approvals</p>
                        </div>
                        <div class="stat-card" style="border-left:5px solid #28a745;">
                            <h3 style="color:#28a745;"><?php echo $approved_count; ?></h3>
                            <p>✅ Approved</p>
                        </div>
                        <div class="stat-card" style="border-left:5px solid #667eea;">
                            <h3 style="color:#667eea;">₹<?php echo number_format($total_approved, 2); ?></h3>
                            <p>💰 Total Approved</p>
                        </div>
                    </div>

                    <!-- QR Code Management Section -->
                    <div class="section-box" style="margin-bottom:30px; border:2px solid #667eea; border-radius:10px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px;">
                            <h3 style="margin:0; color:#667eea;">📱 Payment QR Code</h3>
                            <span style="font-size:12px; color:#999; background:#f5f5f5; padding:4px 10px; border-radius:20px;">UPI: <?php echo PAYMENT_UPI_ID; ?></span>
                        </div>
                        <div style="display:grid; grid-template-columns:auto 1fr; gap:30px; align-items:center; flex-wrap:wrap;">
                            <div style="text-align:center;">
                                <?php if (file_exists(PAYMENT_QR_IMAGE)): ?>
                                    <img src="<?php echo PAYMENT_QR_IMAGE; ?>?v=<?php echo filemtime(PAYMENT_QR_IMAGE); ?>" 
                                         alt="QR Code" 
                                         style="width:140px; height:140px; border:3px solid #667eea; border-radius:8px; padding:6px; background:white; display:block; margin:0 auto; object-fit:contain;">
                                    <small style="display:block; margin-top:8px; color:#10b981; font-weight:600;">✅ Active QR Code</small>
                                <?php else: ?>
                                    <div style="width:140px; height:140px; background:#f5f5f5; border:3px dashed #ccc; border-radius:8px; display:flex; align-items:center; justify-content:center; flex-direction:column; color:#999; margin:0 auto;">
                                        <div style="font-size:36px;">📱</div>
                                        <small style="font-size:11px; margin-top:5px; text-align:center; padding:0 8px;">No QR uploaded</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <p style="color:#555; font-size:14px; margin-bottom:15px;">
                                    Upload a UPI QR code image. Students will see this in their payment screen and can tap to enlarge and scan.
                                </p>
                                <form method="POST" action="actions.php" enctype="multipart/form-data" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                                    <input type="file" name="qr_image" accept="image/*" required 
                                           style="flex:1; min-width:200px; padding:10px; border:2px dashed #667eea; border-radius:6px; background:#fafbff; font-size:13px; cursor:pointer;">
                                    <button type="submit" class="btn btn-primary" style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%); color:white; border:none; padding:10px 20px; border-radius:6px; font-weight:600; white-space:nowrap;">
                                        📤 Upload QR
                                    </button>
                                </form>
                                <small style="color:#999; font-size:11px; margin-top:8px; display:block;">Supported: JPG, PNG, GIF, WebP — Max 2MB</small>
                                <small style="color:#667eea; font-size:11px; margin-top:2px; display:block;">File saved to: <code><?php echo PAYMENT_QR_IMAGE; ?></code></small>
                            </div>
                        </div>
                    </div>

                    <!-- Cash Payment Section (Offline) -->
                    <div class="section-box">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px;">
                            <h3 style="margin:0;">💰 Mark Payment as Paid (Cash Payment)</h3>
                            <button onclick="openDirectCashPaymentModal()" class="btn btn-primary" style="background:linear-gradient(135deg, #10b981 0%, #059669 100%); color:white; padding:10px 16px; border-radius:6px; border:none; cursor:pointer; font-size:14px; font-weight:600; white-space:nowrap;">
                                ➕ Direct Payment Entry
                            </button>
                        </div>
                        <p style="color:#666; margin-bottom:20px; font-size:14px;">Mark fees as paid when student pays cash in office. Receipt will be auto-generated.</p>
                        <div style="overflow-x:auto;">
                            <table class="data-table" style="width:100%;">
                                <thead>
                                    <tr style="background:linear-gradient(135deg, #10b981 0%, #059669 100%); color:white;">
                                        <th style="padding:12px; text-align:left;">Student Name</th>
                                        <th style="padding:12px; text-align:left;">Email</th>
                                        <th style="padding:12px; text-align:center;">Fee Type</th>
                                        <th style="padding:12px; text-align:center;">Amount</th>
                                        <th style="padding:12px; text-align:center;">Paid Amount</th>
                                        <th style="padding:12px; text-align:center;">Status</th>
                                        <th style="padding:12px; text-align:center;">Payment Method</th>
                                        <th style="padding:12px; text-align:center;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $cash_fees = mysqli_query($conn, "SELECT f.*, u.name, u.email FROM fees f 
                                                                      JOIN users u ON f.student_id = u.id 
                                                                      WHERE f.status IN ('pending', 'partial') 
                                                                      ORDER BY f.due_date ASC LIMIT 50");
                                    if ($cash_fees && mysqli_num_rows($cash_fees) > 0):
                                        while ($fee = mysqli_fetch_assoc($cash_fees)):
                                            $balance = $fee['amount'] - $fee['paid_amount'];
                                            $status_color = $fee['status'] === 'pending' ? '#dc3545' : '#ffc107';
                                            $status_text = $fee['status'] === 'pending' ? 'Pending' : 'Partial';
                                    ?>
                                        <tr style="border-bottom:1px solid #eee;">
                                            <td style="padding:12px; font-weight:500;"><?php echo htmlspecialchars($fee['name']); ?></td>
                                            <td style="padding:12px; color:#666; font-size:13px;"><?php echo htmlspecialchars($fee['email']); ?></td>
                                            <td style="padding:12px;"><?php echo htmlspecialchars($fee['fee_type']); ?></td>
                                            <td style="padding:12px; text-align:center; font-weight:600;">₹<?php echo number_format($fee['amount'], 2); ?></td>
                                            <td style="padding:12px; text-align:center;">
                                                <form method="POST" action="actions.php" style="display:flex; gap:6px; align-items:center; justify-content:center;">
                                                    <input type="hidden" name="fee_id" value="<?php echo $fee['id']; ?>">
                                                    <input type="number" name="paid_amount" step="0.01" min="0" max="<?php echo $fee['amount']; ?>" value="<?php echo number_format($fee['paid_amount'], 2, '.', ''); ?>" style="width:90px; padding:6px; border:1px solid #ddd; border-radius:4px; font-size:13px; text-align:center;">
                                                </form>
                                            </td>
                                            <td style="padding:12px; text-align:center;">
                                                <span style="background:<?php echo $status_color; ?>40; color:<?php echo $status_color; ?>; padding:4px 8px; border-radius:4px; font-size:12px; font-weight:600;">
                                                    <?php echo $status_text; ?>
                                                </span>
                                            </td>
                                            <td style="padding:12px; text-align:center;">
                                                <form method="POST" action="actions.php" style="display:flex; gap:6px; align-items:center; justify-content:center;">
                                                    <input type="hidden" name="fee_id" value="<?php echo $fee['id']; ?>">
                                                    <input type="text" name="notes" placeholder="Notes" style="padding:6px 8px; border:1px solid #eee; border-radius:4px; font-size:12px; width:140px;">
                                                    <select name="payment_method" required style="padding:6px 8px; border:1px solid #ddd; border-radius:4px; font-size:12px; width:110px;">
                                                        <option value="">Select</option>
                                                        <option value="cash">💵 Cash</option>
                                                        <option value="cheque">🏦 Cheque</option>
                                                        <option value="bank_transfer">🔄 Transfer</option>
                                                    </select>
                                                    <button type="submit" name="mark_cash_paid" class="btn btn-sm" style="background:#10b981; color:white; padding:6px 10px; border-radius:4px; border:none; cursor:pointer; font-size:11px; white-space:nowrap;">
                                                        ✓ Paid
                                                    </button>
                                                    <button type="button" onclick="openEditCashModal(<?php echo $fee['id']; ?>, '<?php echo htmlspecialchars($fee['name']); ?>', <?php echo $fee['amount']; ?>, <?php echo $fee['paid_amount']; ?>, '<?php echo htmlspecialchars($fee['fee_type']); ?>')" class="btn btn-sm" style="background:#667eea; color:white; padding:6px 10px; border-radius:4px; border:none; cursor:pointer; font-size:11px; white-space:nowrap;" title="Edit payment details">
                                                        ✏️ Edit
                                                    </button>
                                                </form>
                                            </td>
                                            <td style="padding:12px; text-align:center;">
                                                <button onclick="openCashPaymentModal(<?php echo $fee['id']; ?>, '<?php echo htmlspecialchars($fee['name']); ?>', <?php echo $fee['amount']; ?>, <?php echo $fee['paid_amount']; ?>)" class="btn btn-sm" style="background:#667eea; color:white; padding:6px 12px; border-radius:4px; border:none; cursor:pointer; font-size:12px;" title="Add notes/details">
                                                    • More
                                                </button>
                                            </td>
                                        </tr>
                                    <?php 
                                        endwhile;
                                    else:
                                    ?>
                                        <tr>
                                            <td colspan="8" style="padding:30px; text-align:center; color:#999;">
                                                No pending or partial fees to collect
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>


                    <!-- Direct Cash Payment Entry Form -->
<div class="section-box" style="margin-bottom: 30px; border: 2px solid #10b981; border-radius: 12px; background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
        <h3 style="margin: 0; color: #065f46;">
            <i class="fas fa-money-bill-wave"></i> Direct Cash Payment Entry
        </h3>
        <button onclick="toggleDirectPaymentForm()" id="toggleFormBtn" class="btn btn-success" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 10px 20px; border-radius: 6px; border: none; cursor: pointer; font-weight: 600; font-size: 14px;">
            ➕ Add New Payment
        </button>
    </div>
    
    <!-- Hidden Form -->
    <div id="directPaymentForm" style="display: none; padding: 20px; background: white; border-radius: 10px; border: 1px solid #d1fae5; margin-top: 15px;">
        <form method="POST" action="actions.php" id="directPaymentEntryForm">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Select Student *</label>
                    <select name="student_id" id="directStudentSelect" required style="width: 100%; padding: 12px; border: 2px solid #e5e7eb; border-radius: 6px; font-size: 14px;">
                        <option value="">-- Choose Student --</option>
                        <?php
                        $all_students = mysqli_query($conn, "SELECT id, name, email, course, rollno FROM users WHERE role = 'Student' AND status = 'approved' ORDER BY name ASC");
                        while ($s = mysqli_fetch_assoc($all_students)):
                        ?>
                            <option value="<?php echo $s['id']; ?>">
                                <?php echo htmlspecialchars($s['name']) . ' - ' . $s['course'] . ' (' . ($s['rollno'] ?? '-') . ')'; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Fee Type *</label>
                    <input type="text" name="fee_type" id="directFeeType" required placeholder="e.g., Tuition Fee Semester 1" style="width: 100%; padding: 12px; border: 2px solid #e5e7eb; border-radius: 6px; font-size: 14px;">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Total Amount (₹) *</label>
                    <input type="number" name="amount" id="directAmount" step="0.01" min="0" required placeholder="5000.00" style="width: 100%; padding: 12px; border: 2px solid #e5e7eb; border-radius: 6px; font-size: 14px;">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Paid Amount (₹) *</label>
                    <input type="number" name="paid_amount" id="directPaidAmount" step="0.01" min="0" required placeholder="5000.00" style="width: 100%; padding: 12px; border: 2px solid #e5e7eb; border-radius: 6px; font-size: 14px;" onchange="updateDirectBalance()">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Balance (₹)</label>
                    <input type="number" id="directBalance" value="0" readonly style="width: 100%; padding: 12px; border: 2px solid #e5e7eb; border-radius: 6px; font-size: 14px; background: #f9fafb;">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Payment Method *</label>
                    <select name="payment_method" required style="width: 100%; padding: 12px; border: 2px solid #e5e7eb; border-radius: 6px; font-size: 14px;">
                        <option value="cash">💵 Cash</option>
                        <option value="cheque">🏦 Cheque</option>
                        <option value="bank_transfer">🔄 Bank Transfer</option>
                    </select>
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Due Date</label>
                    <input type="date" name="due_date" value="<?php echo date('Y-m-d'); ?>" style="width: 100%; padding: 12px; border: 2px solid #e5e7eb; border-radius: 6px; font-size: 14px;">
                </div>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Notes / Remarks</label>
                <textarea name="remarks" rows="3" placeholder="Payment reference, receipt number, etc." style="width: 100%; padding: 12px; border: 2px solid #e5e7eb; border-radius: 6px; font-size: 14px; resize: vertical;"></textarea>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 25px;">
                <button type="button" onclick="toggleDirectPaymentForm()" class="btn" style="background: #e5e7eb; color: #333; padding: 11px 24px; border-radius: 6px; border: none; cursor: pointer; font-weight: 600;">
                    Cancel
                </button>
                <button type="submit" name="add_direct_cash_payment" class="btn" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 11px 24px; border-radius: 6px; border: none; cursor: pointer; font-weight: 600;">
                    <i class="fas fa-check"></i> Save Payment
                </button>
            </div>
        </form>
    </div>
    
    <!-- Quick Stats -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-top: 20px;">
        <div style="text-align: center; background: white; padding: 15px; border-radius: 8px; border: 1px solid #d1fae5;">
            <div style="font-size: 24px; font-weight: 700; color: #10b981;"><?php echo mysqli_num_rows($all_students); ?></div>
            <div style="color: #666; font-size: 13px;">Total Students</div>
        </div>
        <div style="text-align: center; background: white; padding: 15px; border-radius: 8px; border: 1px solid #d1fae5;">
            <div style="font-size: 24px; font-weight: 700; color: #667eea;" id="todayCashCount">0</div>
            <div style="color: #666; font-size: 13px;">Today's Cash Payments</div>
        </div>
        <div style="text-align: center; background: white; padding: 15px; border-radius: 8px; border: 1px solid #d1fae5;">
            <div style="font-size: 24px; font-weight: 700; color: #f59e0b;" id="monthCashTotal">0</div>
            <div style="color: #666; font-size: 13px;">This Month's Collection</div>
        </div>
    </div>
</div>
                    <!-- Pending Approvals Section -->
                    <div class="section-box">
                        <h3 style="margin-bottom:20px;">⏳ Pending Payment Approvals</h3>
                        <div style="overflow-x:auto;">
                            <table class="data-table" style="width:100%;">
                                <thead>
                                    <tr style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%); color:white;">
                                        <th style="padding:12px; text-align:left;">Student Name</th>
                                        <th style="padding:12px; text-align:left;">Email</th>
                                        <th style="padding:12px; text-align:center;">Fee Amount</th>
                                        <th style="padding:12px; text-align:left;">Transaction ID</th>
                                        <th style="padding:12px; text-align:center;">Uploaded</th>
                                        <th style="padding:12px; text-align:center;">Proof</th>
                                        <th style="padding:12px; text-align:center;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $pending_payments = mysqli_query($conn, "SELECT fp.*, f.amount, u.name, u.email FROM fee_payments fp 
                                                                               JOIN fees f ON fp.fee_id = f.id 
                                                                               JOIN users u ON fp.student_id = u.id 
                                                                               WHERE fp.status = 'pending' 
                                                                               ORDER BY fp.uploaded_at DESC");
                                    if ($pending_payments && mysqli_num_rows($pending_payments) > 0):
                                        while ($payment = mysqli_fetch_assoc($pending_payments)):
                                    ?>
                                        <tr style="border-bottom:1px solid #eee;">
                                            <td style="padding:12px; font-weight:500;"><?php echo htmlspecialchars($payment['name']); ?></td>
                                            <td style="padding:12px; color:#666; font-size:13px;"><?php echo htmlspecialchars($payment['email']); ?></td>
                                            <td style="padding:12px; text-align:center; font-weight:600; color:#667eea;">₹<?php echo number_format($payment['amount'], 2); ?></td>
                                            <td style="padding:12px;">
                                                <?php if ($payment['transaction_id']): ?>
                                                    <code style="background:#f0f0f0; padding:4px 8px; border-radius:4px; font-size:12px;"><?php echo htmlspecialchars($payment['transaction_id']); ?></code>
                                                <?php else: ?>
                                                    <span style="color:#999; font-size:13px;">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding:12px; text-align:center; font-size:13px; color:#666;">
                                                <?php echo date('d M Y, H:i', strtotime($payment['uploaded_at'])); ?>
                                            </td>
                                            <td style="padding:12px; text-align:center;">
                                                <?php if ($payment['file_path'] && file_exists($payment['file_path'])): ?>
                                                    <a href="<?php echo $payment['file_path']; ?>" target="_blank" class="btn btn-sm" style="background:#667eea; color:white; padding:6px 12px; border-radius:4px; text-decoration:none; font-size:12px;">
                                                        👁️ View
                                                    </a>
                                                <?php else: ?>
                                                    <span style="color:#dc3545; font-size:12px;">❌ Missing</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding:12px; text-align:center;">
                                                <form method="POST" action="actions.php" style="display:inline;">
                                                    <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                                    <button type="submit" name="approve_fee_payment" class="btn btn-sm" style="background:#28a745; color:white; padding:6px 12px; border-radius:4px; border:none; cursor:pointer; font-size:12px;">
                                                        ✓ Approve
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php 
                                        endwhile;
                                    else:
                                    ?>
                                        <tr>
                                            <td colspan="7" style="padding:30px; text-align:center; color:#999;">
                                                ✅ No pending approvals - All payments are processed!
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Recently Approved Section -->
                    <div class="section-box" style="margin-top:30px;">
                        <h3 style="margin-bottom:20px;">✅ Recently Approved Payments</h3>
                        <div style="overflow-x:auto;">
                            <table class="data-table" style="width:100%;">
                                <thead>
                                    <tr style="background:#f5f5f5;">
                                        <th style="padding:12px; text-align:left;">Student Name</th>
                                        <th style="padding:12px; text-align:left;">Email</th>
                                        <th style="padding:12px; text-align:center;">Amount</th>
                                        <th style="padding:12px; text-align:center;">Uploaded</th>
                                        <th style="padding:12px; text-align:center;">Approved</th>
                                        <th style="padding:12px; text-align:left;">Approved By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $approved_payments = mysqli_query($conn, "SELECT fp.*, f.amount, u.name as student_name, u.email, admin.name as admin_name FROM fee_payments fp 
                                                                               JOIN fees f ON fp.fee_id = f.id 
                                                                               JOIN users u ON fp.student_id = u.id 
                                                                               LEFT JOIN users admin ON fp.approved_by = admin.id 
                                                                               WHERE fp.status = 'approved' 
                                                                               ORDER BY fp.approved_at DESC LIMIT 20");
                                    if ($approved_payments && mysqli_num_rows($approved_payments) > 0):
                                        while ($payment = mysqli_fetch_assoc($approved_payments)):
                                    ?>
                                        <tr style="border-bottom:1px solid #eee;">
                                            <td style="padding:12px; font-weight:500;"><?php echo htmlspecialchars($payment['student_name']); ?></td>
                                            <td style="padding:12px; color:#666; font-size:13px;"><?php echo htmlspecialchars($payment['email']); ?></td>
                                            <td style="padding:12px; text-align:center; font-weight:600; color:#28a745;">₹<?php echo number_format($payment['amount'], 2); ?></td>
                                            <td style="padding:12px; text-align:center; font-size:13px; color:#666;">
                                                <?php echo date('d M Y', strtotime($payment['uploaded_at'])); ?>
                                            </td>
                                            <td style="padding:12px; text-align:center; font-size:13px; color:#28a745; font-weight:600;">
                                                <?php echo date('d M Y, H:i', strtotime($payment['approved_at'])); ?>
                                            </td>
                                            <td style="padding:12px;">
                                                <span style="background:#e8f5e9; padding:4px 12px; border-radius:20px; font-size:12px; color:#28a745;">
                                                    <?php echo htmlspecialchars($payment['admin_name'] ?? 'System'); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php 
                                        endwhile;
                                    else:
                                    ?>
                                        <tr>
                                            <td colspan="6" style="padding:30px; text-align:center; color:#999;">
                                                No approved payments yet
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Payment Summary by Course -->
                    <div class="section-box" style="margin-top:30px;">
                        <h3 style="margin-bottom:20px;">📊 Payment Summary by Course</h3>
                        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(250px, 1fr)); gap:20px;">
                            <?php
                            $courses = ['B.Com', 'M.Com', 'BCA', 'BA', 'BBA', 'PGDCA', 'DCA', 'M.Sc', 'MA', 'B.Lib'];
                            foreach ($courses as $course):
                                $course_stats = mysqli_fetch_assoc(mysqli_query($conn, 
                                    "SELECT 
                                        COUNT(DISTINCT u.id) as students,
                                        COUNT(fp.id) as total_payments,
                                        SUM(CASE WHEN fp.status = 'pending' THEN 1 ELSE 0 END) as pending_payments,
                                        SUM(CASE WHEN fp.status = 'approved' THEN 1 ELSE 0 END) as approved_payments,
                                        SUM(CASE WHEN fp.status = 'approved' THEN f.amount ELSE 0 END) as approved_amount
                                    FROM users u 
                                    LEFT JOIN fee_payments fp ON u.id = fp.student_id
                                    LEFT JOIN fees f ON fp.fee_id = f.id
                                    WHERE u.role = 'Student' AND u.course = '$course'"));
                                
                                $students = $course_stats['students'];
                                $pending = $course_stats['pending_payments'] ?? 0;
                                $approved = $course_stats['approved_payments'] ?? 0;
                                $amount = $course_stats['approved_amount'] ?? 0;
                                $percentage = $students > 0 ? ($approved / $students) * 100 : 0;
                            ?>
                                <div style="background:linear-gradient(135deg, #f5f7ff 0%, #f0f4ff 100%); padding:20px; border-radius:10px; border-left:5px solid #667eea;">
                                    <h4 style="margin:0 0 15px 0; color:#333;"><?php echo $course; ?></h4>
                                    <p style="margin:8px 0; color:#666; font-size:14px;"><strong>Total Students:</strong> <?php echo $students; ?></p>
                                    <p style="margin:8px 0; color:#ff9800; font-size:14px; font-weight:600;">⏳ Pending: <?php echo $pending; ?></p>
                                    <p style="margin:8px 0; color:#28a745; font-size:14px; font-weight:600;">✅ Approved: <?php echo $approved; ?></p>
                                    <p style="margin:8px 0; color:#667eea; font-size:14px; font-weight:600;">💰 Amount: ₹<?php echo number_format($amount, 2); ?></p>
                                    <div style="margin-top:15px; background:white; height:6px; border-radius:3px; overflow:hidden;">
                                        <div style="background:linear-gradient(90deg, #28a745, #667eea); height:100%; width:<?php echo min($percentage, 100); ?>%;"></div>
                                    </div>
                                    <p style="margin:10px 0 0 0; font-size:12px; color:#999;">
                                        <?php echo number_format($percentage, 1); ?>% paid
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                <?php
                // ADMIN - OVERVIEW SECTION
                elseif ($section === 'overview' && $user['role'] === 'Admin'):
                ?>
                    <div class="dashboard-cards">
                        <?php
                        // Helper function to safely get count
                        function getCount($conn, $query) {
                            $result = mysqli_query($conn, $query);
                            $row = $result ? mysqli_fetch_assoc($result) : null;
                            return $row ? $row['count'] : 0;
                        }
                        
                        $stats = [
                            'students' => getCount($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'Student'"),
                            'teachers' => getCount($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'Teacher'"),
                            'notes' => getCount($conn, "SELECT COUNT(*) as count FROM notes"),
                            'assignments' => getCount($conn, "SELECT COUNT(*) as count FROM assignments"),
                            'tests' => getCount($conn, "SELECT COUNT(*) as count FROM tests"),
                            'locker_files' => getCount($conn, "SELECT COUNT(*) as count FROM digital_locker")
                        ];
                        ?>
                        <div class="stat-card">
                            <h3><?php echo $stats['students']; ?></h3>
                            <p>Total Students</p>
                        </div>
                        <div class="stat-card">
                            <h3><?php echo $stats['teachers']; ?></h3>
                            <p>Total Teachers</p>
                        </div>
                        <div class="stat-card">
                            <h3><?php echo $stats['notes']; ?></h3>
                            <p>Notes Uploaded</p>
                        </div>
                        <div class="stat-card">
                            <h3><?php echo $stats['assignments']; ?></h3>
                            <p>Assignments</p>
                        </div>
                        <div class="stat-card">
                            <h3><?php echo $stats['tests']; ?></h3>
                            <p>Tests Created</p>
                        </div>
                        <div class="stat-card">
                            <h3><?php echo $stats['locker_files']; ?></h3>
                            <p>Locker Files</p>
                        </div>
                    </div>
                    
                    <div class="section-box">
                        <h3>Recent Activity</h3>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Email</th>
                                    <th>Joined</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $recent_users = mysqli_query($conn, "SELECT * FROM users ORDER BY created_at DESC LIMIT 10");
                                while ($u = mysqli_fetch_assoc($recent_users)):
                                ?>
                                    <tr>
                                        <td><?php echo $u['name']; ?></td>
                                        <td><span class="badge"><?php echo $u['role']; ?></span></td>
                                        <td><?php echo $u['email']; ?></td>
                                        <td><?php echo formatDate($u['created_at']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                
                <?php 
                // NOTIFICATIONS SECTION
                elseif ($section === 'notifications' && $user['role'] === 'Student'): 
                ?>
                    <div class="section-header">
                        <h2>🔔 Notifications</h2>
                        <div class="actions">
                            <form method="POST" action="actions.php" style="display: inline;">
                                <button type="submit" name="mark_all_read" class="btn btn-secondary">Mark All as Read</button>
                            </form>
                        </div>
                    </div>
                    
                    <?php
                    $notifications = mysqli_query($conn, "SELECT * FROM notifications WHERE user_id = {$user['id']} ORDER BY created_at DESC LIMIT 50");
                    if ($notifications && mysqli_num_rows($notifications) > 0):
                        while ($notif = mysqli_fetch_assoc($notifications)):
                    ?>
                        <div class="notification-card <?php echo $notif['is_read'] ? '' : 'unread'; ?>" style="background: var(--bg-primary); padding: 20px; margin-bottom: 15px; border-radius: var(--radius); border-left: 4px solid var(--<?php echo $notif['type']; ?>); <?php echo $notif['is_read'] ? '' : 'background: rgba(102, 126, 234, 0.05);'; ?>">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div style="flex: 1;">
                                    <h4 style="margin-bottom: 8px; color: var(--text-primary);">
                                        <?php 
                                        $icons = ['info' => 'ℹ️', 'success' => '✅', 'warning' => '⚠️', 'danger' => '❌'];
                                        echo $icons[$notif['type']] . ' ' . $notif['title']; 
                                        ?>
                                    </h4>
                                    <p style="color: var(--text-secondary); margin-bottom: 10px;"><?php echo $notif['message']; ?></p>
                                    <small style="color: var(--text-tertiary);"><?php echo timeAgo($notif['created_at']); ?></small>
                                </div>
                                <?php if (!$notif['is_read']): ?>
                                <form method="POST" action="actions.php">
                                    <input type="hidden" name="notif_id" value="<?php echo $notif['id']; ?>">
                                    <button type="submit" name="mark_notif_read" class="btn btn-sm btn-secondary">Mark Read</button>
                                </form>
                                <?php endif; ?>
                            </div>
                            <?php if ($notif['link']): ?>
                                <a href="<?php echo $notif['link']; ?>" class="btn btn-sm btn-primary" style="margin-top: 10px;">View</a>
                            <?php endif; ?>
                        </div>
                    <?php 
                        endwhile;
                    else:
                    ?>
                        <div class="empty-state">
                            <i class="fas fa-bell-slash"></i>
                            <h3>No Notifications</h3>
                            <p>You're all caught up!</p>
                        </div>
                    <?php endif; ?>
                
                <?php // Forum and Study Groups removed ?>
                
                <?php 
                // LIBRARY SECTION - Removed by admin
                elseif ($section === 'library' && $user['role'] === 'Student'): 
                    // Feature disabled
                    echo '<div class="section-header"><h2>📖 Library</h2></div><div class="empty-state"><i class="fas fa-book-dead"></i><h3>Library Disabled</h3><p>This feature has been removed by the administrator.</p></div>';
                ?>
                            <div class="book-info">
                                <h4 class="book-title"><?php echo htmlspecialchars($book['title']); ?></h4>
                                <p class="book-author">by <?php echo htmlspecialchars($book['author']); ?></p>
                                <div class="book-details">
                                    <div class="book-detail-item">
                                        <i class="fas fa-barcode"></i>
                                        <span>ISBN: <?php echo $book['isbn']; ?></span>
                                    </div>
                                    <div class="book-detail-item">
                                        <i class="fas fa-book-open"></i>
                                        <span><?php echo $book['subject']; ?></span>
                                    </div>
                                    <div class="book-detail-item">
                                        <i class="fas fa-building"></i>
                                        <span><?php echo $book['publisher']; ?></span>
                                    </div>
                                    <div class="book-detail-item">
                                        <i class="fas fa-calendar"></i>
                                        <span>Edition: <?php echo $book['edition']; ?></span>
                                    </div>
                                    <div class="book-detail-item">
                                        <i class="fas fa-check-circle"></i>
                                        <span style="color: var(--<?php echo $book['available_copies'] > 0 ? 'success' : 'danger'; ?>);">
                                            <?php echo $book['available_copies']; ?> / <?php echo $book['total_copies']; ?> available
                                        </span>
                                    </div>
                                    <div class="book-detail-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span><?php echo $book['location']; ?></span>
                                    </div>
                                </div>
                                <div style="margin-top: 15px;">
                                    <?php if ($is_issued_by_me): ?>
                                        <span class="tag-pill" style="background: rgba(16, 185, 129, 0.2); color: var(--success);">
                                            ✓ Issued to you - Due: <?php echo date('d M Y', strtotime($my_issue['due_date'])); ?>
                                        </span>
                                        <form method="POST" action="actions.php" style="display: inline; margin-left: 10px;">
                                            <input type="hidden" name="issue_id" value="<?php echo $my_issue['id']; ?>">
                                            <button type="submit" name="return_book" class="btn btn-sm btn-secondary">Return Book</button>
                                        </form>
                                    <?php elseif ($book['available_copies'] > 0): ?>
                                        <form method="POST" action="actions.php" style="display: inline;">
                                            <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                            <button type="submit" name="issue_book" class="btn btn-sm btn-primary">Issue Book</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="tag-pill" style="background: rgba(239, 68, 68, 0.2); color: var(--danger);">
                                            Not Available
                                        </span>
                                    <?php endif; ?>
                                </div>

                
                <?php 
                // CALENDAR/EVENTS SECTION
                elseif ($section === 'calendar' && $user['role'] === 'Student'): 
                ?>
                    <div class="section-header">
                        <h2>🗓️ Academic Calendar</h2>
                    </div>
                    
                    <div class="section-box">
                        <h3 style="margin-bottom: 20px;">Upcoming Events</h3>
                        <?php
                        $course = $user['course'] ?? 'All';
                        $events = mysqli_query($conn, "SELECT * FROM events WHERE event_date >= CURDATE() AND (course = '$course' OR course = 'All') ORDER BY event_date ASC, start_time ASC LIMIT 10");
                        
                        if ($events && mysqli_num_rows($events) > 0):
                            while ($event = mysqli_fetch_assoc($events)):
                                $event_class = '';
                                $event_icon = '';
                                switch($event['event_type']) {
                                    case 'exam': $event_class = 'danger'; $event_icon = '📝'; break;
                                    case 'holiday': $event_class = 'success'; $event_icon = '🎉'; break;
                                    case 'meeting': $event_class = 'info'; $event_icon = '👥'; break;
                                    default: $event_class = 'secondary'; $event_icon = '📌'; break;
                                }
                        ?>
                            <div style="padding: 20px; margin-bottom: 15px; background: var(--bg-primary); border-radius: var(--radius); border-left: 4px solid var(--<?php echo $event_class; ?>);">
                                <div style="display: flex; justify-content: space-between; align-items: start;">
                                    <div style="flex: 1;">
                                        <h4 style="margin-bottom: 8px; color: var(--text-primary);">
                                            <?php echo $event_icon . ' ' . htmlspecialchars($event['title']); ?>
                                        </h4>
                                        <p style="color: var(--text-secondary); margin-bottom: 10px;">
                                            <?php echo nl2br(htmlspecialchars($event['description'])); ?>
                                        </p>
                                        <div style="display: flex; gap: 20px; font-size: 14px; color: var(--text-secondary);">
                                            <span><i class="fas fa-calendar"></i> <?php echo date('d M Y', strtotime($event['event_date'])); ?></span>
                                            <?php if ($event['start_time']): ?>
                                                <span><i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($event['start_time'])); ?></span>
                                            <?php endif; ?>
                                            <?php if ($event['location']): ?>
                                                <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($event['location']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <span class="tag-pill" style="background: rgba(102, 126, 234, 0.1); color: var(--primary);">
                                        <?php echo ucfirst($event['event_type']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php 
                            endwhile;
                        else:
                        ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-times"></i>
                                <h3>No Upcoming Events</h3>
                                <p>There are no scheduled events at the moment.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                
                <?php 
                // ACHIEVEMENTS SECTION - Removed by admin
                elseif ($section === 'achievements' && $user['role'] === 'Student'): 
                    echo '<div class="section-header"><h2>🏆 Achievements</h2></div><div class="empty-state"><i class="fas fa-trophy"></i><h3>Achievements Disabled</h3><p>This feature has been removed by the administrator.</p></div>';
                ?>
                
                <?php 
                // FEES SECTION
                elseif ($section === 'fees' && $user['role'] === 'Student'): 
                ?>
                    <div class="section-header">
                        <h2>💳 Fee Management</h2>
                    </div>
                    
                    <?php
                    $fees = mysqli_query($conn, "SELECT * FROM fees WHERE student_id = {$user['id']} ORDER BY due_date DESC");
                    
                    if ($fees && mysqli_num_rows($fees) > 0):
                        $total_pending = 0;
                        $total_paid = 0;
                        
                        while ($fee = mysqli_fetch_assoc($fees)):
                            $percentage_paid = ($fee['paid_amount'] / $fee['amount']) * 100;
                            $total_pending += ($fee['amount'] - $fee['paid_amount']);
                            $total_paid += $fee['paid_amount'];
                    ?>
                        <div class="fee-card">
                            <div class="fee-header">
                                <div>
                                    <h4 style="margin-bottom: 5px;"><?php echo htmlspecialchars($fee['fee_type']); ?></h4>
                                    <p class="fee-amount">₹<?php echo number_format($fee['amount'], 2); ?></p>
                                </div>
                                <span class="fee-status <?php echo $fee['status']; ?>">
                                    <?php echo ucfirst($fee['status']); ?>
                                </span>
                            </div>
                            
                            <?php if ($fee['paid_amount'] > 0 && $fee['paid_amount'] < $fee['amount']): ?>
                                <div class="fee-progress">
                                    <div class="fee-progress-bar" style="width: <?php echo $percentage_paid; ?>%;"></div>
                                </div>
                                <p style="color: var(--text-secondary); font-size: 14px; margin-top: 10px;">
                                    Paid: ₹<?php echo number_format($fee['paid_amount'], 2); ?> of ₹<?php echo number_format($fee['amount'], 2); ?>
                                    (<?php echo round($percentage_paid); ?>%)
                                </p>
                            <?php endif; ?>
                            
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--border);">
                                <div>
                                    <small style="color: var(--text-secondary);">
                                        <i class="fas fa-calendar"></i> Due: <?php echo date('d M Y', strtotime($fee['due_date'])); ?>
                                    </small>
                                    <?php if ($fee['payment_date']): ?>
                                        <br>
                                        <small style="color: var(--success);">
                                            <i class="fas fa-check"></i> Paid: <?php echo date('d M Y', strtotime($fee['payment_date'])); ?>
                                        </small>
                                        <br>
                                        <small style="color: #667eea; font-size:11px;">
                                            📧 <?php echo ucfirst(($fee['payment_method'] ?? 'online')); ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                                <div style="display: flex; gap: 8px; flex-wrap:wrap;">
                                    <?php if ($fee['status'] === 'paid' || $fee['status'] === 'partial'): ?>
                                        <button class="btn btn-sm" onclick="downloadReceipt(
                                            '<?php echo htmlspecialchars($fee['fee_type']); ?>',
                                            <?php echo $fee['amount']; ?>,
                                            <?php echo $fee['paid_amount']; ?>,
                                            '<?php echo htmlspecialchars($fee['payment_date'] ?? ''); ?>',
                                            '<?php echo htmlspecialchars($fee['transaction_id'] ?? ''); ?>',
                                            '<?php echo htmlspecialchars($fee['payment_method'] ?? 'online'); ?>',
                                            '<?php echo ucfirst($fee['status']); ?>'
                                        )" style="background:#667eea; color:white; padding:8px 16px; border-radius:4px; border:none; cursor:pointer; font-size:12px;">
                                            📄 Receipt
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($fee['status'] !== 'paid'): ?>
                                        <button class="btn btn-sm btn-primary" onclick="openPayModal(<?php echo $fee['id']; ?>, '<?php echo $fee['amount']; ?>')">
                                            💳 Pay Now
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php 
                        endwhile;
                    ?>
                        <div class="section-box" style="margin-top: 30px;">
                            <h4>Fee Summary</h4>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 20px;">
                                <div style="text-align: center; padding: 20px; background: var(--bg-secondary); border-radius: var(--radius);">
                                    <h3 style="color: var(--success);">₹<?php echo number_format($total_paid, 2); ?></h3>
                                    <p style="color: var(--text-secondary);">Total Paid</p>
                                </div>
                                <div style="text-align: center; padding: 20px; background: var(--bg-secondary); border-radius: var(--radius);">
                                    <h3 style="color: var(--danger);">₹<?php echo number_format($total_pending, 2); ?></h3>
                                    <p style="color: var(--text-secondary);">Pending</p>
                                </div>
                            </div>
                        </div>
                    <?php 
                    else:
                    ?>
                        <div class="empty-state">
                            <i class="fas fa-receipt"></i>
                            <h3>No Fee Records</h3>
                            <p>You don't have any fee records yet.</p>
                        </div>
                    <?php endif; ?>
                
                <?php 
                // Attendance UI removed (feature disabled)
                // GRADES MANAGEMENT removed (feature disabled by admin)
                // ==== FEE MANAGEMENT (Teacher) ====
                elseif ($section === 'fees_mgmt' && $user['role'] === 'Teacher'): 
                ?>
                    <div class="section-header">
                        <h2>💳 Manage Student Fees</h2>
                    </div>
                    
                    <div class="section-box">
                        <h3 style="margin-bottom: 20px;">Add New Fee Record</h3>
                        <form method="POST" action="actions.php">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Select Course</label>
                                    <select id="feeCourseSelect" name="course_for_fee" onchange="loadStudentsForFees(this.value)">
                                        <option value="">All / Select Course</option>
                                        <option value="All">All Courses</option>
                                        <option value="B.Com">B.Com</option>
                                        <option value="M.Com">M.Com</option>
                                        <option value="BCA">BCA</option>
                                        <option value="BA">BA</option>
                                        <option value="BBA">BBA</option>
                                        <option value="PGDCA">PGDCA</option>
                                        <option value="DCA">DCA</option>
                                        <option value="M.Sc">M.Sc</option>
                                        <option value="MA">MA</option>
                                        <option value="B.Lib">B.Lib</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Select Student</label>
                                    <select id="feeStudentSelect" name="student_id" required>
                                        <option value="">Choose student</option>
                                        <?php
                                        $students = mysqli_query($conn, "SELECT * FROM users WHERE role = 'Student' ORDER BY name ASC");
                                        while ($student = mysqli_fetch_assoc($students)):
                                        ?>
                                            <option value="<?php echo $student['id']; ?>">
                                                <?php echo htmlspecialchars($student['name']) . ' - ' . $student['course'] . ' (' . ($student['rollno'] ?? '-') . ')'; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Fee Type</label>
                                    <input type="text" name="fee_type" placeholder="e.g., Tuition Fee - Semester 1" required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Amount (₹)</label>
                                    <input type="number" name="amount" step="0.01" min="0" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Due Date</label>
                                    <input type="date" name="due_date" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Remarks</label>
                                <textarea name="remarks" rows="2" placeholder="Any additional notes..."></textarea>
                            </div>
                            
                            <button type="submit" name="add_fee" class="btn btn-primary">Add Fee Record</button>
                        </form>
                    </div>
                    
                    <div class="section-box" style="margin-top: 30px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                            <h3 style="margin:0 0 0 0;">All Fee Records</h3>
                            <div class="table-actions">
                                <input id="feeSearch" type="text" placeholder="Search by student, fee type or remarks..." onkeyup="searchFees()">
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="data-table fees-table" aria-describedby="All fee records table">
                                <thead>
                                    <tr>
                                        <th class="col-student">Student Name</th>
                                        <th class="col-type">Fee Type</th>
                                        <th class="text-right col-amount">Amount</th>
                                        <th class="text-right col-paid">Paid</th>
                                        <th class="text-right col-balance">Balance</th>
                                        <th class="col-date">Due Date</th>
                                        <th class="col-status">Status</th>
                                        <th class="col-date">Payment Date</th>
                                        <th class="col-txn">Transaction ID</th>
                                        <th class="col-remarks">Remarks</th>
                                        <th class="col-actions">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $fees_query = mysqli_query($conn, "
                                        SELECT f.*, u.name as student_name 
                                        FROM fees f 
                                        JOIN users u ON f.student_id = u.id 
                                        ORDER BY f.due_date DESC
                                    ");

                                    while ($fee = mysqli_fetch_assoc($fees_query)):
                                        $balance = $fee['amount'] - $fee['paid_amount'];
                                        $status_class = '';
                                        switch($fee['status']) {
                                            case 'paid': $status_class = 'badge-success'; break;
                                            case 'partial': $status_class = 'badge-warning'; break;
                                            case 'overdue': $status_class = 'badge-danger'; break;
                                            default: $status_class = 'badge-info';
                                        }
                                        $remarks_display = $fee['remarks'] ? htmlspecialchars($fee['remarks']) : '-';
                                    ?>
                                        <tr>
                                            <td class="col-student"><?php echo htmlspecialchars($fee['student_name']); ?></td>
                                            <td class="col-type"><?php echo htmlspecialchars($fee['fee_type']); ?></td>
                                            <td class="text-right col-amount">₹<?php echo number_format($fee['amount'], 2); ?></td>
                                            <td class="text-right col-paid">₹<?php echo number_format($fee['paid_amount'], 2); ?></td>
                                            <td class="text-right col-balance"><strong>₹<?php echo number_format($balance, 2); ?></strong></td>
                                            <td class="col-date"><?php echo date('d M Y', strtotime($fee['due_date'])); ?></td>
                                            <td class="col-status"><span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($fee['status']); ?></span></td>
                                            <td class="col-date"><?php echo $fee['payment_date'] ? date('d M Y', strtotime($fee['payment_date'])) : '-'; ?></td>
                                            <td class="col-txn"><?php echo $fee['transaction_id'] ? htmlspecialchars($fee['transaction_id']) : '-'; ?></td>
                                            <td class="col-remarks"><div class="remarks" title="<?php echo $remarks_display; ?>"><?php echo $remarks_display; ?></div></td>
                                            <td class="col-actions">
                                                <button onclick="editFee(<?php echo $fee['id']; ?>)" class="btn btn-sm btn-primary">Update</button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                            <?php if (hasRole('Admin')): ?>
                            <div style="margin-top:20px;">
                                <h4>Pending Fee Payments</h4>
                                <table class="data-table" style="margin-top:10px; width:100%">
                                    <thead>
                                        <tr><th>ID</th><th>Student</th><th>Fee ID</th><th>Screenshot</th><th>Uploaded At</th><th>Actions</th></tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                        $pending = mysqli_query($conn, "SELECT p.*, u.name as student_name FROM fee_payments p JOIN users u ON p.student_id = u.id WHERE p.status = 'pending' ORDER BY p.uploaded_at DESC");
                                        while ($p = mysqli_fetch_assoc($pending)):
                                    ?>
                                        <tr>
                                            <td><?php echo $p['id']; ?></td>
                                            <td><?php echo htmlspecialchars($p['student_name']); ?></td>
                                            <td><?php echo $p['fee_id']; ?></td>
                                            <td><a href="<?php echo $p['file_path']; ?>" target="_blank">View</a></td>
                                            <td><?php echo date('d M Y, h:i A', strtotime($p['uploaded_at'])); ?></td>
                                            <td>
                                                <a href="actions.php?approve_fee_payment=<?php echo $p['id']; ?>" class="btn btn-sm btn-success" onclick="return confirm('Approve this payment?')">Approve</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Fee Update Modal -->
                    <div id="feeModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
                        <div style="background:white; padding:30px; border-radius:10px; max-width:500px; width:90%;">
                            <h3 style="margin-bottom:20px;">Update Fee Payment</h3>
                            <form method="POST" action="actions.php" id="feeUpdateForm">
                                <input type="hidden" name="fee_id" id="fee_id">
                                
                                <div class="form-group">
                                    <label>Paid Amount (₹)</label>
                                    <input type="number" name="paid_amount" placeholder="Enter paid amount" id="paid_amount" step="0.01" min="0" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Payment Date</label>
                                    <input type="date" name="payment_date" id="payment_date">
                                </div>
                                
                                <div class="form-group">
                                    <label>Transaction ID</label>
                                    <input type="text" name="transaction_id" placeholder="Enter transaction ID" id="transaction_id">
                                </div>
                                
                                <div class="form-group">
                                    <label>Remarks</label>
                                    <textarea name="remarks" id="fee_remarks" placeholder="Enter remarks (optional)" rows="3"></textarea>
                                </div>
                                
                                <div style="display:flex; gap:10px; margin-top:20px;">
                                    <button type="submit" name="update_fee" class="btn btn-primary">Update Fee</button>
                                    <button type="button" onclick="closeFeeModal()" class="btn btn-secondary">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <script>
                    function editFee(feeId) {
                        document.getElementById('fee_id').value = feeId;
                        document.getElementById('feeModal').style.display = 'flex';
                    }
                    
                    function closeFeeModal() {
                        document.getElementById('feeModal').style.display = 'none';
                    }
                    </script>
                
                <?php 
                // ==== CALENDAR MANAGEMENT (Admin) ====
                elseif ($section === 'calendar_mgmt' && $user['role'] === 'Admin'): 
                ?>
                    <div class="section-header">
                        <h2>🗓️ Manage Academic Calendar</h2>
                    </div>
                    
                    <div class="section-box">
                        <h3 style="margin-bottom: 20px;">Add New Event</h3>
                        <form method="POST" action="actions.php">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Event Title</label>
                                    <input type="text" name="title" placeholder="e.g., Mid-Term Examination" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Event Type</label>
                                    <select name="event_type" required>
                                        <option value="exam">Exam</option>
                                        <option value="holiday">Holiday</option>
                                        <option value="meeting">Meeting</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Description</label>
                                <textarea name="description" rows="3" placeholder="Event details..."></textarea>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Event Date</label>
                                    <input type="date" name="event_date" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Start Time (Optional)</label>
                                    <input type="time" name="start_time">
                                </div>
                                
                                <div class="form-group">
                                    <label>End Time (Optional)</label>
                                    <input type="time" name="end_time">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Location</label>
                                    <input type="text" name="location" placeholder="e.g., Main Hall">
                                </div>
                                
                                <div class="form-group">
                                    <label>Course</label>
                                    <select name="course" required>
                                        <option value="All">All Courses</option>
                                        <option value="B.Com">B.Com</option>
                                        <option value="M.Com">M.Com</option>
                                        <option value="BCA">BCA</option>
                                        <option value="BA">BA</option>
                                        <option value="BBA">BBA</option>
                                        <option value="PGDCA">PGDCA</option>
                                        <option value="DCA">DCA</option>
                                        <option value="M.Sc">M.Sc</option>
                                        <option value="MA">MA</option>
                                        <option value="B.Lib">B.Lib</option>
                                    </select>
                                </div>
                            </div>
                            
                            <button type="submit" name="add_event" class="btn btn-primary">Add Event</button>
                        </form>
                    </div>
                    
                    <div class="section-box" style="margin-top: 30px;">
                        <h3 style="margin-bottom: 20px;">All Events</h3>
                        
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Type</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Location</th>
                                        <th>Course</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $events_query = mysqli_query($conn, "SELECT * FROM events ORDER BY event_date DESC");
                                    
                                    while ($event = mysqli_fetch_assoc($events_query)):
                                        $type_class = '';
                                        switch($event['event_type']) {
                                            case 'exam': $type_class = 'badge-danger'; break;
                                            case 'holiday': $type_class = 'badge-success'; break;
                                            case 'meeting': $type_class = 'badge-info'; break;
                                            default: $type_class = 'badge-warning';
                                        }
                                    ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($event['title']); ?></strong></td>
                                            <td><span class="badge <?php echo $type_class; ?>"><?php echo ucfirst($event['event_type']); ?></span></td>
                                            <td><?php echo date('d M Y', strtotime($event['event_date'])); ?></td>
                                            <td>
                                                <?php 
                                                if ($event['start_time']) {
                                                    echo date('h:i A', strtotime($event['start_time']));
                                                    if ($event['end_time']) {
                                                        echo ' - ' . date('h:i A', strtotime($event['end_time']));
                                                    }
                                                } else {
                                                    echo '-';
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($event['location'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($event['course']); ?></td>
                                            <td>
                                                <a href="actions.php?delete_event=<?php echo $event['id']; ?>" 
                                                   onclick="return confirm('Delete this event?')" 
                                                   class="btn btn-sm btn-danger">Delete</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Profile Photo Modal -->
    <div id="profileModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Profile Settings</h3>
                <span class="close" onclick="closeProfileModal()">&times;</span>
            </div>
            <div style="text-align: center; margin-bottom: 20px;">
                <h4>Current Photo</h4>
                <?php 
                $profile_photo_path = $user['profile_photo'] ? 'uploads/profile/' . $user['profile_photo'] : null;
                $placeholder = 'https://via.placeholder.com/80x80?text=' . substr($user['name'], 0, 1);
                // Check if uploaded photo exists, otherwise use placeholder
                $img_src = ($profile_photo_path && file_exists($profile_photo_path)) ? $profile_photo_path : $placeholder;
                ?>
                <img src="<?php echo htmlspecialchars($img_src); ?>" 
                     alt="Current Profile" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 2px solid #ddd; background: white;">
            </div>
            
            <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error" style="margin-bottom: 15px;">
                <i class="fa fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['error']); ?>
                <?php unset($_SESSION['error']); ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success" style="margin-bottom: 15px;">
                <i class="fa fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success']); ?>
                <?php unset($_SESSION['success']); ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="actions.php" enctype="multipart/form-data" style="margin-bottom: 15px;">
                <div class="form-group">
                    <label><i class="fa fa-image"></i> Select New Photo (Max 5MB)</label>
                    <input type="file" name="profile_photo" accept="image/*" required onchange="previewFile(this, 'profilePreview')">
                </div>
                <div id="profilePreview" style="text-align: center; margin-bottom: 15px; display: none;">
                    <img id="profilePreviewImg" src="" alt="Preview" style="max-width: 150px; max-height: 150px; border-radius: 8px;">
                </div>
                <button type="submit" name="upload_profile" class="btn btn-primary btn-block">Upload New Photo</button>
            </form>
            <?php if ($user['profile_photo']): ?>
            <form method="POST" action="actions.php">
                <button type="submit" name="remove_profile" class="btn btn-danger btn-block" onclick="return confirm('Are you sure you want to remove your profile photo?')">Remove Current Photo</button>
            </form>
            <?php endif; ?>
            
            <hr style="margin: 20px 0;">
            <h4>Update Profile Details</h4>
            <form method="POST" action="actions.php">
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>
                <?php if ($user['role'] === 'Student'): ?>
                <div class="form-group">
                    <label>Course (Read-only)</label>
                    <input type="text" value="<?php echo htmlspecialchars($user['course'] ?? '-'); ?>" disabled>
                    <small style="display:block; margin-top:5px; color:#666;">Course is set at signup and cannot be changed.</small>
                </div>
                <div class="form-group">
                    <label>Roll No (Read-only)</label>
                    <input type="text" value="<?php echo htmlspecialchars($user['rollno'] ?? '-'); ?>" disabled>
                </div>
                <div class="form-group">
                    <label>Age (Read-only)</label>
                    <input type="text" value="<?php echo htmlspecialchars($user['age'] ?? '-'); ?>" disabled>
                </div>
                <div class="form-group">
                    <label>Course Year (Read-only)</label>
                    <input type="text" value="<?php echo htmlspecialchars($user['course_year'] ?? '-'); ?>" disabled>
                </div>
                <div class="form-group">
                    <label>Phone Number (Changeable <?php echo (!isset($user['phone_changes']) || $user['phone_changes'] < 4 ? '(Can change ' . (4 - ($user['phone_changes'] ?? 0)) . ' more times)' : '(No more changes allowed)'); ?>)</label>
                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" pattern="[0-9]{10}" <?php echo (!isset($user['phone_changes']) || $user['phone_changes'] < 4 ? '' : 'disabled'); ?>>
                </div>
                <div class="form-group">
                    <label>Address (Changeable <?php echo (!isset($user['address_changes']) || $user['address_changes'] < 4 ? '(Can change ' . (4 - ($user['address_changes'] ?? 0)) . ' more times)' : '(No more changes allowed)'); ?>)</label>
                    <textarea name="address" rows="2" <?php echo (!isset($user['address_changes']) || $user['address_changes'] < 4 ? '' : 'disabled'); ?>><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                </div>
                <?php elseif ($user['role'] === 'Teacher'): ?>
                <div class="form-group">
                    <label>Subject</label>
                    <input type="text" name="subject" value="<?php echo htmlspecialchars($user['subject'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>Qualification</label>
                    <input type="text" name="qualification" value="<?php echo htmlspecialchars($user['qualification'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>Experience (years)</label>
                    <input type="number" name="experience" value="<?php echo $user['experience'] ?? 0; ?>" min="0" required>
                </div>
                <?php endif; ?>
                <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
            </form>
        </div>
    </div>
    
        <!-- Pay Now Modal -->
        <div id="payModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
            <div class="modal-content" style="width:90%; max-width:600px; background:white; border-radius:12px; padding:0; box-shadow:0 10px 40px rgba(0,0,0,0.3);">
                <div class="modal-header" style="padding:20px; border-bottom:2px solid #667eea; display:flex; justify-content:space-between; align-items:center; background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <h3 style="margin:0; color:#fff;">💳 Pay Fees</h3>
                    <span class="close" onclick="closePayModal()" style="font-size:28px; cursor:pointer; color:#fff;">&times;</span>
                </div>
                <div style="padding: 25px; max-height:75vh; overflow-y:auto;">
                    <div style="background:linear-gradient(135deg, #f5f7ff 0%, #f0f4ff 100%); padding:20px; border-radius:10px; margin-bottom:25px; border-left:5px solid #667eea;">
                        <h4 style="margin-top:0; margin-bottom:15px; color:#333; font-size:16px; font-weight:700;">📋 Payment Details:</h4>
                        <p style="margin:12px 0; color:#222; font-size:14px;"><strong>📱 UPI ID:</strong> <code style="background:#fff; padding:8px 12px; border-radius:6px; font-family:monospace; color:#667eea; font-weight:600; display:inline-block;"><?php echo PAYMENT_UPI_ID; ?></code></p>
                        <p style="margin:12px 0; color:#222; font-size:14px;"><strong>🏦 Bank Name:</strong> <span style="color:#555;"><?php echo PAYMENT_BANK_NAME; ?></span></p>
                        <p style="margin:12px 0; color:#222; font-size:14px;"><strong>💳 Account Number:</strong> <span style="color:#555; font-family:monospace;"><?php echo PAYMENT_ACCOUNT_NUMBER; ?></span></p>
                        <p style="margin:12px 0; color:#222; font-size:14px;"><strong>🏷️ IFSC Code:</strong> <span style="color:#555; font-family:monospace;"><?php echo PAYMENT_IFSC; ?></span></p>
                    </div>
                    
                    <div style="text-align:center; margin:25px 0; border:3px dashed #667eea; padding:20px; border-radius:10px; background:#fafbff;">
                        <p style="margin-top:0; margin-bottom:15px; color:#333; font-size:15px; font-weight:600;">📱 Scan to Pay via UPI:</p>
                        <?php if (file_exists(PAYMENT_QR_IMAGE)): ?>
                            <img src="<?php echo PAYMENT_QR_IMAGE; ?>?v=<?php echo filemtime(PAYMENT_QR_IMAGE); ?>" 
                                 alt="QR Code" id="paymentQR" 
                                 onclick="openQRModal(event)" 
                                 ontouchend="openQRModal(event)"
                                 style="max-width:220px; max-height:220px; display:block; margin:0 auto; cursor:pointer; border:2px solid #667eea; border-radius:8px; padding:5px; background:white; transition:transform 0.3s ease; -webkit-tap-highlight-color:transparent;"
                                 onmouseover="this.style.transform='scale(1.05)'" 
                                 onmouseout="this.style.transform='scale(1)'">
                            <small style="display:block; margin-top:10px; color:#667eea; font-size:13px; font-weight:600;">👆 Tap QR code to enlarge &amp; scan</small>
                        <?php else: ?>
                            <div style="width:200px; height:200px; margin:0 auto; background:#f5f5f5; border:2px dashed #ccc; border-radius:8px; display:flex; align-items:center; justify-content:center; flex-direction:column; color:#999;">
                                <div style="font-size:48px;">📱</div>
                                <small style="margin-top:8px; font-size:12px; text-align:center; padding:0 10px;">QR not configured.<br>Ask admin to upload.</small>
                            </div>
                        <?php endif; ?>
                        <p style="margin-top:12px; color:#555; font-size:13px;"><strong>UPI ID:</strong> <code style="background:#fff; padding:4px 10px; border-radius:4px; color:#667eea; font-weight:600; border:1px solid #ddd;"><?php echo PAYMENT_UPI_ID; ?></code></p>
                    </div>
                    
                    <form method="POST" action="actions.php" enctype="multipart/form-data">
                        <input type="hidden" name="fee_id" id="pay_fee_id" value="">
                        
                        <div class="form-group">
                            <label style="display:block; margin-bottom:10px; font-weight:600; color:#333; font-size:14px;">Transaction ID / Reference <span style="color:#999;">(Optional)</span></label>
                            <input type="text" name="transaction_id" placeholder="e.g., UPI ref ID or bank receipt No." style="width:100%; padding:12px; border:2px solid #ddd; border-radius:6px; box-sizing:border-box; font-size:14px; transition:border-color 0.3s ease;" onfocus="this.style.borderColor='#667eea'" onblur="this.style.borderColor='#ddd'">
                        </div>
                        
                        <div class="form-group">
                            <label style="display:block; margin-bottom:10px; font-weight:600; color:#333; font-size:14px;">Upload Payment Proof <span style="color:red;">*</span></label>
                            <input type="file" name="payment_screenshot" accept="image/*,.pdf" required style="width:100%; padding:12px; border:2px dashed #ddd; border-radius:6px; box-sizing:border-box; font-size:14px; background:#f9f9f9;" onfocus="this.style.borderColor='#667eea'" onblur="this.style.borderColor='#ddd'">
                            <small style="color:#999; display:block; margin-top:8px; font-size:12px;">✓ Accepted: JPG, PNG, PDF (max 5MB)</small>
                        </div>
                        
                        <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:25px;">
                            <button type="button" class="btn btn-secondary" onclick="closePayModal()" style="padding:11px 24px; font-weight:600; border-radius:6px; border:2px solid #ddd;">Cancel</button>
                            <button type="submit" name="submit_fee_payment" class="btn btn-primary" style="padding:11px 24px; font-weight:600; border-radius:6px; background:linear-gradient(135deg, #667eea 0%, #764ba2 100%); border:none; color:white;">✓ Submit Proof</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- QR Code Modal (Enlarged) -->
        <div id="qrModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:2000; align-items:center; justify-content:center;">
            <div style="position:relative; background:white; border-radius:16px; padding:30px 30px 25px; max-width:520px; width:90%; box-shadow:0 20px 60px rgba(0,0,0,0.5); text-align:center;">
                <span onclick="closeQRModal()" style="position:absolute; top:14px; right:18px; font-size:28px; cursor:pointer; color:#bbb; font-weight:bold; line-height:1;">&times;</span>
                <h3 style="margin:0 0 5px 0; color:#333; font-size:18px;">📱 Scan to Pay</h3>
                <p id="qrAmountDisplay" style="display:none; background:linear-gradient(135deg, #667eea, #764ba2); color:white; font-size:20px; font-weight:700; padding:8px 20px; border-radius:20px; margin:8px auto 16px;"></p>
                <div style="border:3px solid #667eea; border-radius:12px; padding:12px; background:#fafbff; display:inline-block; margin:8px 0;">
                    <?php if (file_exists(PAYMENT_QR_IMAGE)): ?>
                        <img src="<?php echo PAYMENT_QR_IMAGE; ?>?v=<?php echo filemtime(PAYMENT_QR_IMAGE); ?>" alt="QR Code" style="display:block; width:280px; height:280px; object-fit:contain; border-radius:4px; background:white;">
                    <?php else: ?>
                        <div style="width:280px; height:280px; background:#f5f5f5; display:flex; align-items:center; justify-content:center; flex-direction:column; color:#999; border-radius:4px;">
                            <div style="font-size:60px;">📱</div>
                            <p style="margin-top:10px; font-size:13px;">No QR configured</p>
                        </div>
                    <?php endif; ?>
                </div>
                <p style="color:#555; margin:12px 0 4px; font-size:14px;">UPI: <strong style="color:#667eea; font-family:monospace;"><?php echo PAYMENT_UPI_ID; ?></strong></p>
                <p style="color:#999; font-size:12px; margin:0 0 15px;">Open any UPI app and scan this code</p>
                <button onclick="closeQRModal()" style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%); border:none; color:white; padding:11px 35px; border-radius:8px; font-weight:700; cursor:pointer; font-size:14px; letter-spacing:0.5px;">✓ Done</button>
            </div>
        </div>
        
        <!-- Cash Payment Modal -->
        <div id="cashPaymentModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:2000; align-items:center; justify-content:center;">
            <div style="position:relative; background:white; border-radius:12px; padding:0; max-width:500px; width:90%; box-shadow:0 20px 60px rgba(0,0,0,0.4);">
                <div style="padding:20px; border-bottom:2px solid #10b981; display:flex; justify-content:space-between; align-items:center; background:linear-gradient(135deg, #10b981 0%, #059669 100%);">
                    <h3 style="margin:0; color:#fff;">💰 Mark as Paid (Cash)</h3>
                    <span onclick="closeCashPaymentModal()" style="font-size:28px; cursor:pointer; color:#fff; font-weight:bold;">&times;</span>
                </div>
                <form method="POST" action="actions.php" id="cashPaymentForm">
                    <div style="padding:25px;">
                        <div style="background:#f0fdf4; padding:15px; border-radius:8px; margin-bottom:20px; border-left:4px solid #10b981;">
                            <p style="margin:0; color:#155e3d; font-size:14px;"><strong>Student:</strong> <span id="cashStudentName"></span></p>
                            <p style="margin:5px 0 0 0; color:#155e3d; font-size:14px;"><strong>Amount:</strong> ₹<span id="cashFeeAmount">0</span></p>
                        </div>
                        
                        <input type="hidden" name="fee_id" id="cashFeeId" value="">
                        
                        <div style="margin-bottom:20px;">
                            <label style="display:block; margin-bottom:10px; font-weight:600; color:#333; font-size:14px;">Payment Method</label>
                            <select name="payment_method" required style="width:100%; padding:12px; border:2px solid #e5e7eb; border-radius:6px; font-size:14px;" onfocus="this.style.borderColor='#10b981'" onblur="this.style.borderColor='#e5e7eb'">
                                <option value="">-- Select Payment Method --</option>
                                <option value="cash">Cash (Office)</option>
                                <option value="cheque">Cheque</option>
                                <option value="bank_transfer">Bank Transfer</option>
                            </select>
                        </div>
                        
                        <div style="margin-bottom:20px;">
                            <label style="display:block; margin-bottom:10px; font-weight:600; color:#333; font-size:14px;">Notes <span style="color:#999;">(Optional)</span></label>
                            <textarea name="notes" placeholder="Payment reference, transaction ID, etc." rows="3" style="width:100%; padding:12px; border:2px solid #e5e7eb; border-radius:6px; font-size:14px; resize:vertical;" onfocus="this.style.borderColor='#10b981'" onblur="this.style.borderColor='#e5e7eb'"></textarea>
                        </div>
                        
                        <div style="margin-bottom:20px;">
                            <label style="display:block; margin-bottom:10px; font-weight:600; color:#333; font-size:14px;">
                                <input type="checkbox" name="generate_receipt" value="1" checked> Generate Receipt for Student
                            </label>
                            <small style="color:#666;">Receipt will be auto-sent to student email</small>
                        </div>
                        
                        <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:25px;">
                            <button type="button" class="btn" onclick="closeCashPaymentModal()" style="background:#e5e7eb; color:#333; padding:11px 24px; font-weight:600; border-radius:6px; border:none; cursor:pointer;">Cancel</button>
                            <button type="submit" name="mark_cash_paid" class="btn" style="background:linear-gradient(135deg, #10b981 0%, #059669 100%); color:white; padding:11px 24px; font-weight:600; border-radius:6px; border:none; cursor:pointer;">✓ Mark as Paid</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Cash Payment Modal -->
        <div id="editCashModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:2000; align-items:center; justify-content:center;">
            <div style="position:relative; background:white; border-radius:12px; padding:0; max-width:500px; width:90%; box-shadow:0 20px 60px rgba(0,0,0,0.4);">
                <div style="padding:20px; border-bottom:2px solid #667eea; display:flex; justify-content:space-between; align-items:center; background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <h3 style="margin:0; color:#fff;">✏️ Edit Cash Payment</h3>
                    <span onclick="closeEditCashModal()" style="font-size:28px; cursor:pointer; color:#fff; font-weight:bold;">&times;</span>
                </div>
                <form method="POST" action="actions.php" id="editCashPaymentForm">
                    <div style="padding:25px;">
                        <div style="background:#f0f4ff; padding:15px; border-radius:8px; margin-bottom:20px; border-left:4px solid #667eea;">
                            <p style="margin:0; color:#2d3e7d; font-size:14px;"><strong>Student:</strong> <span id="editCashStudentName"></span></p>
                            <p style="margin:5px 0 0 0; color:#2d3e7d; font-size:14px;"><strong>Fee Type:</strong> <span id="editCashFeeType"></span></p>
                            <p style="margin:5px 0 0 0; color:#2d3e7d; font-size:14px;"><strong>Total Amount:</strong> ₹<span id="editCashFeeAmount">0</span></p>
                        </div>
                        
                        <input type="hidden" name="fee_id" id="editCashFeeId" value="">
                        
                        <div style="margin-bottom:20px;">
                            <label style="display:block; margin-bottom:10px; font-weight:600; color:#333; font-size:14px;">Paid Amount</label>
                            <input type="number" name="paid_amount" id="editPaidAmount" step="0.01" min="0" required style="width:100%; padding:12px; border:2px solid #e5e7eb; border-radius:6px; font-size:14px;" onfocus="this.style.borderColor='#667eea'" onblur="this.style.borderColor='#e5e7eb'">
                        </div>
                        
                        <div style="margin-bottom:20px;">
                            <label style="display:block; margin-bottom:10px; font-weight:600; color:#333; font-size:14px;">Payment Method</label>
                            <select name="payment_method" id="editPaymentMethod" required style="width:100%; padding:12px; border:2px solid #e5e7eb; border-radius:6px; font-size:14px;" onfocus="this.style.borderColor='#667eea'" onblur="this.style.borderColor='#e5e7eb'">
                                <option value="">-- Select --</option>
                                <option value="cash">💵 Cash</option>
                                <option value="cheque">🏦 Cheque</option>
                                <option value="bank_transfer">🔄 Bank Transfer</option>
                            </select>
                        </div>
                        
                        <div style="margin-bottom:20px;">
                            <label style="display:block; margin-bottom:10px; font-weight:600; color:#333; font-size:14px;">Notes <span style="color:#999;">(Optional)</span></label>
                            <textarea name="notes" id="editNotes" placeholder="Transaction ID, cheque number, etc." rows="3" style="width:100%; padding:12px; border:2px solid #e5e7eb; border-radius:6px; font-size:14px; resize:vertical;" onfocus="this.style.borderColor='#667eea'" onblur="this.style.borderColor='#e5e7eb'"></textarea>
                        </div>
                        
                        <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:25px;">
                            <button type="button" class="btn" onclick="closeEditCashModal()" style="background:#e5e7eb; color:#333; padding:11px 24px; font-weight:600; border-radius:6px; border:none; cursor:pointer;">Cancel</button>
                            <button type="submit" name="update_cash_payment" class="btn" style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%); color:white; padding:11px 24px; font-weight:600; border-radius:6px; border:none; cursor:pointer;">✓ Update</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <script src="assets.js"></script>
    <script>
        // Question counter for test creation
        let questionCount = 1;
        function addQuestion() {
            const container = document.getElementById('questionsContainer');
            const questionHTML = `
                <div class="question-item">
                    <input type="text" name="questions[${questionCount}][question]" placeholder="Question" required>
                    <div class="options-grid">
                        <input type="text" name="questions[${questionCount}][option_a]" placeholder="Option A" required>
                        <input type="text" name="questions[${questionCount}][option_b]" placeholder="Option B" required>
                        <input type="text" name="questions[${questionCount}][option_c]" placeholder="Option C" required>
                        <input type="text" name="questions[${questionCount}][option_d]" placeholder="Option D" required>
                    </div>
                    <div class="form-row">
                        <select name="questions[${questionCount}][correct]" required>
                            <option value="">Correct Answer</option>
                            <option value="A">A</option>
                            <option value="B">B</option>
                            <option value="C">C</option>
                            <option value="D">D</option>
                        </select>
                        <input type="number" name="questions[${questionCount}][marks]" value="1" min="1" placeholder="Marks" required>
                        <button type="button" onclick="this.parentElement.parentElement.remove()" class="btn btn-danger btn-sm">Remove</button>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', questionHTML);
            questionCount++;
        }
        
        // Toggle course field for student role
        function toggleCourse() {
            const role = document.getElementById('roleSelect').value;
            const courseGroup = document.getElementById('courseGroup');
            courseGroup.style.display = role === 'Student' ? 'block' : 'none';
        }
        
        // View assignment submissions
        function viewSubmissions(assignmentId) {
            const submissionsDiv = document.getElementById('submissions-' + assignmentId);
            if (submissionsDiv) {
                const isHidden = submissionsDiv.style.display === 'none';
                submissionsDiv.style.display = isHidden ? 'block' : 'none';
                if (isHidden) {
                    submissionsDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }
        }
        
        // Start test (Student)
        function startTest(testId) {
            fetch('?section=get_test&id=' + testId)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert(data.error);
                        return;
                    }
                    document.getElementById('testId').value = testId;
                    document.getElementById('testTitle').textContent = data.title;
                    
                    let questionsHTML = '';
                    data.questions.forEach((q, index) => {
                        questionsHTML += `
                            <div class="question-box">
                                <h4>Q${index + 1}. ${q.question} (${q.marks} mark${q.marks > 1 ? 's' : ''})</h4>
                                <div class="options">
                                    <label><input type="radio" name="answers[${q.id}]" value="A" required> ${q.option_a}</label>
                                    <label><input type="radio" name="answers[${q.id}]" value="B" required> ${q.option_b}</label>
                                    <label><input type="radio" name="answers[${q.id}]" value="C" required> ${q.option_c}</label>
                                    <label><input type="radio" name="answers[${q.id}]" value="D" required> ${q.option_d}</label>
                                </div>
                            </div>
                        `;
                    });
                    
                    document.getElementById('questionsDisplay').innerHTML = questionsHTML;
                    document.getElementById('testModal').style.display = 'flex';
                    
                    startTimer(data.time_limit);
                })
                .catch(error => {
                    alert('Error loading test: ' + error.message);
                });
        }
        
        // Profile modal functions
        function openProfileModal() {
            document.getElementById('profileModal').style.display = 'flex';
        }
        
        function closeProfileModal() {
            document.getElementById('profileModal').style.display = 'none';
        }
        
        // Forum search removed
        function searchForums() { /* removed */ }
        
        // Search library removed (feature disabled)
        function searchLibrary() { /* removed */ }

        // Notifications dropdown (student)
        let notifOpen = false;
        function toggleNotifications() {
            const dropdown = document.getElementById('notifDropdown');
            if (!dropdown) return;
            if (notifOpen) {
                dropdown.style.display = 'none';
                notifOpen = false;
                document.removeEventListener('click', closeNotificationsOnClickOutside);
                return;
            }
            loadNotifications();
            dropdown.style.display = 'block';
            notifOpen = true;
            setTimeout(() => document.addEventListener('click', closeNotificationsOnClickOutside), 50);
        }
        function closeNotificationsOnClickOutside(e) {
            const dropdown = document.getElementById('notifDropdown');
            const toggle = document.getElementById('notifToggle');
            if (!dropdown || !toggle) return;
            if (!dropdown.contains(e.target) && !toggle.contains(e.target)) {
                dropdown.style.display = 'none';
                notifOpen = false;
                document.removeEventListener('click', closeNotificationsOnClickOutside);
            }
        }

        async function loadNotifications() {
            try {
                const res = await fetch('actions.php?ajax=get_notifications');
                const data = await res.json();
                renderNotifications(data.notifications || []);
                const countEl = document.getElementById('notifCount');
                if (data.unread && data.unread > 0) {
                    countEl.style.display = '';
                    countEl.textContent = data.unread;
                } else {
                    countEl.style.display = 'none';
                }
            } catch (err) {
                document.getElementById('notifList').innerHTML = '<p class="notif-empty" style="color:#999; text-align:center; padding:10px;">Unable to load notifications</p>';
            }
        }

        function renderNotifications(items) {
            const container = document.getElementById('notifList');
            if (!container) return;
            if (items.length === 0) {
                container.innerHTML = '<p class="notif-empty" style="color:#999; text-align:center; padding:20px;">No new notifications</p>';
                return;
            }
            let html = '';
            items.forEach(n => {
                const unreadClass = n.is_read == 0 ? 'unread' : '';
                const link = n.link ? n.link : '#';
                html += `<div class="notif-item ${unreadClass}" data-id="${n.id}" style="display:flex; gap:10px; padding:10px; border-bottom:1px solid var(--border); cursor:pointer; align-items:center;">
                    <div style="flex:1;">
                        <div style="font-weight:700; color:var(--text-primary);">${escapeHtml(n.title)}</div>
                        <div style="font-size:13px; color:var(--text-secondary); margin-top:4px;">${escapeHtml(n.message)}</div>
                    </div>
                    <div style="text-align:right; font-size:12px; color:var(--text-tertiary); margin-right:8px;">${escapeHtml(n.time_ago)}</div>
                    <div style="width:28px; text-align:center;">
                        <button class="btn btn-ghost btn-sm delete-notif" data-id="${n.id}" title="Delete" style="border:none; background:transparent; color:var(--text-secondary);">✖</button>
                    </div>
                </div>`;
            });
            container.innerHTML = html;
            // Attach click handlers for notification items
            document.querySelectorAll('.notif-item').forEach(item => {
                // clicking item (except delete) opens link and marks read
                item.addEventListener('click', async function(e) {
                    if (e.target && e.target.classList && e.target.classList.contains('delete-notif')) return; // skip if delete clicked
                    const id = this.getAttribute('data-id');
                    await markNotification(id);
                    const idx = Array.from(this.parentNode.children).indexOf(this);
                    const link = items[idx] && items[idx].link ? items[idx].link : null;
                    if (link && link !== '#') window.location.href = link;
                    else loadNotifications();
                });
            });
            // Attach delete handlers
            document.querySelectorAll('.delete-notif').forEach(btn => {
                btn.addEventListener('click', async function(e) {
                    e.stopPropagation();
                    const id = this.getAttribute('data-id');
                    await deleteNotification(id);
                });
            });
        }

        async function deleteNotification(id) {
            try {
                const fd = new FormData();
                fd.append('ajax_delete_notif', 1);
                fd.append('notif_id', id);
                const res = await fetch('actions.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    await loadNotifications();
                }
            } catch (err) { /* ignore */ }
        }

        // Poll unread count periodically (30s)
        async function pollUnread() {
            try {
                const res = await fetch('actions.php?ajax=get_notifications');
                const data = await res.json();
                const countEl = document.getElementById('notifCount');
                if (data.unread && data.unread > 0) {
                    countEl.style.display = '';
                    countEl.textContent = data.unread;
                } else if (countEl) {
                    countEl.style.display = 'none';
                }
            } catch (err) { /* ignore */ }
        }
        setInterval(pollUnread, 30000);
        pollUnread();

        async function markNotification(id) {
            try {
                const fd = new FormData();
                fd.append('ajax_mark_notif_read', 1);
                fd.append('notif_id', id);
                const res = await fetch('actions.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    await loadNotifications();
                }
            } catch (err) { /* ignore */ }
        }

        async function markAllRead() {
            try {
                const fd = new FormData();
                fd.append('ajax_mark_all_read', 1);
                const res = await fetch('actions.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    await loadNotifications();
                }
            } catch (err) { /* ignore */ }
        }

        function escapeHtml(unsafe) {
            return String(unsafe).replace(/[&<"']/g, function(m) {
                return {'&':'&amp;','<':'&lt;','"':'&quot;',"'":'&#039;'}[m];
            });
        }

        // Search fees (teacher view)
        function searchFees() {
            const input = document.getElementById('feeSearch');
            if (!input) return;
            const filter = input.value.toUpperCase();
            const rows = document.querySelectorAll('.fees-table tbody tr');
            rows.forEach(row => {
                const student = (row.querySelector('.col-student') || {textContent: ''}).textContent;
                const type = (row.querySelector('.col-type') || {textContent: ''}).textContent;
                const remarks = (row.querySelector('.col-remarks') || {textContent: ''}).textContent;
                const text = (student + ' ' + type + ' ' + remarks).toUpperCase();
                if (text.indexOf(filter) > -1) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Pay Modal functions
        function openPayModal(feeId, amount) {
            const modal = document.getElementById('payModal');
            const feeInput = document.getElementById('pay_fee_id');
            feeInput.value = feeId;
            // Show fee amount in modal title
            const amountFormatted = '₹' + Number(amount).toLocaleString('en-IN', {minimumFractionDigits: 2});
            const titleEl = document.querySelector('#payModal .modal-header h3');
            if (titleEl) titleEl.textContent = '💳 Pay Fees — ' + amountFormatted;
            // Store amount for QR modal
            window._currentPayAmount = amountFormatted;
            modal.style.display = 'flex';
        }

        function closePayModal() {
            const modal = document.getElementById('payModal');
            modal.style.display = 'none';
        }

        // QR Code Modal functions
        function openQRModal(e) {
            if (e) { e.preventDefault(); e.stopPropagation(); }
            const modal = document.getElementById('qrModal');
            if (modal) {
                // Show amount in QR modal
                const amtEl = document.getElementById('qrAmountDisplay');
                if (amtEl && window._currentPayAmount) {
                    amtEl.textContent = 'Pay: ' + window._currentPayAmount;
                    amtEl.style.display = 'inline-block';
                }
                modal.style.display = 'flex';
            }
        }

        function closeQRModal() {
            const modal = document.getElementById('qrModal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        // Close QR modal when clicking outside
        window.onclick = function(event) {
            const qrModal = document.getElementById('qrModal');
            if (qrModal && event.target === qrModal) {
                qrModal.style.display = 'none';
            }
        }

        // Cash Payment Modal functions
        function openCashPaymentModal(feeId, studentName, feeAmount, paidAmount) {
            document.getElementById('cashFeeId').value = feeId;
            document.getElementById('cashStudentName').textContent = studentName;
            document.getElementById('cashFeeAmount').textContent = feeAmount.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('cashPaymentModal').style.display = 'flex';
        }

        function closeCashPaymentModal() {
            document.getElementById('cashPaymentModal').style.display = 'none';
            document.getElementById('cashPaymentForm').reset();
        }

        // Close cash payment modal when clicking outside
        window.addEventListener('click', function(event) {
            const cashModal = document.getElementById('cashPaymentModal');
            if (cashModal && event.target === cashModal) {
                cashModal.style.display = 'none';
            }
        });
        // Edit Cash Payment Modal functions
        function openEditCashModal(feeId, studentName, feeAmount, paidAmount, feeType) {
            document.getElementById('editCashFeeId').value = feeId;
            document.getElementById('editCashStudentName').textContent = studentName;
            document.getElementById('editCashFeeAmount').textContent = feeAmount.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('editCashFeeType').textContent = feeType;
            document.getElementById('editPaidAmount').value = paidAmount;
            document.getElementById('editCashModal').style.display = 'flex';
        }

        function closeEditCashModal() {
            document.getElementById('editCashModal').style.display = 'none';
            document.getElementById('editCashPaymentForm').reset();
        }

        // Close edit cash modal when clicking outside
        window.addEventListener('click', function(event) {
            const editCashModal = document.getElementById('editCashModal');
            if (editCashModal && event.target === editCashModal) {
                editCashModal.style.display = 'none';
            }
        });

        // Attendance functions
        async function loadStudentsForAttendance(course) {
            if (!course) {
                document.getElementById('studentAttendanceList').innerHTML = '<tr><td colspan="6" style="text-align:center; padding:20px; color:#999;">Select a course to load students</td></tr>';
                return;
            }
            
            try {
                const res = await fetch('get_students.php?course=' + encodeURIComponent(course));
                const students = await res.json();
                
                let html = '';
                if (students.error) {
                    html = '<tr><td colspan="6" style="text-align:center; color:#999;">Error loading students</td></tr>';
                } else if (students.length === 0) {
                    html = '<tr><td colspan="6" style="text-align:center; color:#999;">No students in this course</td></tr>';
                } else {
                    students.forEach((student, index) => {
                        html += `
                            <tr>
                                <td style="padding:10px; text-align:center;">${index + 1}</td>
                                <td style="padding:10px;">${student.name}</td>
                                <td style="padding:10px; text-align:center;">
                                    <input type="radio" name="attendance[${student.id}]" value="present" required>
                                </td>
                                <td style="padding:10px; text-align:center;">
                                    <input type="radio" name="attendance[${student.id}]" value="absent">
                                </td>
                                <td style="padding:10px; text-align:center;">
                                    <input type="radio" name="attendance[${student.id}]" value="late">
                                </td>
                                <td style="padding:10px;">
                                    <input type="text" name="remarks[${student.id}]" placeholder="Optional remarks" style="width:100%; padding:5px; border:1px solid #ddd; border-radius:4px; box-sizing:border-box;">
                                </td>
                            </tr>
                        `;
                    });
                }
                document.getElementById('studentAttendanceList').innerHTML = html;
            } catch (err) {
                console.error('Error:', err);
                document.getElementById('studentAttendanceList').innerHTML = '<tr><td colspan="6" style="text-align:center; color:#999;">Error loading students</td></tr>';
            }
        }

        // Load students for fees management
        async function loadStudentsForFees(course) {
            const sel = document.getElementById('feeStudentSelect');
            if (!sel) return;
            sel.innerHTML = '<option value="">Loading...</option>';

            if (!course) {
                // show all students
                try {
                    const res = await fetch('get_students.php?course=All');
                    const students = await res.json();
                    populateFeeStudents(sel, students);
                } catch (e) {
                    sel.innerHTML = '<option value="">Error loading students</option>';
                }
                return;
            }

            try {
                const res = await fetch('get_students.php?course=' + encodeURIComponent(course));
                const students = await res.json();
                populateFeeStudents(sel, students);
            } catch (err) {
                console.error('Error:', err);
                sel.innerHTML = '<option value="">Error loading students</option>';
            }
        }

        function populateFeeStudents(sel, students) {
            sel.innerHTML = '';
            if (!students || students.error || students.length === 0) {
                sel.innerHTML = '<option value="">No students in this course</option>';
                return;
            }
            const emptyOpt = document.createElement('option');
            emptyOpt.value = '';
            emptyOpt.textContent = 'Choose student';
            sel.appendChild(emptyOpt);
            students.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.id;
                opt.textContent = s.name;
                sel.appendChild(opt);
            });
        }

        function filterAttendanceTable() {
            const input = document.getElementById('searchAttendance');
            const filter = input ? input.value.toUpperCase() : '';
            const table = document.getElementById('attendanceHistoryTable');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            
            rows.forEach(row => {
                const studentName = row.cells[0].textContent.toUpperCase();
                if (studentName.indexOf(filter) > -1) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Download Receipt function
        function downloadReceipt(feeType, amount, paidAmount, paymentDate, transactionId, paymentMethod, status) {
            const receiptNumber = 'RCP-' + new Date().toISOString().replace(/[^0-9]/g, '').slice(0, 14);
            const studentName = document.querySelector('.user-info span')?.textContent || 'Student';
            const today = new Date().toLocaleDateString('en-IN', {day:'2-digit', month:'short', year:'numeric'});
            const formattedPayDate = paymentDate ? new Date(paymentDate).toLocaleDateString('en-IN', {day:'2-digit', month:'short', year:'numeric'}) : today;
            const isPartial = status === 'Partial';
            const balanceAmt = Number(amount) - Number(paidAmount);
            const schoolName = '<?php echo PAYMENT_BANK_NAME; ?>';
            const upiId = '<?php echo PAYMENT_UPI_ID; ?>';
            
            const receiptHTML = `
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>Payment Receipt - ${receiptNumber}</title>
                    <style>
                        * { box-sizing: border-box; margin: 0; padding: 0; }
                        body { font-family: 'Arial', sans-serif; background: #f0f0f0; padding: 20px; }
                        .page { max-width: 680px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.15); }
                        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px 40px; text-align: center; }
                        .header h1 { font-size: 26px; font-weight: 800; letter-spacing: 1px; }
                        .header p { opacity: 0.85; font-size: 13px; margin-top: 4px; }
                        .receipt-badge { display: inline-block; background: rgba(255,255,255,0.2); border: 2px solid rgba(255,255,255,0.5); padding: 6px 20px; border-radius: 20px; font-size: 13px; font-weight: 700; margin-top: 12px; letter-spacing: 2px; }
                        .receipt-no { font-size: 11px; opacity: 0.7; margin-top: 8px; }
                        .body { padding: 30px 40px; }
                        .status-bar { display: flex; align-items: center; justify-content: center; gap: 12px; padding: 14px; border-radius: 8px; margin-bottom: 25px; font-weight: 700; font-size: 15px; }
                        .status-bar.paid { background: #d1fae5; color: #065f46; border: 2px solid #6ee7b7; }
                        .status-bar.partial { background: #fef3c7; color: #92400e; border: 2px solid #fcd34d; }
                        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; margin-bottom: 25px; }
                        .info-cell { padding: 14px 18px; border-right: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb; }
                        .info-cell:nth-child(2n) { border-right: none; }
                        .info-cell:nth-last-child(-n+2) { border-bottom: none; }
                        .info-cell .lbl { font-size: 11px; color: #9ca3af; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
                        .info-cell .val { font-size: 14px; color: #1f2937; font-weight: 600; }
                        .amounts { border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; margin-bottom: 25px; }
                        .amt-row { display: flex; justify-content: space-between; padding: 13px 18px; border-bottom: 1px solid #e5e7eb; font-size: 14px; }
                        .amt-row:last-child { border-bottom: none; font-size: 16px; font-weight: 700; background: #f9fafb; }
                        .amt-row .lbl { color: #6b7280; }
                        .amt-row .val { color: #1f2937; font-weight: 600; }
                        .amt-row.total .val { color: #059669; font-size: 17px; }
                        .amt-row.balance .val { color: #dc2626; }
                        .footer { text-align: center; padding: 20px 40px 30px; border-top: 1px solid #e5e7eb; color: #9ca3af; font-size: 12px; line-height: 1.6; }
                        .print-actions { text-align: center; padding: 0 40px 30px; display: flex; gap: 12px; justify-content: center; }
                        .btn-print { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 12px 28px; border-radius: 6px; font-size: 14px; cursor: pointer; font-weight: 600; }
                        .btn-close { background: #e5e7eb; color: #374151; border: none; padding: 12px 28px; border-radius: 6px; font-size: 14px; cursor: pointer; font-weight: 600; }
                        @media print {
                            body { background: white; padding: 0; }
                            .page { box-shadow: none; border-radius: 0; }
                            .print-actions { display: none !important; }
                        }
                    </style>
                </head>
                <body>
                    <div class="page">
                        <div class="header">
                            <h1>🎓 SAI COLLEGE</h1>
                            <p>Educational Management System</p>
                            <div class="receipt-badge">PAYMENT RECEIPT</div>
                            <div class="receipt-no">Receipt No: ${receiptNumber}</div>
                        </div>
                        
                        <div class="body">
                            <div class="status-bar ${isPartial ? 'partial' : 'paid'}">
                                ${isPartial ? '⚠️ PARTIAL PAYMENT' : '✅ PAYMENT RECEIVED'}
                            </div>
                            
                            <div class="info-grid">
                                <div class="info-cell">
                                    <div class="lbl">Student Name</div>
                                    <div class="val">${studentName}</div>
                                </div>
                                <div class="info-cell">
                                    <div class="lbl">Fee Type</div>
                                    <div class="val">${feeType}</div>
                                </div>
                                <div class="info-cell">
                                    <div class="lbl">Payment Date</div>
                                    <div class="val">${formattedPayDate}</div>
                                </div>
                                <div class="info-cell">
                                    <div class="lbl">Payment Method</div>
                                    <div class="val">${paymentMethod.replace('_', ' ').toUpperCase()}</div>
                                </div>
                                ${transactionId ? `
                                <div class="info-cell" style="grid-column:1/-1">
                                    <div class="lbl">Transaction / Reference ID</div>
                                    <div class="val" style="font-family:monospace; color:#667eea;">${transactionId}</div>
                                </div>` : ''}
                            </div>
                            
                            <div class="amounts">
                                <div class="amt-row">
                                    <span class="lbl">Total Fee Amount</span>
                                    <span class="val">₹${Number(amount).toLocaleString('en-IN', {minimumFractionDigits:2})}</span>
                                </div>
                                <div class="amt-row total">
                                    <span class="lbl">Amount Paid</span>
                                    <span class="val">₹${Number(paidAmount).toLocaleString('en-IN', {minimumFractionDigits:2})}</span>
                                </div>
                                ${isPartial ? `
                                <div class="amt-row balance">
                                    <span class="lbl">Balance Due</span>
                                    <span class="val">₹${balanceAmt.toLocaleString('en-IN', {minimumFractionDigits:2})}</span>
                                </div>` : ''}
                            </div>
                        </div>
                        
                        <div class="footer">
                            <p>This is a system-generated receipt. No signature required.</p>
                            <p>For queries contact the administration office.</p>
                            <p style="margin-top:8px; color:#667eea;">${upiId ? 'UPI: ' + upiId : ''}</p>
                        </div>
                        
                        <div class="print-actions">
                            <button class="btn-print" onclick="window.print()">🖨️ Print Receipt</button>
                            <button class="btn-close" onclick="window.close()">Close</button>
                        </div>
                    </div>
                </body>
                </html>
            `;
            
            const receiptWindow = window.open('', '_blank', 'width=750,height=680');
            if (receiptWindow) {
                receiptWindow.document.write(receiptHTML);
                receiptWindow.document.close();
            } else {
                alert('Please allow popups to view the receipt.');
            }
        }
        // Direct Payment Form Functions
function toggleDirectPaymentForm() {
    const form = document.getElementById('directPaymentForm');
    const btn = document.getElementById('toggleFormBtn');
    if (form.style.display === 'none' || !form.style.display) {
        form.style.display = 'block';
        btn.innerHTML = '✖ Close Form';
        btn.style.background = 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)';
        // Scroll to form
        form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } else {
        form.style.display = 'none';
        btn.innerHTML = '➕ Add New Payment';
        btn.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
    }
}

function updateDirectBalance() {
    const total = parseFloat(document.getElementById('directAmount').value) || 0;
    const paid = parseFloat(document.getElementById('directPaidAmount').value) || 0;
    const balance = total - paid;
    document.getElementById('directBalance').value = balance.toFixed(2);
}

// Load today's cash payment stats
async function loadCashStats() {
    try {
        const response = await fetch('get_cash_stats.php');
        const data = await response.json();
        
        if (data.today_count) {
            document.getElementById('todayCashCount').textContent = data.today_count;
        }
        if (data.month_total) {
            document.getElementById('monthCashTotal').textContent = '₹' + data.month_total;
        }
    } catch (error) {
        console.log('Could not load stats');
    }
}

// Call when page loads
loadCashStats();
    </script>
</body>
</html>
