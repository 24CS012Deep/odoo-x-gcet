<?php
$page_title = "Employees Management";
require_once '../includes/header.php';
require_once '../config/database.php';

// Check if user is admin
if ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'hr') {
    header('Location: ../employee/dashboard.php');
    exit();
}

$conn = getConnection();

// Handle employee actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'add_employee') {
        // Add new employee
        $employee_id = $_POST['employee_id'];
        $email = $_POST['email'];
        $first_name = $_POST['first_name'];
        $last_name = $_POST['last_name'];
        $role = $_POST['role'];
        $job_title = $_POST['job_title'];
        $department = $_POST['department'];
        $salary = $_POST['salary'];
        
        // Default password (should be changed on first login)
        $default_password = password_hash('Welcome123', PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("
            INSERT INTO users (
                employee_id, email, password, role, 
                first_name, last_name, job_title, 
                department, salary, hire_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())
        ");
        $stmt->bind_param(
            "ssssssssd",
            $employee_id, $email, $default_password, $role,
            $first_name, $last_name, $job_title,
            $department, $salary
        );
        
        if ($stmt->execute()) {
            $success_message = "Employee added successfully!";
        } else {
            $error_message = "Failed to add employee. Employee ID or Email may already exist.";
        }
        $stmt->close();
        
    } elseif ($action == 'update_employee') {
        // Update employee
        $user_id = intval($_POST['user_id']);
        $first_name = $_POST['first_name'];
        $last_name = $_POST['last_name'];
        $email = $_POST['email'];
        $role = $_POST['role'];
        $job_title = $_POST['job_title'];
        $department = $_POST['department'];
        $salary = $_POST['salary'];
        $phone = $_POST['phone'];
        $address = $_POST['address'];
        
        $stmt = $conn->prepare("
            UPDATE users 
            SET first_name = ?, last_name = ?, email = ?, role = ?,
                job_title = ?, department = ?, salary = ?, 
                phone = ?, address = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param(
            "ssssssdssi",
            $first_name, $last_name, $email, $role,
            $job_title, $department, $salary,
            $phone, $address, $user_id
        );
        
        if ($stmt->execute()) {
            $success_message = "Employee updated successfully!";
        } else {
            $error_message = "Failed to update employee.";
        }
        $stmt->close();
        
    } elseif ($action == 'delete_employee') {
        // Delete employee
        $user_id = intval($_POST['user_id']);
        
        // Don't delete if user is admin
        $check_stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
        $check_stmt->bind_param("i", $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();
        
        if ($check_result['role'] != 'admin') {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            
            if ($stmt->execute()) {
                $success_message = "Employee deleted successfully!";
            } else {
                $error_message = "Failed to delete employee.";
            }
            $stmt->close();
        } else {
            $error_message = "Cannot delete admin users.";
        }
    }
}

// Get search parameters
$search = $_GET['search'] ?? '';
$department = $_GET['department'] ?? 'all';
$role = $_GET['role'] ?? 'all';

// Build query
$query = "
    SELECT u.*, 
           COUNT(DISTINCT a.id) as attendance_count,
           COUNT(DISTINCT l.id) as leave_count
    FROM users u
    LEFT JOIN attendance a ON u.id = a.user_id AND a.status = 'present'
    LEFT JOIN leave_requests l ON u.id = l.user_id
    WHERE 1=1
";

$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.employee_id LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $types .= "ssss";
}

if ($department != 'all') {
    $query .= " AND u.department = ?";
    $params[] = $department;
    $types .= "s";
}

if ($role != 'all') {
    $query .= " AND u.role = ?";
    $params[] = $role;
    $types .= "s";
}

$query .= " GROUP BY u.id ORDER BY u.first_name ASC";

// Execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$employees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get departments for filter
$dept_stmt = $conn->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL ORDER BY department");
$departments = $dept_stmt->fetch_all(MYSQLI_ASSOC);
$dept_stmt->close();

// Get statistics
$stats_stmt = $conn->query("
    SELECT 
        COUNT(*) as total_employees,
        SUM(CASE WHEN role = 'admin' OR role = 'hr' THEN 1 ELSE 0 END) as total_admins,
        SUM(CASE WHEN role = 'employee' THEN 1 ELSE 0 END) as total_employees_role,
        AVG(salary) as avg_salary
    FROM users
");
$stats = $stats_stmt->fetch_assoc();
$stats_stmt->close();

$conn->close();
?>

<div class="page-header">
    <h2><i class="fas fa-users"></i> Employees Management</h2>
    <div class="header-actions">
        <button class="btn btn-primary" onclick="showAddEmployeeModal()">
            <i class="fas fa-user-plus"></i> Add Employee
        </button>
    </div>
</div>

<!-- Statistics -->
<div class="stats-grid">
    <div class="stat-card stat-red">
        <div class="stat-icon">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $stats['total_employees']; ?></h3>
            <p>Total Employees</p>
        </div>
    </div>
    
    <div class="stat-card stat-orange">
        <div class="stat-icon">
            <i class="fas fa-user-tie"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $stats['total_admins']; ?></h3>
            <p>Admin/HR Staff</p>
        </div>
    </div>
    
    <div class="stat-card stat-yellow">
        <div class="stat-icon">
            <i class="fas fa-briefcase"></i>
        </div>
        <div class="stat-info">
            <h3><?php echo $stats['total_employees_role']; ?></h3>
            <p>Regular Employees</p>
        </div>
    </div>
    
    <div class="stat-card stat-red">
        <div class="stat-icon">
            <i class="fas fa-money-bill-wave"></i>
        </div>
        <div class="stat-info">
            <h3>$<?php echo number_format($stats['avg_salary'], 2); ?></h3>
            <p>Average Salary</p>
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

<!-- Search and Filters -->
<div class="filters-card">
    <h3><i class="fas fa-search"></i> Search & Filter Employees</h3>
    <form method="GET" class="filter-form">
        <div class="filter-row">
            <div class="filter-group">
                <label>Search</label>
                <input type="text" 
                       name="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Search by name, ID, or email..."
                       class="form-control">
            </div>
            
            <div class="filter-group">
                <label>Department</label>
                <select name="department" class="form-control">
                    <option value="all">All Departments</option>
                    <?php foreach($departments as $dept): ?>
                    <option value="<?php echo htmlspecialchars($dept['department']); ?>"
                            <?php echo $department == $dept['department'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($dept['department']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Role</label>
                <select name="role" class="form-control">
                    <option value="all">All Roles</option>
                    <option value="employee" <?php echo $role == 'employee' ? 'selected' : ''; ?>>Employee</option>
                    <option value="admin" <?php echo $role == 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="hr" <?php echo $role == 'hr' ? 'selected' : ''; ?>>HR</option>
                </select>
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
                <a href="employees.php" class="btn btn-secondary">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
        </div>
    </form>
</div>

<!-- Employees Table -->
<div class="content-card">
    <div class="card-header">
        <h3><i class="fas fa-list"></i> Employees List (<?php echo count($employees); ?>)</h3>
        <div class="export-options">
            <button class="btn-export">
                <i class="fas fa-file-export"></i> Export
            </button>
        </div>
    </div>
    <div class="card-body">
        <?php if(count($employees) > 0): ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Contact</th>
                        <th>Job Details</th>
                        <th>Performance</th>
                        <th>Salary</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($employees as $employee): ?>
                    <tr>
                        <td