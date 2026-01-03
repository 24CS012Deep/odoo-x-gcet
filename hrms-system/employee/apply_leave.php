<?php
$page_title = "Apply Leave";
require_once '../includes/header.php';
require_once '../config/database.php';

$user_id = $_SESSION['user_id'];
$conn = getConnection();

// Get remaining leave balance
$leave_balance = [
    'paid' => 15, // Default annual leave
    'sick' => 10, // Default sick leave
    'unpaid' => 999, // Unlimited
    'annual' => 0
];

// Calculate used leaves this year
$current_year = date('Y');
$stmt = $conn->prepare("
    SELECT leave_type, SUM(DATEDIFF(end_date, start_date) + 1) as days 
    FROM leave_requests 
    WHERE user_id = ? 
    AND YEAR(start_date) = ? 
    AND status = 'approved'
    GROUP BY leave_type
");
$stmt->bind_param("ii", $user_id, $current_year);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    if (isset($leave_balance[$row['leave_type']])) {
        $leave_balance[$row['leave_type']] -= $row['days'];
    }
}
$stmt->close();

// Handle leave application
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $leave_type = $_POST['leave_type'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $remarks = $_POST['remarks'];
    
    // Validate dates
    if (strtotime($end_date) < strtotime($start_date)) {
        $error_message = "End date cannot be earlier than start date.";
    } else {
        // Check for overlapping leave requests
        $check_stmt = $conn->prepare("
            SELECT id FROM leave_requests 
            WHERE user_id = ? 
            AND status != 'rejected'
            AND (
                (start_date BETWEEN ? AND ?)
                OR (end_date BETWEEN ? AND ?)
                OR (? BETWEEN start_date AND end_date)
            )
        ");
        $check_stmt->bind_param("isssss", $user_id, $start_date, $end_date, $start_date, $end_date, $start_date);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error_message = "You already have a leave request for these dates.";
        } else {
            // Calculate number of days
            $start = new DateTime($start_date);
            $end = new DateTime($end_date);
            $interval = $start->diff($end);
            $days_requested = $interval->days + 1; // Include both start and end dates
            
            // Check leave balance
            if ($leave_balance[$leave_type] < $days_requested && $leave_type != 'unpaid') {
                $error_message = "Insufficient " . $leave_type . " leave balance. Available: " . $leave_balance[$leave_type] . " days";
            } else {
                // Insert leave request
                $insert_stmt = $conn->prepare("
                    INSERT INTO leave_requests (user_id, leave_type, start_date, end_date, remarks, status) 
                    VALUES (?, ?, ?, ?, ?, 'pending')
                ");
                $insert_stmt->bind_param("issss", $user_id, $leave_type, $start_date, $end_date, $remarks);
                
                if ($insert_stmt->execute()) {
                    $success_message = "Leave application submitted successfully! It will be reviewed by HR.";
                    
                    // Send notification to admin (in real system, this would be email or push notification)
                    $notify_stmt = $conn->prepare("
                        INSERT INTO notifications (user_id, title, message, type, status) 
                        VALUES (?, 'New Leave Request', ?, 'leave', 'unread')
                    ");
                    $message = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'] . ' has applied for ' . $leave_type . ' leave from ' . $start_date . ' to ' . $end_date;
                    $notify_stmt->bind_param("is", $user_id, $message);
                    $notify_stmt->execute();
                    $notify_stmt->close();
                    
                    // Reset form
                    $_POST = [];
                } else {
                    $error_message = "Failed to submit leave application. Please try again.";
                }
                $insert_stmt->close();
            }
        }
        $check_stmt->close();
    }
}

$conn->close();
?>

<div class="page-header">
    <h2><i class="fas fa-plane"></i> Apply for Leave</h2>
    <a href="dashboard.php" class="btn-back">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
</div>

<!-- Leave Balance Overview -->
<div class="balance-cards">
    <h3><i class="fas fa-chart-pie"></i> Your Leave Balance</h3>
    <div class="cards-grid">
        <div class="balance-card paid">
            <div class="balance-icon">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="balance-info">
                <h4>Paid Leave</h4>
                <div class="balance-days">
                    <span class="current"><?php echo max(0, $leave_balance['paid']); ?></span>
                    <span class="total">/ 15 days</span>
                </div>
                <div class="balance-bar">
                    <div class="balance-fill" style="width: <?php echo ($leave_balance['paid'] / 15) * 100; ?>%"></div>
                </div>
            </div>
        </div>
        
        <div class="balance-card sick">
            <div class="balance-icon">
                <i class="fas fa-heartbeat"></i>
            </div>
            <div class="balance-info">
                <h4>Sick Leave</h4>
                <div class="balance-days">
                    <span class="current"><?php echo max(0, $leave_balance['sick']); ?></span>
                    <span class="total">/ 10 days</span>
                </div>
                <div class="balance-bar">
                    <div class="balance-fill" style="width: <?php echo ($leave_balance['sick'] / 10) * 100; ?>%"></div>
                </div>
            </div>
        </div>
        
        <div class="balance-card unpaid">
            <div class="balance-icon">
                <i class="fas fa-calendar-times"></i>
            </div>
            <div class="balance-info">
                <h4>Unpaid Leave</h4>
                <div class="balance-days">
                    <span class="current">Unlimited</span>
                </div>
                <div class="balance-bar">
                    <div class="balance-fill" style="width: 100%"></div>
                </div>
            </div>
        </div>
        
        <div class="balance-card annual">
            <div class="balance-icon">
                <i class="fas fa-umbrella-beach"></i>
            </div>
            <div class="balance-info">
                <h4>Annual Leave</h4>
                <div class="balance-days">
                    <span class="current"><?php echo max(0, $leave_balance['annual']); ?></span>
                    <span class="total">/ 0 days</span>
                </div>
                <div class="balance-bar">
                    <div class="balance-fill" style="width: 0%"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="application-container">
    <div class="application-form-container">
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
        
        <form method="POST" class="application-form" id="leaveForm">
            <div class="form-section">
                <h3><i class="fas fa-file-signature"></i> Leave Details</h3>
                
                <div class="form-group">
                    <label for="leave_type">Leave Type *</label>
                    <select id="leave_type" name="leave_type" required class="form-control">
                        <option value="">Select Leave Type</option>
                        <option value="paid" <?php echo isset($_POST['leave_type']) && $_POST['leave_type'] == 'paid' ? 'selected' : ''; ?>>
                            Paid Leave (Balance: <?php echo $leave_balance['paid']; ?> days)
                        </option>
                        <option value="sick" <?php echo isset($_POST['leave_type']) && $_POST['leave_type'] == 'sick' ? 'selected' : ''; ?>>
                            Sick Leave (Balance: <?php echo $leave_balance['sick']; ?> days)
                        </option>
                        <option value="unpaid" <?php echo isset($_POST['leave_type']) && $_POST['leave_type'] == 'unpaid' ? 'selected' : ''; ?>>
                            Unpaid Leave
                        </option>
                        <option value="annual" <?php echo isset($_POST['leave_type']) && $_POST['leave_type'] == 'annual' ? 'selected' : ''; ?>>
                            Annual Leave (Balance: <?php echo $leave_balance['annual']; ?> days)
                        </option>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="start_date">Start Date *</label>
                        <input type="date" 
                               id="start_date" 
                               name="start_date" 
                               value="<?php echo $_POST['start_date'] ?? ''; ?>"
                               min="<?php echo date('Y-m-d'); ?>"
                               required
                               class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="end_date">End Date *</label>
                        <input type="date" 
                               id="end_date" 
                               name="end_date" 
                               value="<?php echo $_POST['end_date'] ?? ''; ?>"
                               min="<?php echo date('Y-m-d'); ?>"
                               required
                               class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="days_count">Number of Days</label>
                    <input type="text" 
                           id="days_count" 
                           value="0"
                           disabled
                           class="form-control">
                    <small class="form-help">Calculated automatically based on dates</small>
                </div>
                
                <div class="form-group">
                    <label for="remarks">Remarks / Reason *</label>
                    <textarea id="remarks" 
                              name="remarks" 
                              rows="4"
                              required
                              placeholder="Please provide details about your leave request..."
                              class="form-control"><?php echo $_POST['remarks'] ?? ''; ?></textarea>
                    <small class="form-help">Please be specific about the reason for your leave</small>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-paper-plane"></i> Submit Application
                </button>
                <button type="reset" class="btn btn-secondary">
                    <i class="fas fa-undo"></i> Reset Form
                </button>
            </div>
        </form>
    </div>
    
    <div class="application-info">
        <div class="info-card">
            <h3><i class="fas fa-lightbulb"></i> Tips & Guidelines</h3>
            <ul class="guidelines">
                <li>
                    <i class="fas fa-clock"></i>
                    <div>
                        <strong>Apply in Advance</strong>
                        <p>Submit leave requests at least 3 working days in advance for proper planning.</p>
                    </div>
                </li>
                <li>
                    <i class="fas fa-file-medical"></i>
                    <div>
                        <strong>Sick Leave</strong>
                        <p>For sick leave exceeding 3 days, a medical certificate is required.</p>
                    </div>
                </li>
                <li>
                    <i class="fas fa-calendar-alt"></i>
                    <div>
                        <strong>Holiday Consideration</strong>
                        <p>Weekends and public holidays are not counted as leave days.</p>
                    </div>
                </li>
                <li>
                    <i class="fas fa-envelope"></i>
                    <div>
                        <strong>Notification</strong>
                        <p>You'll receive email notifications about your leave status updates.</p>
                    </div>
                </li>
            </ul>
        </div>
        
        <div class="info-card">
            <h3><i class="fas fa-history"></i> Recent Applications</h3>
            <div class="recent-applications">
                <?php
                $conn = getConnection();
                $recent_stmt = $conn->prepare("
                    SELECT * FROM leave_requests 
                    WHERE user_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT 5
                ");
                $recent_stmt->bind_param("i", $user_id);
                $recent_stmt->execute();
                $recent_result = $recent_stmt->get_result();
                
                if ($recent_result->num_rows > 0) {
                    while ($app = $recent_result->fetch_assoc()) {
                        echo '<div class="recent-item status-' . $app['status'] . '">';
                        echo '<div class="recent-dates">';
                        echo '<span class="date-range">' . date('M d', strtotime($app['start_date'])) . ' - ' . date('M d', strtotime($app['end_date'])) . '</span>';
                        echo '<span class="leave-type">' . ucfirst($app['leave_type']) . '</span>';
                        echo '</div>';
                        echo '<div class="recent-status">';
                        echo '<span class="badge badge-' . $app['status'] . '">' . ucfirst($app['status']) . '</span>';
                        echo '</div>';
                        echo '</div>';
                    }
                } else {
                    echo '<p class="no-data">No recent applications found.</p>';
                }
                $recent_stmt->close();
                $conn->close();
                ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    const daysCount = document.getElementById('days_count');
    const leaveType = document.getElementById('leave_type');
    
    function calculateDays() {
        if (startDate.value && endDate.value) {
            const start = new Date(startDate.value);
            const end = new Date(endDate.value);
            
            // Calculate difference in days
            const timeDiff = end.getTime() - start.getTime();
            const dayDiff = Math.ceil(timeDiff / (1000 * 3600 * 24)) + 1; // Include both days
            
            daysCount.value = dayDiff + ' day' + (dayDiff !== 1 ? 's' : '');
            
            // Update end date min to be at least start date
            endDate.min = startDate.value;
            
            // Warn if leave exceeds balance
            if (leaveType.value && leaveType.value !== 'unpaid') {
                const balance = {
                    'paid': <?php echo $leave_balance['paid']; ?>,
                    'sick': <?php echo $leave_balance['sick']; ?>,
                    'annual': <?php echo $leave_balance['annual']; ?>
                };
                
                if (balance[leaveType.value] < dayDiff) {
                    daysCount.style.borderColor = '#ff6b6b';
                    daysCount.style.backgroundColor = '#fff5f5';
                    showWarning(`Insufficient ${leaveType.value} leave balance. Available: ${balance[leaveType.value]} days`);
                } else {
                    daysCount.style.borderColor = '';
                    daysCount.style.backgroundColor = '';
                    hideWarning();
                }
            }
        }
    }
    
    function showWarning(message) {
        let warning = document.getElementById('balance-warning');
        if (!warning) {
            warning = document.createElement('div');
            warning.id = 'balance-warning';
            warning.className = 'alert alert-warning';
            warning.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${message}`;
            daysCount.parentNode.appendChild(warning);
        } else {
            warning.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${message}`;
        }
    }
    
    function hideWarning() {
        const warning = document.getElementById('balance-warning');
        if (warning) {
            warning.remove();
        }
    }
    
    startDate.addEventListener('change', calculateDays);
    endDate.addEventListener('change', calculateDays);
    leaveType.addEventListener('change', calculateDays);
    
    // Form validation
    const leaveForm = document.getElementById('leaveForm');
    leaveForm.addEventListener('submit', function(e) {
        if (!startDate.value || !endDate.value || !leaveType.value) {
            e.preventDefault();
            HRMS.utils.showToast('Please fill all required fields', 'error');
            return;
        }
        
        if (new Date(endDate.value) < new Date(startDate.value)) {
            e.preventDefault();
            HRMS.utils.showToast('End date cannot be earlier than start date', 'error');
            return;
        }
        
        // Confirm submission
        if (!confirm('Submit this leave application?')) {
            e.preventDefault();
        }
    });
    
    // Set min dates to today
    const today = new Date().toISOString().split('T')[0];
    startDate.min = today;
    endDate.min = today;
    
    // Initialize with today's date if empty
    if (!startDate.value) {
        startDate.value = today;
    }
    if (!endDate.value) {
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        endDate.value = tomorrow.toISOString().split('T')[0];
    }
    
    // Initial calculation
    calculateDays();
});
</script>

<style>
.balance-cards {
    margin-bottom: 30px;
}

.balance-cards h3 {
    margin-bottom: 20px;
    color: #ff6b6b;
    display: flex;
    align-items: center;
    gap: 10px;
}

.cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.balance-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    display: flex;
    align-items: center;
    gap: 20px;
    transition: transform 0.3s;
}

