<?php
/**
 * Account Settings - Change Password
 */

use App\Database;

$pageTitle = 'Account Settings';
$page = 'settings';

$db = Database::getInstance();
$message = '';
$error = '';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate inputs
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = 'All fields are required';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'New passwords do not match';
    } elseif (strlen($newPassword) < 8) {
        $error = 'Password must be at least 8 characters long';
    } else {
        // Verify current password
        $user = $db->fetchOne(
            "SELECT * FROM users WHERE id = :id",
            ['id' => $_SESSION['user_id']]
        );
        
        if ($user && password_verify($currentPassword, $user['password_hash'])) {
            // Update password
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $db->update(
                'users',
                ['password_hash' => $newPasswordHash, 'updated_at' => date('Y-m-d H:i:s')],
                'id = :id',
                ['id' => $_SESSION['user_id']]
            );
            
            $message = 'Password changed successfully!';
        } else {
            $error = 'Current password is incorrect';
        }
    }
}

// Get current user info
$currentUser = $db->fetchOne(
    "SELECT id, username, email, full_name, role, created_at, last_login_at FROM users WHERE id = :id",
    ['id' => $_SESSION['user_id']]
);

require __DIR__ . '/../layouts/header.php';
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2>👤 Account Settings</h2>
        <a href="?page=settings&action=users" class="btn btn-success">
            👥 Manage Users
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <!-- User Information -->
    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
        <h3 style="margin-bottom: 15px;">Your Information</h3>
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
            <div>
                <strong>Username:</strong> <?= htmlspecialchars($currentUser['username']) ?>
            </div>
            <div>
                <strong>Full Name:</strong> <?= htmlspecialchars($currentUser['full_name']) ?>
            </div>
            <div>
                <strong>Email:</strong> <?= htmlspecialchars($currentUser['email']) ?>
            </div>
            <div>
                <strong>Role:</strong> <span class="badge badge-info"><?= ucfirst($currentUser['role']) ?></span>
            </div>
            <div>
                <strong>Account Created:</strong> <?= date('d/m/Y', strtotime($currentUser['created_at'])) ?>
            </div>
            <div>
                <strong>Last Login:</strong> <?= $currentUser['last_login_at'] ? date('d/m/Y H:i', strtotime($currentUser['last_login_at'])) : 'Never' ?>
            </div>
        </div>
    </div>
    
    <!-- Change Password Form -->
    <h3 style="margin-bottom: 15px;">🔒 Change Password</h3>
    <form method="POST" style="max-width: 500px;">
        <div class="form-group">
            <label for="current_password">Current Password</label>
            <input type="password" id="current_password" name="current_password" required>
        </div>
        
        <div class="form-group">
            <label for="new_password">New Password</label>
            <input type="password" id="new_password" name="new_password" required minlength="8">
            <small style="color: #666;">Must be at least 8 characters long</small>
        </div>
        
        <div class="form-group">
            <label for="confirm_password">Confirm New Password</label>
            <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
        </div>
        
        <button type="submit" name="change_password" class="btn btn-success">
            Change Password
        </button>
        <a href="?page=dashboard" class="btn" style="margin-left: 10px;">Cancel</a>
    </form>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>

