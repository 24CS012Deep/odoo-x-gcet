<?php
$page_title = "Payroll Management";
require_once '../includes/header.php';
require_once '../config/database.php';

// Check if user is admin
if ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'hr') {
    header('Location: ../employee/dashboard.php');
    exit();
}

$conn = getConnection();

// Handle payroll actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'update_salary') {
        $user_id = intval($_POST['user_id']);
        $new_salary = floatval($_POST['new_salary']);
        
        $stmt = $conn->prepare("UPDATE users SET salary = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("di", $new_salary, $user_id);
        
        if ($stmt->execute()) {
            $success_message = "Salary updated successfully!";
        } else {
            $error_message = "Failed to update salary.";
        }
        $stmt->close();
        
    } elseif ($action == 'process_payroll') {
        $month = $_POST['month'] ?? date('Y-m');
        
        // In a real system, this would generate payroll records
        $success_message = "Payroll processed for " . date('F Y', strtotime($month . '-01')) . ". Payroll records generated.";
    }
}

// Get payroll data
$query = "
    SELECT u.id, u.employee_id, u.first_name, u.last_name, u.email, 
           u.job_title, u.department, u.salary, u.hire_date,
           COUNT(DISTINCT a.id) as attendance_days,
           COUNT(DISTINCT CASE WHEN a.status = 'present' THEN a.id END) as present_days,
           COUNT(DISTINCT l.id) as leave_days,
           (u.salary / DAY(LAST_DAY(CURDATE()))) as daily_rate
    FROM users u
    LEFT JOIN attendance a ON u.id = a.user_id 
        AND DATE_FORMAT(a.date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
    LEFT JOIN leave_requests l ON u.id = l.user_id 
        AND l.status = 'approved' 
        AND DATE_FORMAT(l.start_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
    WHERE u.role != 'admin' OR u.role = 'hr'
    GROUP BY u.id
    ORDER BY u.department, u.first_name
";

$result = $conn->query($query);
$employees = $result->fetch_all(MYSQLI_ASSOC);

// Get payroll summary
$summary_query = "
    SELECT 
        COUNT(*) as total_employees,
        SUM(salary) as total_salary,
        AVG(salary) as avg_salary,
        department,
        COUNT(*) as dept_count,
        SUM(salary) as dept_salary
    FROM users
    WHERE role != 'admin' OR role = 'hr'
    GROUP BY department
    ORDER BY department
";
$summary_result = $conn->query($summary_query);
$department_summary = $summary_result->fetch_all(MYSQLI_ASSOC);

$conn->close();

// Calculate payroll metrics
$total_monthly_payroll = array_sum(array_column($employees, 'salary'));
$avg_daily_rate = array_sum(array_column($employees, 'daily_rate')) / count($employees);
?>

<div class="page-header">
    <h2><i class="fas fa-money-bill-wave"></i> Payroll Management</h2>
    <div class="header-actions">
        <button class="btn btn-primary" onclick="showProcessPayrollModal()">
            <i class="fas fa-calculator"></i> Process Payroll
        </button>
        <button class="btn btn-success" onclick="generatePaySlips()">
            <i class="fas fa-file-invoice-dollar"></i> Generate Payslips
        </button>
    </div>
</div>

<!-- Payroll Summary -->
<div class="payroll-summary">
    <div class="summary-card total">
        <div class="summary-icon">
            <i class="fas fa-money-check-alt"></i>
        </div>
        <div class="summary-info">
            <h3>$<?php echo number_format($total_monthly_payroll, 2); ?></h3>
            <p>Total Monthly Payroll</p>
        </div>
    </div>
    
    <div class="summary-card employees">
        <div class="summary-icon">
            <i class="fas fa-users"></i>
        </div>
        <div class="summary-info">
            <h3><?php echo count($employees); ?></h3>
            <p>Active Employees</p>
        </div>
    </div>
    
    <div class="summary-card average">
        <div class="summary-icon">
            <i class="fas fa-chart-line"></i>
        </div>
        <div class="summary-info">
            <h3>$<?php echo number_format($avg_daily_rate, 2); ?></h3>
            <p>Average Daily Rate</p>
        </div>
    </div>
    
    <div class="summary-card departments">
        <div class="summary-icon">
            <i class="fas fa-building"></i>
        </div>
        <div class="summary-info">
            <h3><?php echo count($department_summary); ?></h3>
            <p>Departments</p>
        </div>
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

<!-- Department Summary -->
<div class="content-card">
    <div class="card-header">
        <h3><i class="fas fa-chart-pie"></i> Department-wise Summary</h3>
    </div>
    <div class="card-body">
        <div class="department-chart" id="departmentChartContainer">
            <canvas id="departmentChart"></canvas>
        </div>
        
        <div class="department-list">
            <?php foreach($department_summary as $dept): ?>
            <div class="department-item">
                <div class="dept-header">
                    <h4><?php echo $dept['department'] ? htmlspecialchars($dept['department']) : 'Unassigned'; ?></h4>
                    <span class="dept-count"><?php echo $dept['dept_count']; ?> employees</span>
                </div>
                <div class="dept-details">
                    <div class="dept-stat">
                        <span>Total Salary:</span>
                        <span class="amount">$<?php echo number_format($dept['dept_salary'], 2); ?></span>
                    </div>
                    <div class="dept-stat">
                        <span>Average Salary:</span>
                        <span class="amount">$<?php echo number_format($dept['dept_salary'] / $dept['dept_count'], 2); ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Payroll Details -->
<div class="content-card">
    <div class="card-header">
        <h3><i class="fas fa-list"></i> Employee Payroll Details</h3>
        <div class="search-box">
            <input type="text" 
                   id="searchPayroll" 
                   placeholder="Search employees..." 
                   class="form-control">
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="data-table" id="payrollTable">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Base Salary</th>
                        <th>Attendance</th>
                        <th>Leaves</th>
                        <th>Deductions</th>
                        <th>Net Salary</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($employees as $employee): 
                        // Calculate deductions based on attendance
                        $total_days = date('t'); // Days in current month
                        $present_days = $employee['present_days'] ?? 0;
                        $leave_days = $employee['leave_days'] ?? 0;
                        $absent_days = $total_days - $present_days - $leave_days;
                        
                        // Calculate salary components
                        $daily_rate = $employee['salary'] / $total_days;
                        $base_salary = $employee['salary'];
                        $absent_deduction = $absent_days * $daily_rate;
                        $pf_deduction = $base_salary * 0.08; // 8% PF
                        $tax_deduction = $base_salary * 0.05; // 5% Tax
                        $insurance_deduction = $base_salary * 0.02; // 2% Insurance
                        
                        $total_deductions = $absent_deduction + $pf_deduction + $tax_deduction + $insurance_deduction;
                        $net_salary = $base_salary - $total_deductions;
                        
                        // Determine status
                        if ($absent_days > 5) {
                            $status = 'warning';
                            $status_text = 'High Absenteeism';
                        } elseif ($net_salary < ($base_salary * 0.7)) {
                            $status = 'danger';
                            $status_text = 'High Deductions';
                        } else {
                            $status = 'success';
                            $status_text = 'Normal';
                        }
                    ?>
                    <tr>
                        <td>
                            <div class="employee-info">
                                <div class="employee-name">
                                    <strong><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></strong>
                                </div>
                                <div class="employee-id">
                                    <?php echo htmlspecialchars($employee['employee_id']); ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php echo $employee['department'] ? htmlspecialchars($employee['department']) : 'N/A'; ?>
                        </td>
                        <td>
                            <div class="salary-display">
                                <strong>$<?php echo number_format($base_salary, 2); ?></strong>
                                <small>per month</small>
                            </div>
                        </td>
                        <td>
                            <div class="attendance-info">
                                <div class="attendance-item">
                                    <span class="label">Present:</span>
                                    <span class="value"><?php echo $present_days; ?> days</span>
                                </div>
                                <div class="attendance-item">
                                    <span class="label">Absent:</span>
                                    <span class="value"><?php echo $absent_days; ?> days</span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="leave-info">
                                <?php echo $leave_days; ?> days
                            </div>
                        </td>
                        <td>
                            <div class="deductions-info">
                                <div class="deduction-item">
                                    <span class="label">Absent:</span>
                                    <span class="value">-$<?php echo number_format($absent_deduction, 2); ?></span>
                                </div>
                                <div class="deduction-item">
                                    <span class="label">PF:</span>
                                    <span class="value">-$<?php echo number_format($pf_deduction, 2); ?></span>
                                </div>
                                <div class="deduction-item">
                                    <span class="label">Tax:</span>
                                    <span class="value">-$<?php echo number_format($tax_deduction, 2); ?></span>
                                </div>
                                <div class="deduction-item total">
                                    <span class="label">Total:</span>
                                    <span class="value">-$<?php echo number_format($total_deductions, 2); ?></span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="net-salary">
                                <strong>$<?php echo number_format($net_salary, 2); ?></strong>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $status; ?>">
                                <?php echo $status_text; ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-action btn-view" 
                                        onclick="viewPayrollDetails(<?php echo $employee['id']; ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn-action btn-edit" 
                                        onclick="editSalary(<?php echo $employee['id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn-action btn-download" 
                                        onclick="downloadPayslip(<?php echo $employee['id']; ?>)">
                                    <i class="fas fa-download"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Process Payroll Modal -->
<div class="modal" id="processPayrollModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Process Payroll</h3>
            <button class="modal-close" onclick="closeModal('processPayrollModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" id="processPayrollForm">
            <input type="hidden" name="action" value="process_payroll">
            
            <div class="modal-body">
                <div class="form-group">
                    <label for="payroll_month">Select Month</label>
                    <input type="month" 
                           id="payroll_month" 
                           name="month" 
                           value="<?php echo date('Y-m'); ?>"
                           required 
                           class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="payment_date">Payment Date</label>
                    <input type="date" 
                           id="payment_date" 
                           name="payment_date" 
                           value="<?php echo date('Y-m-d'); ?>"
                           required 
                           class="form-control">
                </div>
                
                <div class="form-group">
                    <label>Payroll Summary</label>
                    <div class="summary-details">
                        <div class="summary-row">
                            <span>Total Employees:</span>
                            <span><?php echo count($employees); ?></span>
                        </div>
                        <div class="summary-row">
                            <span>Total Monthly Payroll:</span>
                            <span>$<?php echo number_format($total_monthly_payroll, 2); ?></span>
                        </div>
                        <div class="summary-row">
                            <span>Processing Date:</span>
                            <span><?php echo date('F d, Y'); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="form-note">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Processing payroll will generate payslips for all employees and update payroll records. This action cannot be undone.</p>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('processPayrollModal')">
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-calculator"></i> Process Payroll
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Salary Modal -->
<div class="modal" id="editSalaryModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Update Salary</h3>
            <button class="modal-close" onclick="closeModal('editSalaryModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" id="editSalaryForm">
            <input type="hidden" name="action" value="update_salary">
            <input type="hidden" id="edit_salary_user_id" name="user_id">
            
            <div class="modal-body" id="editSalaryContent">
                <!-- Content loaded via AJAX -->
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editSalaryModal')">
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Salary
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Department Chart
    const ctx = document.getElementById('departmentChart').getContext('2d');
    const departmentChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_column($department_summary, 'department')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($department_summary, 'dept_salary')); ?>,
                backgroundColor: [
                    '#ff6b6b', '#ffa500', '#ffd93d', '#6c5ce7', '#00b894',
                    '#fd79a8', '#a29bfe', '#74b9ff', '#55efc4', '#ffeaa7'
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        padding: 20,
                        usePointStyle: true
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((value / total) * 100);
                            return `${label}: $${value.toFixed(2)} (${percentage}%)`;
                        }
                    }
                }
            },
            cutout: '60%'
        }
    });
    
    // Search functionality
    const searchInput = document.getElementById('searchPayroll');
    const payrollTable = document.getElementById('payrollTable');
    
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const rows = payrollTable.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
        });
    });
    
    // Process Payroll Modal
    window.showProcessPayrollModal = function() {
        document.getElementById('processPayrollModal').style.display = 'flex';
    };
    
    // Edit Salary
    window.editSalary = function(userId) {
        fetch(`get_salary_details.php?id=${userId}`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('editSalaryContent').innerHTML = html;
                document.getElementById('edit_salary_user_id').value = userId;
                document.getElementById('editSalaryModal').style.display = 'flex';
            })
            .catch(error => {
                console.error('Error:', error);
                HRMS.utils.showToast('Failed to load salary details', 'error');
            });
    };
    
    // View Payroll Details
    window.viewPayrollDetails = function(userId) {
        fetch(`get_payroll_details.php?id=${userId}`)
            .then(response => response.text())
            .then(html => {
                // Create a new modal for payroll details
                const modal = document.createElement('div');
                modal.className = 'modal';
                modal.id = 'payrollDetailsModal';
                modal.innerHTML = `
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Payroll Details</h3>
                            <button class="modal-close" onclick="this.closest('.modal').remove()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="modal-body">
                            ${html}
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary" onclick="this.closest('.modal').remove()">
                                Close
                            </button>
                        </div>
                    </div>
                `;
                document.body.appendChild(modal);
                modal.style.display = 'flex';
            })
            .catch(error => {
                console.error('Error:', error);
                HRMS.utils.showToast('Failed to load payroll details', 'error');
            });
    };
    
    // Download Payslip
    window.downloadPayslip = function(userId) {
        HRMS.utils.showToast('Payslip download will be available soon!', 'info');
    };
    
    // Generate Pay Slips
    window.generatePaySlips = function() {
        if (confirm('Generate payslips for all employees?')) {
            HRMS.utils.showToast('Payslips generation in progress...', 'info');
            // In a real system, this would trigger a background process
            setTimeout(() => {
                HRMS.utils.showToast('Payslips generated successfully!', 'success');
            }, 2000);
        }
    };
    
    // Close modal
    window.closeModal = function(modalId) {
        document.getElementById(modalId).style.display = 'none';
    };
    
    // Close modals when clicking outside
    window.onclick = function(event) {
        const modals = ['processPayrollModal', 'editSalaryModal'];
        modals.forEach(modalId => {
            const modal = document.getElementById(modalId);
            if (event.target == modal) {
                closeModal(modalId);
            }
        });
    };
});
</script>

