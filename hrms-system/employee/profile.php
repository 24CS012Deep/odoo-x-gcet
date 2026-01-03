<?php
$page_title = "My Profile";
require_once '../includes/header.php';
require_once '../config/database.php';

$user_id = $_SESSION['user_id'];
$conn = getConnection();

// Get user data with enhanced information - FIXED QUERY
$stmt = $conn->prepare("
    SELECT u.*,
           COUNT(DISTINCT CASE WHEN a.status = 'present' THEN a.id END) as present_days,
           COUNT(DISTINCT CASE WHEN a.status = 'absent' THEN a.id END) as absent_days,
           COUNT(DISTINCT CASE WHEN l.status = 'approved' THEN l.id END) as approved_leaves,
           COUNT(DISTINCT CASE WHEN l.status = 'pending' THEN l.id END) as pending_leaves,
           (SELECT overall_score FROM performance WHERE user_id = u.id ORDER BY month_year DESC LIMIT 1) as performance_score
    FROM users u
    LEFT JOIN attendance a ON u.id = a.user_id AND YEAR(a.date) = YEAR(CURDATE())
    LEFT JOIN leave_requests l ON u.id = l.user_id AND YEAR(l.start_date) = YEAR(CURDATE())
    WHERE u.id = ?
    GROUP BY u.id
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get recent activities
$activities_stmt = $conn->prepare("
    (SELECT 'check_in' as type, check_in as time, date, NULL as details 
     FROM attendance 
     WHERE user_id = ? AND check_in IS NOT NULL 
     ORDER BY date DESC, check_in DESC 
     LIMIT 5)
    UNION ALL
    (SELECT 'check_out' as type, check_out as time, date, CONCAT('Hours: ', hours_worked) as details 
     FROM attendance 
     WHERE user_id = ? AND check_out IS NOT NULL 
     ORDER BY date DESC, check_out DESC 
     LIMIT 5)
    UNION ALL
    (SELECT 'leave' as type, created_at as time, start_date as date, 
            CONCAT(leave_type, ' (', status, ')') as details 
     FROM leave_requests 
     WHERE user_id = ? 
     ORDER BY created_at DESC 
     LIMIT 5)
    ORDER BY time DESC 
    LIMIT 10
");
$activities_stmt->bind_param("iii", $user_id, $user_id, $user_id);
$activities_stmt->execute();
$activities = $activities_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$activities_stmt->close();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = trim($_POST['first_name'] ?? $user['first_name']);
    $last_name = trim($_POST['last_name'] ?? $user['last_name']);
    $phone = trim($_POST['phone'] ?? $user['phone']);
    $address = trim($_POST['address'] ?? $user['address']);
    
    // Handle profile picture upload
    $profile_picture = $user['profile_picture'];
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_type = $_FILES['profile_picture']['type'];
        $file_size = $_FILES['profile_picture']['size'];
        
        if (in_array($file_type, $allowed_types)) {
            if ($file_size <= 5 * 1024 * 1024) { // 5MB limit
                $file_ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
                $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $file_ext;
                $upload_path = '../assets/images/profiles/' . $new_filename;
                
                // Create directory if it doesn't exist
                if (!file_exists('../assets/images/profiles/')) {
                    mkdir('../assets/images/profiles/', 0777, true);
                }
                
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                    // Delete old profile picture if not default
                    if ($profile_picture != 'default.png' && $profile_picture != '') {
                        $old_path = '../assets/images/profiles/' . $profile_picture;
                        if (file_exists($old_path)) {
                            unlink($old_path);
                        }
                    }
                    $profile_picture = $new_filename;
                } else {
                    $error_message = "Failed to upload profile picture.";
                }
            } else {
                $error_message = "Profile picture size must be less than 5MB.";
            }
        } else {
            $error_message = "Only JPG, PNG, GIF, and WebP images are allowed.";
        }
    }
    
    if (!isset($error_message)) {
        $update_stmt = $conn->prepare("
            UPDATE users 
            SET first_name = ?, last_name = ?, phone = ?, address = ?, 
                profile_picture = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $update_stmt->bind_param("sssssi", $first_name, $last_name, $phone, $address, $profile_picture, $user_id);
        
        if ($update_stmt->execute()) {
            // Update session variables
            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name'] = $last_name;
            $_SESSION['profile_picture'] = $profile_picture;
            
            $success_message = "Profile updated successfully!";
            
            // Refresh user data
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        } else {
            $error_message = "Failed to update profile. Please try again.";
        }
        $update_stmt->close();
    }
}

$conn->close();

