<?php
// ============================================================
// api/assistance.php
// Assistance Requests REST-style API endpoint
// ============================================================

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                $stmt = $pdo->prepare("
                    SELECT a.*, u.name AS volunteer_name, u.email AS volunteer_email, u.contact_number AS volunteer_contact,
                           r.animal_type, r.location, r.status AS report_status
                    FROM assistance_requests a
                    JOIN users u ON a.volunteer_id = u.user_id
                    JOIN animal_reports r ON a.report_id = r.report_id
                    WHERE a.request_id = ?
                ");
                $stmt->execute([$_GET['id']]);
                $req = $stmt->fetch();

                if (!$req) {
                    http_response_code(404);
                    echo json_encode(["success" => false, "message" => "Assistance request not found."]);
                    exit;
                }

                echo json_encode(["success" => true, "data" => $req]);
            } else {
                $sql = "
                    SELECT a.*, u.name AS volunteer_name, u.contact_number AS volunteer_contact,
                           r.animal_type, r.location, r.status AS report_status
                    FROM assistance_requests a
                    JOIN users u ON a.volunteer_id = u.user_id
                    JOIN animal_reports r ON a.report_id = r.report_id
                    WHERE 1=1
                ";
                $params = [];

                if (!empty($_GET['status'])) {
                    $sql .= " AND a.status = ?";
                    $params[] = $_GET['status'];
                }

                if (!empty($_GET['report_id'])) {
                    $sql .= " AND a.report_id = ?";
                    $params[] = $_GET['report_id'];
                }

                $sql .= " ORDER BY a.request_id DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $requests = $stmt->fetchAll();

                echo json_encode(["success" => true, "data" => $requests]);
            }
            break;

        case 'POST':
            $report_id = $input['report_id'] ?? null;
            $volunteer_id = $input['volunteer_id'] ?? null;
            $request_type = trim($input['request_type'] ?? '');
            $status = $input['status'] ?? 'pending';

            if (empty($report_id)) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Report ID is required."]);
                exit;
            }

            if (empty($volunteer_id)) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Volunteer ID is required."]);
                exit;
            }

            if (empty($request_type)) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Request type is required."]);
                exit;
            }

            // Verify report exists
            $rCheck = $pdo->prepare("SELECT report_id, status FROM animal_reports WHERE report_id = ?");
            $rCheck->execute([$report_id]);
            $report = $rCheck->fetch();
            if (!$report) {
                http_response_code(404);
                echo json_encode(["success" => false, "message" => "Report not found."]);
                exit;
            }

            // Rule 1: Only users with role = 'volunteer' can be assigned
            $vCheck = $pdo->prepare("SELECT user_id, name, role, status FROM users WHERE user_id = ?");
            $vCheck->execute([$volunteer_id]);
            $volunteer = $vCheck->fetch();

            if (!$volunteer) {
                http_response_code(404);
                echo json_encode(["success" => false, "message" => "User not found."]);
                exit;
            }

            if ($volunteer['role'] !== 'volunteer') {
                http_response_code(400);
                echo json_encode([
                    "success" => false,
                    "message" => "User '{$volunteer['name']}' is not a volunteer. Only users with role 'volunteer' can be assigned."
                ]);
                exit;
            }

            if ($volunteer['status'] !== 'active') {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Cannot assign an inactive volunteer."]);
                exit;
            }

            $stmt = $pdo->prepare("
                INSERT INTO assistance_requests (report_id, volunteer_id, request_type, status, requested_at)
                VALUES (?, ?, ?, ?, datetime('now'))
            ");
            $stmt->execute([$report_id, $volunteer_id, $request_type, $status]);
            $requestId = $pdo->lastInsertId();

            // Automatically advance report status to 'volunteer_assigned' if currently 'reported' or 'verified'
            if ($report['status'] === 'reported' || $report['status'] === 'verified') {
                $pdo->prepare("UPDATE animal_reports SET status = 'volunteer_assigned' WHERE report_id = ?")->execute([$report_id]);
                $cstmt = $pdo->prepare("
                    INSERT INTO case_updates (report_id, updated_by, update_text, new_status, created_at)
                    VALUES (?, ?, ?, 'volunteer_assigned', datetime('now'))
                ");
                $cstmt->execute([$report_id, $volunteer_id, "Volunteer {$volunteer['name']} assigned for {$request_type}."]);
            }

            http_response_code(201);
            echo json_encode([
                "success" => true,
                "message" => "Assistance request created successfully.",
                "data" => ["request_id" => $requestId]
            ]);
            break;

        case 'PUT':
            $id = $_GET['id'] ?? $input['request_id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Request ID is required."]);
                exit;
            }

            $stmt = $pdo->prepare("SELECT * FROM assistance_requests WHERE request_id = ?");
            $stmt->execute([$id]);
            $current = $stmt->fetch();
            if (!$current) {
                http_response_code(404);
                echo json_encode(["success" => false, "message" => "Assistance request not found."]);
                exit;
            }

            $request_type = trim($input['request_type'] ?? $current['request_type']);
            $status = $input['status'] ?? $current['status'];
            $volunteer_id = $input['volunteer_id'] ?? $current['volunteer_id'];

            // If volunteer is changed, re-verify role = 'volunteer'
            if ($volunteer_id != $current['volunteer_id']) {
                $vCheck = $pdo->prepare("SELECT user_id, name, role, status FROM users WHERE user_id = ?");
                $vCheck->execute([$volunteer_id]);
                $volunteer = $vCheck->fetch();
                if (!$volunteer || $volunteer['role'] !== 'volunteer') {
                    http_response_code(400);
                    echo json_encode(["success" => false, "message" => "Only users with role 'volunteer' can be assigned."]);
                    exit;
                }
            }

            $ustmt = $pdo->prepare("
                UPDATE assistance_requests
                SET request_type = ?, status = ?, volunteer_id = ?
                WHERE request_id = ?
            ");
            $ustmt->execute([$request_type, $status, $volunteer_id, $id]);

            echo json_encode([
                "success" => true,
                "message" => "Assistance request updated successfully."
            ]);
            break;

        case 'DELETE':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Request ID is required."]);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM assistance_requests WHERE request_id = ?");
            $stmt->execute([$id]);

            echo json_encode([
                "success" => true,
                "message" => "Assistance request deleted successfully."
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
