<?php
$page_title = "Admin Dashboard";
require_once '../includes/header.php';
require_once '../config/database.php';

$conn = getConnection();

// Get statistics
$total_employees = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'employee'")->fetch_assoc()['count'];
$present_today = $conn->query("SELECT COUNT(DISTINCT user_id) as count FROM attendance WHERE date = CURDATE() AND status = 'present'")->fetch_assoc()['count'];
$pending_leaves = $conn->query("SELECT COUNT(*) as count FROM leave_requests WHERE status = 'pending'")->fetch_assoc()['count'];
$total_payroll = $conn->query("SELECT SUM(salary) as total FROM users")->fetch_assoc()['total'];

// Recent leave requests
$recent_leaves = $conn->query("
    SELECT l.*, u.first_name, u.last_name 
    FROM leave_requests l 
    JOIN users u ON l.user_id = u.id 
    ORDER BY l.created_at DESC 
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Recent activities
$recent_activities = $conn->query("
    SELECT 'check_in' as type, u.first_name, u.last_name, a.check_in as time 
    FROM attendance a 
    JOIN users u ON a.user_id = u.id 
    WHERE a.check_in IS NOT NULL 
    ORDER BY a.check_in DESC 
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>

<div class="dashboard-header">
    <h2><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h2>
    <div class="date-info">
        <?php echo date('l, F j, Y'); ?>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card stat-red">
        <div class="stat-icon">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $total_employees; ?></h3>
            <p>Total Employees</p>
        </div>
    </div>
    
    <div class="stat-card stat-orange">
        <div class="stat-icon">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $present_today; ?></h3>
            <p>Present Today</p>
        </div>
    </div>
    
    <div class="stat-card stat-yellow">
        <div class="stat-icon">
            <i class="fas fa-plane"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $pending_leaves; ?></h3>
            <p>Pending Leaves</p>
        </div>
    </div>
    
    <div class="stat-card stat-red">
        <div class="stat-icon">
            <i class="fas fa-money-bill-wave"></i>
        </div>
        <div class="stat-info">
            <h3>$<?php echo number_format($total_payroll, 2); ?></h3>
            <p>Monthly Payroll</p>
        </div>
    </div>
</div>

<div class="content-grid">
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-plane"></i> Recent Leave Requests</h3>
            <a href="leave_requests.php" class="btn-view-all">View All</a>
        </div>
        <div class="card-body">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Leave Type</th>
                        <th>Dates</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($recent_leaves as $leave): ?>
                    <tr>
                        <td><?php echo $leave['first_name'] . ' ' . $leave['last_name']; ?></td>
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
                        <td>
                            <a href="leave_requests.php?action=view&id=<?php echo $leave['id']; ?>" 
                               class="btn-action btn-view">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> Recent Activities</h3>
        </div>
        <div class="card-body">
            <div class="activity-list">
                <?php foreach($recent_activities as $activity): ?>
                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="fas fa-sign-in-alt"></i>
                    </div>
                    <div class="activity-content">
                        <p>
                            <strong><?php echo $activity['first_name'] . ' ' . $activity['last_name']; ?></strong>
                            checked in
                        </p>
                        <span class="activity-time">
                            <?php echo date('h:i A', strtotime($activity['time'])); ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>