// Check if profile picture exists, if not use default
$profile_pic_path = '../assets/images/profiles/' . ($user['profile_picture'] ?: 'default.png');
if (!file_exists($profile_pic_path)) {
    $user['profile_picture'] = 'default.png';
}
?>

<div class="page-header">
    <h2><i class="fas fa-user-circle"></i> My Profile</h2>
    <div class="profile-status">
        <span class="status-badge status-active">
            <i class="fas fa-circle"></i> Active
        </span>
        <span class="member-since">
            Member since <?php echo date('M Y', strtotime($user['created_at'])); ?>
        </span>
    </div>
</div>

<?php if(isset($success_message)): ?>
<div class="alert alert-success alert-dismissible">
    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
    <button type="button" class="alert-close" onclick="this.parentElement.style.display='none'">
        <i class="fas fa-times"></i>
    </button>
</div>
<?php endif; ?>

<?php if(isset($error_message)): ?>
<div class="alert alert-error alert-dismissible">
    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
    <button type="button" class="alert-close" onclick="this.parentElement.style.display='none'">
        <i class="fas fa-times"></i>
    </button>
</div>
<?php endif; ?>

<div class="profile-container">
    <!-- Left Sidebar -->
    <div class="profile-sidebar">
        <!-- Profile Card -->
        <div class="profile-card">
            <div class="profile-image-container">
                <div class="profile-image-wrapper">
                    <img src="../assets/images/profiles/<?php echo htmlspecialchars($user['profile_picture'] ?: 'default.png'); ?>" 
                         alt="Profile Picture"
                         id="profilePreview"
                         class="profile-image"
                         onerror="this.src='../assets/images/default.png'">
                    <div class="profile-image-overlay">
                        <label for="profilePictureInput" class="profile-upload-btn">
                            <i class="fas fa-camera"></i>
                            <span>Change Photo</span>
                        </label>
                    </div>
                </div>
                <form id="profilePictureForm" enctype="multipart/form-data" style="display: none;">
                    <input type="file" 
                           name="profile_picture" 
                           id="profilePictureInput" 
                           accept="image/*"
                           onchange="uploadProfilePicture(this)">
                </form>
            </div>
            
            <div class="profile-info">
                <h3 class="profile-name">
                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                </h3>
                <p class="profile-title">
                    <i class="fas fa-briefcase"></i>
                    <?php echo htmlspecialchars($user['job_title'] ?: 'Not assigned'); ?>
                </p>
                <p class="profile-empid">
                    <i class="fas fa-id-card"></i>
                    ID: <?php echo htmlspecialchars($user['employee_id']); ?>
                </p>
                
                <div class="profile-stats">
                    <div class="stat-item">
                        <div class="stat-icon stat-attendance">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo $user['present_days'] ?: 0; ?></div>
                            <div class="stat-label">Days Present</div>
                        </div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-icon stat-leave">
                            <i class="fas fa-plane"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo $user['approved_leaves'] ?: 0; ?></div>
                            <div class="stat-label">Leaves Approved</div>
                        </div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-icon stat-performance">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo $user['performance_score'] ?: 'N/A'; ?></div>
                            <div class="stat-label">Performance Score</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Information -->
        <div class="quick-info-card">
            <h4><i class="fas fa-info-circle"></i> Quick Information</h4>
            <div class="info-list">
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">Email</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['email']); ?></div>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-phone"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">Phone</div>
                        <div class="info-value">
                            <?php echo $user['phone'] ? htmlspecialchars($user['phone']) : '<span class="text-muted">Not set</span>'; ?>
                        </div>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-building"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">Department</div>
                        <div class="info-value">
                            <?php echo $user['department'] ? htmlspecialchars($user['department']) : '<span class="text-muted">Not assigned</span>'; ?>
                        </div>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">Joined Date</div>
                        <div class="info-value">
                            <?php echo date('F d, Y', strtotime($user['hire_date'])); ?>
                        </div>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">Monthly Salary</div>
                        <div class="info-value">
                            $<?php echo number_format($user['salary'] ?: 0, 2); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="profile-content">
        <!-- Edit Profile Form -->
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-user-edit"></i> Edit Profile Information</h3>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" class="profile-form" id="profileForm">
                    <div class="form-section">
                        <h4>Personal Information</h4>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">
                                    First Name <span class="required">*</span>
                                </label>
                                <input type="text" 
                                       id="first_name" 
                                       name="first_name" 
                                       value="<?php echo htmlspecialchars($user['first_name']); ?>"
                                       required
                                       class="form-control"
                                       placeholder="Enter your first name">
                            </div>
                            
                            <div class="form-group">
                                <label for="last_name">
                                    Last Name <span class="required">*</span>
                                </label>
                                <input type="text" 
                                       id="last_name" 
                                       name="last_name" 
                                       value="<?php echo htmlspecialchars($user['last_name']); ?>"
                                       required
                                       class="form-control"
                                       placeholder="Enter your last name">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" 
                                       id="email" 
                                       name="email" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>"
                                       disabled
                                       class="form-control disabled"
                                       placeholder="Email cannot be changed">
                                <small class="form-help">Contact HR to change email address</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" 
                                       id="phone" 
                                       name="phone" 
                                       value="<?php echo htmlspecialchars($user['phone']); ?>"
                                       class="form-control"
                                       placeholder="+1 (234) 567-8900"
                                       pattern="[\+\d\s\-\(\)]{10,}">
                                <small class="form-help">Format: +1 (234) 567-8900</small>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea id="address" 
                                      name="address" 
                                      rows="3"
                                      class="form-control"
                                      placeholder="Enter your complete address"><?php echo htmlspecialchars($user['address']); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h4>Professional Information</h4>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Employee ID</label>
                                <input type="text" 
                                       value="<?php echo htmlspecialchars($user['employee_id']); ?>"
                                       disabled
                                       class="form-control disabled">
                            </div>
                            
                            <div class="form-group">
                                <label>Job Title</label>
                                <input type="text" 
                                       value="<?php echo htmlspecialchars($user['job_title'] ?: 'Not assigned'); ?>"
                                       disabled
                                       class="form-control disabled">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Department</label>
                                <input type="text" 
                                       value="<?php echo htmlspecialchars($user['department'] ?: 'Not assigned'); ?>"
                                       disabled
                                       class="form-control disabled">
                            </div>
                            
                            <div class="form-group">
                                <label>Employment Type</label>
                                <input type="text" 
                                       value="Full Time"
                                       disabled
                                       class="form-control disabled">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Salary Information</label>
                            <div class="salary-display">
                                <div class="salary-item">
                                    <span class="salary-label">Monthly Salary:</span>
                                    <span class="salary-value">$<?php echo number_format($user['salary'] ?: 0, 2); ?></span>
                                </div>
                                <div class="salary-item">
                                    <span class="salary-label">Annual Salary:</span>
                                    <span class="salary-value">$<?php echo number_format(($user['salary'] ?: 0) * 12, 2); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h4>Account Settings</h4>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Account Status</label>
                                <div class="status-display">
                                    <span class="badge badge-success">Active</span>
                                    <span class="status-date">Since <?php echo date('M d, Y', strtotime($user['created_at'])); ?></span>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Last Login</label>
                                <div class="last-login">
                                    <i class="fas fa-clock"></i>
                                    <span><?php echo date('M d, Y h:i A'); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Security</label>
                            <div class="security-actions">
                                <button type="button" class="btn-security" onclick="changePassword()">
                                    <i class="fas fa-key"></i> Change Password
                                </button>
                                <button type="button" class="btn-security" onclick="enableTwoFactor()">
                                    <i class="fas fa-shield-alt"></i> Two-Factor Auth
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-save">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-undo"></i> Reset
                        </button>
                        <a href="dashboard.php" class="btn btn-outline">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Recent Activities -->
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Recent Activities</h3>
                <a href="dashboard.php" class="btn-view-all">View All</a>
            </div>
            <div class="card-body">
                <div class="activities-list">
                    <?php if(count($activities) > 0): ?>
                        <?php foreach($activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <?php if($activity['type'] == 'check_in'): ?>
                                    <i class="fas fa-sign-in-alt text-success"></i>
                                <?php elseif($activity['type'] == 'check_out'): ?>
                                    <i class="fas fa-sign-out-alt text-warning"></i>
                                <?php else: ?>
                                    <i class="fas fa-plane text-info"></i>
                                <?php endif; ?>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">
                                    <?php if($activity['type'] == 'check_in'): ?>
                                        Checked in at <?php echo $activity['time'] ? date('h:i A', strtotime($activity['time'])) : 'N/A'; ?>
                                    <?php elseif($activity['type'] == 'check_out'): ?>
                                        Checked out at <?php echo $activity['time'] ? date('h:i A', strtotime($activity['time'])) : 'N/A'; ?>
                                    <?php else: ?>
                                        <?php echo ucfirst($activity['details']); ?> leave applied
                                    <?php endif; ?>
                                </div>
                                <div class="activity-meta">
                                    <span class="activity-date">
                                        <?php echo $activity['date'] ? date('M d, Y', strtotime($activity['date'])) : 'N/A'; ?>
                                    </span>
                                    <?php if($activity['details'] && $activity['type'] == 'check_out'): ?>
                                        <span class="activity-details">
                                            <?php echo $activity['details']; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-activities">
                            <i class="fas fa-clock"></i>
                            <p>No recent activities found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div class="modal" id="changePasswordModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-key"></i> Change Password</h3>
            <button class="modal-close" onclick="closeModal('changePasswordModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="passwordForm">
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" 
                           id="current_password" 
                           name="current_password"
                           required
                           class="form-control"
                           placeholder="Enter current password">
                </div>
                
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" 
                           id="new_password" 
                           name="new_password"
                           required
                           class="form-control"
                           placeholder="Enter new password"
                           minlength="8">
                    <small class="form-help">Minimum 8 characters with letters and numbers</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" 
                           id="confirm_password" 
                           name="confirm_password"
                           required
                           class="form-control"
                           placeholder="Confirm new password">
                </div>
                
                <div class="password-strength">
                    <div class="strength-meter">
                        <div class="strength-bar" id="strengthBar"></div>
                    </div>
                    <div class="strength-label" id="strengthLabel">Password strength</div>
                </div>
                
                <div class="password-requirements">
                    <h5>Password Requirements:</h5>
                    <ul>
                        <li id="req-length">Minimum 8 characters</li>
                        <li id="req-uppercase">At least one uppercase letter</li>
                        <li id="req-lowercase">At least one lowercase letter</li>
                        <li id="req-number">At least one number</li>
                        <li id="req-special">At least one special character</li>
                    </ul>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('changePasswordModal')">
                Cancel
            </button>
            <button type="button" class="btn btn-primary" onclick="submitPasswordChange()">
                <i class="fas fa-save"></i> Change Password
            </button>
        </div>
    </div>
</div>

<script>
// Profile picture upload
function uploadProfilePicture(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const maxSize = 5 * 1024 * 1024; // 5MB
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        
        // Validate file type
        if (!allowedTypes.includes(file.type)) {
            HRMS.utils.showToast('Only JPG, PNG, GIF, and WebP images are allowed', 'error');
            return;
        }
        
        // Validate file size
        if (file.size > maxSize) {
            HRMS.utils.showToast('File size must be less than 5MB', 'error');
            return;
        }
        
        // Preview image
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('profilePreview').src = e.target.result;
        };
        reader.readAsDataURL(file);
        
        // Submit form
        const formData = new FormData();
        formData.append('profile_picture', file);
        formData.append('action', 'update_picture');
        
        fetch('upload_profile_picture.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                HRMS.utils.showToast('Profile picture updated successfully!', 'success');
                // Update all profile pictures on the page
                document.querySelectorAll('.profile-pic, .profile-image').forEach(img => {
                    if (img.src.includes('profile')) {
                        img.src = data.image_url + '?t=' + Date.now();
                    }
                });
                // Update navbar profile picture
                const navbarPic = document.querySelector('.profile-pic');
                if (navbarPic) {
                    navbarPic.src = data.image_url + '?t=' + Date.now();
                }
            } else {
                HRMS.utils.showToast(data.message || 'Failed to upload picture', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            HRMS.utils.showToast('Network error. Please try again.', 'error');
        });
    }
}

