<?php
session_start();
require_once 'config/database.php';

// Initialize variables
$error = '';
$success = '';
$action = isset($_GET['action']) ? $_GET['action'] : 'login';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $conn = getConnection();
    
    if (isset($_POST['login'])) {
        // LOGIN PROCESS
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        
        // Prepare statement
        $stmt = $conn->prepare("SELECT id, employee_id, email, password, role, first_name, last_name, profile_picture FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            // Verify password (use password_verify in production)
            if ($password == 'demo123' || password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['employee_id'] = $user['employee_id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['profile_picture'] = $user['profile_picture'];
                $_SESSION['logged_in'] = true;
                
                // Redirect based on role
                if ($user['role'] == 'admin' || $user['role'] == 'hr') {
                    header('Location: admin/dashboard.php');
                    exit();
                } else {
                    header('Location: employee/dashboard.php');
                    exit();
                }
            } else {
                $error = "Invalid email or password!";
                $action = 'login';
            }
        } else {
            $error = "Invalid email or password!";
            $action = 'login';
        }
        
        $stmt->close();
    } 
    elseif (isset($_POST['signup'])) {
        // SIGNUP PROCESS
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['signup_email']);
        $password = $_POST['signup_password'];
        $confirm_password = $_POST['confirm_password'];
        $employee_id = 'EMP' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        
        // Validation
        if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
            $error = "All fields are required!";
            $action = 'signup';
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match!";
            $action = 'signup';
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters long!";
            $action = 'signup';
        } else {
            // Check if email already exists
            $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $checkStmt->bind_param("s", $email);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                $error = "Email already registered!";
                $action = 'signup';
            } else {
                // Hash password for production (using demo for now)
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert new user with default 'employee' role
                $insertStmt = $conn->prepare("INSERT INTO users (employee_id, first_name, last_name, email, password, role) VALUES (?, ?, ?, ?, ?, 'employee')");
                $insertStmt->bind_param("sssss", $employee_id, $first_name, $last_name, $email, $hashed_password);
                
                if ($insertStmt->execute()) {
                    $success = "Registration successful! You can now login.";
                    $action = 'login';
                } else {
                    $error = "Registration failed. Please try again.";
                    $action = 'signup';
                }
                
                $insertStmt->close();
            }
            
            $checkStmt->close();
        }
    }
    
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dayflow HRMS - <?php echo ucfirst($action); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .login-wrapper {
            display: flex;
            width: 90%;
            max-width: 1000px;
            min-height: 600px;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(50, 50, 93, 0.1), 0 5px 15px rgba(0, 0, 0, 0.07);
        }
        
        .image-section {
            flex: 1;
            background: linear-gradient(rgba(255, 107, 107, 0.8), rgba(255, 168, 0, 0.8)), 
                        url('https://images.unsplash.com/photo-1552664730-d307ca884978?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 50px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .image-section h1 {
            font-size: 2.8rem;
            margin-bottom: 20px;
            font-weight: 800;
            line-height: 1.2;
        }
        
        .image-section p {
            font-size: 1.1rem;
            line-height: 1.6;
            opacity: 0.9;
            margin-bottom: 30px;
        }
        
        .features {
            margin-top: 30px;
        }
        
        .feature {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .feature i {
            margin-right: 12px;
            background: rgba(255, 255, 255, 0.2);
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .form-section {
            flex: 1;
            padding: 50px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h2 {
            color: #ff6b6b;
            font-size: 2.5rem;
            margin-bottom: 5px;
        }
        
        .logo p {
            color: #666;
            font-size: 1rem;
        }
        
        .form-tabs {
            display: flex;
            margin-bottom: 30px;
            border-bottom: 2px solid #eee;
        }
        
        .tab-btn {
            flex: 1;
            padding: 15px;
            background: none;
            border: none;
            font-size: 1.1rem;
            font-weight: 600;
            color: #999;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }
        
        .tab-btn.active {
            color: #ff6b6b;
        }
        
        .tab-btn.active:after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 3px;
            background: #ff6b6b;
            border-radius: 3px 3px 0 0;
        }
        
        .form-container {
            position: relative;
            min-height: 400px;
        }
        
        .form {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            opacity: 0;
            visibility: hidden;
            transform: translateX(20px);
            transition: all 0.4s ease;
        }
        
        .form.active {
            opacity: 1;
            visibility: visible;
            transform: translateX(0);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
            font-size: 0.95rem;
        }
        
        .form-group input {
            width: 100%;
            padding: 14px 15px;
            border: 2px solid #e1e5ee;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .form-group input:focus {
            border-color: #ffa500;
            outline: none;
            box-shadow: 0 0 0 3px rgba(255, 165, 0, 0.2);
        }
        
        .btn-submit {
            width: 100%;
            padding: 15px;
            background: linear-gradient(to right, #ff6b6b, #ffa500);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }
        
        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 7px 14px rgba(255, 107, 107, 0.2);
        }
        
        .form-footer {
            text-align: center;
            margin-top: 25px;
            color: #666;
            font-size: 0.9rem;
        }
        
        .form-footer a {
            color: #ff6b6b;
            text-decoration: none;
            font-weight: 600;
        }
        
        .form-footer a:hover {
            text-decoration: underline;
        }
        
        .message {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
        }
        
        .error-message {
            background: #ffe6e6;
            color: #ff6b6b;
            border-left: 4px solid #ff6b6b;
        }
        
        .success-message {
            background: #e6ffe6;
            color: #2ecc71;
            border-left: 4px solid #2ecc71;
        }
        
        .demo-credentials {
            background: #fff9e6;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin-top: 25px;
            font-size: 0.9rem;
        }
        
        .demo-credentials h4 {
            margin-top: 0;
            color: #e17055;
            margin-bottom: 8px;
        }
        
        .demo-credentials p {
            margin: 5px 0;
            color: #666;
        }
        
        .password-toggle {
            position: relative;
        }
        
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 42px;
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .login-wrapper {
                flex-direction: column;
                min-height: auto;
                width: 95%;
                margin: 20px 0;
            }
            
            .image-section {
                padding: 30px 25px;
                min-height: 300px;
            }
            
            .form-section {
                padding: 30px 25px;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <!-- Left Side - Image Section -->
        <div class="image-section">
            <h1>Welcome to Dayflow HRMS</h1>
            <p>Streamline your human resource management with our comprehensive solution. Manage employees, track attendance, process payroll, and more from a single platform.</p>
            
            <div class="features">
                <div class="feature">
                    <i class="fas fa-users"></i>
                    <span>Employee Management</span>
                </div>
                <div class="feature">
                    <i class="fas fa-calendar-check"></i>
                    <span>Attendance Tracking</span>
                </div>
                <div class="feature">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Payroll Processing</span>
                </div>
                <div class="feature">
                    <i class="fas fa-chart-line"></i>
                    <span>Performance Analytics</span>
                </div>
            </div>
        </div>
        
        <!-- Right Side - Form Section -->
        <div class="form-section">
            <div class="logo">
                <h2>Dayflow</h2>
                <p>Human Resource Management System</p>
            </div>
            
            <!-- Error/Success Messages -->
            <?php if(!empty($error)): ?>
                <div class="message error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if(!empty($success)): ?>
                <div class="message success-message"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <!-- Tabs for Login/Signup -->
            <div class="form-tabs">
                <button class="tab-btn <?php echo $action == 'login' ? 'active' : ''; ?>" data-tab="login">Login</button>
                <button class="tab-btn <?php echo $action == 'signup' ? 'active' : ''; ?>" data-tab="signup">Sign Up</button>
            </div>
            
            <!-- Forms Container -->
            <div class="form-container">
                <!-- Login Form -->
                <form method="POST" action="" class="form <?php echo $action == 'login' ? 'active' : ''; ?>" id="login-form">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" required 
                               value="<?php echo isset($_POST['email']) && !isset($_POST['signup']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                    
                    <div class="form-group password-toggle">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required>
                        <button type="button" class="toggle-password" data-target="password">
                            <i class="far fa-eye"></i>
                        </button>
                    </div>
                    
                    <button type="submit" name="login" class="btn-submit">Sign In</button>
                    
                    <div class="form-footer">
                        <p>Don't have an account? <a href="#" class="switch-form" data-form="signup">Create Account</a></p>
                    </div>
                </form>
                
                <!-- Signup Form -->
                <form method="POST" action="" class="form <?php echo $action == 'signup' ? 'active' : ''; ?>" id="signup-form">
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" required 
                               value="<?php echo isset($_POST['first_name']) && isset($_POST['signup']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" required 
                               value="<?php echo isset($_POST['last_name']) && isset($_POST['signup']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="signup_email">Email Address</label>
                        <input type="email" id="signup_email" name="signup_email" required 
                               value="<?php echo isset($_POST['signup_email']) && isset($_POST['signup']) ? htmlspecialchars($_POST['signup_email']) : ''; ?>">
                    </div>
                    
                    <div class="form-group password-toggle">
                        <label for="signup_password">Password</label>
                        <input type="password" id="signup_password" name="signup_password" required>
                        <button type="button" class="toggle-password" data-target="signup_password">
                            <i class="far fa-eye"></i>
                        </button>
                    </div>
                    
                    <div class="form-group password-toggle">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                        <button type="button" class="toggle-password" data-target="confirm_password">
                            <i class="far fa-eye"></i>
                        </button>
                    </div>
                    
                    <button type="submit" name="signup" class="btn-submit">Create Account</button>
                    
                    <div class="form-footer">
                        <p>Already have an account? <a href="#" class="switch-form" data-form="login">Sign In</a></p>
                    </div>
                </form>
            </div>
            
            <!-- Demo Credentials -->
            <div class="demo-credentials">
                <h4>Demo Credentials:</h4>
                <p><strong>Admin:</strong> admin@hrms.com / demo123</p>
                <p><strong>Employee:</strong> employee@hrms.com / demo123</p>
                <p><small>For production, update passwords in the database</small></p>
            </div>
        </div>
    </div>

    <script>
        // Tab switching functionality
        document.querySelectorAll('.tab-btn').forEach(button => {
            button.addEventListener('click', () => {
                const tab = button.getAttribute('data-tab');
                
                // Update active tab button
                document.querySelectorAll('.tab-btn').forEach(btn => {
                    btn.classList.remove('active');
                });
                button.classList.add('active');
                
                // Show the selected form
                document.querySelectorAll('.form').forEach(form => {
                    form.classList.remove('active');
                });
                document.getElementById(tab + '-form').classList.add('active');
                
                // Update URL parameter
                const url = new URL(window.location);
                url.searchParams.set('action', tab);
                window.history.replaceState({}, '', url);
            });
        });
        
        // Switch form links
        document.querySelectorAll('.switch-form').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const form = link.getAttribute('data-form');
                
                // Update active tab button
                document.querySelectorAll('.tab-btn').forEach(btn => {
                    btn.classList.remove('active');
                    if(btn.getAttribute('data-tab') === form) {
                        btn.classList.add('active');
                    }
                });
                
                // Show the selected form
                document.querySelectorAll('.form').forEach(formEl => {
                    formEl.classList.remove('active');
                });
                document.getElementById(form + '-form').classList.add('active');
                
                // Update URL parameter
                const url = new URL(window.location);
                url.searchParams.set('action', form);
                window.history.replaceState({}, '', url);
            });
        });
        
        // Password toggle functionality
        document.querySelectorAll('.toggle-password').forEach(button => {
            button.addEventListener('click', () => {
                const targetId = button.getAttribute('data-target');
                const passwordInput = document.getElementById(targetId);
                const icon = button.querySelector('i');
                
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    passwordInput.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });
        
        // Set active form based on PHP action
        window.addEventListener('DOMContentLoaded', () => {
            const action = '<?php echo $action; ?>';
            if (action) {
                // Show the correct form
                document.querySelectorAll('.form').forEach(form => {
                    form.classList.remove('active');
                });
                document.getElementById(action + '-form').classList.add('active');
            }
        });
    </script>
</body>
</html>