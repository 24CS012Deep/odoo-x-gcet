<?php
$page_title = "Salary Details";
require_once '../includes/header.php';
require_once '../config/database.php';

$user_id = $_SESSION['user_id'];
$conn = getConnection();

// Get salary details
$stmt = $conn->prepare("
    SELECT salary, 
           salary * 0.08 as pf, 
           salary * 0.05 as tax, 
           salary * 0.02 as insurance,
           salary - (salary * 0.08) - (salary * 0.05) - (salary * 0.02) as net_salary
    FROM users 
    WHERE id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$salary_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get salary history (in a real system, this would be from a payroll table)
$salary_history = [
    ['month' => 'January 2024', 'gross' => $salary_data['salary'], 'deductions' => ($salary_data['pf'] + $salary_data['tax'] + $salary_data['insurance']), 'net' => $salary_data['net_salary'], 'status' => 'Paid'],
    ['month' => 'December 2023', 'gross' => $salary_data['salary'], 'deductions' => ($salary_data['pf'] + $salary_data['tax'] + $salary_data['insurance']), 'net' => $salary_data['net_salary'], 'status' => 'Paid'],
    ['month' => 'November 2023', 'gross' => $salary_data['salary'], 'deductions' => ($salary_data['pf'] + $salary_data['tax'] + $salary_data['insurance']), 'net' => $salary_data['net_salary'], 'status' => 'Paid'],
    ['month' => 'October 2023', 'gross' => $salary_data['salary'], 'deductions' => ($salary_data['pf'] + $salary_data['tax'] + $salary_data['insurance']), 'net' => $salary_data['net_salary'], 'status' => 'Paid'],
];

$conn->close();

// Format currency
function format_currency($amount) {
    return '$' . number_format($amount, 2);
}
?>

<div class="page-header">
    <h2><i class="fas fa-money-bill-wave"></i> Salary Details</h2>
    <div class="current-month">
        <?php echo date('F Y'); ?>
    </div>
</div>

<!-- Salary Overview -->
<div class="salary-overview">
    <div class="overview-card gross">
        <div class="overview-icon">
            <i class="fas fa-money-bill"></i>
        </div>
        <div class="overview-info">
            <h3>Gross Salary</h3>
            <div class="overview-amount">
                <?php echo format_currency($salary_data['salary']); ?>
            </div>
            <p class="overview-period">Per Month</p>
        </div>
    </div>
    
    <div class="overview-card deductions">
        <div class="overview-icon">
            <i class="fas fa-minus-circle"></i>
        </div>
        <div class="overview-info">
            <h3>Total Deductions</h3>
            <div class="overview-amount">
                <?php echo format_currency($salary_data['pf'] + $salary_data['tax'] + $salary_data['insurance']); ?>
            </div>
            <p class="overview-period">Monthly</p>
        </div>
    </div>
    
    <div class="overview-card net">
        <div class="overview-icon">
            <i class="fas fa-wallet"></i>
        </div>
        <div class="overview-info">
            <h3>Net Salary</h3>
            <div class="overview-amount">
                <?php echo format_currency($salary_data['net_salary']); ?>
            </div>
            <p class="overview-period">Take Home</p>
        </div>
    </div>
</div>

<div class="salary-details">
    <div class="details-left">
        <!-- Salary Breakdown -->
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-chart-pie"></i> Salary Breakdown</h3>
                <span class="period-label">Monthly</span>
            </div>
            <div class="card-body">
                <div class="breakdown-chart" id="salaryChartContainer">
                    <canvas id="salaryChart"></canvas>
                </div>
                
                <div class="breakdown-list">
                    <div class="breakdown-item">
                        <div class="breakdown-label">
                            <span class="breakdown-color" style="background: #ff6b6b;"></span>
                            <span>Gross Salary</span>
                        </div>
                        <div class="breakdown-amount">
                            <?php echo format_currency($salary_data['salary']); ?>
                        </div>
                    </div>
                    
                    <div class="breakdown-item deduction">
                        <div class="breakdown-label">
                            <span class="breakdown-color" style="background: #6c757d;"></span>
                            <span>Provident Fund (8%)</span>
                        </div>
                        <div class="breakdown-amount">
                            -<?php echo format_currency($salary_data['pf']); ?>
                        </div>
                    </div>
                    
                    <div class="breakdown-item deduction">
                        <div class="breakdown-label">
                            <span class="breakdown-color" style="background: #17a2b8;"></span>
                            <span>Income Tax (5%)</span>
                        </div>
                        <div class="breakdown-amount">
                            -<?php echo format_currency($salary_data['tax']); ?>
                        </div>
                    </div>
                    
                    <div class="breakdown-item deduction">
                        <div class="breakdown-label">
                            <span class="breakdown-color" style="background: #ffc107;"></span>
                            <span>Health Insurance (2%)</span>
                        </div>
                        <div class="breakdown-amount">
                            -<?php echo format_currency($salary_data['insurance']); ?>
                        </div>
                    </div>
                    
                    <div class="breakdown-item total">
                        <div class="breakdown-label">
                            <strong>Net Salary</strong>
                        </div>
                        <div class="breakdown-amount">
                            <strong><?php echo format_currency($salary_data['net_salary']); ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Payment Information -->
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-credit-card"></i> Payment Information</h3>
            </div>
            <div class="card-body">
                <div class="payment-info">
                    <div class="info-item">
                        <div class="info-label">Payment Method:</div>
                        <div class="info-value">Direct Bank Transfer</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Bank Name:</div>
                        <div class="info-value">National Bank</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Account Number:</div>
                        <div class="info-value">XXXX-XXXX-XXXX-1234</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Payment Date:</div>
                        <div class="info-value">Last working day of each month</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Payment Status:</div>
                        <div class="info-value">
                            <span class="badge badge-success">Active</span>
                        </div>
                    </div>
                </div>
                
                <div class="note-box">
                    <i class="fas fa-info-circle"></i>
                    <p>Salary slips are generated on the 25th of each month and can be downloaded from this portal.</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="details-right">
        <!-- Salary History -->
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Salary History</h3>
            </div>
            <div class="card-body">
                <div class="history-list">
                    <?php foreach($salary_history as $history): ?>
                    <div class="history-item">
                        <div class="history-month">
                            <div class="month-name"><?php echo $history['month']; ?></div>
                            <div class="month-status">
                                <span class="badge badge-<?php echo strtolower($history['status']); ?>">
                                    <?php echo $history['status']; ?>
                                </span>
                            </div>
                        </div>
                        <div class="history-details">
                            <div class="detail-row">
                                <span>Gross:</span>
                                <span><?php echo format_currency($history['gross']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span>Deductions:</span>
                                <span>-<?php echo format_currency($history['deductions']); ?></span>
                            </div>
                            <div class="detail-row total">
                                <span>Net:</span>
                                <span><?php echo format_currency($history['net']); ?></span>
                            </div>
                        </div>
                        <div class="history-actions">
                            <button class="btn-action btn-view">
                                <i class="fas fa-file-invoice-dollar"></i> View Slip
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="download-section">
                    <h4><i class="fas fa-download"></i> Download Salary Slips</h4>
                    <div class="download-options">
                        <button class="btn-download">
                            <i class="fas fa-file-pdf"></i> Download PDF
                        </button>
                        <button class="btn-download">
                            <i class="fas fa-file-excel"></i> Download Excel
                        </button>
                        <button class="btn-download">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tax Information -->
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-percentage"></i> Tax Information</h3>
            </div>
            <div class="card-body">
                <div class="tax-info">
                    <div class="tax-progress">
                        <div class="progress-label">
                            <span>Tax Paid This Year</span>
                            <span><?php echo format_currency($salary_data['tax'] * 12); ?></span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 45%"></div>
                        </div>
                        <div class="progress-text">
                            45% of annual tax paid
                        </div>
                    </div>
                    
                    <div class="tax-details">
                        <div class="tax-item">
                            <span>Tax ID:</span>
                            <span>TAX-<?php echo $_SESSION['employee_id']; ?></span>
                        </div>
                        <div class="tax-item">
                            <span>Tax Year:</span>
                            <span><?php echo date('Y'); ?></span>
                        </div>
                        <div class="tax-item">
                            <span>Tax Category:</span>
                            <span>Employee (Standard)</span>
                        </div>
                        <div class="tax-item">
                            <span>Next Filing:</span>
                            <span>April 15, <?php echo date('Y') + 1; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Salary Chart
    const ctx = document.getElementById('salaryChart').getContext('2d');
    const salaryChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Gross Salary', 'Provident Fund', 'Income Tax', 'Health Insurance'],
            datasets: [{
                data: [
                    <?php echo $salary_data['salary']; ?>,
                    <?php echo $salary_data['pf']; ?>,
                    <?php echo $salary_data['tax']; ?>,
                    <?php echo $salary_data['insurance']; ?>
                ],
                backgroundColor: [
                    '#ff6b6b',
                    '#6c757d',
                    '#17a2b8',
                    '#ffc107'
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
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            if (label) {
                                label += ': ';
                            }
                            label += '$' + context.parsed.toFixed(2);
                            return label;
                        }
                    }
                }
            },
            cutout: '65%'
        }
    });
    
    // Download button functionality
    document.querySelectorAll('.btn-download').forEach(btn => {
        btn.addEventListener('click', function() {
            const action = this.querySelector('i').className.includes('pdf') ? 'PDF' : 
                         this.querySelector('i').className.includes('excel') ? 'Excel' : 'Print';
            
            if (action === 'Print') {
                window.print();
            } else {
                HRMS.utils.showToast(`${action} download will be available soon!`, 'info');
            }
        });
    });
    
    // View salary slip
    document.querySelectorAll('.btn-view').forEach(btn => {
        btn.addEventListener('click', function() {
            HRMS.utils.showToast('Salary slip viewer will be available soon!', 'info');
        });
    });
    
    // Print functionality for salary page
    const printBtn = document.createElement('button');
    printBtn.className = 'btn-print';
    printBtn.innerHTML = '<i class="fas fa-print"></i> Print Salary Details';
    printBtn.style.marginTop = '20px';
    printBtn.style.width = '100%';
    
    printBtn.addEventListener('click', function() {
        window.print();
    });
    
    document.querySelector('.download-section').appendChild(printBtn);
});
</script>