// Change password modal
function changePassword() {
    document.getElementById('changePasswordModal').style.display = 'flex';
    initPasswordStrengthChecker();
}

// Initialize password strength checker
function initPasswordStrengthChecker() {
    const passwordInput = document.getElementById('new_password');
    const strengthBar = document.getElementById('strengthBar');
    const strengthLabel = document.getElementById('strengthLabel');
    const requirements = {
        length: document.getElementById('req-length'),
        uppercase: document.getElementById('req-uppercase'),
        lowercase: document.getElementById('req-lowercase'),
        number: document.getElementById('req-number'),
        special: document.getElementById('req-special')
    };
    
    passwordInput.addEventListener('input', function() {
        const password = this.value;
        let strength = 0;
        
        // Check requirements
        const hasLength = password.length >= 8;
        const hasUppercase = /[A-Z]/.test(password);
        const hasLowercase = /[a-z]/.test(password);
        const hasNumber = /\d/.test(password);
        const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);
        
        // Update requirement indicators
        requirements.length.classList.toggle('met', hasLength);
        requirements.uppercase.classList.toggle('met', hasUppercase);
        requirements.lowercase.classList.toggle('met', hasLowercase);
        requirements.number.classList.toggle('met', hasNumber);
        requirements.special.classList.toggle('met', hasSpecial);
        
        // Calculate strength
        if (hasLength) strength++;
        if (hasUppercase) strength++;
        if (hasLowercase) strength++;
        if (hasNumber) strength++;
        if (hasSpecial) strength++;
        
        // Update strength bar and label
        const percent = (strength / 5) * 100;
        strengthBar.style.width = percent + '%';
        
        if (strength <= 1) {
            strengthBar.className = 'strength-bar weak';
            strengthLabel.textContent = 'Weak Password';
            strengthLabel.className = 'strength-label weak';
        } else if (strength <= 3) {
            strengthBar.className = 'strength-bar medium';
            strengthLabel.textContent = 'Medium Password';
            strengthLabel.className = 'strength-label medium';
        } else {
            strengthBar.className = 'strength-bar strong';
            strengthBar.style.background = 'linear-gradient(90deg, #28a745, #20c997)';
            strengthLabel.textContent = 'Strong Password';
            strengthLabel.className = 'strength-label strong';
        }
    });
}

