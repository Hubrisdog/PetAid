<?php
// ============================================================
// reports.php
// View and filter all animal reports
// ============================================================

require_once __DIR__ . '/config/database.php';

$message = '';
$error = '';

// Handle Delete Request
if (isset($_GET['action']) && $_GET['action'] === 'delete' && !empty($_GET['id'])) {
    $deleteId = (int)$_GET['id'];
    try {
        // Delete dependent records first, then report
        $pdo->prepare("DELETE FROM case_updates WHERE report_id = ?")->execute([$deleteId]);
        $pdo->prepare("DELETE FROM assistance_requests WHERE report_id = ?")->execute([$deleteId]);
        $pdo->prepare("DELETE FROM animal_reports WHERE report_id = ?")->execute([$deleteId]);
        $message = "Report #$deleteId was deleted successfully.";
    } catch (Exception $e) {
        $error = "Could not delete report: " . $e->getMessage();
    }
}

// Fetch Filter Values
$search = trim($_GET['search'] ?? '');
$animalType = trim($_GET['animal_type'] ?? '');
$condition = trim($_GET['condition'] ?? '');
$status = trim($_GET['status'] ?? '');

// Build Query
$sql = "
    SELECT r.*, u.name AS reporter_name
    FROM animal_reports r
    JOIN users u ON r.reported_by = u.user_id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (LOWER(r.animal_type) LIKE ? OR LOWER(r.description) LIKE ? OR LOWER(r.location) LIKE ?)";
    $term = '%' . strtolower($search) . '%';
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if (!empty($animalType)) {
    $sql .= " AND LOWER(r.animal_type) = LOWER(?)";
    $params[] = $animalType;
}

if (!empty($condition)) {
    $sql .= " AND r.condition = ?";
    $params[] = $condition;
}

if (!empty($status)) {
    $sql .= " AND r.status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY r.report_id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reports = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Animal Reports - PetAid</title>
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
        <h2>Animal Reports</h2>
        <a href="add_report.php" class="btn">+ New Animal Report</a>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Search and Filter Form -->
    <form method="GET" action="reports.php" class="filter-bar">
        <input type="text" name="search" placeholder="Search description, location..." value="<?= htmlspecialchars($search) ?>">

        <select name="animal_type">
            <option value="">All Animals</option>
            <option value="Dog" <?= $animalType === 'Dog' ? 'selected' : '' ?>>Dog</option>
            <option value="Cat" <?= $animalType === 'Cat' ? 'selected' : '' ?>>Cat</option>
            <option value="Bird" <?= $animalType === 'Bird' ? 'selected' : '' ?>>Bird</option>
        </select>

        <select name="condition">
            <option value="">All Conditions</option>
            <option value="safe" <?= $condition === 'safe' ? 'selected' : '' ?>>Safe</option>
            <option value="needs_attention" <?= $condition === 'needs_attention' ? 'selected' : '' ?>>Needs Attention</option>
            <option value="injured" <?= $condition === 'injured' ? 'selected' : '' ?>>Injured</option>
            <option value="critical" <?= $condition === 'critical' ? 'selected' : '' ?>>Critical</option>
        </select>

        <select name="status">
            <option value="">All Statuses</option>
            <option value="reported" <?= $status === 'reported' ? 'selected' : '' ?>>Reported</option>
            <option value="verified" <?= $status === 'verified' ? 'selected' : '' ?>>Verified</option>
            <option value="assistance_requested" <?= $status === 'assistance_requested' ? 'selected' : '' ?>>Assistance Requested</option>
            <option value="volunteer_assigned" <?= $status === 'volunteer_assigned' ? 'selected' : '' ?>>Volunteer Assigned</option>
            <option value="rescued" <?= $status === 'rescued' ? 'selected' : '' ?>>Rescued</option>
            <option value="treated" <?= $status === 'treated' ? 'selected' : '' ?>>Treated</option>
            <option value="closed" <?= $status === 'closed' ? 'selected' : '' ?>>Closed</option>
        </select>

        <button type="submit" class="btn btn-sm">Filter</button>
        <a href="reports.php" class="btn btn-secondary btn-sm">Reset</a>
    </form>

    <!-- Reports Table -->
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Animal</th>
                <th>Description</th>
                <th>Location</th>
                <th>Condition</th>
                <th>Status</th>
                <th>Reported By</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($reports)): ?>
                <tr><td colspan="8" style="text-align:center;">No animal reports found.</td></tr>
            <?php else: ?>
                <?php foreach ($reports as $r): ?>
                    <tr>
                        <td>#<?= htmlspecialchars($r['report_id']) ?></td>
                        <td><strong><?= htmlspecialchars($r['animal_type']) ?></strong></td>
                        <td><?= htmlspecialchars(strlen($r['description']) > 40 ? substr($r['description'], 0, 40) . '...' : $r['description']) ?></td>
                        <td><?= htmlspecialchars($r['location']) ?></td>
                        <td>
                            <span class="badge badge-<?= htmlspecialchars($r['condition']) ?>">
                                <?= htmlspecialchars(str_replace('_', ' ', $r['condition'])) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-<?= htmlspecialchars($r['status']) ?>">
                                <?= htmlspecialchars(str_replace('_', ' ', $r['status'])) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($r['reporter_name']) ?></td>
                        <td>
                            <a href="report_details.php?id=<?= (int)$r['report_id'] ?>" class="btn btn-secondary btn-sm">View</a>
                            <a href="edit_report.php?id=<?= (int)$r['report_id'] ?>" class="btn btn-sm">Edit</a>
                            <a href="reports.php?action=delete&id=<?= (int)$r['report_id'] ?>" 
                               class="btn btn-danger btn-sm" 
                               onclick="return confirmDelete('Are you sure you want to delete report #<?= (int)$r['report_id'] ?>?');">Delete</a>
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
