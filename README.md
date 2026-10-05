# PetAid — Community Animal Assistance Mini System

**Course:** Integrative Programming (College Level Project)  
**Project Name:** PetAid  
**Student Activity:** Small Web-Based Animal Assistance Reporting & Case Tracking Mini System  

---

## 1. Project Description

**PetAid** is a simple, college-level community animal assistance reporting system. It is designed to help campus community members report stray, injured, or vulnerable animals (such as dogs, cats, and birds), dispatch student volunteers, and track the status of each case from the initial report until resolution.

### Core Concept: Case Tracking
Each report represents a case with a lifecycle:
```text
Reported → Verified → Assistance Requested → Volunteer Assigned → Rescued → Treated → Closed
```

---

## 2. Features

- **Dashboard:** Simple overview showing total reports, open cases, critical cases, rescued cases, and closed cases.
- **Animal Reports (Full CRUD):**
  - Create new reports (Animal Type, Description, Location, Condition, Reporter).
  - View all reports in a clean table with search and filtering (by animal, condition, and status).
  - View full report details with reporter information and history.
  - Edit report details and update status.
  - Delete/remove reports.
- **Volunteer Assistance Dispatch (Full CRUD):**
  - Assign registered volunteers to cases (Rescue, Medical, Food, Transport, etc.).
  - View all active and completed assistance requests.
  - Update status (Pending, Accepted, Completed, Cancelled).
- **Case History Timeline:**
  - Automatically records a chronological entry in `case_updates` every time a report's status changes.
- **User & Volunteer Management:**
  - Register community members and volunteers.
  - Soft-deactivate accounts (`status = 'inactive'`) to preserve relational database integrity.
- **REST-style API:**
  - Simple PHP endpoints for Users, Reports, Assistance Requests, and Case Updates returning clean JSON.

---

## 3. Technology Used

- **Backend:** PHP 8.x (using standard PDO and prepared statements)
- **Database:** MySQL / MariaDB (via `database/petaid.sql`, with zero-config SQLite fallback)
- **Frontend:** Plain HTML5, CSS3, and Vanilla JavaScript
- **API:** REST-style JSON endpoints using standard HTTP methods (`GET`, `POST`, `PUT`, `DELETE`)

*No complex frameworks, ORMs, build tools, or third-party dependencies were used.*

---

## 4. Database Setup

The database schema consists of **four core tables**:

1. `users`: Stores community reporters, volunteers, and admins.
2. `animal_reports`: Stores reported animal cases (references `users.user_id`).
3. `assistance_requests`: Stores volunteer dispatch tasks (references `animal_reports.report_id` and `users.user_id`).
4. `case_updates`: Stores the history of case status changes (references `animal_reports.report_id` and `users.user_id`).

### How to Import into MySQL (e.g. XAMPP)
1. Start **Apache** and **MySQL** in XAMPP / WampServer.
2. Open **phpMyAdmin** (`http://localhost/phpmyadmin`) or MySQL CLI.
3. Import the file:
   ```bash
   mysql -u root -p < database/petaid.sql
   ```
4. Verify database credentials in `config/database.php` (default: host `localhost`, user `root`, no password).

*(Note: If MySQL is not running on your machine, `config/database.php` automatically uses a local SQLite database so the system can be demonstrated immediately with zero configuration).*

---

## 5. How to Run the Project

### Option A: Using PHP Built-in Server (Easiest)
1. Open terminal/PowerShell in the project folder:
   ```bash
   cd minisys
   ```
2. Run the PHP server:
   ```bash
   php -S localhost:8000
   ```
3. Open your browser and navigate to:
   ```
   http://localhost:8000
   ```

### Option B: Using XAMPP
1. Move or clone this folder into `htdocs`:
   ```text
   C:\xampp\htdocs\petaid
   ```
2. Open your browser:
   ```
   http://localhost/petaid
   ```

---

## 6. Project Structure

```text
petaid/
│
├── api/
│   ├── users.php          # Users REST API endpoint
│   ├── reports.php        # Reports REST API endpoint
│   ├── assistance.php     # Assistance requests API endpoint
│   └── case_updates.php   # Case updates API endpoint
│
├── database/
│   └── petaid.sql         # MySQL database dump and sample data
│
├── config/
│   └── database.php       # Simple PDO database connection
│
├── css/
│   └── style.css          # Clean, simple student project CSS
│
├── js/
│   └── script.js          # Helper JavaScript functions
│
├── index.php              # Dashboard homepage with summary cards
├── reports.php            # List of reports with search and filter
├── report_details.php     # Case view, volunteer assignment & history
├── add_report.php         # Form to submit a new animal report
├── edit_report.php        # Form to edit a report
├── assistance.php         # Volunteer assistance management
├── users.php              # User registration & soft-deactivation
└── README.md
```

---

## 7. Business Logic Implemented

- **Rule 1:** Only users with `role = 'volunteer'` can be assigned to an assistance request.
- **Rule 2:** A report cannot be marked as `closed` unless it has first reached `rescued` or `treated`.
- **Rule 3:** Whenever a report's status changes, an entry is automatically recorded in `case_updates`.

---

## 8. API Endpoints Summary

All API endpoints return simple JSON in the format:
- Success: `{ "success": true, "message": "...", "data": ... }`
- Error: `{ "success": false, "message": "..." }`

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/reports.php` | Get all reports (supports `?search=`, `?animal_type=`, `?condition=`, `?status=`) |
| `GET` | `/api/reports.php?id=1` | Get single report with assistance and case history |
| `POST` | `/api/reports.php` | Create a new animal report |
| `PUT` | `/api/reports.php?id=1` | Update report details / status |
| `DELETE` | `/api/reports.php?id=1` | Delete report and related updates |
| `GET` | `/api/users.php` | List all users (supports `?role=`, `?status=`) |
| `POST` | `/api/users.php` | Register a new user / volunteer |
| `PUT` | `/api/users.php?id=1` | Update user details |
| `DELETE` | `/api/users.php?id=1` | Soft-deactivate user (`status = 'inactive'`) |
| `GET` | `/api/assistance.php` | List all volunteer assistance requests |
| `POST` | `/api/assistance.php` | Assign volunteer to an animal report |
| `PUT` | `/api/assistance.php?id=1` | Update assistance status (e.g. `completed`) |
| `DELETE` | `/api/assistance.php?id=1` | Delete an assistance request |
| `GET` | `/api/case_updates.php?report_id=1` | View history updates for a specific report |
| `POST` | `/api/case_updates.php` | Add a case update record |

---

## 9. CRUD Operations Supported

- **Users:** Create user, View users, Update info, Soft-deactivate user.
- **Animal Reports:** Create report, Read/Search/Filter reports, Update details & status, Delete report.
- **Assistance Requests:** Create request, View requests, Update status, Delete request.
- **Case Updates:** Auto-created on status updates, Read chronological history.
