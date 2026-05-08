<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["user_id"])) {
    header("Location: " . str_repeat("../", substr_count($_SERVER["PHP_SELF"], "/") - 2) . "login.php");
    exit();
}

function requireRole(string $role): void {
    if ($_SESSION["role"] !== $role) {
        if ($_SESSION["role"] === "admin") {
            header("Location: " . str_repeat("../", substr_count($_SERVER["PHP_SELF"], "/") - 2) . "admin/admin_dashboard.php");
        } else {
            header("Location: " . str_repeat("../", substr_count($_SERVER["PHP_SELF"], "/") - 2) . "dashboard.php");
        }
        exit();
    }
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function csrf_verify(): void {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        http_response_code(403);
        die('Invalid request. Please go back and try again.');
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}