<?php
// ============================================================
// api/reports.php
// Animal Reports REST-style API endpoint
// ============================================================

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                // Get single report with reporter name
                $stmt = $pdo->prepare("
                    SELECT r.*, u.name AS reporter_name, u.email AS reporter_email, u.contact_number AS reporter_contact
                    FROM animal_reports r
                    JOIN users u ON r.reported_by = u.user_id
                    WHERE r.report_id = ?
                ");
                $stmt->execute([$_GET['id']]);
                $report = $stmt->fetch();

                if (!$report) {
                    http_response_code(404);
                    echo json_encode(["success" => false, "message" => "Report not found."]);
                    exit;
                }

                // Get assistance requests for this report
                $astmt = $pdo->prepare("
                    SELECT a.*, u.name AS volunteer_name, u.contact_number AS volunteer_contact
                    FROM assistance_requests a
                    JOIN users u ON a.volunteer_id = u.user_id
                    WHERE a.report_id = ?
                    ORDER BY a.request_id DESC
                ");
                $astmt->execute([$_GET['id']]);
                $report['assistance_requests'] = $astmt->fetchAll();

                // Get case updates history for this report
                $hstmt = $pdo->prepare("
                    SELECT c.*, u.name AS updated_by_name
                    FROM case_updates c
                    JOIN users u ON c.updated_by = u.user_id
                    WHERE c.report_id = ?
                    ORDER BY c.update_id ASC
                ");
                $hstmt->execute([$_GET['id']]);
                $report['history'] = $hstmt->fetchAll();

                echo json_encode(["success" => true, "data" => $report]);
            } else {
                // List reports with filters and search
                $sql = "
                    SELECT r.*, u.name AS reporter_name
                    FROM animal_reports r
                    JOIN users u ON r.reported_by = u.user_id
                    WHERE 1=1
                ";
                $params = [];

                if (!empty($_GET['status'])) {
                    $sql .= " AND r.status = ?";
                    $params[] = $_GET['status'];
                }

                if (!empty($_GET['condition'])) {
                    $sql .= " AND r.condition = ?";
                    $params[] = $_GET['condition'];
                }

                if (!empty($_GET['animal_type'])) {
                    $sql .= " AND LOWER(r.animal_type) = LOWER(?)";
                    $params[] = $_GET['animal_type'];
                }

                if (!empty($_GET['search'])) {
                    $sql .= " AND (LOWER(r.animal_type) LIKE ? OR LOWER(r.description) LIKE ? OR LOWER(r.location) LIKE ?)";
                    $term = '%' . strtolower(trim($_GET['search'])) . '%';
                    $params[] = $term;
                    $params[] = $term;
                    $params[] = $term;
                }

                $sql .= " ORDER BY r.report_id DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $reports = $stmt->fetchAll();

                echo json_encode(["success" => true, "data" => $reports]);
            }
            break;

        case 'POST':
            $reported_by = $input['reported_by'] ?? null;
            $animal_type = trim($input['animal_type'] ?? '');
            $description = trim($input['description'] ?? '');
            $location = trim($input['location'] ?? '');
            $condition = $input['condition'] ?? '';

            // Validation
            if (empty($reported_by)) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Reported By (user) is required."]);
                exit;
            }

            if (empty($animal_type)) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Animal type is required."]);
                exit;
            }

            if (empty($description)) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Description is required."]);
                exit;
            }

            if (empty($location)) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Location is required."]);
                exit;
            }

            if (empty($condition)) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Condition is required."]);
                exit;
            }

            // Verify reporting user exists
            $uCheck = $pdo->prepare("SELECT user_id FROM users WHERE user_id = ?");
            $uCheck->execute([$reported_by]);
            if (!$uCheck->fetch()) {
                http_response_code(404);
                echo json_encode(["success" => false, "message" => "Reporting user does not exist."]);
                exit;
            }

            // Initial status is automatically 'reported'
            $status = 'reported';

            $stmt = $pdo->prepare("
                INSERT INTO animal_reports (reported_by, animal_type, description, location, condition, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, datetime('now'))
            ");
            $stmt->execute([$reported_by, $animal_type, $description, $location, $condition, $status]);
            $report_id = $pdo->lastInsertId();

            // Rule 3: Insert initial case update
            $cstmt = $pdo->prepare("
                INSERT INTO case_updates (report_id, updated_by, update_text, new_status, created_at)
                VALUES (?, ?, ?, ?, datetime('now'))
            ");
            $cstmt->execute([$report_id, $reported_by, "Initial report submitted.", $status]);

            http_response_code(201);
            echo json_encode([
                "success" => true,
                "message" => "Report created successfully.",
                "data" => ["report_id" => $report_id]
            ]);
            break;

        case 'PUT':
            $id = $_GET['id'] ?? $input['report_id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Report ID is required."]);
                exit;
            }

            $stmt = $pdo->prepare("SELECT * FROM animal_reports WHERE report_id = ?");
            $stmt->execute([$id]);
            $current = $stmt->fetch();

            if (!$current) {
                http_response_code(404);
                echo json_encode(["success" => false, "message" => "Report not found."]);
                exit;
            }

            $animal_type = trim($input['animal_type'] ?? $current['animal_type']);
            $description = trim($input['description'] ?? $current['description']);
            $location = trim($input['location'] ?? $current['location']);
            $condition = $input['condition'] ?? $current['condition'];
            $new_status = $input['status'] ?? $current['status'];
            $updated_by = $input['updated_by'] ?? $current['reported_by'];
            $update_notes = trim($input['update_text'] ?? '');

            // Rule 2: A report cannot be marked 'closed' unless it has reached 'rescued' or 'treated'
            if ($new_status === 'closed' && $current['status'] !== 'closed') {
                if ($current['status'] !== 'rescued' && $current['status'] !== 'treated') {
                    http_response_code(400);
                    echo json_encode([
                        "success" => false,
                        "message" => "A report cannot be marked closed unless it has been rescued or treated first."
                    ]);
                    exit;
                }
            }

            // Update report record
            $ustmt = $pdo->prepare("
                UPDATE animal_reports
                SET animal_type = ?, description = ?, location = ?, condition = ?, status = ?
                WHERE report_id = ?
            ");
            $ustmt->execute([$animal_type, $description, $location, $condition, $new_status, $id]);

            // Rule 3: When a report status changes, add a row to case_updates
            if ($new_status !== $current['status'] || !empty($update_notes)) {
                $noteText = !empty($update_notes) ? $update_notes : "Status updated from " . $current['status'] . " to " . $new_status . ".";
                $cstmt = $pdo->prepare("
                    INSERT INTO case_updates (report_id, updated_by, update_text, new_status, created_at)
                    VALUES (?, ?, ?, ?, datetime('now'))
                ");
                $cstmt->execute([$id, $updated_by, $noteText, $new_status]);
            }

            echo json_encode([
                "success" => true,
                "message" => "Report updated successfully."
            ]);
            break;

        case 'DELETE':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Report ID is required."]);
                exit;
            }

            // Delete associated case updates & assistance requests, then report
            $pdo->prepare("DELETE FROM case_updates WHERE report_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM assistance_requests WHERE report_id = ?")->execute([$id]);
            $stmt = $pdo->prepare("DELETE FROM animal_reports WHERE report_id = ?");
            $stmt->execute([$id]);

            echo json_encode([
                "success" => true,
                "message" => "Report deleted successfully."
            ]);
            break;

        default:
            http_response_code(405);
            echo json_encode(["success" => false, "message" => "Method not allowed."]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