// Submit password change
function submitPasswordChange() {
    const currentPassword = document.getElementById('current_password').value;
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    // Validation
    if (!currentPassword || !newPassword || !confirmPassword) {
        HRMS.utils.showToast('Please fill all password fields', 'error');
        return;
    }
    
    if (newPassword !== confirmPassword) {
        HRMS.utils.showToast('New passwords do not match', 'error');
        return;
    }
    
    if (newPassword.length < 8) {
        HRMS.utils.showToast('Password must be at least 8 characters', 'error');
        return;
    }
    
    // Password strength validation
    const hasUppercase = /[A-Z]/.test(newPassword);
    const hasLowercase = /[a-z]/.test(newPassword);
    const hasNumber = /\d/.test(newPassword);
    
    if (!hasUppercase || !hasLowercase || !hasNumber) {
        HRMS.utils.showToast('Password must contain uppercase, lowercase letters and numbers', 'error');
        return;
    }
    
    // Submit password change
    fetch('change_password.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            current_password: currentPassword,
            new_password: newPassword
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            HRMS.utils.showToast('Password changed successfully!', 'success');
            closeModal('changePasswordModal');
            // Clear password fields
            document.getElementById('passwordForm').reset();
            document.querySelectorAll('.password-requirements li').forEach(li => {
                li.classList.remove('met');
            });
            document.getElementById('strengthBar').style.width = '0%';
            document.getElementById('strengthLabel').textContent = 'Password strength';
        } else {
            HRMS.utils.showToast(data.message || 'Failed to change password', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        HRMS.utils.showToast('Network error. Please try again.', 'error');
    });
}

