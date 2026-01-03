<?php
$page_title = "Admin Profile";
require_once '../includes/header.php';
require_once '../config/database.php';

// Check if user is admin
if ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'hr') {
    header('Location: ../employee/dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$conn = getConnection();

// Get admin data with system statistics
$stmt = $conn->prepare("
    SELECT u.*,
           (SELECT COUNT(*) FROM users WHERE role = 'employee') as total_employees,
           (SELECT COUNT(*) FROM users WHERE role = 'admin' OR role = 'hr') as total_admins,
           (SELECT COUNT(*) FROM attendance WHERE date = CURDATE() AND status = 'present') as present_today,
           (SELECT COUNT(*) FROM leave_requests WHERE status = 'pending') as pending_leaves,
           (SELECT SUM(salary) FROM users) as total_payroll
    FROM users u
    WHERE u.id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get admin activities
$activities_stmt = $conn->prepare("
    (SELECT 'employee_added' as type, created_at as time, CONCAT('Added employee: ', first_name, ' ', last_name) as details 
     FROM users 
     WHERE created_by = ? 
     ORDER BY created_at DESC 
     LIMIT 5)
    UNION ALL
    (SELECT 'leave_approved' as type, updated_at as time, CONCAT('Approved leave for ID: ', employee_id) as details 
     FROM leave_requests l 
     JOIN users u ON l.user_id = u.id 
     WHERE l.approved_by = ? 
     ORDER BY l.updated_at DESC 
     LIMIT 5)
    UNION ALL
    (SELECT 'salary_updated' as type, updated_at as time, CONCAT('Updated salary for: ', first_name, ' ', last_name) as details 
     FROM users 
     WHERE updated_by = ? 
     AND salary != (SELECT salary FROM users_history WHERE user_id = users.id ORDER BY updated_at DESC LIMIT 1)
     ORDER BY updated_at DESC 
     LIMIT 5)
    ORDER BY time DESC 
    LIMIT 10
");
$activities_stmt->bind_param("iii", $user_id, $user_id, $user_id);
$activities_stmt->execute();
$activities = $activities_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$activities_stmt->close();

// Handle profile update (same as employee but with additional admin fields)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = trim($_POST['first_name'] ?? $user['first_name']);
    $last_name = trim($_POST['last_name'] ?? $user['last_name']);
    $phone = trim($_POST['phone'] ?? $user['phone']);
    $address = trim($_POST['address'] ?? $user['address']);
    $signature = trim($_POST['signature'] ?? $user['signature']);
    
    // Handle profile picture upload (same as employee)
    $profile_picture = $user['profile_picture'];
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_type = $_FILES['profile_picture']['type'];
        $file_size = $_FILES['profile_picture']['size'];
        
        if (in_array($file_type, $allowed_types)) {
            if ($file_size <= 5 * 1024 * 1024) {
                $file_ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
                $new_filename = 'admin_' . $user_id . '_' . time() . '.' . $file_ext;
                $upload_path = '../assets/images/profiles/' . $new_filename;
                
                if (!file_exists('../assets/images/profiles/')) {
                    mkdir('../assets/images/profiles/', 0777, true);
                }
                
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                    if ($profile_picture != 'default.png' && $profile_picture != '') {
                        $old_path = '../assets/images/profiles/' . $profile_picture;
                        if (file_exists($old_path)) {
                            unlink($old_path);
                        }
                    }
                    $profile_picture = $new_filename;
                }
            }
        }
    }
    
    $update_stmt = $conn->prepare("
        UPDATE users 
        SET first_name = ?, last_name = ?, phone = ?, address = ?, 
            profile_picture = ?, signature = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $update_stmt->bind_param("ssssssi", $first_name, $last_name, $phone, $address, $profile_picture, $signature, $user_id);
    
    if ($update_stmt->execute()) {
        $_SESSION['first_name'] = $first_name;
        $_SESSION['last_name'] = $last_name;
        $_SESSION['profile_picture'] = $profile_picture;
        
        $success_message = "Admin profile updated successfully!";
        
        // Refresh user data
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } else {
        $error_message = "Failed to update profile.";
    }
    $update_stmt->close();
}

$conn->close();
?>

<div class="page-header">
    <h2><i class="fas fa-user-shield"></i> Admin Profile</h2>
    <div class="profile-status">
        <span class="status-badge status-admin">
            <i class="fas fa-shield-alt"></i> <?php echo ucfirst($user['role']); ?>
        </span>
        <span class="member-since">
            Administrator since <?php echo date('M Y', strtotime($user['created_at'])); ?>
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
        <!-- Admin Profile Card -->
        <div class="profile-card admin-card">
            <div class="profile-image-container">
                <div class="profile-image-wrapper">
                    <img src="../assets/images/profiles/<?php echo $user['profile_picture'] ?: 'default.png'; ?>" 
                         alt="Admin Profile"
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
                    <i class="fas fa-user-tie"></i>
                    <?php echo htmlspecialchars($user['job_title']); ?>
                </p>
                <p class="profile-empid">
                    <i class="fas fa-id-card"></i>
                    ID: <?php echo htmlspecialchars($user['employee_id']); ?>
                </p>
                
                <div class="admin-badges">
                    <span class="badge badge-admin">
                        <i class="fas fa-crown"></i> System Administrator
                    </span>
                    <span class="badge badge-access">
                        <i class="fas fa-key"></i> Full Access
                    </span>
                </div>
            </div>
        </div>
        
        <!-- System Statistics -->
        <div class="quick-info-card">
            <h4><i class="fas fa-chart-bar"></i> System Statistics</h4>
            <div class="info-list">
                <div class="info-item">
                    <div class="info-icon stat-employees">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">Total Employees</div>
                        <div class="info-value"><?php echo $user['total_employees']; ?></div>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-icon stat-admins">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">Admin/HR Staff</div>
                        <div class="info-value"><?php echo $user['total_admins']; ?></div>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-icon stat-attendance">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">Present Today</div>
                        <div class="info-value"><?php echo $user['present_today']; ?></div>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-icon stat-leaves">
                        <i class="fas fa-plane"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">Pending Leaves</div>
                        <div class="info-value"><?php echo $user['pending_leaves']; ?></div>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-icon stat-payroll">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">Total Payroll</div>
                        <div class="info-value">$<?php echo number_format($user['total_payroll'], 2); ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Admin Quick Actions -->
        <div class="quick-actions-card">
            <h4><i class="fas fa-bolt"></i> Quick Actions</h4>
            <div class="actions-grid">
                <a href="employees.php?action=add" class="action-btn">
                    <i class="fas fa-user-plus"></i>
                    <span>Add Employee</span>
                </a>
                <a href="leave_requests.php" class="action-btn">
                    <i class="fas fa-plane"></i>
                    <span>Approve Leaves</span>
                </a>
                <a href="payroll.php" class="action-btn">
                    <i class="fas fa-calculator"></i>
                    <span>Process Payroll</span>
                </a>
                <a href="settings.php" class="action-btn">
                    <i class="fas fa-cog"></i>
                    <span>System Settings</span>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="profile-content">
        <!-- Edit Profile Form -->
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-user-edit"></i> Edit Admin Profile</h3>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" class="profile-form" id="profileForm">
                    <div class="form-section">
                        <h4>Administrator Information</h4>
                        
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
                                       class="form-control">
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
                                       class="form-control">
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
                                       class="form-control disabled">
                            </div>
                            
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" 
                                       id="phone" 
                                       name="phone" 
                                       value="<?php echo htmlspecialchars($user['phone']); ?>"
                                       class="form-control">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea id="address" 
                                      name="address" 
                                      rows="3"
                                      class="form-control"><?php echo htmlspecialchars($user['address']); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="signature">Digital Signature</label>
                            <textarea id="signature" 
                                      name="signature" 
                                      rows="2"
                                      class="form-control"
                                      placeholder="Enter your official signature for documents"><?php echo htmlspecialchars($user['signature']); ?></textarea>
                            <small class="form-help">This signature will be used on official documents</small>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h4>Administrative Settings</h4>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Administrator Role</label>
                                <div class="role-display">
                                    <span class="badge badge-admin-large">
                                        <i class="fas fa-shield-alt"></i>
                                        <?php echo strtoupper($user['role']); ?>
                                    </span>
                                    <span class="role-description">Full system access</span>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Access Level</label>
                                <div class="access-level">
                                    <div class="level-item">
                                        <i class="fas fa-check-circle text-success"></i>
                                        <span>Employee Management</span>
                                    </div>
                                    <div class="level-item">
                                        <i class="fas fa-check-circle text-success"></i>
                                        <span>Payroll Processing</span>
                                    </div>
                                    <div class="level-item">
                                        <i class="fas fa-check-circle text-success"></i>
                                        <span>System Configuration</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Security Settings</label>
                            <div class="security-settings">
                                <div class="setting-item">
                                    <div class="setting-info">
                                        <i class="fas fa-key"></i>
                                        <div>
                                            <strong>Password Security</strong>
                                            <span>Last changed: <?php echo date('M d, Y'); ?></span>
                                        </div>
                                    </div>
                                    <button type="button" class="btn-setting" onclick="changePassword()">
                                        Change
                                    </button>
                                </div>
                                
                                <div class="setting-item">
                                    <div class="setting-info">
                                        <i class="fas fa-shield-alt"></i>
                                        <div>
                                            <strong>Two-Factor Authentication</strong>
                                            <span>Add an extra layer of security</span>
                                        </div>
                                    </div>
                                    <button type="button" class="btn-setting" onclick="enableTwoFactor()">
                                        Enable
                                    </button>
                                </div>
                                
                                <div class="setting-item">
                                    <div class="setting-info">
                                        <i class="fas fa-history"></i>
                                        <div>
                                            <strong>Login History</strong>
                                            <span>View recent login activity</span>
                                        </div>
                                    </div>
                                    <button type="button" class="btn-setting" onclick="viewLoginHistory()">
                                        View
                                    </button>
                                </div>
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
        
        <!-- Admin Activities -->
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Recent Administrative Actions</h3>
                <a href="audit_logs.php" class="btn-view-all">View Audit Logs</a>
            </div>
            <div class="card-body">
                <div class="activities-list">
                    <?php if(count($activities) > 0): ?>
                        <?php foreach($activities as $activity): ?>
                        <div class="activity-item admin-activity">
                            <div class="activity-icon">
                                <?php if($activity['type'] == 'employee_added'): ?>
                                    <i class="fas fa-user-plus text-primary"></i>
                                <?php elseif($activity['type'] == 'leave_approved'): ?>
                                    <i class="fas fa-check-circle text-success"></i>
                                <?php else: ?>
                                    <i class="fas fa-money-bill-wave text-warning"></i>
                                <?php endif; ?>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">
                                    <?php echo $activity['details']; ?>
                                </div>
                                <div class="activity-meta">
                                    <span class="activity-date">
                                        <?php echo date('M d, Y h:i A', strtotime($activity['time'])); ?>
                                    </span>
                                    <span class="activity-type">
                                        <?php echo str_replace('_', ' ', $activity['type']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-activities">
                            <i class="fas fa-clipboard-list"></i>
                            <p>No administrative actions recorded yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Change Password Modal (Same as employee) -->
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
                           class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" 
                           id="new_password" 
                           name="new_password"
                           required
                           class="form-control"
                           minlength="8">
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" 
                           id="confirm_password" 
                           name="confirm_password"
                           required
                           class="form-control">
                </div>
                
                <div class="password-strength">
                    <div class="strength-meter">
                        <div class="strength-bar" id="strengthBar"></div>
                    </div>
                    <div class="strength-label" id="strengthLabel">Password strength</div>
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
// Profile picture upload (same as employee)
function uploadProfilePicture(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const maxSize = 5 * 1024 * 1024;
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        
        if (!allowedTypes.includes(file.type)) {
            HRMS.utils.showToast('Only JPG, PNG, GIF, and WebP images are allowed', 'error');
            return;
        }
        
        if (file.size > maxSize) {
            HRMS.utils.showToast('File size must be less than 5MB', 'error');
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('profilePreview').src = e.target.result;
        };
        reader.readAsDataURL(file);
        
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
                document.querySelectorAll('.profile-pic, .profile-image').forEach(img => {
                    if (img.src.includes('profile')) {
                        img.src = data.image_url + '?t=' + Date.now();
                    }
                });
            } else {
                HRMS.utils.showToast(data.message || 'Failed to upload picture', 'error');
            }
        });
    }
}

