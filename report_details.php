<?php
// ============================================================
// report_details.php
// Detailed Case View, Volunteer Assignment & Case History
// ============================================================

require_once __DIR__ . '/config/database.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: reports.php");
    exit;
}

$message = '';
$error = '';

if (isset($_GET['created'])) {
    $message = "Report created successfully! Case tracking initialized.";
}
if (isset($_GET['updated'])) {
    $message = "Report updated successfully.";
}

// ------------------------------------------------------------
// Handle Quick Status Update Form Submission
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $new_status = $_POST['new_status'] ?? '';
    $updated_by = $_POST['updated_by'] ?? '';
    $notes = trim($_POST['notes'] ?? '');

    // Get current status
    $checkStmt = $pdo->prepare("SELECT status FROM animal_reports WHERE report_id = ?");
    $checkStmt->execute([$id]);
    $currentStatus = $checkStmt->fetchColumn();

    // Rule 2 Check: A report cannot be marked closed unless it has reached rescued or treated
    if ($new_status === 'closed' && $currentStatus !== 'closed') {
        if ($currentStatus !== 'rescued' && $currentStatus !== 'treated') {
            $error = "Rule 2: A report cannot be marked 'closed' unless it has been rescued or treated first.";
        }
    }

    if (empty($error)) {
        try {
            $ustmt = $pdo->prepare("UPDATE animal_reports SET status = ? WHERE report_id = ?");
            $ustmt->execute([$new_status, $id]);

            // Rule 3: Insert case update
            $noteText = !empty($notes) ? $notes : "Status changed from $currentStatus to $new_status.";
            $cstmt = $pdo->prepare("
                INSERT INTO case_updates (report_id, updated_by, update_text, new_status, created_at)
                VALUES (?, ?, ?, ?, datetime('now'))
            ");
            $cstmt->execute([$id, $updated_by, $noteText, $new_status]);

            $message = "Status updated to " . ucfirst(str_replace('_', ' ', $new_status)) . ".";
        } catch (Exception $e) {
            $error = "Error updating status: " . $e->getMessage();
        }
    }
}