// Two-factor authentication
function enableTwoFactor() {
    HRMS.utils.showToast('Two-factor authentication feature coming soon!', 'info');
}

// Phone number formatting
document.addEventListener('DOMContentLoaded', function() {
    const phoneInput = document.getElementById('phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 0) {
                if (value.length <= 3) {
                    value = value;
                } else if (value.length <= 6) {
                    value = '(' + value.substring(0, 3) + ') ' + value.substring(3);
                } else {
                    value = '(' + value.substring(0, 3) + ') ' + value.substring(3, 6) + '-' + value.substring(6, 10);
                }
                e.target.value = value;
            }
        });
    }
    
    // Form validation
    const profileForm = document.getElementById('profileForm');
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            const firstName = document.getElementById('first_name').value.trim();
            const lastName = document.getElementById('last_name').value.trim();
            
            if (!firstName || !lastName) {
                e.preventDefault();
                HRMS.utils.showToast('Please fill all required fields', 'error');
                return;
            }
            
            // Show loading
            HRMS.utils.showLoader(true);
        });
    }
    
    // Close modal function
    window.closeModal = function(modalId) {
        document.getElementById(modalId).style.display = 'none';
    };
    
    // Close modals when clicking outside
    window.onclick = function(event) {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        });
    };
    
    // Profile picture upload trigger
    document.querySelector('.profile-upload-btn').addEventListener('click', function() {
        document.getElementById('profilePictureInput').click();
    });
});
</script>

<style>
/* Your CSS styles remain the same as in your file */
.profile-container {
    display: grid;
    grid-template-columns: 350px 1fr;
    gap: 30px;
    margin-top: 20px;
}

.profile-status {
    display: flex;
    align-items: center;
    gap: 15px;
    font-size: 0.95em;
}

.status-badge {
    padding: 6px 15px;
    border-radius: 20px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.status-active {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.status-badge i {
    font-size: 0.8em;
}

.member-since {
    color: #666;
}

/* ... (rest of your CSS remains exactly the same) ... */

</style>

<?php require_once '../includes/footer.php'; ?>