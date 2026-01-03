<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../login.php');
    exit();
}

$user_role = $_SESSION['role'];
$user_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
$profile_pic = $_SESSION['profile_picture'] ?: 'default.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dayflow HRMS - <?php echo $page_title ?? 'Dashboard'; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/hrms-ui.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-red: #ff6b6b;
            --primary-orange: #ffa500;
            --primary-yellow: #ffd93d;
            --light-red: #ffe6e6;
            --light-orange: #fff3e0;
            --light-yellow: #fffde7;
            --dark-red: #e74c3c;
            --dark-orange: #e67e22;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-left">
            <div class="logo">
                <h1><i class="fas fa-sun"></i> Dayflow</h1>
                <span class="badge badge-role"><?php echo ucfirst($user_role); ?></span>
            </div>
        </div>
        
        <div class="nav-center">
            <span class="welcome-message">Welcome, <?php echo $user_name; ?>!</span>
            <?php if($user_role == 'employee'): ?>
                <div class="clock-container" id="clockContainer">
                    <i class="fas fa-clock"></i>
                    <span id="currentTime"></span>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="nav-right">
            <div class="user-menu">
                <img src="../assets/images/<?php echo $profile_pic; ?>" 
                     alt="Profile" 
                     class="profile-pic"
                     onerror="this.src='../assets/images/default.png'">
                <div class="dropdown">
                    <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <?php if($user_role == 'employee'): ?>
                        <a href="attendance.php"><i class="fas fa-calendar-check"></i> Attendance</a>
                        <a href="apply_leave.php"><i class="fas fa-plane"></i> Apply Leave</a>
                    <?php endif; ?>
                    <div class="divider"></div>
                    <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </nav>
    
    <div class="sidebar">
        <?php if($user_role == 'admin' || $user_role == 'hr'): ?>
            <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="employees.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'employees.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> Employees
            </a>
            <a href="attendance.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'attendance.php' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-check"></i> Attendance
            </a>
            <a href="leave_requests.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'leave_requests.php' ? 'active' : ''; ?>">
                <i class="fas fa-plane"></i> Leave Requests
                <span class="badge" id="pendingBadge">0</span>
            </a>
            <a href="payroll.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'payroll.php' ? 'active' : ''; ?>">
                <i class="fas fa-money-bill-wave"></i> Payroll
            </a>
            <a href="profile.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-cog"></i> Profile
            </a>
        <?php else: ?>
            <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="attendance.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'attendance.php' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-check"></i> Attendance
            </a>
            <a href="apply_leave.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'apply_leave.php' ? 'active' : ''; ?>">
                <i class="fas fa-plane"></i> Apply Leave
            </a>
            <a href="salary.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'salary.php' ? 'active' : ''; ?>">
                <i class="fas fa-money-bill"></i> Salary
            </a>
            <a href="summary.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'summary.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i> Performance
            </a>
            <a href="profile.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                <i class="fas fa-user"></i> Profile
            </a>
        <?php endif; ?>
    </div>
    
    <main class="main-content">