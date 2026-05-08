<?php
include("../includes/auth.php");
include("../includes/db.php");
requireRole("admin");

// ── Stats ────────────────────────────────────────────────
$stats = [];
foreach ([
    'total'    => "SELECT COUNT(*) as c FROM complaints",
    'pending'  => "SELECT COUNT(*) as c FROM complaints WHERE status='Pending'",
    'progress' => "SELECT COUNT(*) as c FROM complaints WHERE status='In Progress'",
    'resolved' => "SELECT COUNT(*) as c FROM complaints WHERE status='Resolved'",
] as $key => $sql) {
    $stats[$key] = $conn->query($sql)->fetch_assoc()['c'];
}

// ── Category breakdown ───────────────────────────────────
$cat_result = $conn->query(
    "SELECT category, COUNT(*) as total FROM complaints GROUP BY category ORDER BY total DESC"
);
$categories = [];
$cat_totals = [];
while ($row = $cat_result->fetch_assoc()) {
    $categories[] = $row['category'];
    $cat_totals[]  = (int) $row['total'];
}

// ── Recently resolved ────────────────────────────────────
$recent_resolved = $conn->query(
    "SELECT complaints.title, complaints.created_at, users.full_name
     FROM complaints JOIN users ON complaints.user_id = users.id
     WHERE complaints.status = 'Resolved'
     ORDER BY complaints.created_at DESC
     LIMIT 4"
)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports — Citizen Complaint Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root { --navy:#0b1f3a; --bg:#eef2f7; --white:#fff; --border:#e5e7eb; --muted:#6b7280; --text:#111827; }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; flex-direction: column; }

        /* Nav */
        nav { background: var(--navy); height: 58px; padding: 0 28px; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
        .nav-brand { display: flex; align-items: center; gap: 9px; text-decoration: none; }
        .nav-brand .name { font-size: 14px; font-weight: 500; color: #fff; }
        .nav-badge { font-size: 10px; background: rgba(255,255,255,0.15); color: rgba(255,255,255,0.8); padding: 3px 9px; border-radius: 10px; margin-left: 4px; }
        .nav-right { display: flex; gap: 6px; }
        .nav-right a { font-size: 12.5px; color: rgba(255,255,255,0.7); text-decoration: none; padding: 6px 13px; border-radius: 6px; transition: background 0.15s; }
        .nav-right a:hover, .nav-right a.active { background: rgba(255,255,255,0.12); color: #fff; }

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

        /* Stats row */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: var(--white); border: 1px solid var(--border); border-radius: 12px; padding: 20px 22px; }
        .stat-label { font-size: 12px; color: var(--muted); margin-bottom: 10px; }
        .stat-value { font-size: 30px; font-weight: 500; color: var(--navy); line-height: 1; margin-bottom: 4px; }
        .stat-sub { font-size: 11.5px; color: #9ca3af; font-weight: 300; }
        .stat-bar { height: 3px; background: #f0f4f9; border-radius: 2px; overflow: hidden; margin-top: 10px; }
        .stat-bar-fill { height: 100%; border-radius: 2px; }
        .bar-total    { background: var(--navy); }
        .bar-pending  { background: #f59e0b; }
        .bar-progress { background: #3b82f6; }
        .bar-resolved { background: #22c55e; }

        /* Two-column content row */
        .content-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

        /* Panel */
        .panel { background: var(--white); border: 1px solid var(--border); border-radius: 12px; }
        .panel-header { padding: 18px 22px 14px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .panel-header h3 { font-size: 14px; font-weight: 500; color: var(--navy); }
        .panel-header .sub { font-size: 12px; color: var(--muted); font-weight: 300; }
        .panel-body { padding: 20px 22px; }

        /* Category bar chart */
        .chart-wrap { position: relative; width: 100%; }

        /* Category breakdown list */
        .cat-list { display: flex; flex-direction: column; gap: 12px; }
        .cat-row { display: flex; flex-direction: column; gap: 5px; }
        .cat-row-top { display: flex; justify-content: space-between; align-items: center; }
        .cat-name { font-size: 13px; color: var(--text); }
        .cat-count { font-size: 12px; color: var(--muted); }
        .cat-track { height: 7px; background: #f0f4f9; border-radius: 4px; overflow: hidden; }
        .cat-fill { height: 100%; border-radius: 4px; background: var(--navy); transition: width 0.8s ease; }

        /* Recently resolved */
        .resolved-list { display: flex; flex-direction: column; }
        .resolved-item { display: flex; align-items: center; justify-content: space-between; padding: 13px 0; border-bottom: 1px solid #f3f4f6; gap: 12px; }
        .resolved-item:last-child { border-bottom: none; }
        .ri-left { flex: 1; min-width: 0; }
        .ri-title { font-size: 13.5px; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 2px; }
        .ri-name  { font-size: 12px; color: var(--muted); font-weight: 300; }
        .ri-right { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
        .ri-date  { font-size: 11.5px; color: #9ca3af; }
        .badge-resolved { display: inline-flex; padding: 3px 9px; border-radius: 8px; font-size: 11.5px; background: #dcfce7; color: #166534; }

        @media (max-width: 960px) { .stats-grid { grid-template-columns: repeat(2,1fr); } .content-row { grid-template-columns: 1fr; } }
        @media (max-width: 700px) { .sidebar { display: none; } .main { padding: 20px 16px; } }
    </style>
</head>
<body>

<nav>
    <a class="nav-brand" href="admin_dashboard.php">
        <span style="font-size:18px">🏛</span>
        <span class="name">Citizen Complaint Portal</span>
        <span class="nav-badge">Admin</span>
    </a>
    <div class="nav-right">
        <a href="admin_dashboard.php">Dashboard</a>
        <a href="manage_complaints.php">Complaints</a>
        <a href="complaints_map.php">Map</a>
        <a href="reports.php" class="active">Reports</a>
        <a href="#" onclick="showLogoutModal('../logout.php')" >Sign out</a>
    </div>
</nav>

<div class="page">

    <aside class="sidebar">
        <div class="sidebar-label">Overview</div>
        <a href="admin_dashboard.php"><span class="si">📊</span> Dashboard</a>
        <div class="sidebar-sep"></div>
        <div class="sidebar-label">Management</div>
        <a href="manage_complaints.php"><span class="si">📋</span> Complaints</a>
        <a href="complaints_map.php"><span class="si">🗺️</span> Map</a>
        <a href="reports.php" class="active"><span class="si">📈</span> Reports</a>
        <div class="sidebar-sep"></div>
        <a href="#" onclick="showLogoutModal('../logout.php')" ><span class="si">🚪</span> Sign out</a>
    </aside>

    <main class="main">

        <div class="page-header">
            <h1>System Reports</h1>
            <p>Analytics and statistics across all submitted complaints</p>
        </div>

        <!-- Stats row -->
        <div class="stats-grid">
            <?php $pct = fn($v) => $stats['total'] > 0 ? round($v / $stats['total'] * 100) : 0; ?>
            <div class="stat-card">
                <div class="stat-label">📋 Total complaints</div>
                <div class="stat-value"><?php echo $stats['total']; ?></div>
                <div class="stat-sub">All time</div>
                <div class="stat-bar"><div class="stat-bar-fill bar-total" style="width:100%"></div></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">⏳ Pending</div>
                <div class="stat-value"><?php echo $stats['pending']; ?></div>
                <div class="stat-sub"><?php echo $pct($stats['pending']); ?>% of total</div>
                <div class="stat-bar"><div class="stat-bar-fill bar-pending" style="width:<?php echo $pct($stats['pending']); ?>%"></div></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">🔄 In Progress</div>
                <div class="stat-value"><?php echo $stats['progress']; ?></div>
                <div class="stat-sub"><?php echo $pct($stats['progress']); ?>% of total</div>
                <div class="stat-bar"><div class="stat-bar-fill bar-progress" style="width:<?php echo $pct($stats['progress']); ?>%"></div></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">✅ Resolved</div>
                <div class="stat-value"><?php echo $stats['resolved']; ?></div>
                <div class="stat-sub"><?php echo $pct($stats['resolved']); ?>% of total</div>
                <div class="stat-bar"><div class="stat-bar-fill bar-resolved" style="width:<?php echo $pct($stats['resolved']); ?>%"></div></div>
            </div>
        </div>

        <!-- Content row: Category bar + breakdown list / Recently resolved -->
        <div class="content-row">

            <!-- Left: Category bar chart + breakdown list stacked -->
            <div style="display:flex;flex-direction:column;gap:20px;">

                <div class="panel">
                    <div class="panel-header">
                        <h3>Complaints by category</h3>
                        <span class="sub">Volume per service type</span>
                    </div>
                    <div class="panel-body">
                        <div class="chart-wrap">
                            <canvas id="categoryChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h3>Category breakdown</h3>
                        <span class="sub">Proportional comparison</span>
                    </div>
                    <div class="panel-body">
                        <?php if (empty($categories)): ?>
                            <p style="font-size:13px;color:var(--muted);font-weight:300">No data yet.</p>
                        <?php else: ?>
                        <div class="cat-list">
                            <?php
                            $max = max($cat_totals) ?: 1;
                            foreach ($categories as $i => $cat):
                                $w = round($cat_totals[$i] / $max * 100);
                            ?>
                            <div class="cat-row">
                                <div class="cat-row-top">
                                    <span class="cat-name"><?php echo htmlspecialchars($cat); ?></span>
                                    <span class="cat-count"><?php echo $cat_totals[$i]; ?></span>
                                </div>
                                <div class="cat-track">
                                    <div class="cat-fill" style="width:<?php echo $w; ?>%"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- Right: Recently resolved -->
            <div class="panel">
                <div class="panel-header">
                    <h3>Recently resolved</h3>
                    <span class="sub">Latest closed complaints</span>
                </div>
                <div class="panel-body">
                    <?php if (empty($recent_resolved)): ?>
                        <p style="font-size:13px;color:var(--muted);font-weight:300">No resolved complaints yet.</p>
                    <?php else: ?>
                    <div class="resolved-list">
                        <?php foreach ($recent_resolved as $r): ?>
                        <div class="resolved-item">
                            <div class="ri-left">
                                <div class="ri-title"><?php echo htmlspecialchars($r['title']); ?></div>
                                <div class="ri-name"><?php echo htmlspecialchars($r['full_name']); ?></div>
                            </div>
                            <div class="ri-right">
                                <span class="ri-date"><?php echo date('d M Y', strtotime($r['created_at'])); ?></span>
                                <span class="badge-resolved">✅ Resolved</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    </main>
</div>

<script>
const muted = '#9ca3af', border = '#e5e7eb', navy = '#0b1f3a';

new Chart(document.getElementById('categoryChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($categories ?: ['No data']); ?>,
        datasets: [{
            label: 'Complaints',
            data: <?php echo json_encode($cat_totals ?: [0]); ?>,
            backgroundColor: 'rgba(11,31,58,0.85)',
            borderRadius: 6,
            borderSkipped: false,
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#1e293b',
                titleFont: { family: 'DM Sans', size: 12 },
                bodyFont:  { family: 'DM Sans', size: 12 },
                padding: 10,
                cornerRadius: 6,
            }
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: { font: { family: 'DM Sans', size: 11 }, color: muted }
            },
            y: {
                beginAtZero: true,
                ticks: { precision: 0, font: { family: 'DM Sans', size: 11 }, color: muted },
                grid: { color: border }
            }
        }
    }
});
</script>


<!-- Logout confirmation modal -->
<div id="logoutModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:14px;padding:32px 36px;width:100%;max-width:380px;box-shadow:0 8px 40px rgba(0,0,0,0.18);text-align:center;animation:modalIn 0.2s ease;">
        <div style="font-size:36px;margin-bottom:14px;">🚪</div>
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