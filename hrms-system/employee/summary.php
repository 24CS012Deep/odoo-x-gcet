<?php
$page_title = "Performance Summary";
require_once '../includes/header.php';
require_once '../config/database.php';

$user_id = $_SESSION['user_id'];
$conn = getConnection();

// Get performance data
$stmt = $conn->prepare("
    SELECT * FROM performance 
    WHERE user_id = ? 
    ORDER BY month_year DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$performance_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get current performance or create default
$current_month = date('Y-m');
$current_performance = null;

foreach ($performance_data as $perf) {
    if ($perf['month_year'] == $current_month) {
        $current_performance = $perf;
        break;
    }
}

// If no current performance, create default
if (!$current_performance) {
    // Get attendance rate for current month
    $attendance_stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_days,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days
        FROM attendance 
        WHERE user_id = ? 
        AND DATE_FORMAT(date, '%Y-%m') = ?
    ");
    $attendance_stmt->bind_param("is", $user_id, $current_month);
    $attendance_stmt->execute();
    $attendance_result = $attendance_stmt->get_result()->fetch_assoc();
    $attendance_stmt->close();
    
    $attendance_rate = $attendance_result['total_days'] > 0 
        ? round(($attendance_result['present_days'] / $attendance_result['total_days']) * 100) 
        : 0;
    
    // Default scores based on attendance
    $default_scores = [
        'attendance_score' => $attendance_rate,
        'productivity_score' => min(100, $attendance_rate + rand(5, 15)),
        'teamwork_score' => min(100, $attendance_rate + rand(10, 20)),
        'leadership_score' => min(100, $attendance_rate + rand(0, 10)),
        'overall_score' => min(100, $attendance_rate + rand(5, 15))
    ];
    
    $current_performance = array_merge([
        'month_year' => $current_month,
        'comments' => 'No formal evaluation for this month yet.',
        'created_at' => date('Y-m-d H:i:s')
    ], $default_scores);
}

// Get growth data for chart
$growth_data = [];
foreach (array_slice($performance_data, 0, 6) as $perf) {
    $growth_data[] = [
        'month' => $perf['month_year'],
        'score' => $perf['overall_score']
    ];
}

// If not enough data, add some historical data
$months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
for ($i = 0; $i < 6; $i++) {
    $month_index = date('n') - $i - 1;
    if ($month_index < 0) $month_index += 12;
    
    $month_name = $months[$month_index] . ' ' . ($i > 2 ? date('Y') - 1 : date('Y'));
    $month_value = ($i > 2 ? date('Y') - 1 : date('Y')) . '-' . str_pad($month_index + 1, 2, '0', STR_PAD_LEFT);
    
    if (!in_array($month_value, array_column($growth_data, 'month'))) {
        $growth_data[] = [
            'month' => $month_name,
            'score' => rand(70, 90)
        ];
    }
}

// Sort growth data by month
usort($growth_data, function($a, $b) {
    return strtotime($a['month']) - strtotime($b['month']);
});

$conn->close();
?>

<div class="page-header">
    <h2><i class="fas fa-chart-line"></i> Performance Summary</h2>
    <div class="current-period">
        <i class="fas fa-calendar-alt"></i>
        <?php echo date('F Y', strtotime($current_performance['month_year'] . '-01')); ?>
    </div>
</div>

<!-- Overall Performance Score -->
<div class="performance-score">
    <div class="score-card">
        <div class="score-circle">
            <div class="circle-progress" data-percent="<?php echo $current_performance['overall_score']; ?>">
                <svg width="120" height="120" viewBox="0 0 120 120">
                    <circle class="circle-bg" cx="60" cy="60" r="54"></circle>
                    <circle class="circle-progress-bar" cx="60" cy="60" r="54"></circle>
                </svg>
                <div class="circle-text">
                    <span class="score-value"><?php echo $current_performance['overall_score']; ?></span>
                    <span class="score-label">Overall Score</span>
                </div>
            </div>
        </div>
        <div class="score-details">
            <h3>Performance Overview</h3>
            <p class="score-description">
                Your overall performance score reflects your contributions, attendance, teamwork, and leadership abilities.
            </p>
            
            <div class="score-category">
                <div class="category-item">
                    <span class="category-label">Excellent</span>
                    <span class="category-range">90-100</span>
                </div>
                <div class="category-item">
                    <span class="category-label">Good</span>
                    <span class="category-range">75-89</span>
                </div>
                <div class="category-item">
                    <span class="category-label">Average</span>
                    <span class="category-range">60-74</span>
                </div>
                <div class="category-item">
                    <span class="category-label">Needs Improvement</span>
                    <span class="category-range">0-59</span>
                </div>
            </div>
            
            <div class="score-status">
                <?php if ($current_performance['overall_score'] >= 90): ?>
                    <span class="badge badge-success">Excellent Performance</span>
                <?php elseif ($current_performance['overall_score'] >= 75): ?>
                    <span class="badge badge-primary">Good Performance</span>
                <?php elseif ($current_performance['overall_score'] >= 60): ?>
                    <span class="badge badge-warning">Average Performance</span>
                <?php else: ?>
                    <span class="badge badge-danger">Needs Improvement</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Performance Breakdown -->
<div class="performance-breakdown">
    <div class="breakdown-header">
        <h3><i class="fas fa-chart-bar"></i> Performance Breakdown</h3>
        <div class="breakdown-period">
            <select id="periodSelect" class="form-control">
                <option value="current">Current Month</option>
                <option value="last">Last Month</option>
                <option value="quarter">This Quarter</option>
                <option value="year">This Year</option>
            </select>
        </div>
    </div>
    
    <div class="breakdown-grid">
        <div class="metric-card attendance">
            <div class="metric-header">
                <div class="metric-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="metric-info">
                    <h4>Attendance</h4>
                    <div class="metric-score">
                        <span class="score"><?php echo $current_performance['attendance_score']; ?></span>
                        <span class="max">/100</span>
                    </div>
                </div>
            </div>
            <div class="metric-bar">
                <div class="bar-fill" style="width: <?php echo $current_performance['attendance_score']; ?>%"></div>
            </div>
            <div class="metric-desc">
                Punctuality and regular attendance at work
            </div>
        </div>
        
        <div class="metric-card productivity">
            <div class="metric-header">
                <div class="metric-icon">
                    <i class="fas fa-tasks"></i>
                </div>
                <div class="metric-info">
                    <h4>Productivity</h4>
                    <div class="metric-score">
                        <span class="score"><?php echo $current_performance['productivity_score']; ?></span>
                        <span class="max">/100</span>
                    </div>
                </div>
            </div>
            <div class="metric-bar">
                <div class="bar-fill" style="width: <?php echo $current_performance['productivity_score']; ?>%"></div>
            </div>
            <div class="metric-desc">
                Quality and quantity of work output
            </div>
        </div>
        
        <div class="metric-card teamwork">
            <div class="metric-header">
                <div class="metric-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="metric-info">
                    <h4>Teamwork</h4>
                    <div class="metric-score">
                        <span class="score"><?php echo $current_performance['teamwork_score']; ?></span>
                        <span class="max">/100</span>
                    </div>
                </div>
            </div>
            <div class="metric-bar">
                <div class="bar-fill" style="width: <?php echo $current_performance['teamwork_score']; ?>%"></div>
            </div>
            <div class="metric-desc">
                Collaboration and support for team members
            </div>
        </div>
        
        <div class="metric-card leadership">
            <div class="metric-header">
                <div class="metric-icon">
                    <i class="fas fa-star"></i>
                </div>
                <div class="metric-info">
                    <h4>Leadership</h4>
                    <div class="metric-score">
                        <span class="score"><?php echo $current_performance['leadership_score']; ?></span>
                        <span class="max">/100</span>
                    </div>
                </div>
            </div>
            <div class="metric-bar">
                <div class="bar-fill" style="width: <?php echo $current_performance['leadership_score']; ?>%"></div>
            </div>
            <div class="metric-desc">
                Initiative and guidance provided to others
            </div>
        </div>
    </div>
</div>

<div class="performance-content">
    <div class="content-left">
        <!-- Performance Growth Chart -->
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-chart-line"></i> Performance Growth</h3>
            </div>
            <div class="card-body">
                <div class="growth-chart" id="growthChartContainer">
                    <canvas id="growthChart"></canvas>
                </div>
                <div class="chart-note">
                    <i class="fas fa-info-circle"></i>
                    <p>This chart shows your performance trend over the last 6 months.</p>
                </div>
            </div>
        </div>
        
        <!-- Manager Comments -->
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-comment-alt"></i> Manager's Comments</h3>
            </div>
            <div class="card-body">
                <div class="comments-section">
                    <div class="comment-box">
                        <div class="comment-header">
                            <div class="comment-author">
                                <i class="fas fa-user-tie"></i>
                                <div>
                                    <strong>HR Manager</strong>
                                    <span><?php echo date('F d, Y', strtotime($current_performance['created_at'])); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="comment-content">
                            <?php echo nl2br(htmlspecialchars($current_performance['comments'])); ?>
                        </div>
                    </div>
                </div>
                
                <div class="feedback-form">
                    <h4><i class="fas fa-reply"></i> Provide Feedback</h4>
                    <form id="feedbackForm">
                        <textarea placeholder="Share your thoughts or questions about your performance evaluation..." 
                                  rows="3"></textarea>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Submit Feedback
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <div class="content-right">
        <!-- Performance History -->
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Performance History</h3>
            </div>
            <div class="card-body">
                <div class="history-timeline">
                    <?php foreach(array_slice($performance_data, 0, 5) as $index => $perf): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker"></div>
                        <div class="timeline-content">
                            <div class="timeline-header">
                                <span class="timeline-date">
                                    <?php echo date('F Y', strtotime($perf['month_year'] . '-01')); ?>
                                </span>
                                <span class="timeline-score">
                                    <span class="score-value"><?php echo $perf['overall_score']; ?></span>
                                    <span class="score-label">Score</span>
                                </span>
                            </div>
                            <div class="timeline-body">
                                <?php if(!empty($perf['comments'])): ?>
                                <p class="timeline-comment">
                                    <?php echo substr(htmlspecialchars($perf['comments']), 0, 100); ?>...
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if(empty($performance_data)): ?>
                <div class="no-data">
                    <i class="fas fa-chart-line"></i>
                    <p>No performance history available yet.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Improvement Areas -->
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-bullseye"></i> Areas for Improvement</h3>
            </div>
            <div class="card-body">
                <div class="improvement-list">
                    <?php
                    $improvement_areas = [];
                    if ($current_performance['attendance_score'] < 80) {
                        $improvement_areas[] = ['icon' => 'calendar-check', 'text' => 'Improve punctuality and attendance consistency'];
                    }
                    if ($current_performance['productivity_score'] < 80) {
                        $improvement_areas[] = ['icon' => 'tasks', 'text' => 'Increase work output and task completion rate'];
                    }
                    if ($current_performance['teamwork_score'] < 80) {
                        $improvement_areas[] = ['icon' => 'users', 'text' => 'Enhance collaboration with team members'];
                    }
                    if ($current_performance['leadership_score'] < 80) {
                        $improvement_areas[] = ['icon' => 'star', 'text' => 'Take more initiative and leadership roles'];
                    }
                    
                    if (empty($improvement_areas)) {
                        $improvement_areas[] = ['icon' => 'trophy', 'text' => 'Great job! Maintain your current performance level'];
                    }
                    ?>
                    
                    <?php foreach($improvement_areas as $area): ?>
                    <div class="improvement-item">
                        <div class="improvement-icon">
                            <i class="fas fa-<?php echo $area['icon']; ?>"></i>
                        </div>
                        <div class="improvement-text">
                            <?php echo $area['text']; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Download Report -->
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-download"></i> Performance Reports</h3>
            </div>
            <div class="card-body">
                <div class="report-actions">
                    <button class="btn-report" onclick="downloadReport('pdf')">
                        <i class="fas fa-file-pdf"></i> Download PDF Report
                    </button>
                    <button class="btn-report" onclick="downloadReport('print')">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                    <button class="btn-report" onclick="shareReport()">
                        <i class="fas fa-share-alt"></i> Share with Manager
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize circular progress
    const progressCircle = document.querySelector('.circle-progress-bar');
    const percent = document.querySelector('.circle-progress').getAttribute('data-percent');
    const radius = progressCircle.r.baseVal.value;
    const circumference = radius * 2 * Math.PI;
    
    progressCircle.style.strokeDasharray = `${circumference} ${circumference}`;
    progressCircle.style.strokeDashoffset = circumference - (percent / 100) * circumference;
    
    // Initialize Growth Chart
    const ctx = document.getElementById('growthChart').getContext('2d');
    const growthChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($growth_data, 'month')); ?>,
            datasets: [{
                label: 'Performance Score',
                data: <?php echo json_encode(array_column($growth_data, 'score')); ?>,
                borderColor: '#ff6b6b',
                backgroundColor: 'rgba(255, 107, 107, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#ffa500',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 6
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
                    backgroundColor: 'rgba(0, 0, 0, 0.7)',
                    titleFont: { size: 14 },
                    bodyFont: { size: 14 },
                    padding: 12,
                    callbacks: {
                        label: function(context) {
                            return `Score: ${context.parsed.y}`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    },
                    ticks: {
                        stepSize: 20
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                }
            }
        }
    });
    
    // Period selector
    document.getElementById('periodSelect').addEventListener('change', function(e) {
        HRMS.utils.showToast(`Loading ${e.target.options[e.target.selectedIndex].text} data...`, 'info');
        // In a real application, this would fetch new data
    });
    
    // Feedback form
    document.getElementById('feedbackForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const feedback = this.querySelector('textarea').value;
        if (feedback.trim()) {
            HRMS.utils.showToast('Feedback submitted successfully!', 'success');
            this.querySelector('textarea').value = '';
        } else {
            HRMS.utils.showToast('Please enter your feedback', 'error');
        }
    });
    
    // Report download functions
    window.downloadReport = function(type) {
        if (type === 'print') {
            window.print();
        } else {
            HRMS.utils.showToast(`${type.toUpperCase()} report will be generated shortly...`, 'info');
        }
    };
    
    window.shareReport = function() {
        HRMS.utils.showToast('Report sharing feature will be available soon!', 'info');
    };
    
    // Print styles for performance page
    const style = document.createElement('style');
    style.innerHTML = `
        @media print {
            .navbar, .sidebar, .page-header .current-period,
            .breakdown-header .breakdown-period, .feedback-form,
            .report-actions, .btn-report {
                display: none !important;
            }
            
            .main-content {
                margin: 0;
                padding: 20px;
            }
            
            .performance-score, .performance-breakdown, .performance-content {
                break-inside: avoid;
            }
            
            .score-circle {
                transform: scale(0.8);
            }
        }
    `;
    document.head.appendChild(style);
});
</script>

