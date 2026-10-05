<?php
// ============================================================
// assistance.php
// Volunteer Assistance Assignments - PetAid
// ============================================================

require_once __DIR__ . '/config/database.php';

$message = '';
$error = '';

// Handle Status Change Action (e.g. mark completed)
if (isset($_GET['action']) && $_GET['action'] === 'status' && !empty($_GET['id']) && !empty($_GET['status'])) {
    $reqId = (int)$_GET['id'];
    $newStatus = $_GET['status'];
    $allowed = ['pending', 'accepted', 'completed', 'cancelled'];

    if (in_array($newStatus, $allowed)) {
        try {
            $pdo->prepare("UPDATE assistance_requests SET status = ? WHERE request_id = ?")->execute([$newStatus, $reqId]);
            $message = "Assistance request #$reqId status updated to $newStatus.";
        } catch (Exception $e) {
            $error = "Error updating status: " . $e->getMessage();
        }
    }
}

// Handle Delete Action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && !empty($_GET['id'])) {
    $reqId = (int)$_GET['id'];
    try {
        $pdo->prepare("DELETE FROM assistance_requests WHERE request_id = ?")->execute([$reqId]);
        $message = "Assistance request #$reqId deleted successfully.";
    } catch (Exception $e) {
        $error = "Error deleting request: " . $e->getMessage();
    }
}

// Handle Create Assistance Request Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_assistance') {
    $report_id = $_POST['report_id'] ?? '';
    $volunteer_id = $_POST['volunteer_id'] ?? '';
    $request_type = trim($_POST['request_type'] ?? '');
    $status = $_POST['status'] ?? 'pending';

    if (empty($report_id) || empty($volunteer_id) || empty($request_type)) {
        $error = "Please fill in all required fields.";
    } else {
        // Rule 1: Check if assigned user is a volunteer
        $vCheck = $pdo->prepare("SELECT user_id, name, role, status FROM users WHERE user_id = ?");
        $vCheck->execute([$volunteer_id]);
        $vol = $vCheck->fetch();

        if (!$vol || $vol['role'] !== 'volunteer') {
            $error = "Rule 1: Only users with role 'volunteer' can be assigned.";
        } elseif ($vol['status'] !== 'active') {
            $error = "Selected volunteer account is inactive.";
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO assistance_requests (report_id, volunteer_id, request_type, status, requested_at)
                    VALUES (?, ?, ?, ?, datetime('now'))
                ");
                $stmt->execute([$report_id, $volunteer_id, $request_type, $status]);

                // Update report status to 'volunteer_assigned'
                $pdo->prepare("UPDATE animal_reports SET status = 'volunteer_assigned' WHERE report_id = ?")->execute([$report_id]);

                // Insert into case_updates
                $cstmt = $pdo->prepare("
                    INSERT INTO case_updates (report_id, updated_by, update_text, new_status, created_at)
                    VALUES (?, ?, ?, 'volunteer_assigned', datetime('now'))
                ");
                $cstmt->execute([$report_id, $volunteer_id, "Volunteer {$vol['name']} assigned for $request_type."]);

                $message = "Volunteer {$vol['name']} assigned to Report #$report_id successfully!";
            } catch (Exception $e) {
                $error = "Error creating assistance request: " . $e->getMessage();
            }
        }
    }
}

// Fetch Reports for Dropdown
$reportsStmt = $pdo->query("SELECT report_id, animal_type, location, status FROM animal_reports WHERE status != 'closed' ORDER BY report_id DESC");
$activeReports = $reportsStmt->fetchAll();

// Fetch Active Volunteers for Dropdown (Rule 1)
$volStmt = $pdo->query("SELECT user_id, name, contact_number FROM users WHERE role = 'volunteer' AND status = 'active' ORDER BY name ASC");
$volunteers = $volStmt->fetchAll();

