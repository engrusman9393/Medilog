<?php
session_start();
require_once 'config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $shop_name = trim($_POST['shop_name'] ?? '');
    $shop_type = $_POST['shop_type'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    // Validation
    if (empty($name) || empty($email) || empty($password) || empty($shop_name) || empty($shop_type)) {
        $error = 'Please fill in all required fields';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } else {
        try {
            $db = new Database();
            $conn = $db->getConnection();
            
            // Check if email already exists
            $stmt = $conn->prepare("SELECT id FROM users_tbl WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Email already registered';
            } else {
                // Insert new user
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users_tbl (name, email, password, shop_name, user_type, phone, address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                
                if ($stmt->execute([$name, $email, $hashedPassword, $shop_name, $shop_type, $phone, $address])) {
                    $success = 'Registration successful! You can now login.';
                } else {
                    $error = 'Registration failed. Please try again.';
                }
            }
        } catch (Exception $e) {
            $error = 'Registration failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - MediLog Admin</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .register-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 2rem 0;
        }
        .register-card {
            background: white;
            padding: 3rem;
            border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            width: 100%;
            max-width: 600px;
        }
        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .register-logo {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        .register-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        .register-subtitle {
            color: var(--text-secondary);
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <div class="register-header">
                <div class="register-logo">
                    <i class="fas fa-pills"></i>
                </div>
                <h1 class="register-title">Register Your Business</h1>
                <p class="register-subtitle">Create an account for your pharmacy or medical shop</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="name" class="form-label">Full Name *</label>
                        <input type="text" id="name" name="name" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email" class="form-label">Email Address *</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="password" class="form-label">Password *</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirm Password *</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="shop_name" class="form-label">Business Name *</label>
                        <input type="text" id="shop_name" name="shop_name" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['shop_name'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="shop_type" class="form-label">Business Type *</label>
                        <select id="shop_type" name="shop_type" class="form-control" required>
                            <option value="">Select Type</option>
                            <option value="pharmacy" <?php echo ($_POST['shop_type'] ?? '') === 'pharmacy' ? 'selected' : ''; ?>>Pharmacy</option>
                            <option value="medical_store" <?php echo ($_POST['shop_type'] ?? '') === 'medical_store' ? 'selected' : ''; ?>>Medical Store</option>
                            <option value="clinic" <?php echo ($_POST['shop_type'] ?? '') === 'clinic' ? 'selected' : ''; ?>>Clinic</option>
                            <option value="hospital" <?php echo ($_POST['shop_type'] ?? '') === 'hospital' ? 'selected' : ''; ?>>Hospital</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input type="tel" id="phone" name="phone" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="address" class="form-label">Address</label>
                    <textarea id="address" name="address" class="form-control" rows="3"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary w-full">
                    <i class="fas fa-user-plus"></i>
                    Register
                </button>
            </form>

            <div class="text-center mt-4">
                <p class="text-secondary">Already have an account? <a href="login.php" class="text-primary">Sign in here</a></p>
            </div>
        </div>
    </div>
</body>
</html>