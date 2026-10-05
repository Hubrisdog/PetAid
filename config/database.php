<?php
// ============================================================
// config/database.php
// Simple PDO Database Connection
// ============================================================

$host = 'localhost';
$dbname = 'petaid';
$username = 'root';
$password = '';

try {
    // Connect to MySQL (standard college/XAMPP setup)
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    // Fallback: If MySQL service is not running locally, use SQLite
    // so the student or instructor can run the project immediately with zero setup!
    $sqliteFile = __DIR__ . '/../database/petaid.sqlite';
    $isNew = !file_exists($sqliteFile);

    $pdo = new PDO("sqlite:" . $sqliteFile, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    $pdo->exec("PRAGMA foreign_keys = ON;");

    if ($isNew) {
        // Create tables
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                user_id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                contact_number TEXT,
                role TEXT DEFAULT 'reporter',
                status TEXT DEFAULT 'active'
            );

            CREATE TABLE IF NOT EXISTS animal_reports (
                report_id INTEGER PRIMARY KEY AUTOINCREMENT,
                reported_by INTEGER NOT NULL,
                animal_type TEXT NOT NULL,
                description TEXT NOT NULL,
                location TEXT NOT NULL,
                condition TEXT NOT NULL,
                status TEXT DEFAULT 'reported',
                created_at TEXT DEFAULT (datetime('now')),
                FOREIGN KEY (reported_by) REFERENCES users(user_id)
            );

            CREATE TABLE IF NOT EXISTS assistance_requests (
                request_id INTEGER PRIMARY KEY AUTOINCREMENT,
                report_id INTEGER NOT NULL,
                volunteer_id INTEGER NOT NULL,
                request_type TEXT NOT NULL,
                status TEXT DEFAULT 'pending',
                requested_at TEXT DEFAULT (datetime('now')),
                FOREIGN KEY (report_id) REFERENCES animal_reports(report_id),
                FOREIGN KEY (volunteer_id) REFERENCES users(user_id)
            );

            CREATE TABLE IF NOT EXISTS case_updates (
                update_id INTEGER PRIMARY KEY AUTOINCREMENT,
                report_id INTEGER NOT NULL,
                updated_by INTEGER NOT NULL,
                update_text TEXT NOT NULL,
                new_status TEXT,
                created_at TEXT DEFAULT (datetime('now')),
                FOREIGN KEY (report_id) REFERENCES animal_reports(report_id),
                FOREIGN KEY (updated_by) REFERENCES users(user_id)
            );
        ");

        // Insert sample users
        $pdo->exec("
            INSERT INTO users (user_id, name, email, contact_number, role, status) VALUES
            (1, 'Juan Dela Cruz', 'juan@example.com', '0918-234-5678', 'reporter', 'active'),
            (2, 'Maria Santos', 'maria@example.com', '0917-123-4567', 'volunteer', 'active'),
            (3, 'Alex Reyes', 'alex@example.com', '0919-345-6789', 'volunteer', 'active'),
            (4, 'Dr. Jose Rizal', 'jose@example.com', '0921-567-8901', 'admin', 'active'),
            (5, 'Ana Lim', 'ana@example.com', '0922-678-9012', 'reporter', 'inactive');
        ");

        // Insert sample reports
        $pdo->exec("
            INSERT INTO animal_reports (report_id, reported_by, animal_type, description, location, condition, status, created_at) VALUES
            (1, 1, 'Dog', 'Brown dog with injured front leg limping near the security gate.', 'Near university gate', 'injured', 'rescued', '2026-10-06 08:30:00'),
            (2, 1, 'Cat', 'Stray calico cat near cafeteria food stalls searching for food.', 'Cafeteria', 'needs_attention', 'volunteer_assigned', '2026-10-06 09:15:00'),
            (3, 1, 'Dog', 'Friendly black dog needing food near parking lot.', 'Near parking area', 'safe', 'reported', '2026-10-06 10:00:00'),
            (4, 1, 'Bird', 'Maya bird trapped in net near student center.', 'Student Center', 'safe', 'closed', '2026-10-05 14:00:00');
        ");

        // Insert sample assistance requests
        $pdo->exec("
            INSERT INTO assistance_requests (request_id, report_id, volunteer_id, request_type, status, requested_at) VALUES
            (1, 1, 2, 'Rescue', 'completed', '2026-10-06 09:00:00'),
            (2, 2, 3, 'Food & Shelter', 'accepted', '2026-10-06 10:30:00');
        ");

        // Insert sample case updates
        $pdo->exec("
            INSERT INTO case_updates (update_id, report_id, updated_by, update_text, new_status, created_at) VALUES
            (1, 1, 1, 'Report created for injured brown dog.', 'reported', '2026-10-06 08:30:00'),
            (2, 1, 4, 'Guard on duty verified the animal location.', 'verified', '2026-10-06 08:50:00'),
            (3, 1, 2, 'Volunteer Maria assigned for rescue.', 'volunteer_assigned', '2026-10-06 09:00:00'),
            (4, 1, 2, 'Animal successfully rescued and taken to vet.', 'rescued', '2026-10-06 10:15:00'),
            (5, 2, 1, 'Report created for stray calico cat.', 'reported', '2026-10-06 09:15:00'),
            (6, 2, 3, 'Volunteer Alex assigned to bring food and carrier.', 'volunteer_assigned', '2026-10-06 10:30:00'),
            (7, 3, 1, 'Report created for lost dog in parking area.', 'reported', '2026-10-06 10:00:00'),
            (8, 4, 1, 'Maya bird trapped in badminton netting.', 'reported', '2026-10-05 14:00:00'),
            (9, 4, 4, 'Bird safely freed and released into trees. Case closed.', 'closed', '2026-10-05 14:45:00');
        ");
    }
}