// Fetch All Assistance Requests
$allRequestsStmt = $pdo->query("
    SELECT a.*, u.name AS volunteer_name, u.contact_number AS volunteer_contact,
           r.animal_type, r.location, r.status AS report_status
    FROM assistance_requests a
    JOIN users u ON a.volunteer_id = u.user_id
    JOIN animal_reports r ON a.report_id = r.report_id
    ORDER BY a.request_id DESC
");
$requests = $allRequestsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assistance Requests - PetAid</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header>
    <h1>🐾 Pet<span>Aid</span></h1>
    <nav>
        <a href="index.php">Dashboard</a>
        <a href="reports.php">Reports</a>
        <a href="assistance.php" class="active">Assistance</a>
        <a href="users.php">Users</a>
    </nav>
</header>

<div class="container">
    <div class="page-header">
        <h2>Volunteer Assistance Dispatch</h2>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Assign Volunteer Form Box -->
    <div class="form-box">
        <h3 style="font-size: 16px; margin-bottom: 15px; color: #2c3e50;">Request Volunteer Assistance</h3>
        <form method="POST" action="assistance.php">
            <input type="hidden" name="action" value="create_assistance">

            <div class="form-group">
                <label for="report_id">Select Animal Case *</label>
                <select name="report_id" id="report_id" required>
                    <option value="">-- Select Active Case --</option>
                    <?php foreach ($activeReports as $r): ?>
                        <option value="<?= (int)$r['report_id'] ?>">
                            Case #<?= (int)$r['report_id'] ?>: <?= htmlspecialchars($r['animal_type']) ?> at <?= htmlspecialchars($r['location']) ?> (Status: <?= htmlspecialchars($r['status']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="volunteer_id">Assign Volunteer (Role: volunteer only) *</label>
                <select name="volunteer_id" id="volunteer_id" required>
                    <option value="">-- Select Active Volunteer --</option>
                    <?php foreach ($volunteers as $v): ?>
                        <option value="<?= (int)$v['user_id'] ?>">
                            <?= htmlspecialchars($v['name']) ?> (<?= htmlspecialchars($v['contact_number'] ?? 'No contact') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="request_type">Request Type *</label>
                <input type="text" name="request_type" id="request_type" placeholder="e.g. Rescue, Medical transport, Temporary shelter, Food delivery" required>
            </div>

            <div class="form-group">
                <label for="status">Initial Status</label>
                <select name="status" id="status">
                    <option value="pending">Pending</option>
                    <option value="accepted" selected>Accepted</option>
                    <option value="completed">Completed</option>
                </select>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">Dispatch Volunteer</button>
            </div>
        </form>
    </div>

    <!-- Assistance Requests Table -->
    <div class="page-header">
        <h2>Active & Past Requests</h2>
    </div>

    <table>
        <thead>
            <tr>
                <th>Req #</th>
                <th>Case #</th>
                <th>Animal & Location</th>
                <th>Volunteer</th>
                <th>Request Type</th>
                <th>Status</th>
                <th>Requested At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($requests)): ?>
                <tr><td colspan="8" style="text-align:center;">No volunteer assistance requests recorded.</td></tr>
            <?php else: ?>
                <?php foreach ($requests as $a): ?>
                    <tr>
                        <td>#<?= (int)$a['request_id'] ?></td>
                        <td>
                            <a href="report_details.php?id=<?= (int)$a['report_id'] ?>"><strong>Case #<?= (int)$a['report_id'] ?></strong></a>
                        </td>
                        <td><?= htmlspecialchars($a['animal_type']) ?> — <span style="color:#7f8c8d;"><?= htmlspecialchars($a['location']) ?></span></td>
                        <td>
                            <strong><?= htmlspecialchars($a['volunteer_name']) ?></strong>
                            <div style="font-size:12px; color:#7f8c8d;"><?= htmlspecialchars($a['volunteer_contact'] ?? '') ?></div>
                        </td>
                        <td><?= htmlspecialchars($a['request_type']) ?></td>
                        <td><span class="badge badge-<?= htmlspecialchars($a['status']) ?>"><?= htmlspecialchars($a['status']) ?></span></td>
                        <td><?= htmlspecialchars($a['requested_at']) ?></td>
                        <td>
                            <?php if ($a['status'] !== 'completed'): ?>
                                <a href="assistance.php?action=status&id=<?= (int)$a['request_id'] ?>&status=completed" class="btn btn-sm">Complete</a>
                            <?php endif; ?>
                            <?php if ($a['status'] !== 'cancelled'): ?>
                                <a href="assistance.php?action=status&id=<?= (int)$a['request_id'] ?>&status=cancelled" class="btn btn-secondary btn-sm">Cancel</a>
                            <?php endif; ?>
                            <a href="assistance.php?action=delete&id=<?= (int)$a['request_id'] ?>" 
                               class="btn btn-danger btn-sm" 
                               onclick="return confirmDelete('Delete assistance request #<?= (int)$a['request_id'] ?>?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<footer>
    <p>PetAid — Integrative Programming Mini System Project</p>
</footer>

<script src="js/script.js"></script>
</body>
</html>
