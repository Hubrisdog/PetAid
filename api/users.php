<?php
// ============================================================
// api/users.php
// Users REST-style API endpoint
// ============================================================

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
                $stmt->execute([$_GET['id']]);
                $user = $stmt->fetch();

                if (!$user) {
                    http_response_code(404);
                    echo json_encode(["success" => false, "message" => "User not found."]);
                    exit;
                }

                echo json_encode(["success" => true, "data" => $user]);
            } else {
                $sql = "SELECT * FROM users WHERE 1=1";
                $params = [];

                if (!empty($_GET['role'])) {
                    $sql .= " AND role = ?";
                    $params[] = $_GET['role'];
                }

                if (!empty($_GET['status'])) {
                    $sql .= " AND status = ?";
                    $params[] = $_GET['status'];
                }

                $sql .= " ORDER BY user_id DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $users = $stmt->fetchAll();

                echo json_encode(["success" => true, "data" => $users]);
            }
            break;

        case 'POST':
            $name = trim($input['name'] ?? '');
            $email = trim($input['email'] ?? '');
            $contact = trim($input['contact_number'] ?? '');
            $role = $input['role'] ?? 'reporter';
            $status = $input['status'] ?? 'active';

            if (empty($name)) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Name is required."]);
                exit;
            }

            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "Valid email is required."]);
                exit;
            }

            // Check if email already exists
            $check = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch()) {
                http_response_code(409);
                echo json_encode(["success" => false, "message" => "Email is already registered."]);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO users (name, email, contact_number, role, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $contact, $role, $status]);
            $newId = $pdo->lastInsertId();

            http_response_code(201);
            echo json_encode([
                "success" => true,
                "message" => "User created successfully.",
                "data" => ["user_id" => $newId, "name" => $name, "email" => $email, "role" => $role, "status" => $status]
            ]);
            break;

        case 'PUT':
            $id = $_GET['id'] ?? $input['user_id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "User ID is required."]);
                exit;
            }

            $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch();
            if (!$user) {
                http_response_code(404);
                echo json_encode(["success" => false, "message" => "User not found."]);
                exit;
            }

            $name = trim($input['name'] ?? $user['name']);
            $email = trim($input['email'] ?? $user['email']);
            $contact = trim($input['contact_number'] ?? $user['contact_number']);
            $role = $input['role'] ?? $user['role'];
            $status = $input['status'] ?? $user['status'];

            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, contact_number = ?, role = ?, status = ? WHERE user_id = ?");
            $stmt->execute([$name, $email, $contact, $role, $status, $id]);

            echo json_encode([
                "success" => true,
                "message" => "User updated successfully."
            ]);
            break;

        case 'DELETE':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(["success" => false, "message" => "User ID is required."]);
                exit;
            }

            // Soft-delete user by setting status = 'inactive'
            $stmt = $pdo->prepare("UPDATE users SET status = 'inactive' WHERE user_id = ?");
            $stmt->execute([$id]);

            echo json_encode([
                "success" => true,
                "message" => "User deactivated successfully."
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
