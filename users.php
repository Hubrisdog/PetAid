<?php
// ============================================================
// users.php
// Users & Volunteers Management - PetAid
// ============================================================

require_once __DIR__ . '/config/database.php';

$message = '';
$error = '';

// Handle Status Toggle (Soft-Delete: status = 'inactive')
if (isset($_GET['action']) && $_GET['action'] === 'toggle_status' && !empty($_GET['id'])) {
    $userId = (int)$_GET['id'];
    $newStatus = ($_GET['status'] ?? 'active') === 'active' ? 'inactive' : 'active';
    try {
        $pdo->prepare("UPDATE users SET status = ? WHERE user_id = ?")->execute([$newStatus, $userId]);
        $message = "User status updated to $newStatus.";
    } catch (Exception $e) {
        $error = "Error updating user: " . $e->getMessage();
    }
}

// Handle Add User Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_user') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contact = trim($_POST['contact_number'] ?? '');
    $role = $_POST['role'] ?? 'reporter';

    if (empty($name)) {
        $error = "Name is required.";
    } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Valid email address is required.";
    } else {
        // Check for duplicate email
        $check = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = "Email '$email' is already registered.";
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO users (name, email, contact_number, role, status)
                    VALUES (?, ?, ?, ?, 'active')
                ");
                $stmt->execute([$name, $email, $contact, $role]);
                $message = "User '$name' added successfully as $role!";
            } catch (Exception $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Fetch all users
$users = $pdo->query("SELECT * FROM users ORDER BY user_id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users & Volunteers - PetAid</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header>
    <h1>🐾 Pet<span>Aid</span></h1>
    <nav>
        <a href="index.php">Dashboard</a>
        <a href="reports.php">Reports</a>
        <a href="assistance.php">Assistance</a>
        <a href="users.php" class="active">Users</a>
    </nav>
</header>

<div class="container">
    <div class="page-header">
        <h2>Users & Volunteers Management</h2>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Add User Form -->
    <div class="form-box">
        <h3 style="font-size: 16px; margin-bottom: 15px; color: #2c3e50;">Register New Community Member / Volunteer</h3>
        <form method="POST" action="users.php">
            <input type="hidden" name="action" value="add_user">

            <div class="form-group">
                <label for="name">Full Name *</label>
                <input type="text" name="name" id="name" placeholder="e.g. Maria Santos" required>
            </div>

            <div class="form-group">
                <label for="email">Email Address *</label>
                <input type="email" name="email" id="email" placeholder="e.g. maria@example.com" required>
            </div>

            <div class="form-group">
                <label for="contact_number">Contact Number</label>
                <input type="text" name="contact_number" id="contact_number" placeholder="e.g. 0917-123-4567">
            </div>

            <div class="form-group">
                <label for="role">Role *</label>
                <select name="role" id="role" required>
                    <option value="reporter">Reporter (Community Member)</option>
                    <option value="volunteer" selected>Volunteer (Rescue / Shelter)</option>
                    <option value="admin">Admin</option>
                </select>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">Add User</button>
            </div>
        </form>
    </div>

    <!-- Users Table -->
    <div class="page-header">
        <h2>Registered Users</h2>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Contact</th>
                <th>Role</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td>#<?= (int)$u['user_id'] ?></td>
                    <td><strong><?= htmlspecialchars($u['name']) ?></strong></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><?= htmlspecialchars($u['contact_number'] ?? '-') ?></td>
                    <td><span class="badge"><?= htmlspecialchars($u['role']) ?></span></td>
                    <td><span class="badge badge-<?= htmlspecialchars($u['status']) ?>"><?= htmlspecialchars($u['status']) ?></span></td>
                    <td>
                        <?php if ($u['status'] === 'active'): ?>
                            <a href="users.php?action=toggle_status&id=<?= (int)$u['user_id'] ?>&status=active" 
                               class="btn btn-danger btn-sm"
                               onclick="return confirmDelete('Deactivate user <?= htmlspecialchars($u['name']) ?>?');">
                               Deactivate
                            </a>
                        <?php else: ?>
                            <a href="users.php?action=toggle_status&id=<?= (int)$u['user_id'] ?>&status=inactive" 
                               class="btn btn-sm">
                               Activate
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<footer>
    <p>PetAid — Integrative Programming Mini System Project</p>
</footer>

<script src="js/script.js"></script>
</body>
</html>