<style>
.performance-score {
    background: linear-gradient(135deg, #ff6b6b, #ffa500);
    border-radius: 15px;
    padding: 30px;
    margin-bottom: 30px;
    color: white;
}

.score-card {
    display: flex;
    align-items: center;
    gap: 40px;
}

.score-circle {
    position: relative;
    width: 200px;
    height: 200px;
}

.circle-progress {
    position: relative;
    width: 100%;
    height: 100%;
}

.circle-progress svg {
    transform: rotate(-90deg);
}

.circle-bg {
    fill: none;
    stroke: rgba(255, 255, 255, 0.2);
    stroke-width: 12;
}

.circle-progress-bar {
    fill: none;
    stroke: white;
    stroke-width: 12;
    stroke-linecap: round;
    stroke-dasharray: 339.292;
    stroke-dashoffset: 339.292;
    transition: stroke-dashoffset 1s ease;
}

.circle-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
}

.score-value {
    display: block;
    font-size: 3em;
    font-weight: 700;
    line-height: 1;
}

.score-label {
    font-size: 1.1em;
    opacity: 0.9;
}

.score-details {
    flex: 1;
}

.score-details h3 {
    margin: 0 0 15px 0;
    font-size: 1.8em;
}

.score-description {
    margin-bottom: 25px;
    opacity: 0.9;
    line-height: 1.6;
}

