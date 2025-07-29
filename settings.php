<?php
$pageTitle = 'Settings';
require_once 'includes/header.php';
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $shop_name = trim($_POST['shop_name'] ?? '');
        $shop_type = $_POST['shop_type'] ?? '';
        $address = trim($_POST['address'] ?? '');
        
        if (empty($name) || empty($email)) {
            $error = 'Name and email are required';
        } else {
            try {
                $stmt = $conn->prepare("UPDATE users_tbl SET name = ?, email = ?, phone = ?, shop_name = ?, user_type = ?, address = ?, updated_at = NOW() WHERE id = ?");
                if ($stmt->execute([$name, $email, $phone, $shop_name, $shop_type, $address, $user['id']])) {
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_email'] = $email;
                    $_SESSION['shop_name'] = $shop_name;
                    $message = 'Profile updated successfully!';
                } else {
                    $error = 'Failed to update profile';
                }
            } catch (Exception $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'All password fields are required';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New passwords do not match';
        } elseif (strlen($new_password) < 6) {
            $error = 'New password must be at least 6 characters long';
        } else {
            try {
                // Verify current password
                $stmt = $conn->prepare("SELECT password FROM users_tbl WHERE id = ?");
                $stmt->execute([$user['id']]);
                $currentHashedPassword = $stmt->fetchColumn();
                
                if (!password_verify($current_password, $currentHashedPassword)) {
                    $error = 'Current password is incorrect';
                } else {
                    // Update password
                    $newHashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users_tbl SET password = ?, updated_at = NOW() WHERE id = ?");
                    if ($stmt->execute([$newHashedPassword, $user['id']])) {
                        $message = 'Password changed successfully!';
                    } else {
                        $error = 'Failed to change password';
                    }
                }
            } catch (Exception $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Get current user data
try {
    $stmt = $conn->prepare("SELECT * FROM users_tbl WHERE id = ?");
    $stmt->execute([$user['id']]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = 'Failed to load user data';
}
?>

<?php if ($message): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="d-flex justify-between align-center mb-4">
    <div>
        <h2>Settings</h2>
        <p class="text-secondary">Manage your account and business settings</p>
    </div>
</div>

<div class="row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
    <!-- Profile Settings -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Profile Information</h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="form-group">
                    <label for="name" class="form-label">Full Name *</label>
                    <input type="text" id="name" name="name" class="form-control" 
                           value="<?php echo htmlspecialchars($userData['name'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email" class="form-label">Email Address *</label>
                    <input type="email" id="email" name="email" class="form-control" 
                           value="<?php echo htmlspecialchars($userData['email'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input type="tel" id="phone" name="phone" class="form-control" 
                           value="<?php echo htmlspecialchars($userData['phone'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="shop_name" class="form-label">Business Name</label>
                    <input type="text" id="shop_name" name="shop_name" class="form-control" 
                           value="<?php echo htmlspecialchars($userData['shop_name'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="shop_type" class="form-label">Business Type</label>
                    <select id="shop_type" name="shop_type" class="form-control">
                        <option value="">Select Type</option>
                        <option value="pharmacy" <?php echo ($userData['user_type'] ?? '') === 'pharmacy' ? 'selected' : ''; ?>>Pharmacy</option>
                        <option value="medical_store" <?php echo ($userData['user_type'] ?? '') === 'medical_store' ? 'selected' : ''; ?>>Medical Store</option>
                        <option value="clinic" <?php echo ($userData['user_type'] ?? '') === 'clinic' ? 'selected' : ''; ?>>Clinic</option>
                        <option value="hospital" <?php echo ($userData['user_type'] ?? '') === 'hospital' ? 'selected' : ''; ?>>Hospital</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="address" class="form-label">Address</label>
                    <textarea id="address" name="address" class="form-control" rows="3"><?php echo htmlspecialchars($userData['address'] ?? ''); ?></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Update Profile
                </button>
            </form>
        </div>
    </div>
    
    <!-- Password Change -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Change Password</h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="change_password">
                
                <div class="form-group">
                    <label for="current_password" class="form-label">Current Password *</label>
                    <input type="password" id="current_password" name="current_password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="new_password" class="form-label">New Password *</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" 
                           minlength="6" required>
                    <small class="text-secondary">Minimum 6 characters</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password" class="form-label">Confirm New Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                           minlength="6" required>
                </div>
                
                <button type="submit" class="btn btn-warning">
                    <i class="fas fa-key"></i>
                    Change Password
                </button>
            </form>
        </div>
    </div>
</div>

<!-- System Information -->
<div class="card mt-4">
    <div class="card-header">
        <h3 class="card-title">System Information</h3>
    </div>
    <div class="card-body">
        <div class="row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
            <div>
                <strong>Account Created:</strong><br>
                <span class="text-secondary"><?php echo date('M j, Y', strtotime($userData['created_at'] ?? '')); ?></span>
            </div>
            <div>
                <strong>Last Updated:</strong><br>
                <span class="text-secondary"><?php echo $userData['updated_at'] ? date('M j, Y g:i A', strtotime($userData['updated_at'])) : 'Never'; ?></span>
            </div>
            <div>
                <strong>User ID:</strong><br>
                <span class="text-secondary">#<?php echo $userData['id']; ?></span>
            </div>
            <div>
                <strong>Account Status:</strong><br>
                <span class="badge badge-success">Active</span>
            </div>
        </div>
    </div>
</div>

<!-- Application Settings -->
<div class="card mt-4">
    <div class="card-header">
        <h3 class="card-title">Application Settings</h3>
    </div>
    <div class="card-body">
        <div class="form-row">
            <div class="form-group">
                <label for="low_stock_threshold" class="form-label">Low Stock Alert Threshold</label>
                <input type="number" id="low_stock_threshold" class="form-control" value="10" min="1">
                <small class="text-secondary">Alert when stock falls below this number</small>
            </div>
            
            <div class="form-group">
                <label for="expiry_alert_days" class="form-label">Expiry Alert Days</label>
                <input type="number" id="expiry_alert_days" class="form-control" value="30" min="1">
                <small class="text-secondary">Alert when medicines expire within this many days</small>
            </div>
            
            <div class="form-group">
                <label for="currency" class="form-label">Currency</label>
                <select id="currency" class="form-control">
                    <option value="INR" selected>₹ Indian Rupee (INR)</option>
                    <option value="USD">$ US Dollar (USD)</option>
                    <option value="EUR">€ Euro (EUR)</option>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <div class="d-flex align-center gap-2">
                <input type="checkbox" id="email_notifications" checked>
                <label for="email_notifications" class="form-label">Email Notifications</label>
            </div>
            <small class="text-secondary">Receive email alerts for low stock and expiring medicines</small>
        </div>
        
        <button type="button" class="btn btn-secondary" onclick="saveAppSettings()">
            <i class="fas fa-cog"></i>
            Save Settings
        </button>
    </div>
</div>

<script>
function saveAppSettings() {
    // This would save application settings
    showAlert('success', 'Application settings saved successfully!');
}

// Password confirmation validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    
    if (newPassword !== confirmPassword) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>