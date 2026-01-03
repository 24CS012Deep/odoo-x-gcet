// Utility Functions
class HRMSUtils {
    static formatDate(date) {
        return new Date(date).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

    static formatTime(date) {
        return new Date(date).toLocaleTimeString('en-US', {
            hour12: true,
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    static showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            <span>${message}</span>
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.add('show');
        }, 10);
        
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => {
                document.body.removeChild(toast);
            }, 300);
        }, 3000);
    }

    static showLoader(show = true) {
        let loader = document.getElementById('page-loader');
        if (!loader && show) {
            loader = document.createElement('div');
            loader.id = 'page-loader';
            loader.innerHTML = `
                <div class="loader-content">
                    <div class="loader-spinner"></div>
                    <div class="loader-text">Loading...</div>
                </div>
            `;
            document.body.appendChild(loader);
        }
        
        if (loader) {
            loader.style.display = show ? 'flex' : 'none';
        }
    }

    static confirmAction(message, callback) {
        if (confirm(message)) {
            callback();
        }
    }

    static formatCurrency(amount) {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(amount);
    }
}

// Attendance Functions
class AttendanceManager {
    static async checkIn() {
        try {
            HRMSUtils.showLoader(true);
            const response = await fetch('../employee/check_in_out.php?action=checkin');
            const data = await response.json();
            
            if (data.success) {
                HRMSUtils.showToast('Checked in successfully!');
                setTimeout(() => location.reload(), 1500);
            } else {
                HRMSUtils.showToast(data.message, 'error');
            }
        } catch (error) {
            HRMSUtils.showToast('Network error. Please try again.', 'error');
        } finally {
            HRMSUtils.showLoader(false);
        }
    }

    static async checkOut() {
        if (confirm('Are you sure you want to check out?')) {
            try {
                HRMSUtils.showLoader(true);
                const response = await fetch('../employee/check_in_out.php?action=checkout');
                const data = await response.json();
                
                if (data.success) {
                    HRMSUtils.showToast('Checked out successfully!');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    HRMSUtils.showToast(data.message, 'error');
                }
            } catch (error) {
                HRMSUtils.showToast('Network error. Please try again.', 'error');
            } finally {
                HRMSUtils.showLoader(false);
            }
        }
    }

    static async applyLeave(formData) {
        try {
            HRMSUtils.showLoader(true);
            const response = await fetch('../employee/apply_leave.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            
            if (data.success) {
                HRMSUtils.showToast('Leave application submitted successfully!');
                setTimeout(() => {
                    window.location.href = 'dashboard.php';
                }, 2000);
            } else {
                HRMSUtils.showToast(data.message, 'error');
            }
        } catch (error) {
            HRMSUtils.showToast('Network error. Please try again.', 'error');
        } finally {
            HRMSUtils.showLoader(false);
        }
    }
}

// Admin Functions
class AdminManager {
    static async approveLeave(leaveId) {
        if (confirm('Approve this leave request?')) {
            try {
                const response = await fetch(`../admin/process_leave.php?action=approve&id=${leaveId}`);
                const data = await response.json();
                
                if (data.success) {
                    HRMSUtils.showToast('Leave approved successfully!');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    HRMSUtils.showToast(data.message, 'error');
                }
            } catch (error) {
                HRMSUtils.showToast('Network error. Please try again.', 'error');
            }
        }
    }