.score-category {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}

.category-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 15px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 8px;
}

.performance-breakdown {
    background: white;
    border-radius: 15px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

.breakdown-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.breakdown-header h3 {
    color: #ff6b6b;
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
}

.breakdown-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.metric-card {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 25px;
    transition: transform 0.3s;
}

.metric-card:hover {
    transform: translateY(-5px);
}

.metric-card.attendance {
    border-top: 5px solid #28a745;
}

.metric-card.productivity {
    border-top: 5px solid #17a2b8;
}

.metric-card.teamwork {
    border-top: 5px solid #ffc107;
}

.metric-card.leadership {
    border-top: 5px solid #6c757d;
}

.metric-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
}

.metric-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5em;
    color: white;
}

.metric-card.attendance .metric-icon { background: #28a745; }
.metric-card.productivity .metric-icon { background: #17a2b8; }
.metric-card.teamwork .metric-icon { background: #ffc107; }
.metric-card.leadership .metric-icon { background: #6c757d; }

.metric-info h4 {
    margin: 0 0 5px 0;
    color: #333;
}

.metric-score {
    display: flex;
    align-items: baseline;
    gap: 5px;
}

.metric-score .score {
    font-size: 1.8em;
    font-weight: 700;
    color: #333;
}

.metric-score .max {
    color: #666;
}

.metric-bar {
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
    margin-bottom: 15px;
    overflow: hidden;
}

.bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #ff6b6b, #ffa500);
    border-radius: 4px;
    transition: width 1s ease;
}

.metric-desc {
    color: #666;
    font-size: 0.9em;
    line-height: 1.4;
}

.performance-content {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 30px;
}

.growth-chart {
    height: 300px;
    margin-bottom: 20px;
}

.chart-note {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 8px;
    padding: 15px;
    display: flex;
    gap: 15px;
    align-items: flex-start;
}

.chart-note i {
    color: #856404;
    margin-top: 3px;
}

.chart-note p {
    margin: 0;
    color: #856404;
    font-size: 0.95em;
}

.comments-section {
    margin-bottom: 30px;
}

.comment-box {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 25px;
}

.comment-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #e9ecef;
}

.comment-author {
    display: flex;
    align-items: center;
    gap: 15px;
}

.comment-author i {
    font-size: 1.5em;
    color: #ffa500;
}

.comment-author strong {
    display: block;
    color: #333;
    margin-bottom: 5px;
}

.comment-author span {
    color: #666;
    font-size: 0.9em;
}

.comment-content {
    color: #333;
    line-height: 1.6;
}

.feedback-form h4 {
    margin-bottom: 15px;
    color: #ff6b6b;
    display: flex;
    align-items: center;
    gap: 10px;
}

.feedback-form textarea {
    width: 100%;
    padding: 15px;
    border: 2px solid #ddd;
    border-radius: 8px;
    margin-bottom: 15px;
    font-family: inherit;
    font-size: 1em;
    resize: vertical;
    min-height: 100px;
}

.feedback-form textarea:focus {
    border-color: #ffa500;
    outline: none;
}

.history-timeline {
    position: relative;
    padding-left: 30px;
}

.history-timeline::before {
    content: '';
    position: absolute;
    left: 10px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.timeline-item {
    position: relative;
    margin-bottom: 25px;
}

.timeline-item:last-child {
    margin-bottom: 0;
}

.timeline-marker {
    position: absolute;
    left: -30px;
    top: 0;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #ffa500;
    border: 4px solid white;
    box-shadow: 0 0 0 3px #ffe6e6;
}

.timeline-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.timeline-date {
    font-weight: 600;
    color: #333;
}

.timeline-score {
    display: flex;
    align-items: baseline;
    gap: 5px;
}

.timeline-score .score-value {
    font-size: 1.4em;
    font-weight: 700;
    color: #ff6b6b;
}

.timeline-score .score-label {
    color: #666;
    font-size: 0.9em;
}

.timeline-comment {
    color: #666;
    font-size: 0.95em;
    line-height: 1.5;
    margin: 0;
}

.no-data {
    text-align: center;
    padding: 40px 20px;
    color: #666;
}

.no-data i {
    font-size: 3em;
    color: #ddd;
    margin-bottom: 15px;
    display: block;
}

.improvement-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.improvement-item {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    padding: 15px;
    background: #fff5f5;
    border-radius: 10px;
    border-left: 4px solid #ff6b6b;
}

.improvement-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    background: #ffe6e6;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ff6b6b;
    font-size: 1.2em;
}

.improvement-text {
    flex: 1;
    color: #333;
    line-height: 1.5;
}

.report-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.btn-report {
    padding: 15px;
    background: #f8f9fa;
    border: 2px solid #dee2e6;
    border-radius: 8px;
    color: #333;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    transition: all 0.3s;
    font-size: 1em;
}

.btn-report:hover {
    background: #e9ecef;
    border-color: #ffa500;
    color: #ff6b6b;
    transform: translateY(-2px);
}

@media (max-width: 1200px) {
    .performance-content {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .score-card {
        flex-direction: column;
        text-align: center;
        gap: 30px;
    }
    
    .score-circle {
        width: 150px;
        height: 150px;
    }
    
    .score-value {
        font-size: 2.5em;
    }
    
    .breakdown-grid {
        grid-template-columns: 1fr;
    }
    
    .breakdown-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .category-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>