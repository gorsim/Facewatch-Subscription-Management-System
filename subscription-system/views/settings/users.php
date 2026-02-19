<?php
/**
 * User Management
 */

use App\Database;

$pageTitle = 'User Management';
$page = 'settings';

$db = Database::getInstance();
$message = '';
$error = '';

// Handle user creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate inputs
    if (empty($username) || empty($email) || empty($fullName) || empty($password)) {
        $error = 'All fields are required';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address';
    } else {
        // Check if username or email already exists
        $existing = $db->fetchOne(
            "SELECT id FROM users WHERE username = :username OR email = :email",
            ['username' => $username, 'email' => $email]
        );
        
        if ($existing) {
            $error = 'Username or email already exists';
        } else {
            // Create user
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            
            try {
                $db->insert('users', [
                    'username' => $username,
                    'email' => $email,
                    'full_name' => $fullName,
                    'password_hash' => $passwordHash,
                    'role' => 'admin', // All users have same access
                    'is_active' => 1,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                $message = "User '{$username}' created successfully! They can now log in.";
            } catch (Exception $e) {
                $error = 'Failed to create user: ' . $e->getMessage();
            }
        }
    }
}

// Handle user deactivation
if (isset($_GET['deactivate'])) {
    $userId = (int)$_GET['deactivate'];
    
    // Don't allow deactivating yourself
    if ($userId === $_SESSION['user_id']) {
        $error = 'You cannot deactivate your own account';
    } else {
        $db->update('users', ['is_active' => 0], 'id = :id', ['id' => $userId]);
        $message = 'User deactivated successfully';
    }
}

// Handle user reactivation
if (isset($_GET['activate'])) {
    $userId = (int)$_GET['activate'];
    $db->update('users', ['is_active' => 1], 'id = :id', ['id' => $userId]);
    $message = 'User activated successfully';
}

// Get all users
$users = $db->fetchAll("
    SELECT id, username, email, full_name, role, is_active, created_at, last_login_at
    FROM users
    ORDER BY created_at DESC
");

require __DIR__ . '/../layouts/header.php';
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2>👥 User Management</h2>
        <a href="?page=settings&action=account" class="btn">← Back to Account Settings</a>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <!-- Create New User Form -->
    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
        <h3 style="margin-bottom: 15px;">➕ Invite New User</h3>
        <form method="POST" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
            <div class="form-group">
                <label for="username">Username *</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="full_name">Full Name *</label>
                <input type="text" id="full_name" name="full_name" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password *</label>
                <input type="password" id="password" name="password" required minlength="8">
                <small style="color: #666;">Min 8 characters</small>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password *</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
            </div>
            
            <div style="display: flex; align-items: flex-end;">
                <button type="submit" name="create_user" class="btn btn-success">
                    Create User
                </button>
            </div>
        </form>
    </div>

    <!-- Users List -->
    <h3 style="margin-bottom: 15px;">All Users</h3>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Last Login</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($user['username']) ?></strong></td>
                        <td><?= htmlspecialchars($user['full_name']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td>
                            <span class="badge badge-info"><?= ucfirst($user['role']) ?></span>
                        </td>
                        <td>
                            <?php if ($user['is_active']): ?>
                                <span class="badge badge-success">Active</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                        <td>
                            <?= $user['last_login_at'] ? date('d/m/Y H:i', strtotime($user['last_login_at'])) : '<span style="color: #999;">Never</span>' ?>
                        </td>
                        <td>
                            <?php if ($user['id'] === $_SESSION['user_id']): ?>
                                <span style="color: #999; font-style: italic;">You</span>
                            <?php else: ?>
                                <?php if ($user['is_active']): ?>
                                    <a href="?page=settings&action=users&deactivate=<?= $user['id'] ?>"
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Deactivate user <?= htmlspecialchars($user['username']) ?>?')">
                                        Deactivate
                                    </a>
                                <?php else: ?>
                                    <a href="?page=settings&action=users&activate=<?= $user['id'] ?>"
                                       class="btn btn-sm btn-success">
                                        Activate
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>

