<?php
session_start();

if (isset($_SESSION["user_id"])) {
    header("Location: " . ($_SESSION["role"] == "admin" ? "admin/admin_dashboard.php" : "dashboard.php"));
    exit();
}

include("includes/db.php");

// CSRF helpers (inline — login doesn't include auth.php)
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
    return $_SESSION['csrf_token'];
}
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}
function csrf_verify(): void {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        http_response_code(403); die('Invalid request. Please go back and try again.');
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message         = "";
$max_attempts    = 5;
$lockout_seconds = 300; // 5 minutes

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    csrf_verify();

    $email    = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    $attempt_key = 'login_attempts_' . md5($email);
    $lockout_key = 'login_lockout_'  . md5($email);

    if (!empty($_SESSION[$lockout_key]) && $_SESSION[$lockout_key] > time()) {
        $wait    = ceil(($_SESSION[$lockout_key] - time()) / 60);
        $message = "Too many failed attempts. Please wait {$wait} minute(s) before trying again.";
    } else {
        $stmt = $conn->prepare("SELECT id, full_name, password, role FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user["password"])) {
                unset($_SESSION[$attempt_key], $_SESSION[$lockout_key]);
                session_regenerate_id(true);
                $_SESSION["user_id"]   = $user["id"];
                $_SESSION["full_name"] = $user["full_name"];
                $_SESSION["role"]      = $user["role"];
                header("Location: " . ($user["role"] == "admin" ? "admin/admin_dashboard.php" : "dashboard.php"));
                exit();
            }
        }

        $_SESSION[$attempt_key] = ($_SESSION[$attempt_key] ?? 0) + 1;
        if ($_SESSION[$attempt_key] >= $max_attempts) {
            $_SESSION[$lockout_key] = time() + $lockout_seconds;
            unset($_SESSION[$attempt_key]);
            $message = "Too many failed attempts. Account locked for 5 minutes.";
        } else {
            $remaining = $max_attempts - $_SESSION[$attempt_key];
            $message   = "Incorrect email or password. {$remaining} attempt(s) remaining.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Citizen Complaint Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', sans-serif; background: #eef2f7; min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 24px; }
        .brand { display: flex; align-items: center; gap: 10px; margin-bottom: 28px; animation: fadeUp 0.5s ease both; }
        .brand-icon { width: 40px; height: 40px; background: #0b1f3a; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .brand-text { font-size: 13px; font-weight: 500; color: #0b1f3a; line-height: 1.4; }
        .brand-text span { display: block; font-size: 11px; font-weight: 300; color: #6b7280; }
        .card { background: #fff; border-radius: 14px; padding: 36px 40px; width: 100%; max-width: 400px; box-shadow: 0 2px 16px rgba(0,0,0,0.07); animation: fadeUp 0.5s 0.08s ease both; }
        .card-title { font-size: 20px; font-weight: 500; color: #0b1f3a; margin-bottom: 4px; }
        .card-sub { font-size: 13px; color: #6b7280; font-weight: 300; margin-bottom: 28px; }
        .error-box { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 10px 14px; font-size: 13px; color: #b91c1c; margin-bottom: 20px; animation: shake 0.35s ease; }
        .field { margin-bottom: 18px; }
        .field label { display: block; font-size: 12px; font-weight: 500; color: #374151; margin-bottom: 6px; }
        .input-wrap { position: relative; }
        .input-wrap input { width: 100%; height: 44px; padding: 0 40px 0 14px; border: 1.5px solid #e5e7eb; border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 14px; color: #111827; outline: none; transition: border-color 0.2s, box-shadow 0.2s; background: #fff; }
        .input-wrap input:focus { border-color: #0b1f3a; box-shadow: 0 0 0 3px rgba(11,31,58,0.07); }
        .toggle-pw { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 14px; color: #9ca3af; padding: 0; }
        .toggle-pw:hover { color: #374151; }
        .btn { width: 100%; height: 46px; background: #0b1f3a; color: #fff; border: none; border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 14px; font-weight: 500; cursor: pointer; margin-top: 4px; transition: background 0.2s; }
        .btn:hover { background: #122848; }
        .footer-link { margin-top: 20px; text-align: center; font-size: 13px; color: #6b7280; }
        .footer-link a { color: #0b1f3a; font-weight: 500; text-decoration: none; }
        .footer-link a:hover { text-decoration: underline; }
        .page-footer { margin-top: 24px; font-size: 11px; color: #9ca3af; text-align: center; animation: fadeUp 0.5s 0.15s ease both; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes shake { 0%,100% { transform: translateX(0); } 25% { transform: translateX(-5px); } 75% { transform: translateX(5px); } }
    </style>
</head>
<body>
    <div class="brand">
        <div class="brand-icon">🏛</div>
        <div class="brand-text">Citizen Complaint Portal<span>Government of Nepal — Ministry of Public Services</span></div>
    </div>
    <div class="card">
        <h2 class="card-title">Sign in</h2>
        <p class="card-sub">Access your complaint dashboard</p>
        <?php if (!empty($message)): ?>
        <div class="error-box">⚠ <?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <form method="POST" action="login.php" novalidate>
            <?php echo csrf_field(); ?>
            <div class="field">
                <label for="email">Email address</label>
                <div class="input-wrap">
                    <input type="email" id="email" name="email" placeholder="you@example.com"
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                        required autocomplete="email">
                </div>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <div class="input-wrap">
                    <input type="password" id="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
                    <button type="button" class="toggle-pw" onclick="togglePw()"><span id="eye">👁</span></button>
                </div>
            </div>
            <button type="submit" class="btn">Sign in</button>
        </form>
        <div class="footer-link">Don't have an account? <a href="register.php">Register</a></div>
    </div>
    <p class="page-footer">Official Government Portal &nbsp;·&nbsp; All Rights Reserved &copy; 2026</p>
    <script>
        function togglePw() {
            const input = document.getElementById('password');
            const eye = document.getElementById('eye');
            input.type = input.type === 'password' ? 'text' : 'password';
            eye.textContent = input.type === 'password' ? '👁' : '🙈';
        }
    </script>
</body>
</html>