<?php
include("includes/auth.php");
include("includes/db.php");
requireRole("user");

$user_id = (int) $_SESSION["user_id"];

// Use prepared statements — fixes SQL injection vulnerability
$stats = [];
foreach ([
    'total'    => "SELECT COUNT(*) as c FROM complaints WHERE user_id = ?",
    'pending'  => "SELECT COUNT(*) as c FROM complaints WHERE user_id = ? AND status='Pending'",
    'progress' => "SELECT COUNT(*) as c FROM complaints WHERE user_id = ? AND status='In Progress'",
    'resolved' => "SELECT COUNT(*) as c FROM complaints WHERE user_id = ? AND status='Resolved'",
] as $key => $sql) {
    $s = $conn->prepare($sql);
    $s->bind_param("i", $user_id);
    $s->execute();
    $stats[$key] = $s->get_result()->fetch_assoc()['c'];
}

// 3 most recent complaints
$recent_stmt = $conn->prepare(
    "SELECT title, status, tracking_number, created_at
     FROM complaints WHERE user_id = ?
     ORDER BY created_at DESC LIMIT 3"
);
$recent_stmt->bind_param("i", $user_id);
$recent_stmt->execute();
$recent = $recent_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Citizen Complaint Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root { --navy:#0b1f3a; --bg:#eef2f7; --white:#fff; --border:#e5e7eb; --muted:#6b7280; --text:#111827; }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; flex-direction: column; }

        /* Nav */
        nav { background: var(--navy); height: 58px; padding: 0 28px; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
        .nav-brand { display: flex; align-items: center; gap: 9px; text-decoration: none; }
        .nav-brand span { font-size: 14px; font-weight: 500; color: #fff; }
        .nav-right { display: flex; align-items: center; gap: 6px; }
        .nav-right a { font-size: 12.5px; color: rgba(255,255,255,0.7); text-decoration: none; padding: 6px 13px; border-radius: 6px; transition: background 0.15s; }
        .nav-right a:hover { background: rgba(255,255,255,0.1); color: #fff; }
        .nav-right a.active { background: rgba(255,255,255,0.12); color: #fff; }

        /* Layout */
        .page { display: flex; flex: 1; }
        .sidebar { width: 220px; background: var(--white); border-right: 1px solid var(--border); padding: 24px 0; flex-shrink: 0; }
        .sidebar-label { font-size: 10px; font-weight: 500; letter-spacing: 0.12em; text-transform: uppercase; color: #9ca3af; padding: 0 20px; margin-bottom: 8px; }
        .sidebar a { display: flex; align-items: center; gap: 10px; font-size: 13.5px; color: var(--muted); text-decoration: none; padding: 9px 20px; border-left: 3px solid transparent; transition: all 0.15s; }
        .sidebar a:hover { color: var(--navy); background: #f8fafc; }
        .sidebar a.active { color: var(--navy); font-weight: 500; border-left-color: var(--navy); background: #f0f4f9; }
        .sidebar .si { font-size: 15px; width: 20px; text-align: center; }
        .sidebar-sep { height: 1px; background: var(--border); margin: 12px 0; }
        .main { flex: 1; padding: 32px 28px; overflow-y: auto; }

        /* Page header */
        .page-header { margin-bottom: 28px; }
        .page-header h1 { font-size: 20px; font-weight: 500; color: var(--navy); margin-bottom: 3px; }
        .page-header p { font-size: 13px; color: var(--muted); font-weight: 300; }

        /* Stats */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 28px; }
        .stat-card { background: var(--white); border: 1px solid var(--border); border-radius: 12px; padding: 20px 22px; }
        .stat-label { font-size: 12px; color: var(--muted); margin-bottom: 10px; }
        .stat-value { font-size: 30px; font-weight: 500; color: var(--navy); line-height: 1; margin-bottom: 6px; }
        .stat-bar { height: 3px; background: #f0f4f9; border-radius: 2px; overflow: hidden; }
        .stat-bar-fill { height: 100%; border-radius: 2px; }
        .bar-total    { background: var(--navy); }
        .bar-pending  { background: #f59e0b; }
        .bar-progress { background: #3b82f6; }
        .bar-resolved { background: #22c55e; }

        /* Content row */
        .content-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

        /* Panels */
        .panel { background: var(--white); border: 1px solid var(--border); border-radius: 12px; }
        .panel-header { padding: 18px 22px 14px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .panel-header h3 { font-size: 14px; font-weight: 500; color: var(--navy); }
        .panel-header a { font-size: 12px; color: var(--muted); text-decoration: none; }
        .panel-header a:hover { color: var(--navy); }
        .panel-body { padding: 18px 22px; }

        /* Recent complaints list */
        .complaint-list { display: flex; flex-direction: column; gap: 1px; }
        .complaint-item { display: flex; align-items: center; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f3f4f6; gap: 12px; }
        .complaint-item:last-child { border-bottom: none; }
        .ci-left { flex: 1; min-width: 0; }
        .ci-title { font-size: 13.5px; font-weight: 400; color: var(--text); margin-bottom: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .ci-tracking { font-family: monospace; font-size: 11px; color: #9ca3af; }
        .ci-date { font-size: 11.5px; color: #9ca3af; white-space: nowrap; }

        /* Empty state */
        .empty-state { text-align: center; padding: 36px 16px; }
        .empty-state .emoji { font-size: 32px; margin-bottom: 10px; }
        .empty-state p { font-size: 13px; color: var(--muted); font-weight: 300; margin-bottom: 14px; }
        .empty-state a { font-size: 13px; color: var(--navy); font-weight: 500; text-decoration: none; }
        .empty-state a:hover { text-decoration: underline; }

        /* Quick actions */
        .actions-list { display: flex; flex-direction: column; gap: 10px; }
        .action-item { display: flex; align-items: center; gap: 14px; padding: 14px 16px; border: 1px solid var(--border); border-radius: 10px; text-decoration: none; color: var(--text); transition: border-color 0.15s, box-shadow 0.15s; }
        .action-item:hover { border-color: #c0cdd8; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .action-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
        .action-icon.blue  { background: #eff6ff; }
        .action-icon.green { background: #f0fdf4; }
        .action-text .title { font-size: 13.5px; font-weight: 500; color: var(--navy); margin-bottom: 2px; }
        .action-text .desc  { font-size: 11.5px; color: var(--muted); font-weight: 300; }
        .action-arrow { margin-left: auto; font-size: 16px; color: #d1d5db; }

        /* Status badges */
        .badge { display: inline-flex; align-items: center; gap: 3px; padding: 3px 9px; border-radius: 10px; font-size: 11.5px; }
        .badge-pending  { background: #fef9c3; color: #854d0e; }
        .badge-progress { background: #dbeafe; color: #1e40af; }
        .badge-resolved { background: #dcfce7; color: #166534; }

        @media (max-width: 960px) { .stats-grid { grid-template-columns: repeat(2,1fr); } .content-row { grid-template-columns: 1fr; } }
        @media (max-width: 700px) { .sidebar { display: none; } .main { padding: 20px 16px; } }
    </style>
</head>
<body>

<nav>
    <a class="nav-brand" href="dashboard.php">
        <span style="font-size:18px">🏛</span>
        <span>Citizen Complaint Portal</span>
    </a>
    <div class="nav-right">
        <a href="dashboard.php" class="active">Dashboard</a>
        <a href="submit_complaint.php">Submit</a>
        <a href="view_complaints.php">My Complaints</a>
        <a href="#" onclick="showLogoutModal('logout.php')" >Sign out</a>
    </div>
</nav>

<div class="page">
    <aside class="sidebar">
        <div class="sidebar-label">Menu</div>
        <a href="dashboard.php" class="active"><span class="si">📊</span> Dashboard</a>
        <div class="sidebar-sep"></div>
        <div class="sidebar-label">Complaints</div>
        <a href="submit_complaint.php"><span class="si">✏️</span> Submit complaint</a>
        <a href="view_complaints.php"><span class="si">📋</span> My complaints</a>
        <div class="sidebar-sep"></div>
        <a href="#" onclick="showLogoutModal('logout.php')" ><span class="si">🚪</span> Sign out</a>
    </aside>

    <main class="main">
        <div class="page-header">
            <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?> 👋</h1>
            <p>Here's an overview of your submitted complaints.</p>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">📋 Total submitted</div>
                <div class="stat-value"><?php echo $stats['total']; ?></div>
                <div class="stat-bar"><div class="stat-bar-fill bar-total" style="width:100%"></div></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">⏳ Pending</div>
                <div class="stat-value"><?php echo $stats['pending']; ?></div>
                <div class="stat-bar"><div class="stat-bar-fill bar-pending" style="width:<?php echo $stats['total']>0 ? round($stats['pending']/$stats['total']*100) : 0; ?>%"></div></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">🔄 In Progress</div>
                <div class="stat-value"><?php echo $stats['progress']; ?></div>
                <div class="stat-bar"><div class="stat-bar-fill bar-progress" style="width:<?php echo $stats['total']>0 ? round($stats['progress']/$stats['total']*100) : 0; ?>%"></div></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">✅ Resolved</div>
                <div class="stat-value"><?php echo $stats['resolved']; ?></div>
                <div class="stat-bar"><div class="stat-bar-fill bar-resolved" style="width:<?php echo $stats['total']>0 ? round($stats['resolved']/$stats['total']*100) : 0; ?>%"></div></div>
            </div>
        </div>

        <!-- Content row -->
        <div class="content-row">

            <!-- Recent complaints -->
            <div class="panel">
                <div class="panel-header">
                    <h3>Recent complaints</h3>
                    <a href="view_complaints.php">View all →</a>
                </div>
                <div class="panel-body">
                    <?php if (empty($recent)): ?>
                        <div class="empty-state">
                            <div class="emoji">📭</div>
                            <p>You haven't submitted any complaints yet.</p>
                            <a href="submit_complaint.php">Submit your first complaint →</a>
                        </div>
                    <?php else: ?>
                        <div class="complaint-list">
                        <?php foreach ($recent as $r): ?>
                            <?php
                                $cls = ['Pending'=>'badge-pending','In Progress'=>'badge-progress','Resolved'=>'badge-resolved'];
                                $c = $cls[$r['status']] ?? 'badge-pending';
                            ?>
                            <div class="complaint-item">
                                <div class="ci-left">
                                    <div class="ci-title"><?php echo htmlspecialchars($r['title']); ?></div>
                                    <div class="ci-tracking"><?php echo htmlspecialchars($r['tracking_number']); ?></div>
                                </div>
                                <span class="badge <?php echo $c; ?>"><?php echo htmlspecialchars($r['status']); ?></span>
                                <div class="ci-date"><?php echo date('d M', strtotime($r['created_at'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick actions -->
            <div class="panel">
                <div class="panel-header"><h3>Quick actions</h3></div>
                <div class="panel-body">
                    <div class="actions-list">
                        <a href="submit_complaint.php" class="action-item">
                            <div class="action-icon blue">✏️</div>
                            <div class="action-text">
                                <div class="title">Submit a complaint</div>
                                <div class="desc">Report a new public service issue</div>
                            </div>
                            <span class="action-arrow">→</span>
                        </a>
                        <a href="view_complaints.php" class="action-item">
                            <div class="action-icon green">📋</div>
                            <div class="action-text">
                                <div class="title">My complaints</div>
                                <div class="desc">Track the status of all your submissions</div>
                            </div>
                            <span class="action-arrow">→</span>
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </main>
</div>


<!-- Logout confirmation modal -->
<div id="logoutModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:14px;padding:32px 36px;width:100%;max-width:380px;box-shadow:0 8px 40px rgba(0,0,0,0.18);text-align:center;animation:modalIn 0.2s ease;">
        <div style="font-size:36px;margin-bottom:14px;"></div>
        <h3 style="font-size:17px;font-weight:500;color:#0b1f3a;margin-bottom:8px;">Sign out?</h3>
        <p style="font-size:13px;color:#6b7280;font-weight:300;margin-bottom:24px;line-height:1.6;">You will be returned to the login page. Any unsaved changes will be lost.</p>
        <div style="display:flex;gap:10px;justify-content:center;">
            <button onclick="closeLogoutModal()" style="height:42px;padding:0 24px;background:transparent;color:#6b7280;border:1.5px solid #e5e7eb;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13.5px;cursor:pointer;transition:border-color 0.15s;">Cancel</button>
            <a id="logoutConfirmBtn" href="#" style="height:42px;padding:0 24px;background:#0b1f3a;color:#fff;border:none;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13.5px;font-weight:500;text-decoration:none;display:inline-flex;align-items:center;cursor:pointer;transition:background 0.15s;">Sign out</a>
        </div>
    </div>
</div>
<style>
@keyframes modalIn { from { opacity:0; transform:scale(0.95); } to { opacity:1; transform:scale(1); } }
</style>
<script>
function showLogoutModal(href) {
    document.getElementById('logoutConfirmBtn').href = href;
    const modal = document.getElementById('logoutModal');
    modal.style.display = 'flex';
}
function closeLogoutModal() {
    document.getElementById('logoutModal').style.display = 'none';
}
document.getElementById('logoutModal').addEventListener('click', function(e) {
    if (e.target === this) closeLogoutModal();
});
</script>

</body>
</html>