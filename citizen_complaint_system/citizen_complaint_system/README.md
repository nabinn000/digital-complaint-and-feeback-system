# Digital Citizen Complaint and Feedback System

A web-based complaint management system for local government in Nepal, built with PHP, MySQL, HTML, CSS, and JavaScript.

---

## Features

- Citizen registration and login with bcrypt password hashing
- Complaint submission with category selection, file upload, and interactive map location picker
- Unique tracking ID generated for every complaint
- Public complaint tracking without login required
- Automated email notifications to citizens on status updates
- Citizen satisfaction rating (1–5 stars) after complaint resolution
- Administrator dashboard with complaint management, status updates, and internal notes
- Interactive admin map showing all pinned complaints colour-coded by status
- Statistical reports with complaint breakdown by category and status

---

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- XAMPP (recommended for local development)
- Composer (for PHPMailer)
- A modern web browser (Chrome, Firefox, Edge)

---

## Installation

### Step 1 — Install XAMPP

Download and install XAMPP from [apachefriends.org](https://www.apachefriends.org).

Start **Apache** and **MySQL** from the XAMPP control panel.

---

### Step 2 — Copy the project files

Copy the `citizen_complaint_system` folder into your XAMPP `htdocs` directory:

```
C:\xampp\htdocs\citizen_complaint_system\
```

---

### Step 3 — Create the database

1. Open your browser and go to:
```
http://localhost/phpmyadmin
```

2. Click **New** and create a database named:
```
complaint_system
```

3. Select the database, click the **SQL** tab, paste the following and click **Go**:

```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    status ENUM('Pending', 'In Progress', 'Resolved') DEFAULT 'Pending',
    tracking_number VARCHAR(20) UNIQUE,
    category VARCHAR(50),
    evidence VARCHAR(255),
    latitude DECIMAL(10,8) NULL,
    longitude DECIMAL(11,8) NULL,
    feedback_rating TINYINT(1) NULL,
    feedback_comment TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE complaint_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id INT NOT NULL,
    admin_id INT NOT NULL,
    note TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES users(id)
);
```

---

### Step 4 — Create an admin account

After running the SQL above, insert an admin user by running this in phpMyAdmin SQL tab (replace the password hash with your own generated using bcrypt):

```sql
INSERT INTO users (full_name, email, password, role)
VALUES ('Admin User', 'admin@example.com', '$2y$10$YourBcryptHashHere', 'admin');
```

To generate a bcrypt hash for your chosen password, create a temporary PHP file in htdocs:

```php
<?php
echo password_hash('your_password_here', PASSWORD_BCRYPT);
?>
```

Visit `http://localhost/hash.php`, copy the output, paste it into the SQL above, then delete the file.

---

### Step 5 — Configure the database connection

Open `includes/db.php` and update the credentials if needed:

```php
$host     = 'localhost';
$dbname   = 'complaint_system';
$username = 'root';
$password = '';
```

---

### Step 6 — Install PHPMailer (for email notifications)

Open a terminal in the `citizen_complaint_system` folder and run:

```bash
composer require phpmailer/phpmailer
```

If you do not have Composer, download it from [getcomposer.org](https://getcomposer.org).

---

### Step 7 — Configure email notifications

Open `includes/email_notification.php` and update the SMTP settings:

```php
$mail->Host       = 'smtp.gmail.com';   // Your SMTP server
$mail->Username   = 'your@email.com';   // Your email address
$mail->Password   = 'your_app_password'; // Your app password
$mail->Port       = 587;
```

For testing without a live email account, use [Mailtrap](https://mailtrap.io) — sign up free and copy the SMTP credentials from your Mailtrap inbox settings.

---

### Step 8 — Create the uploads folder

Make sure an `uploads` folder exists in the project root and is writable:

```
citizen_complaint_system/uploads/
```

If it does not exist, create it manually. On Windows XAMPP it should work automatically. On Linux/Mac run:

```bash
chmod 755 uploads/
```

---

## Running the system

Open your browser and go to:

```
http://localhost/citizen_complaint_system/
```

**Citizen login:** Register a new account on the registration page.

**Admin login:** Use the admin credentials you created in Step 4.

---

## Project structure

```
citizen_complaint_system/
├── index.php                  — Homepage
├── register.php               — Citizen registration
├── login.php                  — Login page
├── logout.php                 — Session logout
├── dashboard.php              — Citizen dashboard
├── submit_complaint.php       — Complaint submission with map
├── view_complaints.php        — Citizen complaint history and feedback
├── track.php                  — Public complaint tracking
├── admin/
│   ├── admin_dashboard.php    — Admin overview
│   ├── manage_complaints.php  — Complaint list with filters
│   ├── complaint_detail.php   — View, update, and add notes
│   ├── complaints_map.php     — Geographic map of complaints
│   └── reports.php            — Statistics and charts
├── includes/
│   ├── auth.php               — Session, authentication, CSRF
│   ├── db.php                 — Database connection
│   └── email_notification.php — PHPMailer email helper
└── uploads/                   — Uploaded evidence files
```

---

## Technologies used

| Technology | Purpose |
|---|---|
| PHP 7.4+ | Server-side application logic |
| MySQL | Relational database |
| HTML / CSS / JavaScript | Frontend interface |
| Leaflet.js 1.9.4 | Interactive maps |
| OpenStreetMap | Free map tiles |
| Nominatim API | Address geocoding search |
| PHPMailer | SMTP email notifications |
| Chart.js | Reports bar chart |
| XAMPP | Local development environment |

---

## Security features

- Passwords hashed with bcrypt via `password_hash()`
- CSRF tokens on all POST forms verified with `hash_equals()`
- SQL injection prevented with PDO prepared statements
- XSS prevented with `htmlspecialchars()` on all output
- Session fixation prevented with `session_regenerate_id(true)` on login
- Login lockout after 5 consecutive failed attempts
- File uploads validated by extension whitelist and real MIME type check
- Role-based access control enforced on all admin pages

---

## Academic context

This system was developed as a final year undergraduate project at the University of West London, demonstrating the application of software engineering principles in a public sector context. It is a prototype intended for academic evaluation and is not intended for live government deployment without further development.

---

## License

This project was created for academic purposes. All rights reserved.
