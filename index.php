<?php
// ============================================================
// index.php
// Dashboard - PetAid Community Animal Assistance
// ============================================================

require_once __DIR__ . '/config/database.php';

// Fetch simple dashboard counts
$totalReports = $pdo->query("SELECT COUNT(*) FROM animal_reports")->fetchColumn();
$openCases = $pdo->query("SELECT COUNT(*) FROM animal_reports WHERE status != 'closed'")->fetchColumn();
$criticalCases = $pdo->query("SELECT COUNT(*) FROM animal_reports WHERE condition = 'critical' AND status != 'closed'")->fetchColumn();
$rescuedCases = $pdo->query("SELECT COUNT(*) FROM animal_reports WHERE status = 'rescued'")->fetchColumn();
$closedCases = $pdo->query("SELECT COUNT(*) FROM animal_reports WHERE status = 'closed'")->fetchColumn();

// Fetch 5 recent reports
$recentStmt = $pdo->query("
    SELECT r.*, u.name AS reporter_name
    FROM animal_reports r
    JOIN users u ON r.reported_by = u.user_id
    ORDER BY r.report_id DESC
    LIMIT 5
");
$recentReports = $recentStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PetAid - Community Animal Assistance</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header>
    <h1>🐾 Pet<span>Aid</span></h1>
    <nav>
        <a href="index.php" class="active">Dashboard</a>
        <a href="reports.php">Reports</a>
        <a href="assistance.php">Assistance</a>
        <a href="users.php">Users</a>
    </nav>
</header>

<div class="container">
    <div class="page-header">
        <h2>Dashboard Overview</h2>
        <a href="add_report.php" class="btn">+ Report an Animal</a>
    </div>

    <!-- Simple Number Cards -->
    <div class="cards-grid">
        <div class="card">
            <h3>Total Reports</h3>
            <div class="number"><?= (int)$totalReports ?></div>
        </div>
        <div class="card open">
            <h3>Open Cases</h3>
            <div class="number"><?= (int)$openCases ?></div>
        </div>
        <div class="card critical">
            <h3>Critical Cases</h3>
            <div class="number"><?= (int)$criticalCases ?></div>
        </div>
        <div class="card rescued">
            <h3>Rescued Cases</h3>
            <div class="number"><?= (int)$rescuedCases ?></div>
        </div>
        <div class="card">
            <h3>Closed Cases</h3>
            <div class="number"><?= (int)$closedCases ?></div>
        </div>
    </div>

    <!-- Recent Reports Table -->
    <div class="page-header">
        <h2>Recent Animal Reports</h2>
        <a href="reports.php" class="btn btn-secondary btn-sm">View All Reports →</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Animal</th>
                <th>Location</th>
                <th>Condition</th>
                <th>Status</th>
                <th>Reported By</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($recentReports)): ?>
                <tr><td colspan="7" style="text-align:center;">No animal reports found.</td></tr>
            <?php else: ?>
                <?php foreach ($recentReports as $r): ?>
                    <tr>
                        <td>#<?= htmlspecialchars($r['report_id']) ?></td>
                        <td><strong><?= htmlspecialchars($r['animal_type']) ?></strong></td>
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
                            <a href="report_details.php?id=<?= (int)$r['report_id'] ?>" class="btn btn-secondary btn-sm">View Case</a>
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
