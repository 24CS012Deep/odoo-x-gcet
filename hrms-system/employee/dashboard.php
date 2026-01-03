<?php
$page_title = "Employee Dashboard";
require_once '../includes/header.php';
require_once '../config/database.php';

$user_id = $_SESSION['user_id'];
$conn = getConnection();

// Get employee data
$employee_query = $conn->prepare("
    SELECT u.*, 
           COUNT(DISTINCT a.id) as total_days,
           SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_days,
           (SELECT COUNT(*) FROM leave_requests WHERE user_id = ? AND status = 'approved' AND MONTH(start_date) = MONTH(CURDATE())) as leaves_taken
    FROM users u
    LEFT JOIN attendance a ON u.id = a.user_id AND MONTH(a.date) = MONTH(CURDATE())
    WHERE u.id = ?
");
$employee_query->bind_param("ii", $user_id, $user_id);
$employee_query->execute();
$employee = $employee_query->get_result()->fetch_assoc();
$employee_query->close();

// Today's attendance status
$today_status = $conn->query("
    SELECT status, check_in, check_out FROM attendance 
    WHERE user_id = $user_id AND date = CURDATE()
")->fetch_assoc();

// Recent leave requests
$my_leaves = $conn->query("
    SELECT * FROM leave_requests 
    WHERE user_id = $user_id 
    ORDER BY created_at DESC 
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Performance data
$performance = $conn->query("
    SELECT * FROM performance 
    WHERE user_id = $user_id 
    ORDER BY month_year DESC 
    LIMIT 1
")->fetch_assoc();

$conn->close();

// Calculate attendance percentage
$attendance_percentage = $employee['total_days'] > 0 
    ? round(($employee['present_days'] / $employee['total_days']) * 100) 
    : 0;
?>

<div class="dashboard-header">
    <h2><i class="fas fa-tachometer-alt"></i> My Dashboard</h2>
    <div class="date-info">
        <?php echo date('l, F j, Y'); ?>
        <span class="status-badge status-<?php echo $today_status ? strtolower($today_status['status']) : 'absent'; ?>">
            <?php echo $today_status ? ucfirst($today_status['status']) : 'Not Checked In'; ?>
        </span>
    </div>
</div>

<!-- Quick Actions -->
<div class="quick-actions">
    <?php if(!$today_status || !$today_status['check_in']): ?>
    <button class="quick-btn btn-checkin" onclick="checkIn()">
        <i class="fas fa-sign-in-alt"></i>
        <span>Check In</span>
    </button>
    <?php elseif($today_status['check_in'] && !$today_status['check_out']): ?>
    <button class="quick-btn btn-checkout" onclick="checkOut()">
        <i class="fas fa-sign-out-alt"></i>
        <span>Check Out</span>
    </button>
    <?php endif; ?>
    
    <a href="apply_leave.php" class="quick-btn btn-leave">
        <i class="fas fa-plane"></i>
        <span>Apply Leave</span>
    </a>
    
    <a href="attendance.php" class="quick-btn btn-attendance">
        <i class="fas fa-calendar-alt"></i>
        <span>View Attendance</span>
    </a>
    
    <a href="salary.php" class="quick-btn btn-salary">
        <i class="fas fa-money-bill"></i>
        <span>Salary Details</span>
    </a>
</div>

<div class="stats-grid">
    <div class="stat-card stat-red">
        <div class="stat-icon">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $attendance_percentage; ?>%</h3>
            <p>Attendance Rate</p>
        </div>
        <div class="stat-progress">
            <div class="progress-bar" style="width: <?php echo $attendance_percentage; ?>%"></div>
        </div>
    </div>
    
    <div class="stat-card stat-orange">
        <div class="stat-icon">
            <i class="fas fa-plane"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $employee['leaves_taken']; ?></h3>
            <p>Leaves This Month</p>
        </div>
    </div>
    
    <div class="stat-card stat-yellow">
        <div class="stat-icon">
            <i class="fas fa-bullseye"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $performance ? $performance['overall_score'] : 'N/A'; ?></h3>
            <p>Performance Score</p>
        </div>
    </div>
    
    <div class="stat-card stat-red">
        <div class="stat-icon">
            <i class="fas fa-money-bill-wave"></i>
        </div>
        <div class="stat-info">
            <h3>$<?php echo number_format($employee['salary'], 2); ?></h3>
            <p>Monthly Salary</p>
        </div>
    </div>
</div>

<div class="content-grid">
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-plane"></i> My Recent Leaves</h3>
            <a href="apply_leave.php" class="btn-view-all">Apply New</a>
        </div>
        <div class="card-body">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Dates</th>
                        <th>Status</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($my_leaves as $leave): ?>
                    <tr>
                        <td>
                            <span class="badge badge-<?php echo $leave['leave_type']; ?>">
                                <?php echo ucfirst($leave['leave_type']); ?>
                            </span>
                        </td>
                        <td>
                            <?php echo date('M d', strtotime($leave['start_date'])); ?> - 
                            <?php echo date('M d', strtotime($leave['end_date'])); ?>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $leave['status']; ?>">
                                <?php echo ucfirst($leave['status']); ?>
                            </span>
                        </td>
                        <td><?php echo substr($leave['remarks'], 0, 30) . '...'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-chart-line"></i> Today's Status</h3>
        </div>
        <div class="card-body">
            <div class="attendance-status">
                <div class="status-item">
                    <div class="status-label">Check In Time:</div>
                    <div class="status-value">
                        <?php echo $today_status && $today_status['check_in'] 
                            ? date('h:i A', strtotime($today_status['check_in'])) 
                            : '--:--'; ?>
                    </div>
                </div>
                <div class="status-item">
                    <div class="status-label">Check Out Time:</div>
                    <div class="status-value">
                        <?php echo $today_status && $today_status['check_out'] 
                            ? date('h:i A', strtotime($today_status['check_out'])) 
                            : '--:--'; ?>
                    </div>
                </div>
                <div class="status-item">
                    <div class="status-label">Hours Worked:</div>
                    <div class="status-value">
                        <?php if($today_status && $today_status['check_in'] && $today_status['check_out']): 
                            $diff = strtotime($today_status['check_out']) - strtotime($today_status['check_in']);
                            $hours = floor($diff / 3600);
                            $minutes = floor(($diff % 3600) / 60);
                            echo $hours . 'h ' . $minutes . 'm';
                        else:
                            echo '--:--';
                        endif; ?>
                    </div>
                </div>
                <div class="status-item">
                    <div class="status-label">Current Status:</div>
                    <div class="status-value">
                        <span class="badge badge-<?php echo $today_status ? strtolower($today_status['status']) : 'absent'; ?>">
                            <?php echo $today_status ? ucfirst($today_status['status']) : 'Absent'; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function checkIn() {
    fetch('check_in_out.php?action=checkin')
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                alert('Checked in successfully at ' + data.time);
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
}

function checkOut() {
    if(confirm('Are you sure you want to check out?')) {
        fetch('check_in_out.php?action=checkout')
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    alert('Checked out successfully at ' + data.time);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>