<style>
.salary-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.overview-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    display: flex;
    align-items: center;
    gap: 20px;
    transition: transform 0.3s;
}

.overview-card:hover {
    transform: translateY(-5px);
}

.overview-card.gross {
    border-top: 5px solid #ff6b6b;
}

.overview-card.deductions {
    border-top: 5px solid #6c757d;
}

.overview-card.net {
    border-top: 5px solid #28a745;
}

.overview-icon {
    width: 70px;
    height: 70px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2em;
    color: white;
}

.overview-card.gross .overview-icon { background: #ff6b6b; }
.overview-card.deductions .overview-icon { background: #6c757d; }
.overview-card.net .overview-icon { background: #28a745; }

.overview-info h3 {
    margin: 0 0 10px 0;
    color: #555;
    font-size: 1.1em;
}

.overview-amount {
    font-size: 2em;
    font-weight: 700;
    color: #333;
    margin-bottom: 5px;
}

.overview-period {
    color: #666;
    font-size: 0.9em;
    margin: 0;
}

.salary-details {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 30px;
}

.period-label {
    background: #ffe6e6;
    color: #ff6b6b;
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 0.9em;
    font-weight: 600;
}

.breakdown-chart {
    height: 250px;
    margin-bottom: 30px;
    position: relative;
}

.breakdown-list {
    border-top: 2px solid #f0f0f0;
    padding-top: 20px;
}

.breakdown-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #f8f9fa;
}

.breakdown-item:last-child {
    border-bottom: none;
}

.breakdown-item.total {
    border-top: 2px solid #f0f0f0;
    margin-top: 10px;
    padding-top: 15px;
}

.breakdown-label {
    display: flex;
    align-items: center;
    gap: 10px;
}

.breakdown-color {
    width: 12px;
    height: 12px;
    border-radius: 3px;
    display: inline-block;
}

.breakdown-amount {
    font-weight: 600;
    color: #333;
}

.breakdown-item.deduction .breakdown-amount {
    color: #dc3545;
}

.breakdown-item.total .breakdown-amount {
    color: #28a745;
    font-size: 1.1em;
}

.payment-info {
    margin-bottom: 25px;
}

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f8f9fa;
}

.info-item:last-child {
    border-bottom: none;
}

.info-label {
    color: #666;
    font-weight: 500;
}

.info-value {
    color: #333;
    font-weight: 600;
}

.note-box {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 10px;
    padding: 15px;
    display: flex;
    gap: 15px;
}

.note-box i {
    color: #856404;
    font-size: 1.2em;
    margin-top: 3px;
}

.note-box p {
    margin: 0;
    color: #856404;
    font-size: 0.95em;
}

.history-list {
    max-height: 400px;
    overflow-y: auto;
    margin-bottom: 25px;
}

.history-item {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 15px;
    border-left: 4px solid #ffa500;
}

.history-month {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.month-name {
    font-weight: 600;
    color: #333;
    font-size: 1.1em;
}

.history-details {
    margin-bottom: 15px;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 5px 0;
    color: #666;
}

.detail-row.total {
    border-top: 1px solid #ddd;
    margin-top: 5px;
    padding-top: 10px;
    color: #333;
    font-weight: 600;
}

.history-actions {
    text-align: right;
}

.download-section {
    border-top: 2px solid #f0f0f0;
    padding-top: 20px;
}

.download-section h4 {
    margin-bottom: 15px;
    color: #ff6b6b;
    display: flex;
    align-items: center;
    gap: 10px;
}

.download-options {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn-download {
    flex: 1;
    min-width: 120px;
    padding: 12px;
    background: #f8f9fa;
    border: 2px solid #dee2e6;
    border-radius: 8px;
    color: #333;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.3s;
}

.btn-download:hover {
    background: #e9ecef;
    border-color: #ffa500;
    color: #ff6b6b;
    transform: translateY(-2px);
}

.tax-progress {
    margin-bottom: 25px;
}

.progress-label {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.progress-label span:first-child {
    color: #666;
}

.progress-label span:last-child {
    color: #333;
    font-weight: 600;
}

.progress-bar {
    height: 10px;
    background: #f0f0f0;
    border-radius: 5px;
    overflow: hidden;
    margin-bottom: 10px;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #ff6b6b, #ffa500);
    border-radius: 5px;
}

.progress-text {
    text-align: center;
    color: #666;
    font-size: 0.9em;
}

.tax-details {
    border-top: 1px solid #f0f0f0;
    padding-top: 20px;
}

.tax-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #f8f9fa;
}

.tax-item:last-child {
    border-bottom: none;
}

.btn-print {
    padding: 15px;
    background: linear-gradient(90deg, #ff6b6b, #ffa500);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 1em;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    transition: all 0.3s;
}

.btn-print:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(255,107,107,0.3);
}

@media (max-width: 1200px) {
    .salary-details {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .salary-overview {
        grid-template-columns: 1fr;
    }
    
    .overview-card {
        flex-direction: column;
        text-align: center;
    }
    
    .download-options {
        flex-direction: column;
    }
    
    .btn-download {
        width: 100%;
    }
}

@media print {
    .navbar, .sidebar, .card-header .btn-view-all, .history-actions, .download-options, .btn-print {
        display: none !important;
    }
    
    .main-content {
        margin: 0;
        padding: 20px;
    }
    
    .content-card {
        break-inside: avoid;
        box-shadow: none;
        border: 1px solid #ddd;
        margin-bottom: 20px;
    }
    
    .salary-details {
        display: block;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>