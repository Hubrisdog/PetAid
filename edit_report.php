<?php
// ============================================================
// edit_report.php
// Edit an existing animal report & status
// ============================================================

require_once __DIR__ . '/config/database.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: reports.php");
    exit;
}

// Fetch report
$stmt = $pdo->prepare("SELECT * FROM animal_reports WHERE report_id = ?");
$stmt->execute([$id]);
$report = $stmt->fetch();

if (!$report) {
    die("Report not found.");
}

// Fetch active users for updater selection
$usersStmt = $pdo->query("SELECT user_id, name, role FROM users WHERE status = 'active' ORDER BY name ASC");
$users = $usersStmt->fetchAll();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $animal_type = trim($_POST['animal_type'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $condition = $_POST['condition'] ?? '';
    $new_status = $_POST['status'] ?? '';
    $updated_by = $_POST['updated_by'] ?? $report['reported_by'];
    $notes = trim($_POST['notes'] ?? '');

    // Validation
    if (empty($animal_type) || empty($description) || empty($location) || empty($condition) || empty($new_status)) {
        $error = "All fields marked with an asterisk are required.";
    } elseif ($new_status === 'closed' && $report['status'] !== 'closed') {
        // Rule 2: A report cannot be marked closed unless it has reached rescued or treated
        if ($report['status'] !== 'rescued' && $report['status'] !== 'treated') {
            $error = "Rule 2: A report cannot be marked 'closed' unless it has been rescued or treated first.";
        }
    }

    if (empty($error)) {
        try {
            $ustmt = $pdo->prepare("
                UPDATE animal_reports
                SET animal_type = ?, description = ?, location = ?, condition = ?, status = ?
                WHERE report_id = ?
            ");
            $ustmt->execute([$animal_type, $description, $location, $condition, $new_status, $id]);

            // Rule 3: If status changed or notes provided, add row to case_updates
            if ($new_status !== $report['status'] || !empty($notes)) {
                $updateText = !empty($notes) ? $notes : "Status changed from " . $report['status'] . " to " . $new_status . ".";
                $cstmt = $pdo->prepare("
                    INSERT INTO case_updates (report_id, updated_by, update_text, new_status, created_at)
                    VALUES (?, ?, ?, ?, datetime('now'))
                ");
                $cstmt->execute([$id, $updated_by, $updateText, $new_status]);
            }

            header("Location: report_details.php?id=" . $id . "&updated=1");
            exit;
        } catch (Exception $e) {
            $error = "Error updating report: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Report #<?= (int)$id ?> - PetAid</title>
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
        <h2>Edit Animal Report #<?= (int)$id ?></h2>
        <a href="report_details.php?id=<?= (int)$id ?>" class="btn btn-secondary btn-sm">← Back to Details</a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="form-box">
        <form method="POST" action="edit_report.php?id=<?= (int)$id ?>">
            <div class="form-group">
                <label for="animal_type">Animal Type *</label>
                <input type="text" name="animal_type" id="animal_type" value="<?= htmlspecialchars($_POST['animal_type'] ?? $report['animal_type']) ?>" required>
            </div>

            <div class="form-group">
                <label for="location">Location *</label>
                <input type="text" name="location" id="location" value="<?= htmlspecialchars($_POST['location'] ?? $report['location']) ?>" required>
            </div>

            <div class="form-group">
                <label for="condition">Condition *</label>
                <select name="condition" id="condition" required>
                    <?php
                    $cond = $_POST['condition'] ?? $report['condition'];
                    ?>
                    <option value="safe" <?= $cond === 'safe' ? 'selected' : '' ?>>Safe</option>
                    <option value="needs_attention" <?= $cond === 'needs_attention' ? 'selected' : '' ?>>Needs Attention</option>
                    <option value="injured" <?= $cond === 'injured' ? 'selected' : '' ?>>Injured</option>
                    <option value="critical" <?= $cond === 'critical' ? 'selected' : '' ?>>Critical</option>
                </select>
            </div>

            <div class="form-group">
                <label for="status">Case Status *</label>
                <select name="status" id="status" required>
                    <?php
                    $curStat = $_POST['status'] ?? $report['status'];
                    $statuses = [
                        'reported' => 'Reported',
                        'verified' => 'Verified',
                        'assistance_requested' => 'Assistance Requested',
                        'volunteer_assigned' => 'Volunteer Assigned',
                        'rescued' => 'Rescued',
                        'treated' => 'Treated',
                        'closed' => 'Closed'
                    ];
                    foreach ($statuses as $val => $label):
                    ?>
                        <option value="<?= $val ?>" <?= $curStat === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
                <small style="color:#7f8c8d; display:block; margin-top:4px;">
                    * Rule 2 note: A case can only be marked "Closed" if it has been "Rescued" or "Treated".
                </small>
            </div>

            <div class="form-group">
                <label for="description">Description *</label>
                <textarea name="description" id="description" rows="4" required><?= htmlspecialchars($_POST['description'] ?? $report['description']) ?></textarea>
            </div>

            <div class="form-group">
                <label for="updated_by">Action Taken By</label>
                <select name="updated_by" id="updated_by">
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int)$u['user_id'] ?>">
                            <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['role']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="notes">Case Update Notes (Optional)</label>
                <input type="text" name="notes" id="notes" placeholder="Describe what changed or why..." value="<?= htmlspecialchars($_POST['notes'] ?? '') ?>">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">Save Changes</button>
                <a href="report_details.php?id=<?= (int)$id ?>" class="btn btn-secondary">Cancel</a>
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
