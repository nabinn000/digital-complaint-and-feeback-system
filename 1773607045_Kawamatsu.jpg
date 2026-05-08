<?php
session_start();
include("includes/db.php");

$complaint = null;
$error     = "";
$searched  = false;

if ($_SERVER["REQUEST_METHOD"] === "POST" || !empty($_GET['id'])) {
    $tracking_number = trim($_POST['tracking_number'] ?? $_GET['id'] ?? '');
    $searched = true;

    if (empty($tracking_number)) {
        $error = "Please enter a tracking ID.";
    } else {
        $stmt = $conn->prepare(
            "SELECT complaints.id, complaints.title, complaints.description,
                    complaints.category, complaints.status, complaints.tracking_number,
                    complaints.created_at, complaints.evidence, users.full_name
             FROM complaints
             JOIN users ON complaints.user_id = users.id
             WHERE complaints.tracking_number = ?"
        );
        $stmt->bind_param("s", $tracking_number);
        $stmt->execute();
        $complaint = $stmt->get_result()->fetch_assoc();
        if (!$complaint) $error = "No complaint found with that tracking ID. Please check and try again.";
    }
}

$status_steps = ['Pending', 'In Progress', 'Resolved'];
$status_index = $complaint ? array_search($complaint['status'], $status_steps) : -1;
$icons = ['Road Issues'=>'🛣️','Water Supply'=>'💧','Electricity'=>'⚡','Sanitation'=>'🗑️','Public Safety'=>'🚨','Other'=>'📝'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Complaint — Citizen Complaint Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root { --navy:#0b1f3a; --bg:#eef2f7; --white:#fff; --border:#e5e7eb; --muted:#6b7280; --text:#111827; }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; flex-direction: column; }
        nav { background: var(--navy); height: 58px; padding: 0 28px; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
        .nav-brand { display: flex; align-items: center; gap: 9px; text-decoration: none; }
        .nav-brand span { font-size: 14px; font-weight: 500; color: #fff; }
        .nav-right { display: flex; gap: 6px; }
        .nav-right a { font-size: 12.5px; color: rgba(255,255,255,0.7); text-decoration: none; padding: 6px 13px; border-radius: 6px; transition: background 0.15s; }
        .nav-right a:hover { background: rgba(255,255,255,0.12); color: #fff; }
        .page-wrap { flex: 1; display: flex; flex-direction: column; align-items: center; padding: 48px 20px; }
        .page-title { font-size: 22px; font-weight: 500; color: var(--navy); margin-bottom: 6px; text-align: center; }
        .page-sub { font-size: 14px; color: var(--muted); font-weight: 300; margin-bottom: 32px; text-align: center; }
        .search-card { background: var(--white); border: 1px solid var(--border); border-radius: 14px; padding: 28px 32px; width: 100%; max-width: 520px; margin-bottom: 24px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
        .search-row { display: flex; gap: 10px; }
        .search-input { flex: 1; height: 46px; padding: 0 16px; border: 1.5px solid var(--border); border-radius: 8px; font-family: monospace; font-size: 14px; color: var(--text); outline: none; transition: border-color 0.2s; }
        .search-input:focus { border-color: var(--navy); box-shadow: 0 0 0 3px rgba(11,31,58,0.07); }
        .search-btn { height: 46px; padding: 0 24px; background: var(--navy); color: #fff; border: none; border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 14px; font-weight: 500; cursor: pointer; white-space: nowrap; transition: background 0.15s; }
        .search-btn:hover { background: #122848; }
        .search-hint { font-size: 12px; color: #9ca3af; margin-top: 10px; }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; border-radius: 8px; padding: 12px 16px; font-size: 13px; width: 100%; max-width: 520px; margin-bottom: 20px; }
        .result-card { background: var(--white); border: 1px solid var(--border); border-radius: 14px; width: 100%; max-width: 520px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
        .result-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
        .rh-title { font-size: 16px; font-weight: 500; color: var(--navy); margin-bottom: 6px; }
        .rh-meta { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .cat-pill { font-size: 12px; background: #f0f4f9; color: var(--navy); padding: 3px 10px; border-radius: 7px; }
        .date-text { font-size: 12px; color: #9ca3af; }
        .tracking-tag { font-family: monospace; font-size: 11.5px; background: #f3f4f6; color: #374151; padding: 3px 9px; border-radius: 5px; white-space: nowrap; }
        .timeline-wrap { padding: 24px; border-bottom: 1px solid var(--border); }
        .tl-label { font-size: 11px; font-weight: 500; letter-spacing: 0.08em; text-transform: uppercase; color: #9ca3af; margin-bottom: 16px; }
        .timeline { display: flex; align-items: center; }
        .t-step { display: flex; flex-direction: column; align-items: center; flex: 1; }
        .t-dot { width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 500; flex-shrink: 0; }
        .t-dot.done    { background: #22c55e; color: #fff; }
        .t-dot.active  { background: var(--navy); color: #fff; }
        .t-dot.waiting { background: #f3f4f6; color: #9ca3af; border: 1.5px solid #e5e7eb; }
        .t-name { font-size: 11.5px; color: var(--muted); margin-top: 6px; text-align: center; }
        .t-name.active { color: var(--navy); font-weight: 500; }
        .t-line { flex: 1; height: 2px; margin-bottom: 22px; }
        .t-line.done    { background: #22c55e; }
        .t-line.waiting { background: #e5e7eb; }
        .result-body { padding: 20px 24px; }
        .rb-label { font-size: 11px; font-weight: 500; letter-spacing: 0.08em; text-transform: uppercase; color: #9ca3af; margin-bottom: 6px; }
        .rb-text { font-size: 13.5px; color: #374151; font-weight: 300; line-height: 1.7; }
        .login-prompt { width: 100%; max-width: 520px; margin-top: 16px; background: #f8fafc; border: 1px solid var(--border); border-radius: 10px; padding: 14px 18px; font-size: 13px; color: var(--muted); text-align: center; }
        .login-prompt a { color: var(--navy); font-weight: 500; text-decoration: none; }
        .login-prompt a:hover { text-decoration: underline; }
        @media (max-width: 560px) { .search-row { flex-direction: column; } .search-btn { width: 100%; } }
    </style>
</head>
<body>
<nav>
    <a class="nav-brand" href="index.php"><span style="font-size:18px">🏛</span><span>Citizen Complaint Portal</span></a>
    <div class="nav-right">
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="<?php echo $_SESSION['role']==='admin' ? 'admin/admin_dashboard.php' : 'dashboard.php'; ?>">Dashboard</a>
            <a href="logout.php">Sign out</a>
        <?php else: ?>
            <a href="login.php">Sign in</a>
            <a href="register.php">Register</a>
        <?php endif; ?>
    </div>
</nav>

<div class="page-wrap">
    <h1 class="page-title">Track your complaint</h1>
    <p class="page-sub">Enter your tracking ID to check the current status — no login required.</p>

    <div class="search-card">
        <form method="POST" action="track.php">
            <div class="search-row">
                <input type="text" name="tracking_number" class="search-input"
                    placeholder="e.g. CMP17234001"
                    value="<?php echo htmlspecialchars($complaint['tracking_number'] ?? ($_POST['tracking_number'] ?? '')); ?>"
                    autocomplete="off" spellcheck="false">
                <button type="submit" class="search-btn">Track</button>
            </div>
            <p class="search-hint">Your tracking ID was shown after submitting your complaint.</p>
        </form>
    </div>

    <?php if ($searched && !empty($error)): ?>
    <div class="alert-error">⚠ <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($complaint): ?>
    <div class="result-card">
        <div class="result-header">
            <div>
                <div class="rh-title"><?php echo ($icons[$complaint['category']] ?? '📝'); ?> <?php echo htmlspecialchars($complaint['title']); ?></div>
                <div class="rh-meta">
                    <span class="cat-pill"><?php echo htmlspecialchars($complaint['category']); ?></span>
                    <span class="date-text">Submitted <?php echo date('d M Y', strtotime($complaint['created_at'])); ?></span>
                </div>
            </div>
            <span class="tracking-tag"><?php echo htmlspecialchars($complaint['tracking_number']); ?></span>
        </div>

        <div class="timeline-wrap">
            <div class="tl-label">Progress</div>
            <div class="timeline">
                <?php foreach ($status_steps as $i => $step):
                    $dot_class  = $i < $status_index ? 'done' : ($i === $status_index ? 'active' : 'waiting');
                    $name_class = $i === $status_index ? 'active' : '';
                ?>
                    <div class="t-step">
                        <div class="t-dot <?php echo $dot_class; ?>"><?php echo $i < $status_index ? '✓' : ($i+1); ?></div>
                        <div class="t-name <?php echo $name_class; ?>"><?php echo $step; ?></div>
                    </div>
                    <?php if ($i < count($status_steps)-1): ?>
                    <div class="t-line <?php echo $i < $status_index ? 'done' : 'waiting'; ?>"></div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="result-body">
            <div class="rb-label">Description</div>
            <div class="rb-text"><?php echo nl2br(htmlspecialchars($complaint['description'])); ?></div>
        </div>
    </div>

    <?php if (!isset($_SESSION['user_id'])): ?>
    <div class="login-prompt">
        Want to submit a new complaint? <a href="register.php">Register</a> or <a href="login.php">sign in</a> to your account.
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>