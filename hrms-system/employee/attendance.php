<?php
$page_title = "My Attendance";
require_once '../includes/header.php';
require_once '../config/database.php';

$user_id = $_SESSION['user_id'];
$conn = getConnection();

// Get current month and year
$month = isset($_GET['month']) ? $_GET['month'] : date('m');
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Get attendance for the month
$stmt = $conn->prepare("
    SELECT * FROM attendance 
    WHERE user_id = ? 
    AND MONTH(date) = ? 
    AND YEAR(date) = ? 
    ORDER BY date DESC
");
$stmt->bind_param("iii", $user_id, $month, $year);
$stmt->execute();
$attendance = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate statistics
$present_days = 0;
$absent_days = 0;
$half_days = 0;
$leave_days = 0;
$total_hours = 0;

foreach($attendance as $record) {
    switch($record['status']) {
        case 'present': $present_days++; break;
        case 'absent': $absent_days++; break;
        case 'half-day': $half_days++; break;
        case 'leave': $leave_days++; break;
    }
    $total_hours += $record['hours_worked'] ?: 0;
}

$conn->close();
?>

<div class="page-header">
    <h2><i class="fas fa-calendar-check"></i> My Attendance</h2>
    
    <div class="page-actions">
        <div class="month-selector">
            <form method="GET" class="inline-form">
                <select name="month" onchange="this.form.submit()">
                    <?php for($m=1; $m<=12; $m++): 
                        $selected = $m == $month ? 'selected' : '';
                    ?>
                    <option value="<?php echo $m; ?>" <?php echo $selected; ?>>
                        <?php echo date('F', mktime(0,0,0,$m,1)); ?>
                    </option>
                    <?php endfor; ?>
                </select>
                
                <select name="year" onchange="this.form.submit()">
                    <?php for($y=date('Y')-1; $y<=date('Y')+1; $y++): 
                        $selected = $y == $year ? 'selected' : '';
                    ?>
                    <option value="<?php echo $y; ?>" <?php echo $selected; ?>>
                        <?php echo $y; ?>
                    </option>
                    <?php endfor; ?>
                </select>
            </form>
        </div>
    </div>
</div>

<!-- Attendance Stats -->
<div class="stats-grid">
    <div class="stat-card mini-card stat-green">
        <div class="stat-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $present_days; ?></h3>
            <p>Present Days</p>
        </div>
    </div>
    
    <div class="stat-card mini-card stat-red">
        <div class="stat-icon">
            <i class="fas fa-times-circle"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $absent_days; ?></h3>
            <p>Absent Days</p>
        </div>
    </div>
    
    <div class="stat-card mini-card stat-orange">
        <div class="stat-icon">
            <i class="fas fa-adjust"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $half_days; ?></h3>
            <p>Half Days</p>
        </div>
    </div>
    
    <div class="stat-card mini-card stat-blue">
        <div class="stat-icon">
            <i class="fas fa-plane"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $leave_days; ?></h3>
            <p>Leave Days</p>
        </div>
    </div>
    
    <div class="stat-card mini-card stat-yellow">
        <div class="stat-icon">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo round($total_hours, 1); ?></h3>
            <p>Total Hours</p>
        </div>
    </div>
</div>

<!-- Attendance Calendar View -->
<div class="content-card">
    <div class="card-header">
        <h3><i class="fas fa-calendar-alt"></i> Attendance Calendar - <?php echo date('F Y', mktime(0,0,0,$month,1,$year)); ?></h3>
    </div>
    <div class="card-body">
        <div class="calendar">
            <div class="calendar-header">
                <?php
                $prev_month = $month - 1;
                $prev_year = $year;
                if($prev_month < 1) {
                    $prev_month = 12;
                    $prev_year--;
                }
                
                $next_month = $month + 1;
                $next_year = $year;
                if($next_month > 12) {
                    $next_month = 1;
                    $next_year++;
                }
                ?>
                <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" class="calendar-nav">
                    <i class="fas fa-chevron-left"></i> Prev
                </a>
                <span class="calendar-title"><?php echo date('F Y', mktime(0,0,0,$month,1,$year)); ?></span>
                <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" class="calendar-nav">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
            </div>
            
            <div class="calendar-grid">
                <div class="calendar-day-header">Sun</div>
                <div class="calendar-day-header">Mon</div>
                <div class="calendar-day-header">Tue</div>
                <div class="calendar-day-header">Wed</div>
                <div class="calendar-day-header">Thu</div>
                <div class="calendar-day-header">Fri</div>
                <div class="calendar-day-header">Sat</div>
                
                <?php
                $first_day = mktime(0,0,0,$month,1,$year);
                $days_in_month = date('t', $first_day);
                $day_of_week = date('w', $first_day);
                
                // Create attendance map for quick lookup
                $attendance_map = [];
                foreach($attendance as $record) {
                    $attendance_map[date('j', strtotime($record['date']))] = $record;
                }
                
                // Empty cells for days before first day of month
                for($i=0; $i<$day_of_week; $i++) {
                    echo '<div class="calendar-day empty"></div>';
                }
                
                // Days of the month
                for($day=1; $day<=$days_in_month; $day++) {
                    $current_date = date('Y-m-d', mktime(0,0,0,$month,$day,$year));
                    $is_today = ($day == date('j') && $month == date('m') && $year == date('Y'));
                    $attendance_class = '';
                    $tooltip = '';
                    
                    if(isset($attendance_map[$day])) {
                        $record = $attendance_map[$day];
                        $attendance_class = 'status-' . $record['status'];
                        $tooltip = 'Status: ' . ucfirst($record['status']);
                        if($record['check_in']) {
                            $tooltip .= '\\nCheck-in: ' . date('h:i A', strtotime($record['check_in']));
                        }
                        if($record['check_out']) {
                            $tooltip .= '\\nCheck-out: ' . date('h:i A', strtotime($record['check_out']));
                        }
                        if($record['hours_worked']) {
                            $tooltip .= '\\nHours: ' . $record['hours_worked'];
                        }
                    }
                    
                    echo '<div class="calendar-day ' . ($is_today ? 'today ' : '') . $attendance_class . '" ' . 
                         ($tooltip ? 'title="' . $tooltip . '"' : '') . '>';
                    echo '<span class="day-number">' . $day . '</span>';
                    
                    if(isset($attendance_map[$day])) {
                        $record = $attendance_map[$day];
                        echo '<div class="day-status">';
                        switch($record['status']) {
                            case 'present':
                                echo '<i class="fas fa-check"></i>';
                                break;
                            case 'absent':
                                echo '<i class="fas fa-times"></i>';
                                break;
                            case 'half-day':
                                echo '<i class="fas fa-adjust"></i>';
                                break;
                            case 'leave':
                                echo '<i class="fas fa-plane"></i>';
                                break;
                        }
                        echo '</div>';
                    }
                    
                    echo '</div>';
                }
                ?>
            </div>
            
            <div class="calendar-legend">
                <div class="legend-item">
                    <span class="legend-color status-present"></span>
                    <span>Present</span>
                </div>
                <div class="legend-item">
                    <span class="legend-color status-absent"></span>
                    <span>Absent</span>
                </div>
                <div class="legend-item">
                    <span class="legend-color status-half-day"></span>
                    <span>Half Day</span>
                </div>
                <div class="legend-item">
                    <span class="legend-color status-leave"></span>
                    <span>Leave</span>
                </div>
                <div class="legend-item">
                    <div class="today-marker"></div>
                    <span>Today</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Attendance List -->
<div class="content-card">
    <div class="card-header">
        <h3><i class="fas fa-list"></i> Detailed Attendance</h3>
    </div>
    <div class="card-body">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Day</th>
                    <th>Check In</th>
                    <th>Check Out</th>
                    <th>Hours</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($attendance as $record): ?>
                <tr>
                    <td><?php echo date('M d, Y', strtotime($record['date'])); ?></td>
                    <td><?php echo date('l', strtotime($record['date'])); ?></td>
                    <td>
                        <?php echo $record['check_in'] 
                            ? date('h:i A', strtotime($record['check_in'])) 
                            : '--:--'; ?>
                    </td>
                    <td>
                        <?php echo $record['check_out'] 
                            ? date('h:i A', strtotime($record['check_out'])) 
                            : '--:--'; ?>
                    </td>
                    <td>
                        <?php echo $record['hours_worked'] 
                            ? number_format($record['hours_worked'], 1) . 'h' 
                            : '--'; ?>
                    </td>
                    <td>
                        <span class="badge badge-<?php echo $record['status']; ?>">
                            <?php echo ucfirst($record['status']); ?>
                        </span>
                    </td>
                    <td>
                        <?php if($record['check_in'] && !$record['check_out']): ?>
                        <button class="btn-action btn-small" 
                                onclick="manualCheckout('<?php echo $record['id']; ?>')">
                            Check Out
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function manualCheckout(attendanceId) {
    if(confirm('Record manual check out for this day?')) {
        fetch('check_in_out.php?action=manual_checkout&id=' + attendanceId)
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    alert('Check out recorded successfully');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>