<?php
// ============================================================
// add_report.php
// Form to submit a new animal report
// ============================================================

require_once __DIR__ . '/config/database.php';

$error = '';
$success = '';

// Fetch active users for the dropdown
$usersStmt = $pdo->query("SELECT user_id, name, role FROM users WHERE status = 'active' ORDER BY name ASC");
$users = $usersStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reported_by = $_POST['reported_by'] ?? '';
    $animal_type = trim($_POST['animal_type'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $condition = $_POST['condition'] ?? '';

    // Simple Student-Level Validation
    if (empty($reported_by)) {
        $error = "Please select who is reporting this animal.";
    } elseif (empty($animal_type)) {
        $error = "Animal type is required.";
    } elseif (empty($description)) {
        $error = "Description is required.";
    } elseif (empty($location)) {
        $error = "Location is required.";
    } elseif (empty($condition)) {
        $error = "Please select animal condition.";
    } else {
        try {
            // Initial status is always 'reported'
            $status = 'reported';

            $stmt = $pdo->prepare("
                INSERT INTO animal_reports (reported_by, animal_type, description, location, condition, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, datetime('now'))
            ");
            $stmt->execute([$reported_by, $animal_type, $description, $location, $condition, $status]);
            $newReportId = $pdo->lastInsertId();

            // Insert initial case update
            $cstmt = $pdo->prepare("
                INSERT INTO case_updates (report_id, updated_by, update_text, new_status, created_at)
                VALUES (?, ?, ?, ?, datetime('now'))
            ");
            $cstmt->execute([$newReportId, $reported_by, "Initial report submitted.", $status]);

            header("Location: report_details.php?id=" . $newReportId . "&created=1");
            exit;
        } catch (Exception $e) {
            $error = "Error creating report: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report an Animal - PetAid</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header>
    <h1>🐾 Pet<span>Aid</span></h1>
    <nav>
        <a href="index.php">Dashboard</a>
        <a href="reports.php" class="active">Reports</a>
        <a href="assistance.php">Assistance</a>
        <a href="users.php">Users</a>
    </nav>
</header>

<div class="container">
    <div class="page-header">
        <h2>Report an Animal in Need</h2>
        <a href="reports.php" class="btn btn-secondary btn-sm">← Back to Reports</a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="form-box">
        <form method="POST" action="add_report.php">
            <div class="form-group">
                <label for="reported_by">Reported By (Community Member) *</label>
                <select name="reported_by" id="reported_by" required>
                    <option value="">-- Select Member --</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int)$u['user_id'] ?>" <?= isset($_POST['reported_by']) && $_POST['reported_by'] == $u['user_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['role']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="animal_type">Animal Type *</label>
                <input type="text" name="animal_type" id="animal_type" placeholder="e.g. Dog, Cat, Bird, Kitten" value="<?= htmlspecialchars($_POST['animal_type'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="location">Location *</label>
                <input type="text" name="location" id="location" placeholder="e.g. Near university gate, Library 2nd floor, Cafeteria" value="<?= htmlspecialchars($_POST['location'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="condition">Animal Condition *</label>
                <select name="condition" id="condition" required>
                    <option value="">-- Select Condition --</option>
                    <option value="safe" <?= (isset($_POST['condition']) && $_POST['condition'] === 'safe') ? 'selected' : '' ?>>Safe (healthy, but lost/stray)</option>
                    <option value="needs_attention" <?= (isset($_POST['condition']) && $_POST['condition'] === 'needs_attention') ? 'selected' : '' ?>>Needs Attention (minor wounds / hungry)</option>
                    <option value="injured" <?= (isset($_POST['condition']) && $_POST['condition'] === 'injured') ? 'selected' : '' ?>>Injured (limping / visible cuts)</option>
                    <option value="critical" <?= (isset($_POST['condition']) && $_POST['condition'] === 'critical') ? 'selected' : '' ?>>Critical (unresponsive / severe bleeding)</option>
                </select>
            </div>

            <div class="form-group">
                <label for="description">Detailed Description *</label>
                <textarea name="description" id="description" rows="4" placeholder="Describe the animal's color, size, exact location, behavior, and any immediate help needed..." required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">Submit Animal Report</button>
                <a href="reports.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<footer>
    <p>PetAid — Integrative Programming Mini System Project</p>
</footer>

<script src="js/script.js"></script>
</body>
</html>
