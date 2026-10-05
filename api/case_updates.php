<?php
// ============================================================
// api/case_updates.php
// Case Updates REST-style API endpoint
// ============================================================

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

try {
    switch ($method) {
        case 'GET':
            $report_id = $_GET['report_id'] ?? null;
            if ($report_id) {
                $stmt = $pdo->prepare("
                    SELECT c.*, u.name AS updated_by_name
                    FROM case_updates c
                    JOIN users u ON c.updated_by = u.user_id
                    WHERE c.report_id = ?
                    ORDER BY c.update_id ASC
                ");
                $stmt->execute([$report_id]);
                $updates = $stmt->fetchAll();
                echo json_encode(["success" => true, "data" => $updates]);
            } else {
                $stmt = $pdo->query("
                    SELECT c.*, u.name AS updated_by_name, r.animal_type, r.location
                    FROM case_updates c
                    JOIN users u ON c.updated_by = u.user_id
                    JOIN animal_reports r ON c.report_id = r.report_id
                    ORDER BY c.update_id DESC
                    LIMIT 20
                ");
                $updates = $stmt->fetchAll();
                echo json_encode(["success" => true, "data" => $updates]);
            }
            break;

        case 'POST':
            $report_id = $input['report_id'] ?? null;
            $updated_by = $input['updated_by'] ?? null;
            $update_text = trim($input['update_text'] ?? '');
            $new_status = $input['new_status'] ?? null;

            if (empty($report_id) || empty($updated_by) || empty($update_text)) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "report_id, updated_by, and update_text are required."]);
                exit;
            }

            $stmt = $pdo->prepare("
                INSERT INTO case_updates (report_id, updated_by, update_text, new_status, created_at)
                VALUES (?, ?, ?, ?, datetime('now'))
            ");
            $stmt->execute([$report_id, $updated_by, $update_text, $new_status]);

            // If a new status is specified, update the report status as well
            if (!empty($new_status)) {
                $ustmt = $pdo->prepare("UPDATE animal_reports SET status = ? WHERE report_id = ?");
                $ustmt->execute([$new_status, $report_id]);
            }

            http_response_code(201);
            echo json_encode([
                "success" => true,
                "message" => "Case update added successfully.",
                "data" => ["update_id" => $pdo->lastInsertId()]
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