    static async rejectLeave(leaveId) {
        const comment = prompt('Enter rejection reason:');
        if (comment !== null) {
            try {
                const response = await fetch(`../admin/process_leave.php?action=reject&id=${leaveId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `comment=${encodeURIComponent(comment)}`
                });
                const data = await response.json();
                
                if (data.success) {
                    HRMSUtils.showToast('Leave rejected successfully!');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    HRMSUtils.showToast(data.message, 'error');
                }
            } catch (error) {
                HRMSUtils.showToast('Network error. Please try again.', 'error');
            }
        }
    }

    static async updateEmployee(employeeId, formData) {
        try {
            HRMSUtils.showLoader(true);
            const response = await fetch(`../admin/update_employee.php?id=${employeeId}`, {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            
            if (data.success) {
                HRMSUtils.showToast('Employee updated successfully!');
                setTimeout(() => location.reload(), 1500);
            } else {
                HRMSUtils.showToast(data.message, 'error');
            }
        } catch (error) {
            HRMSUtils.showToast('Network error. Please try again.', 'error');
        } finally {
            HRMSUtils.showLoader(false);
        }
    }
}

// Profile Functions
class ProfileManager {
    static async updateProfile(formData) {
        try {
            HRMSUtils.showLoader(true);
            const response = await fetch('update_profile.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            
            if (data.success) {
                HRMSUtils.showToast('Profile updated successfully!');
                // Update session data
                if (data.user_data) {
                    Object.keys(data.user_data).forEach(key => {
                        // This would typically be handled server-side via session update
                    });
                }
                setTimeout(() => location.reload(), 1500);
            } else {
                HRMSUtils.showToast(data.message, 'error');
            }
        } catch (error) {
            HRMSUtils.showToast('Network error. Please try again.', 'error');
        } finally {
            HRMSUtils.showLoader(false);
        }
    }

    static async uploadProfilePicture(file) {
        if (!file) return;
        
        if (file.size > 2 * 1024 * 1024) { // 2MB limit
            HRMSUtils.showToast('File size must be less than 2MB', 'error');
            return;
        }
        
        if (!file.type.match('image.*')) {
            HRMSUtils.showToast('Only image files are allowed', 'error');
            return;
        }
        
        const formData = new FormData();
        formData.append('profile_picture', file);
        
        try {
            HRMSUtils.showLoader(true);
            const response = await fetch('upload_profile.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            
            if (data.success) {
                HRMSUtils.showToast('Profile picture updated successfully!');
                // Update profile picture immediately
                const profilePic = document.querySelector('.profile-pic');
                if (profilePic) {
                    profilePic.src = data.image_url + '?t=' + Date.now();
                }
                // Update in sidebar if exists
                const sidebarPic = document.querySelector('.sidebar-profile-pic');
                if (sidebarPic) {
                    sidebarPic.src = data.image_url + '?t=' + Date.now();
                }
            } else {
                HRMSUtils.showToast(data.message, 'error');
            }
        } catch (error) {
            HRMSUtils.showToast('Network error. Please try again.', 'error');
        } finally {
            HRMSUtils.showLoader(false);
        }
    }
}

// Dashboard Charts
class DashboardCharts {
    static initAttendanceChart(data) {
        const ctx = document.getElementById('attendanceChart');
        if (!ctx) return;
        
        new Chart(ctx.getContext('2d'), {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Attendance Hours',
                    data: data.hours,
                    borderColor: '#ff6b6b',
                    backgroundColor: 'rgba(255, 107, 107, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Hours'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    }
                }
            }
        });
    }

    static initPerformanceChart(data) {
        const ctx = document.getElementById('performanceChart');
        if (!ctx) return;
        
        new Chart(ctx.getContext('2d'), {
            type: 'radar',
            data: {
                labels: ['Attendance', 'Productivity', 'Teamwork', 'Leadership', 'Punctuality'],
                datasets: [{
                    label: 'Performance Score',
                    data: data.scores,
                    backgroundColor: 'rgba(255, 165, 0, 0.2)',
                    borderColor: '#ffa500',
                    borderWidth: 2,
                    pointBackgroundColor: '#ff6b6b'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    r: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            stepSize: 20
                        }
                    }
                }
            }
        });
    }

    static initPayrollChart(data) {
        const ctx = document.getElementById('payrollChart');
        if (!ctx) return;
        
        new Chart(ctx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: data.departments,
                datasets: [{
                    data: data.totals,
                    backgroundColor: [
                        '#ff6b6b',
                        '#ffa500',
                        '#ffd93d',
                        '#6c5ce7',
                        '#00b894'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
    }
}

// Event Listeners
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide messages
    const messages = document.querySelectorAll('.alert-message');
    messages.forEach(msg => {
        setTimeout(() => {
            msg.style.opacity = '0';
            setTimeout(() => msg.remove(), 300);
        }, 5000);
    });

    // Form validation
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const required = this.querySelectorAll('[required]');
            let valid = true;
            
            required.forEach(field => {
                if (!field.value.trim()) {
                    field.style.borderColor = '#ff6b6b';
                    valid = false;
                } else {
                    field.style.borderColor = '';
                }
            });
            
            if (!valid) {
                e.preventDefault();
                HRMSUtils.showToast('Please fill all required fields', 'error');
            }
        });
    });

    // Date pickers
    const dateInputs = document.querySelectorAll('.date-picker');
    dateInputs.forEach(input => {
        input.type = 'date';
    });

    // Initialize tooltips
    const tooltips = document.querySelectorAll('[title]');
    tooltips.forEach(el => {
        new bootstrap.Tooltip(el);
    });
});

// Export for use in browser console
window.HRMS = {
    utils: HRMSUtils,
    attendance: AttendanceManager,
    admin: AdminManager,
    profile: ProfileManager,
    charts: DashboardCharts
};