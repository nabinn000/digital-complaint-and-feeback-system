<?php
include("../includes/auth.php");
include("../includes/db.php");
requireRole("admin");

// Use prepared-style queries consistently
$stats = [];
foreach ([
    'total'    => "SELECT COUNT(*) as c FROM complaints",
    'pending'  => "SELECT COUNT(*) as c FROM complaints WHERE status='Pending'",
    'progress' => "SELECT COUNT(*) as c FROM complaints WHERE status='In Progress'",
    'resolved' => "SELECT COUNT(*) as c FROM complaints WHERE status='Resolved'",
] as $key => $sql) {
    $stats[$key] = $conn->query($sql)->fetch_assoc()['c'];
}

// Recent 5 complaints
$recent = $conn->query(
    "SELECT complaints.title, complaints.status, complaints.created_at, users.full_name
     FROM complaints JOIN users ON complaints.user_id = users.id
     ORDER BY complaints.created_at DESC LIMIT 5"
);

$resolution_rate = $stats['total'] > 0 ? round(($stats['resolved'] / $stats['total']) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — Citizen Complaint Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root { --navy:#0b1f3a; --bg:#eef2f7; --white:#fff; --border:#e5e7eb; --muted:#6b7280; --text:#111827; }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; flex-direction: column; }

        /* Nav */
        nav { background: var(--navy); height: 58px; padding: 0 28px; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
        .nav-brand { display: flex; align-items: center; gap: 9px; text-decoration: none; }
        .nav-brand .icon { font-size: 18px; }
        .nav-brand .name { font-size: 14px; font-weight: 500; color: #fff; }
        .nav-right { display: flex; align-items: center; gap: 6px; }
        .nav-right a { font-size: 12.5px; color: rgba(255,255,255,0.7); text-decoration: none; padding: 6px 13px; border-radius: 6px; transition: background 0.15s; }
        .nav-right a:hover { background: rgba(255,255,255,0.1); color: #fff; }
        .nav-right a.active { background: rgba(255,255,255,0.12); color: #fff; }
        .nav-badge { font-size: 10px; background: rgba(255,255,255,0.15); color: rgba(255,255,255,0.8); padding: 3px 9px; border-radius: 10px; margin-left: 4px; letter-spacing: 0.05em; }

        /* Layout */
        .page { display: flex; flex: 1; }
        .sidebar { width: 220px; background: var(--white); border-right: 1px solid var(--border); padding: 24px 0; flex-shrink: 0; }
        .sidebar-label { font-size: 10px; font-weight: 500; letter-spacing: 0.12em; text-transform: uppercase; color: #9ca3af; padding: 0 20px; margin-bottom: 8px; }
        .sidebar a { display: flex; align-items: center; gap: 10px; font-size: 13.5px; color: var(--muted); text-decoration: none; padding: 9px 20px; border-left: 3px solid transparent; transition: all 0.15s; }
        .sidebar a:hover { color: var(--navy); background: #f8fafc; }
        .sidebar a.active { color: var(--navy); font-weight: 500; border-left-color: var(--navy); background: #f0f4f9; }
        .sidebar .icon { font-size: 15px; width: 20px; text-align: center; }
        .sidebar-sep { height: 1px; background: var(--border); margin: 12px 0; }
        .main { flex: 1; padding: 32px 28px; overflow-y: auto; }

        /* Page header */
        .page-header { margin-bottom: 28px; }
        .page-header h1 { font-size: 20px; font-weight: 500; color: var(--navy); margin-bottom: 3px; }
        .page-header p { font-size: 13px; color: var(--muted); font-weight: 300; }

        /* Stats grid */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 28px; }
        .stat-card { background: var(--white); border: 1px solid var(--border); border-radius: 12px; padding: 20px 22px; }
        .stat-label { font-size: 11.5px; color: var(--muted); font-weight: 400; margin-bottom: 10px; display: flex; align-items: center; gap: 6px; }
        .stat-value { font-size: 28px; font-weight: 500; color: var(--navy); line-height: 1; margin-bottom: 6px; }
        .stat-bar { height: 3px; background: #f0f4f9; border-radius: 2px; overflow: hidden; }
        .stat-bar-fill { height: 100%; border-radius: 2px; transition: width 0.8s ease; }
        .bar-pending  { background: #f59e0b; }
        .bar-progress { background: #3b82f6; }
        .bar-resolved { background: #22c55e; }
        .bar-total    { background: var(--navy); }

        /* Content row */
        .content-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }

        /* Cards */
        .panel { background: var(--white); border: 1px solid var(--border); border-radius: 12px; }
        .panel-header { padding: 18px 22px 0; border-bottom: 1px solid var(--border); margin-bottom: 0; padding-bottom: 14px; display: flex; align-items: center; justify-content: space-between; }
        .panel-header h3 { font-size: 14px; font-weight: 500; color: var(--navy); }
        .panel-header a { font-size: 12px; color: #6b7280; text-decoration: none; }
        .panel-header a:hover { color: var(--navy); }
        .panel-body { padding: 18px 22px; }

        /* Quick actions */
        .actions-list { display: flex; flex-direction: column; gap: 10px; }
        .action-item { display: flex; align-items: center; gap: 14px; padding: 14px 16px; border: 1px solid var(--border); border-radius: 10px; text-decoration: none; color: var(--text); transition: border-color 0.15s, box-shadow 0.15s; }
        .action-item:hover { border-color: #c0cdd8; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .action-icon { width: 38px; height: 38px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0; }
        .action-icon.blue { background: #eff6ff; }
        .action-icon.green { background: #f0fdf4; }
        .action-text .title { font-size: 13.5px; font-weight: 500; color: var(--navy); margin-bottom: 2px; }
        .action-text .desc { font-size: 11.5px; color: var(--muted); font-weight: 300; }

        /* Recent table */
        .recent-table { width: 100%; border-collapse: collapse; }
        .recent-table th { font-size: 11px; font-weight: 500; letter-spacing: 0.06em; text-transform: uppercase; color: #9ca3af; padding: 0 0 10px; text-align: left; border-bottom: 1px solid var(--border); }
        .recent-table td { font-size: 13px; padding: 11px 0; border-bottom: 1px solid #f9fafb; vertical-align: middle; }
        .recent-table tr:last-child td { border-bottom: none; }
        .td-name { font-weight: 400; color: var(--text); max-width: 120px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .td-title { color: var(--muted); font-weight: 300; max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .td-date { color: #9ca3af; font-size: 12px; white-space: nowrap; }

        /* Status badges */
        .badge { display: inline-flex; align-items: center; padding: 3px 9px; border-radius: 10px; font-size: 11.5px; font-weight: 400; white-space: nowrap; }
        .badge-pending  { background: #fef9c3; color: #854d0e; }
        .badge-progress { background: #dbeafe; color: #1e40af; }
        .badge-resolved { background: #dcfce7; color: #166534; }

        /* Resolution rate */
        .resolution-wrap { display: flex; align-items: center; gap: 14px; }
        .resolution-circle { position: relative; width: 72px; height: 72px; flex-shrink: 0; }
        .resolution-circle svg { transform: rotate(-90deg); }
        .circle-bg { fill: none; stroke: #f0f4f9; stroke-width: 7; }
        .circle-fill { fill: none; stroke: #22c55e; stroke-width: 7; stroke-linecap: round; transition: stroke-dashoffset 1s ease; }
        .resolution-pct { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 15px; font-weight: 500; color: var(--navy); }
        .resolution-text .label { font-size: 13.5px; font-weight: 500; color: var(--navy); margin-bottom: 4px; }
        .resolution-text .sub { font-size: 12px; color: var(--muted); font-weight: 300; }

        @media (max-width: 900px) { .stats-grid { grid-template-columns: repeat(2,1fr); } .content-row { grid-template-columns: 1fr; } .sidebar { display: none; } }
    </style>
</head>
<body>

<nav>
    <a class="nav-brand" href="admin_dashboard.php">
        <span class="icon">🏛</span>
        <span class="name">Citizen Complaint Portal</span>
        <span class="nav-badge">Admin</span>
    </a>
    <div class="nav-right">
        <a href="admin_dashboard.php" class="active">Dashboard</a>
        <a href="manage_complaints.php">Complaints</a>
        <a href="complaints_map.php">Map</a>
        <a href="reports.php">Reports</a>
        <a href="#" onclick="showLogoutModal('../logout.php')" >Sign out</a>
    </div>
</nav>

<div class="page">

    <aside class="sidebar">
        <div class="sidebar-label">Overview</div>
        <a href="admin_dashboard.php" class="active"><span class="icon">📊</span> Dashboard</a>
        <div class="sidebar-sep"></div>
        <div class="sidebar-label">Management</div>
        <a href="manage_complaints.php"><span class="icon">📋</span> Complaints</a>
        <a href="complaints_map.php"><span class="icon">🗺️</span> Map</a>
        <a href="reports.php"><span class="icon">📈</span> Reports</a>
        <div class="sidebar-sep"></div>
        <a href="#" onclick="showLogoutModal('../logout.php')" ><span class="icon">🚪</span> Sign out</a>
    </aside>

    <main class="main">

        <div class="page-header">
            <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?></h1>
            <p>Here's a summary of complaint activity across the portal.</p>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">📋 Total complaints</div>
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
                    <a href="manage_complaints.php">View all →</a>
                </div>
                <div class="panel-body">
                    <table class="recent-table">
                        <thead>
                            <tr>
                                <th>Citizen</th>
                                <th>Title</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($row = $recent->fetch_assoc()): ?>
                            <tr>
                                <td class="td-name"><?php echo htmlspecialchars($row['full_name']); ?></td>
                                <td class="td-title"><?php echo htmlspecialchars($row['title']); ?></td>
                                <td>
                                    <?php
                                    $cls = ['Pending'=>'badge-pending','In Progress'=>'badge-progress','Resolved'=>'badge-resolved'];
                                    $c = $cls[$row['status']] ?? 'badge-pending';
                                    echo '<span class="badge '.$c.'">'.htmlspecialchars($row['status']).'</span>';
                                    ?>
                                </td>
                                <td class="td-date"><?php echo date('d M', strtotime($row['created_at'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Right column -->
            <div style="display:flex;flex-direction:column;gap:20px;">

                <!-- Resolution rate -->
                <div class="panel">
                    <div class="panel-header"><h3>Resolution rate</h3></div>
                    <div class="panel-body">
                        <div class="resolution-wrap">
                            <div class="resolution-circle">
                                <svg width="72" height="72" viewBox="0 0 72 72">
                                    <circle class="circle-bg" cx="36" cy="36" r="30"/>
                                    <circle class="circle-fill" cx="36" cy="36" r="30"
                                        stroke-dasharray="188.4"
                                        stroke-dashoffset="<?php echo 188.4 - (188.4 * $resolution_rate / 100); ?>"
                                        id="circle-fill"/>
                                </svg>
                                <div class="resolution-pct"><?php echo $resolution_rate; ?>%</div>
                            </div>
                            <div class="resolution-text">
                                <div class="label"><?php echo $resolution_rate; ?>% resolved</div>
                                <div class="sub"><?php echo $stats['resolved']; ?> of <?php echo $stats['total']; ?> complaints closed</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick actions -->
                <div class="panel">
                    <div class="panel-header"><h3>Quick actions</h3></div>
                    <div class="panel-body">
                        <div class="actions-list">
                            <a href="manage_complaints.php" class="action-item">
                                <div class="action-icon blue">📋</div>
                                <div class="action-text">
                                    <div class="title">Manage complaints</div>
                                    <div class="desc">Review, filter and update status</div>
                                </div>
                            </a>
                            <a href="reports.php" class="action-item">
                                <div class="action-icon green">📈</div>
                                <div class="action-text">
                                    <div class="title">View reports</div>
                                    <div class="desc">Charts and complaint analytics</div>
                                </div>
                            </a>
                            <a href="complaints_map.php" class="action-item">
                                <div class="action-icon" style="background:#ede9fe;color:#5b21b6;font-size:18px;width:40px;height:40px;border-radius:9px;display:flex;align-items:center;justify-content:center;">🗺️</div>
                                <div class="action-text">
                                    <div class="title">View map</div>
                                    <div class="desc">See pinned complaint locations</div>
                                </div>
                            </a>
                        </div>
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