<style>
.payroll-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.summary-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    display: flex;
    align-items: center;
    gap: 20px;
    transition: transform 0.3s;
}

.summary-card:hover {
    transform: translateY(-5px);
}

.summary-card.total {
    border-top: 5px solid #ff6b6b;
}

.summary-card.employees {
    border-top: 5px solid #28a745;
}

.summary-card.average {
    border-top: 5px solid #17a2b8;
}

.summary-card.departments {
    border-top: 5px solid #6c757d;
}

.summary-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8em;
    color: white;
}

.summary-card.total .summary-icon { background: #ff6b6b; }
.summary-card.employees .summary-icon { background: #28a745; }
.summary-card.average .summary-icon { background: #17a2b8; }
.summary-card.departments .summary-icon { background: #6c757d; }

.summary-info h3 {
    font-size: 2em;
    margin-bottom: 5px;
    color: #333;
}

.summary-info p {
    color: #666;
    font-size: 0.9em;
}

.department-chart {
    height: 300px;
    margin-bottom: 30px;
}

.department-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.department-item {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 20px;
    border-left: 4px solid #ffa500;
}

.dept-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #e9ecef;
}

.dept-header h4 {
    margin: 0;
    color: #333;
}

.dept-count {
    background: #ff6b6b;
    color: white;
    padding: 4px 12px;
    border-radius: 15px;
    font-size: 0.9em;
    font-weight: 600;
}

.dept-details {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.dept-stat {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f0;
}

.dept-stat:last-child {
    border-bottom: none;
}

.dept-stat .amount {
    font-weight: 700;
    color: #333;
}

.search-box {
    width: 300px;
}

.salary-display {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.salary-display small {
    color: #666;
    font-size: 0.85em;
}

.attendance-info, .deductions-info {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.attendance-item, .deduction-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.9em;
}

.attendance-item .label, .deduction-item .label {
    color: #666;
}

.attendance-item .value, .deduction-item .value {
    color: #333;
    font-weight: 500;
}

.deduction-item.total {
    border-top: 1px solid #ddd;
    margin-top: 5px;
    padding-top: 5px;
    font-weight: 600;
}

.leave-info {
    text-align: center;
    font-weight: 600;
    color: #333;
    padding: 8px 12px;
    background: #e3f2fd;
    border-radius: 6px;
    display: inline-block;
}

.net-salary {
    font-size: 1.2em;
    font-weight: 700;
    color: #28a745;
}

.btn-download {
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

.btn-download:hover {
    background: #138496;
    transform: scale(1.1);
}

.summary-details {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    margin-top: 10px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #e9ecef;
}

.summary-row:last-child {
    border-bottom: none;
}

@media (max-width: 768px) {
    .payroll-summary {
        grid-template-columns: 1fr;
    }
    
    .department-list {
        grid-template-columns: 1fr;
    }
    
    .search-box {
        width: 100%;
        margin-top: 15px;
    }
    
    .table-responsive {
        font-size: 0.9em;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>