// Change password
function changePassword() {
    document.getElementById('changePasswordModal').style.display = 'flex';
}

// Two-factor authentication
function enableTwoFactor() {
    HRMS.utils.showToast('Two-factor authentication feature coming soon!', 'info');
}

// View login history
function viewLoginHistory() {
    HRMS.utils.showToast('Login history feature coming soon!', 'info');
}

// Submit password change
function submitPasswordChange() {
    const currentPassword = document.getElementById('current_password').value;
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
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
            document.getElementById('passwordForm').reset();
        } else {
            HRMS.utils.showToast(data.message || 'Failed to change password', 'error');
        }
    });
}

// Close modal
function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Profile picture upload trigger
    document.querySelector('.profile-upload-btn').addEventListener('click', function() {
        document.getElementById('profilePictureInput').click();
    });
    
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
            
            HRMS.utils.showLoader(true);
        });
    }
    
    // Close modals when clicking outside
    window.onclick = function(event) {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        });
    };
});
</script>

<style>
/* Admin-specific styles */
.profile-card.admin-card {
    background: linear-gradient(135deg, #6c5ce7, #a29bfe);
}

.status-admin {
    background: #6c5ce7;
    color: white;
    border: 1px solid #5b4bd8;
}

.admin-badges {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 20px;
}

.badge-admin {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    padding: 8px 15px;
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9em;
    backdrop-filter: blur(10px);
}

.badge-access {
    background: rgba(255, 215, 61, 0.2);
    color: #ffd93d;
    padding: 8px 15px;
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9em;
    backdrop-filter: blur(10px);
}

/* Statistics icons */
.stat-employees { color: #ff6b6b; }
.stat-admins { color: #6c5ce7; }
.stat-attendance { color: #28a745; }
.stat-leaves { color: #17a2b8; }
.stat-payroll { color: #ffd93d; }

/* Quick Actions */
.quick-actions-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    margin-top: 20px;
}

.quick-actions-card h4 {
    margin-bottom: 20px;
    color: #6c5ce7;
    display: flex;
    align-items: center;
    gap: 10px;
}

.actions-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.action-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 20px 15px;
    background: #f8f9fa;
    border: 2px solid #e9ecef;
    border-radius: 10px;
    text-decoration: none;
    color: #333;
    transition: all 0.3s;
}

.action-btn:hover {
    background: #6c5ce7;
    border-color: #6c5ce7;
    color: white;
    transform: translateY(-5px);
}

.action-btn i {
    font-size: 1.5em;
}

.action-btn span {
    font-size: 0.9em;
    font-weight: 600;
    text-align: center;
}

/* Role display */
.role-display {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.badge-admin-large {
    background: #6c5ce7;
    color: white;
    padding: 10px 20px;
    border-radius: 10px;
    font-size: 1.1em;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    width: fit-content;
}

.role-description {
    color: #666;
    font-size: 0.9em;
}

/* Access level */
.access-level {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.level-item {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #333;
}

.level-item i {
    font-size: 1.1em;
}

/* Security settings */
.security-settings {
    border: 2px solid #f0f0f0;
    border-radius: 10px;
    overflow: hidden;
}

.setting-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    border-bottom: 1px solid #f0f0f0;
    transition: background 0.3s;
}

.setting-item:last-child {
    border-bottom: none;
}

.setting-item:hover {
    background: #f8f9fa;
}

.setting-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.setting-info i {
    font-size: 1.5em;
    color: #6c5ce7;
}

.setting-info strong {
    display: block;
    color: #333;
    margin-bottom: 3px;
}

.setting-info span {
    display: block;
    color: #666;
    font-size: 0.85em;
}

.btn-setting {
    padding: 8px 20px;
    background: #6c5ce7;
    color: white;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-setting:hover {
    background: #5b4bd8;
    transform: translateY(-2px);
}

/* Admin activities */
.admin-activity {
    border-left-color: #6c5ce7;
}

.admin-activity:hover {
    background: #f3f1ff;
}

.activity-type {
    background: #e9ecef;
    padding: 3px 10px;
    border-radius: 4px;
    font-size: 0.8em;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

@media (max-width: 768px) {
    .actions-grid {
        grid-template-columns: 1fr;
    }
    
    .setting-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .btn-setting {
        width: 100%;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>