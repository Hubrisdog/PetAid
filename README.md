# PetAid

A simple web-based system for reporting animals that need assistance and tracking their cases.

This project was made for an **Integrative Programming** activity. The main purpose is to practice CRUD operations, PHP, MySQL, API requests, and connecting a web application to a database.

## What it does

PetAid allows users to report animals that may need help, such as stray or injured dogs, cats, and other animals.

A report can be followed through different stages:

```text
Reported → Verified → Assistance Requested → Volunteer Assigned → Rescued → Treated → Closed
```

The system also keeps a basic history of changes made to each report.

## Main Features

* Add, view, edit, and delete animal reports
* Search and filter reports
* View the details of a report
* Assign a volunteer to a case
* Update assistance status
* Track the history of a case
* Add and manage users and volunteers
* Simple dashboard with report statistics
* PHP API endpoints that return JSON

## Technologies

* PHP 8
* MySQL / MariaDB
* HTML
* CSS
* JavaScript
* PDO

No major framework is used. The project is kept simple for the scope of the activity.

## Database

The database has four main tables:

* `users` - stores reporters, volunteers, and admins
* `animal_reports` - stores animal reports
* `assistance_requests` - stores volunteer assignments
* `case_updates` - stores changes made to a report

The SQL file with the tables and sample data is located at:

```text
database/petaid.sql
```

## Running the Project

### Using PHP

Make sure PHP is installed, then open a terminal in the project folder:

```bash
php -S localhost:8000
```

Open:

```text
http://localhost:8000
```

### Using XAMPP

Put the project folder inside:

```text
C:\xampp\htdocs\
```

Start Apache and MySQL from XAMPP.

Then open:

```text
http://localhost/petaid
```

Import `database/petaid.sql` into MySQL using phpMyAdmin or the MySQL command line.

Check the database settings in:

```text
config/database.php
```

## Project Structure

```text
petaid/
│
├── api/
│   ├── users.php
│   ├── reports.php
│   ├── assistance.php
│   └── case_updates.php
│
├── config/
│   └── database.php
│
├── database/
│   └── petaid.sql
│
├── css/
│   └── style.css
│
├── js/
│   └── script.js
│
├── index.php
├── reports.php
├── report_details.php
├── add_report.php
├── edit_report.php
├── assistance.php
├── users.php
└── README.md
```

## API

The project has simple PHP API endpoints for working with the database.

### Reports

```text
GET    /api/reports.php
POST   /api/reports.php
GET    /api/reports.php?id=1
PUT    /api/reports.php?id=1
DELETE /api/reports.php?id=1
```

Reports can also be filtered using parameters such as:

```text
/api/reports.php?status=reported
/api/reports.php?condition=injured
/api/reports.php?animal_type=dog
/api/reports.php?search=gate
```

### Users

```text
GET    /api/users.php
POST   /api/users.php
PUT    /api/users.php?id=1
DELETE /api/users.php?id=1
```

### Assistance

```text
GET    /api/assistance.php
POST   /api/assistance.php
PUT    /api/assistance.php?id=1
DELETE /api/assistance.php?id=1
```

### Case Updates

```text
GET    /api/case_updates.php?report_id=1
POST   /api/case_updates.php
```

The APIs return JSON responses.

Example:

```json
{
    "success": true,
    "message": "Report created successfully"
}
```

## Some Rules in the System

There are a few basic rules to keep the data consistent:

* Only users marked as volunteers can be assigned to assistance requests.
* A report cannot be closed before it has been rescued or treated.
* Changes to a report's status are recorded in `case_updates`.
* Users are deactivated instead of being completely removed from the database when possible.

## CRUD

The main CRUD operations are:

| Part           | Create | Read | Update | Delete |
| -------------- | ------ | ---- | ------ | ------ |
| Users          | ✓      | ✓    | ✓      | ✓      |
| Animal Reports | ✓      | ✓    | ✓      | ✓      |
| Assistance     | ✓      | ✓    | ✓      | ✓      |
| Case Updates   | ✓      | ✓    | ✓      | -      |

## Notes

This is a small school project, so the system intentionally keeps the features and implementation simple. It is mainly intended to demonstrate how a PHP application can communicate with a MySQL database through CRUD operations and API requests.
