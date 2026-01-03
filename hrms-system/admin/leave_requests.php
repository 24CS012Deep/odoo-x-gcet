<?php
$page_title = "Leave Requests";
require_once '../includes/header.php';
require_once '../config/database.php';

// Check if user is admin
if ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'hr') {
    header('Location: ../employee/dashboard.php');
    exit();
}

$conn = getConnection();

// Handle leave approval/rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $leave_id = intval($_POST['leave_id'] ?? 0);
    $admin_comments = $_POST['admin_comments'] ?? '';
    
    if ($leave_id > 0 && in_array($action, ['approve', 'reject'])) {
        $status = $action == 'approve' ? 'approved' : 'rejected';
        
        $stmt = $conn->prepare("
            UPDATE leave_requests 
            SET status = ?, approved_by = ?, admin_comments = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->bind_param("sisi", $status, $_SESSION['user_id'], $admin_comments, $leave_id);
        
        if ($stmt->execute()) {
            // Get leave details for notification
            $leave_stmt = $conn->prepare("
                SELECT user_id, leave_type, start_date, end_date 
                FROM leave_requests 
                WHERE id = ?
            ");
            $leave_stmt->bind_param("i", $leave_id);
            $leave_stmt->execute();
            $leave_result = $leave_stmt->get_result()->fetch_assoc();
            $leave_stmt->close();
            
            if ($leave_result) {
                // Update attendance records if approved
                if ($status == 'approved') {
                    $start = new DateTime($leave_result['start_date']);
                    $end = new DateTime($leave_result['end_date']);
                    $interval = $start->diff($end);
                    
                    // Mark each day as leave in attendance
                    for ($i = 0; $i <= $interval->days; $i++) {
                        $current_date = clone $start;
                        $current_date->add(new DateInterval("P{$i}D"));
                        $date_str = $current_date->format('Y-m-d');
                        
                        // Check if attendance record exists
                        $check_stmt = $conn->prepare("
                            SELECT id FROM attendance 
                            WHERE user_id = ? AND date = ?
                        ");
                        $check_stmt->bind_param("is", $leave_result['user_id'], $date_str);
                        $check_stmt->execute();
                        $check_result = $check_stmt->get_result();
                        $check_stmt->close();
                        
                        if ($check_result->num_rows > 0) {
                            // Update existing record
                            $update_att_stmt = $conn->prepare("
                                UPDATE attendance 
                                SET status = 'leave' 
                                WHERE user_id = ? AND date = ?
                            ");
                            $update_att_stmt->bind_param("is", $leave_result['user_id'], $date_str);
                            $update_att_stmt->execute();
                            $update_att_stmt->close();
                        } else {
                            // Insert new record
                            $insert_att_stmt = $conn->prepare("
                                INSERT INTO attendance (user_id, date, status) 
                                VALUES (?, ?, 'leave')
                            ");
                            $insert_att_stmt->bind_param("is", $leave_result['user_id'], $date_str);
                            $insert_att_stmt->execute();
                            $insert_att_stmt->close();
                        }
                    }
                }
                
                $success_message = "Leave request $status successfully!";
            }
        } else {
            $error_message = "Failed to update leave request.";
        }
        $stmt->close();
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$type_filter = $_GET['type'] ?? 'all';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query
$query = "
    SELECT l.*, 
           u.first_name, 
           u.last_name, 
           u.employee_id,
           u.email,
           u.job_title,
           a.first_name as approved_first_name,
           a.last_name as approved_last_name
    FROM leave_requests l
    JOIN users u ON l.user_id = u.id
    LEFT JOIN users a ON l.approved_by = a.id
    WHERE 1=1
";

$params = [];
$types = "";

if ($status_filter != 'all') {
    $query .= " AND l.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($type_filter != 'all') {
    $query .= " AND l.leave_type = ?";
    $params[] = $type_filter;
    $types .= "s";
}

if (!empty($date_from)) {
    $query .= " AND l.start_date >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if (!empty($date_to)) {
    $query .= " AND l.end_date <= ?";
    $params[] = $date_to;
    $types .= "s";
}

$query .= " ORDER BY l.created_at DESC";

// Execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$leave_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get statistics
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM leave_requests
");
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

$conn->close();
?>

<div class="page-header">
    <h2><i class="fas fa-plane"></i> Leave Requests Management</h2>
    <div class="header-stats">
        <span class="stat pending">
            <i class="fas fa-clock"></i>
            <strong><?php echo $stats['pending']; ?></strong> Pending
        </span>
        <span class="stat approved">
            <i class="fas fa-check-circle"></i>
            <strong><?php echo $stats['approved']; ?></strong> Approved
        </span>
        <span class="stat rejected">
            <i class="fas fa-times-circle"></i>
            <strong><?php echo $stats['rejected']; ?></strong> Rejected
        </span>
    </div>
</div>

<?php if(isset($success_message)): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
</div>
<?php endif; ?>

<?php if(isset($error_message)): ?>
<div class="alert alert-error">
    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="filters-card">
    <h3><i class="fas fa-filter"></i> Filters</h3>
    <form method="GET" class="filter-form">
        <div class="filter-row">
            <div class="filter-group">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Leave Type</label>
                <select name="type" class="form-control">
                    <option value="all" <?php echo $type_filter == 'all' ? 'selected' : ''; ?>>All Types</option>
                    <option value="paid" <?php echo $type_filter == 'paid' ? 'selected' : ''; ?>>Paid Leave</option>
                    <option value="sick" <?php echo $type_filter == 'sick' ? 'selected' : ''; ?>>Sick Leave</option>
                    <option value="unpaid" <?php echo $type_filter == 'unpaid' ? 'selected' : ''; ?>>Unpaid Leave</option>
                    <option value="annual" <?php echo $type_filter == 'annual' ? 'selected' : ''; ?>>Annual Leave</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Date From</label>
                <input type="date" 
                       name="date_from" 
                       value="<?php echo $date_from; ?>" 
                       class="form-control">
            </div>
            
            <div class="filter-group">
                <label>Date To</label>
                <input type="date" 
                       name="date_to" 
                       value="<?php echo $date_to; ?>" 
                       class="form-control">
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Apply Filters
                </button>
                <a href="leave_requests.php" class="btn btn-secondary">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
        </div>
    </form>
</div>

<!-- Leave Requests Table -->
<div class="content-card">
    <div class="card-header">
        <h3><i class="fas fa-list"></i> Leave Requests (<?php echo count($leave_requests); ?>)</h3>
        <div class="export-options">
            <button class="btn-export">
                <i class="fas fa-file-export"></i> Export
            </button>
        </div>
    </div>
    <div class="card-body">
        <?php if(count($leave_requests) > 0): ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Type</th>
                        <th>Dates</th>
                        <th>Days</th>
                        <th>Status</th>
                        <th>Applied On</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($leave_requests as $request): 
                        $start_date = new DateTime($request['start_date']);
                        $end_date = new DateTime($request['end_date']);
                        $interval = $start_date->diff($end_date);
                        $days = $interval->days + 1;
                    ?>
                    <tr class="status-<?php echo $request['status']; ?>">
                        <td>
                            <div class="employee-info">
                                <div class="employee-name">
                                    <strong><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></strong>
                                </div>
                                <div class="employee-details">
                                    <span class="emp-id"><?php echo htmlspecialchars($request['employee_id']); ?></span>
                                    <span class="emp-title"><?php echo htmlspecialchars($request['job_title']); ?></span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $request['leave_type']; ?>">
                                <?php echo ucfirst($request['leave_type']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="date-range">
                                <?php echo $start_date->format('M d'); ?> - <?php echo $end_date->format('M d, Y'); ?>
                            </div>
                            <div class="date-details">
                                <?php echo $days; ?> day<?php echo $days != 1 ? 's' : ''; ?>
                            </div>
                        </td>
                        <td><?php echo $days; ?></td>
                        <td>
                            <span class="badge badge-<?php echo $request['status']; ?>">
                                <?php echo ucfirst($request['status']); ?>
                            </span>
                            <?php if($request['approved_first_name']): ?>
                            <div class="approved-by">
                                by <?php echo $request['approved_first_name'] . ' ' . $request['approved_last_name']; ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo date('M d, Y', strtotime($request['created_at'])); ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-action btn-view" 
                                        onclick="viewLeaveDetails(<?php echo $request['id']; ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                
                                <?php if($request['status'] == 'pending'): ?>
                                <button class="btn-action btn-approve" 
                                        onclick="approveLeave(<?php echo $request['id']; ?>)">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button class="btn-action btn-reject" 
                                        onclick="rejectLeave(<?php echo $request['id']; ?>)">
                                    <i class="fas fa-times"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="no-data">
            <i class="fas fa-inbox"></i>
            <h4>No Leave Requests Found</h4>
            <p>No leave requests match your current filters.</p>
        </div>
        <?php endif; ?>
        
        <!-- Pagination -->
        <div class="pagination">
            <button class="page-btn" disabled>
                <i class="fas fa-chevron-left"></i>
            </button>
            <span class="page-info">Page 1 of 1</span>
            <button class="page-btn">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    </div>
</div>

<!-- Leave Details Modal -->
<div class="modal" id="leaveDetailsModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Leave Request Details</h3>
            <button class="modal-close" onclick="closeModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body" id="leaveDetailsContent">
            <!-- Content will be loaded via AJAX -->
        </div>
    </div>
</div>

<script>
// View leave details
function viewLeaveDetails(leaveId) {
    fetch(`get_leave_details.php?id=${leaveId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('leaveDetailsContent').innerHTML = html;
            document.getElementById('leaveDetailsModal').style.display = 'flex';
        })
        .catch(error => {
            console.error('Error:', error);
            HRMS.utils.showToast('Failed to load leave details', 'error');
        });
}

// Approve leave
function approveLeave(leaveId) {
    if (confirm('Are you sure you want to approve this leave request?')) {
        const formData = new FormData();
        formData.append('action', 'approve');
        formData.append('leave_id', leaveId);
        formData.append('admin_comments', 'Leave approved by HR');
        
        fetch('leave_requests.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(() => {
            location.reload();
        })
        .catch(error => {
            console.error('Error:', error);
            HRMS.utils.showToast('Failed to approve leave', 'error');
        });
    }
}

// Reject leave
function rejectLeave(leaveId) {
    const comment = prompt('Please enter reason for rejection:');
    if (comment !== null && comment.trim() !== '') {
        const formData = new FormData();
        formData.append('action', 'reject');
        formData.append('leave_id', leaveId);
        formData.append('admin_comments', comment);
        
        fetch('leave_requests.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(() => {
            location.reload();
        })
        .catch(error => {
            console.error('Error:', error);
            HRMS.utils.showToast('Failed to reject leave', 'error');
        });
    }
}

// Modal functions
function closeModal() {
    document.getElementById('leaveDetailsModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('leaveDetailsModal');
    if (event.target == modal) {
        closeModal();
    }
}

// Export functionality
document.querySelector('.btn-export').addEventListener('click', function() {
    HRMS.utils.showToast('Export feature will be available soon!', 'info');
});
</script>

<style>
.header-stats {
    display: flex;
    gap: 20px;
}

.header-stats .stat {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 8px;
    background: white;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.header-stats .stat i {
    font-size: 1.2em;
}

.header-stats .stat.pending i { color: #ffc107; }
.header-stats .stat.approved i { color: #28a745; }
.header-stats .stat.rejected i { color: #dc3545; }

.header-stats .stat strong {
    font-size: 1.2em;
    margin-right: 5px;
}

.filters-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

.filters-card h3 {
    margin-bottom: 20px;
    color: #ff6b6b;
    display: flex;
    align-items: center;
    gap: 10px;
}

.filter-form {
    width: 100%;
}

.filter-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.filter-group label {
    font-weight: 500;
    color: #555;
    font-size: 0.95em;
}

.filter-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

.table-responsive {
    overflow-x: auto;
}

.data-table {
    min-width: 1000px;
}

.data-table tr.status-pending {
    background: #fffdf0;
}

.data-table tr.status-approved {
    background: #f0fff0;
}

.data-table tr.status-rejected {
    background: #fff5f5;
}

.employee-info {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.employee-name {
    font-weight: 600;
    color: #333;
}

.employee-details {
    display: flex;
    gap: 10px;
    font-size: 0.9em;
    color: #666;
}

.emp-id {
    background: #f0f0f0;
    padding: 2px 8px;
    border-radius: 10px;
}

.emp-title {
    font-style: italic;
}

.date-range {
    font-weight: 500;
    color: #333;
}

.date-details {
    font-size: 0.9em;
    color: #666;
}

.approved-by {
    font-size: 0.85em;
    color: #666;
    margin-top: 3px;
    font-style: italic;
}

.action-buttons {
    display: flex;
    gap: 8px;
}

.btn-view {
    background: #17a2b8;
    color: white;
    border: none;
    width: 36px;
    height: 36px;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
}

.btn-approve {
    background: #28a745;
    color: white;
    border: none;
    width: 36px;
    height: 36px;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
}

.btn-reject {
    background: #dc3545;
    color: white;
    border: none;
    width: 36px;
    height: 36px;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
}

.btn-view:hover, .btn-approve:hover, .btn-reject:hover {
    transform: scale(1.1);
    box-shadow: 0 3px 10px rgba(0,0,0,0.2);
}

.no-data {
    text-align: center;
    padding: 60px 20px;
    color: #666;
}

.no-data i {
    font-size: 4em;
    color: #ddd;
    margin-bottom: 20px;
    display: block;
}

.no-data h4 {
    margin-bottom: 10px;
    color: #555;
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 20px;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 2px solid #f0f0f0;
}

.page-btn {
    width: 40px;
    height: 40px;
    border: 2px solid #ddd;
    border-radius: 8px;
    background: white;
    color: #666;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
}

.page-btn:not(:disabled):hover {
    border-color: #ffa500;
    color: #ff6b6b;
}

.page-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.page-info {
    color: #666;
    font-weight: 500;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 2000;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.modal-content {
    background: white;
    border-radius: 15px;
    width: 100%;
    max-width: 700px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    animation: modalSlide 0.3s ease;
}

@keyframes modalSlide {
    from {
        transform: translateY(-50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.modal-header {
    padding: 25px;
    border-bottom: 2px solid #f0f0f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    color: #ff6b6b;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5em;
    color: #666;
    cursor: pointer;
    padding: 5px;
    transition: color 0.3s;
}

.modal-close:hover {
    color: #ff6b6b;
}

.modal-body {
    padding: 25px;
}

.btn-export {
    padding: 10px 20px;
    background: linear-gradient(90deg, #ff6b6b, #ffa500);
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: transform 0.3s;
}

.btn-export:hover {
    transform: translateY(-2px);
}

@media (max-width: 768px) {
    .header-stats {
        flex-direction: column;
        gap: 10px;
    }
    
    .header-stats .stat {
        width: 100%;
        justify-content: center;
    }
    
    .filter-row {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .btn-view, .btn-approve, .btn-reject {
        width: 100%;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>