// ------------------------------------------------------------
// Handle Assign Volunteer Form Submission
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_volunteer') {
    $volunteer_id = $_POST['volunteer_id'] ?? '';
    $request_type = trim($_POST['request_type'] ?? '');

    if (empty($volunteer_id) || empty($request_type)) {
        $error = "Volunteer and request type are required.";
    } else {
        // Rule 1: Only users with role = 'volunteer' can be assigned
        $vCheck = $pdo->prepare("SELECT user_id, name, role, status FROM users WHERE user_id = ?");
        $vCheck->execute([$volunteer_id]);
        $vol = $vCheck->fetch();

        if (!$vol || $vol['role'] !== 'volunteer') {
            $error = "Rule 1: Only users with role 'volunteer' can be assigned.";
        } elseif ($vol['status'] !== 'active') {
            $error = "Cannot assign an inactive volunteer.";
        } else {
            try {
                $astmt = $pdo->prepare("
                    INSERT INTO assistance_requests (report_id, volunteer_id, request_type, status, requested_at)
                    VALUES (?, ?, ?, 'accepted', datetime('now'))
                ");
                $astmt->execute([$id, $volunteer_id, $request_type]);

                // Update report status to 'volunteer_assigned'
                $pdo->prepare("UPDATE animal_reports SET status = 'volunteer_assigned' WHERE report_id = ?")->execute([$id]);

                // Record in case updates
                $cstmt = $pdo->prepare("
                    INSERT INTO case_updates (report_id, updated_by, update_text, new_status, created_at)
                    VALUES (?, ?, ?, 'volunteer_assigned', datetime('now'))
                ");
                $cstmt->execute([$id, $volunteer_id, "Volunteer {$vol['name']} assigned for {$request_type}."]);

                $message = "Volunteer {$vol['name']} was assigned successfully!";
            } catch (Exception $e) {
                $error = "Error assigning volunteer: " . $e->getMessage();
            }
        }
    }
}

// ------------------------------------------------------------
// Fetch Report Data
// ------------------------------------------------------------
$stmt = $pdo->prepare("
    SELECT r.*, u.name AS reporter_name, u.email AS reporter_email, u.contact_number AS reporter_contact
    FROM animal_reports r
    JOIN users u ON r.reported_by = u.user_id
    WHERE r.report_id = ?
");
$stmt->execute([$id]);
$report = $stmt->fetch();

if (!$report) {
    die("Animal report #$id not found.");
}

// Fetch Assistance Requests for this Report
$astmt = $pdo->prepare("
    SELECT a.*, u.name AS volunteer_name, u.contact_number AS volunteer_contact
    FROM assistance_requests a
    JOIN users u ON a.volunteer_id = u.user_id
    WHERE a.report_id = ?
    ORDER BY a.request_id DESC
");
$astmt->execute([$id]);
$assistanceList = $astmt->fetchAll();

// Fetch Case History Timeline for this Report
$hstmt = $pdo->prepare("
    SELECT c.*, u.name AS updated_by_name
    FROM case_updates c
    JOIN users u ON c.updated_by = u.user_id
    WHERE c.report_id = ?
    ORDER BY c.update_id ASC
");
$hstmt->execute([$id]);
$caseHistory = $hstmt->fetchAll();

// Fetch active volunteers for dropdown
$volStmt = $pdo->query("SELECT user_id, name, contact_number FROM users WHERE role = 'volunteer' AND status = 'active' ORDER BY name ASC");
$volunteers = $volStmt->fetchAll();

// Fetch all active users for status updater dropdown
$userListStmt = $pdo->query("SELECT user_id, name, role FROM users WHERE status = 'active' ORDER BY name ASC");
$allUsers = $userListStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Case #<?= (int)$report['report_id'] ?> - PetAid</title>
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
        <h2>Case #<?= (int)$report['report_id'] ?>: <?= htmlspecialchars($report['animal_type']) ?></h2>
        <div>
            <a href="edit_report.php?id=<?= (int)$report['report_id'] ?>" class="btn btn-sm">Edit Case</a>
            <a href="reports.php" class="btn btn-secondary btn-sm">← Back to Reports</a>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Animal Info Box -->
    <div class="details-section">
        <div class="details-grid">
            <div class="detail-item">
                <strong>Status</strong>
                <p>
                    <span class="badge badge-<?= htmlspecialchars($report['status']) ?>">
                        <?= htmlspecialchars(str_replace('_', ' ', $report['status'])) ?>
                    </span>
                </p>
            </div>
            <div class="detail-item">
                <strong>Condition</strong>
                <p>
                    <span class="badge badge-<?= htmlspecialchars($report['condition']) ?>">
                        <?= htmlspecialchars(str_replace('_', ' ', $report['condition'])) ?>
                    </span>
                </p>
            </div>
            <div class="detail-item">
                <strong>Location</strong>
                <p><?= htmlspecialchars($report['location']) ?></p>
            </div>
            <div class="detail-item">
                <strong>Date Reported</strong>
                <p><?= htmlspecialchars($report['created_at']) ?></p>
            </div>
            <div class="detail-item">
                <strong>Reported By</strong>
                <p><?= htmlspecialchars($report['reporter_name']) ?></p>
            </div>
            <div class="detail-item">
                <strong>Reporter Contact</strong>
                <p><?= htmlspecialchars($report['reporter_contact'] ?? $report['reporter_email'] ?? 'N/A') ?></p>
            </div>
        </div>

        <div class="detail-item" style="margin-top: 15px;">
            <strong>Description</strong>
            <p style="white-space: pre-wrap;"><?= htmlspecialchars($report['description']) ?></p>
        </div>
    </div>

    <!-- Quick Status Update Form -->
    <div class="details-section">
        <h3 style="margin-bottom: 12px; font-size: 16px; color: #2c3e50;">Update Case Status</h3>
        <form method="POST" action="report_details.php?id=<?= (int)$id ?>" class="filter-bar" style="margin-bottom:0;">
            <input type="hidden" name="action" value="update_status">

            <select name="new_status" required>
                <option value="reported" <?= $report['status'] === 'reported' ? 'selected' : '' ?>>Reported</option>
                <option value="verified" <?= $report['status'] === 'verified' ? 'selected' : '' ?>>Verified</option>
                <option value="assistance_requested" <?= $report['status'] === 'assistance_requested' ? 'selected' : '' ?>>Assistance Requested</option>
                <option value="volunteer_assigned" <?= $report['status'] === 'volunteer_assigned' ? 'selected' : '' ?>>Volunteer Assigned</option>
                <option value="rescued" <?= $report['status'] === 'rescued' ? 'selected' : '' ?>>Rescued</option>
                <option value="treated" <?= $report['status'] === 'treated' ? 'selected' : '' ?>>Treated</option>
                <option value="closed" <?= $report['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
            </select>

            <select name="updated_by" required>
                <?php foreach ($allUsers as $u): ?>
                    <option value="<?= (int)$u['user_id'] ?>">
                        By: <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['role']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="text" name="notes" placeholder="Optional notes for history..." style="flex-grow:1;">
            <button type="submit" class="btn btn-sm">Update Status</button>
        </form>
    </div>

    <!-- Volunteer Assistance Section -->
    <div class="details-section">
        <h3 style="margin-bottom: 12px; font-size: 16px; color: #2c3e50;">Volunteer Assistance</h3>
        
        <?php if (!empty($assistanceList)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Req #</th>
                        <th>Volunteer</th>
                        <th>Contact</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assistanceList as $a): ?>
                        <tr>
                            <td>#<?= (int)$a['request_id'] ?></td>
                            <td><strong><?= htmlspecialchars($a['volunteer_name']) ?></strong></td>
                            <td><?= htmlspecialchars($a['volunteer_contact'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($a['request_type']) ?></td>
                            <td><span class="badge badge-<?= htmlspecialchars($a['status']) ?>"><?= htmlspecialchars($a['status']) ?></span></td>
                            <td><?= htmlspecialchars($a['requested_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="color:#7f8c8d; margin-bottom: 15px;">No volunteers have been assigned to this report yet.</p>
        <?php endif; ?>

        <!-- Quick Assign Volunteer Form -->
        <h4 style="font-size: 14px; margin-bottom: 10px; color:#555;">Assign a Volunteer</h4>
        <form method="POST" action="report_details.php?id=<?= (int)$id ?>" class="filter-bar" style="margin-bottom: 0;">
            <input type="hidden" name="action" value="assign_volunteer">

            <select name="volunteer_id" required>
                <option value="">-- Select Volunteer (Rule 1) --</option>
                <?php foreach ($volunteers as $v): ?>
                    <option value="<?= (int)$v['user_id'] ?>">
                        <?= htmlspecialchars($v['name']) ?> (<?= htmlspecialchars($v['contact_number'] ?? 'No contact') ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="text" name="request_type" placeholder="Type (e.g. Rescue, Transport, Food, Medical)" required style="flex-grow:1;">

            <button type="submit" class="btn btn-sm">Assign Volunteer</button>
        </form>
    </div>

    <!-- Case History Section -->
    <div class="details-section">
        <h3 style="font-size: 16px; color: #2c3e50;">Case Progression History</h3>
        <p style="font-size: 12px; color: #7f8c8d;">Chronological history of all status changes and notes recorded for this animal.</p>

        <?php if (!empty($caseHistory)): ?>
            <ul class="history-list">
                <?php foreach ($caseHistory as $h): ?>
                    <li class="history-item">
                        <span class="date"><?= htmlspecialchars($h['created_at']) ?></span>
                        <div class="status-change">
                            <?php if (!empty($h['new_status'])): ?>
                                Status: <span class="badge badge-<?= htmlspecialchars($h['new_status']) ?>"><?= htmlspecialchars(str_replace('_', ' ', $h['new_status'])) ?></span>
                            <?php endif; ?>
                            <span style="font-size: 13px; font-weight: normal; color: #7f8c8d;">— by <?= htmlspecialchars($h['updated_by_name']) ?></span>
                        </div>
                        <div class="note"><?= htmlspecialchars($h['update_text']) ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p style="color:#7f8c8d; margin-top: 10px;">No updates recorded yet.</p>
        <?php endif; ?>
    </div>
</div>

<footer>
    <p>PetAid — Integrative Programming Mini System Project</p>
</footer>

<script src="js/script.js"></script>
</body>
</html>