.balance-card:hover {
    transform: translateY(-5px);
}

.balance-card.paid {
    border-top: 5px solid #28a745;
}

.balance-card.sick {
    border-top: 5px solid #17a2b8;
}

.balance-card.unpaid {
    border-top: 5px solid #ffc107;
}

.balance-card.annual {
    border-top: 5px solid #6c757d;
}

.balance-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8em;
    color: white;
}

.balance-card.paid .balance-icon { background: #28a745; }
.balance-card.sick .balance-icon { background: #17a2b8; }
.balance-card.unpaid .balance-icon { background: #ffc107; }
.balance-card.annual .balance-icon { background: #6c757d; }

.balance-info h4 {
    margin: 0 0 10px 0;
    color: #333;
    font-size: 1.1em;
}

.balance-days {
    display: flex;
    align-items: baseline;
    gap: 5px;
    margin-bottom: 10px;
}

.balance-days .current {
    font-size: 2em;
    font-weight: 700;
    color: #333;
}

.balance-days .total {
    color: #666;
    font-size: 0.9em;
}

.balance-bar {
    height: 8px;
    background: #f0f0f0;
    border-radius: 4px;
    overflow: hidden;
}

.balance-fill {
    height: 100%;
    background: linear-gradient(90deg, #ff6b6b, #ffa500);
    border-radius: 4px;
    transition: width 1s ease;
}

.application-container {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 30px;
}

.application-form-container {
    background: white;
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

.application-info {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.info-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

.info-card h3 {
    margin-bottom: 20px;
    color: #ff6b6b;
    display: flex;
    align-items: center;
    gap: 10px;
}

.guidelines {
    list-style: none;
    padding: 0;
}

.guidelines li {
    display: flex;
    gap: 15px;
    padding: 15px 0;
    border-bottom: 1px solid #f0f0f0;
}

.guidelines li:last-child {
    border-bottom: none;
}

.guidelines li i {
    color: #ffa500;
    font-size: 1.2em;
    margin-top: 3px;
}

.guidelines li strong {
    display: block;
    margin-bottom: 5px;
    color: #333;
}

.guidelines li p {
    margin: 0;
    color: #666;
    font-size: 0.9em;
}

.recent-applications {
    max-height: 300px;
    overflow-y: auto;
}

.recent-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 15px;
    margin-bottom: 10px;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 4px solid #ddd;
}

.recent-item.status-approved {
    border-left-color: #28a745;
    background: #f0fff0;
}

.recent-item.status-pending {
    border-left-color: #ffc107;
    background: #fffdf0;
}

.recent-item.status-rejected {
    border-left-color: #dc3545;
    background: #fff5f5;
}

.recent-dates {
    display: flex;
    flex-direction: column;
}

.date-range {
    font-weight: 600;
    color: #333;
}

.leave-type {
    font-size: 0.85em;
    color: #666;
}

.no-data {
    text-align: center;
    color: #666;
    font-style: italic;
    padding: 20px;
}

.btn-back {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #ff6b6b;
    text-decoration: none;
    font-weight: 500;
    padding: 8px 15px;
    border: 2px solid #ffe6e6;
    border-radius: 8px;
    transition: all 0.3s;
}

.btn-back:hover {
    background: #ffe6e6;
    transform: translateX(-5px);
}

.btn-lg {
    padding: 15px 40px;
    font-size: 1.1em;
}

.alert-warning {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
    padding: 10px 15px;
    border-radius: 6px;
    margin-top: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.9em;
}

@media (max-width: 1024px) {
    .application-container {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .cards-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>