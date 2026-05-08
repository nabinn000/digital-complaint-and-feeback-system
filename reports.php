<?php

include("../includes/auth.php");
include("../includes/db.php");
requireRole("admin");

// Fetch all complaints that have coordinates
$result = $conn->query(
    "SELECT complaints.id, complaints.title, complaints.category, complaints.status,
            complaints.tracking_number, complaints.latitude, complaints.longitude,
            complaints.created_at, users.full_name
     FROM complaints
     JOIN users ON complaints.user_id = users.id
     WHERE complaints.latitude IS NOT NULL AND complaints.longitude IS NOT NULL
     ORDER BY complaints.created_at DESC"
);
$complaints = $result->fetch_all(MYSQLI_ASSOC);

// Also get total counts for the summary bar
$totals = [];
foreach (['total','Pending','In Progress','Resolved'] as $k) {
    $sql = $k === 'total'
        ? "SELECT COUNT(*) AS c FROM complaints WHERE latitude IS NOT NULL"
        : "SELECT COUNT(*) AS c FROM complaints WHERE latitude IS NOT NULL AND status='$k'";
    $totals[$k] = $conn->query($sql)->fetch_assoc()['c'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complaints Map — Admin Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">

    <!-- Leaflet.js -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>

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
        .page-wrap { display: flex; flex: 1; flex-direction: column; padding: 28px; gap: 20px; }

        /* Summary bar */
        .summary-bar { display: flex; gap: 14px; flex-wrap: wrap; }
        .stat-card { background: var(--white); border: 1px solid var(--border); border-radius: 10px; padding: 14px 20px; display: flex; align-items: center; gap: 12px; flex: 1; min-width: 140px; }
        .stat-dot { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; }
        .stat-dot.all      { background: var(--navy); }
        .stat-dot.pending  { background: #d97706; }
        .stat-dot.progress { background: #2563eb; }
        .stat-dot.resolved { background: #16a34a; }
        .stat-num  { font-size: 22px; font-weight: 500; color: var(--navy); }
        .stat-label{ font-size: 12px; color: var(--muted); }

        /* Filter bar */
        .filter-bar { background: var(--white); border: 1px solid var(--border); border-radius: 10px; padding: 14px 20px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .filter-bar label { font-size: 12px; font-weight: 500; color: #374151; }
        .filter-bar select { height: 36px; padding: 0 12px; border: 1.5px solid var(--border); border-radius: 7px; font-family: 'DM Sans', sans-serif; font-size: 13px; color: var(--text); outline: none; cursor: pointer; }
        .filter-bar select:focus { border-color: var(--navy); }
        .filter-sep { flex: 1; }
        .legend { display: flex; gap: 16px; align-items: center; flex-wrap: wrap; }
        .legend-item { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--muted); }
        .legend-pin { width: 14px; height: 14px; border-radius: 50% 50% 50% 0; transform: rotate(-45deg); border: 2px solid #fff; box-shadow: 0 1px 4px rgba(0,0,0,0.3); }

        /* Map container */
        .map-container { background: var(--white); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; flex: 1; min-height: 520px; position: relative; }
        #admin-map { width: 100%; height: 100%; min-height: 520px; }

        /* Popup styles (injected via JS) */
        .map-popup h4   { font-size: 13.5px; font-weight: 500; color: var(--navy); margin-bottom: 6px; line-height: 1.4; }
        .map-popup .meta{ font-size: 11.5px; color: var(--muted); margin-bottom: 8px; line-height: 1.6; }
        .map-popup .badge { display:inline-block; padding:2px 10px; border-radius:20px; font-size:11px; font-weight:500; }
        .badge-Pending     { background:#fef3c7; color:#92400e; }
        .badge-In.Progress { background:#dbeafe; color:#1e40af; }
        .badge-Resolved    { background:#d1fae5; color:#065f46; }
        .map-popup a { display:block; margin-top:10px; font-size:12px; color:var(--navy); font-weight:500; text-decoration:none; }
        .map-popup a:hover { text-decoration:underline; }

        /* No-location notice */
        .no-location-note { background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:10px 16px; font-size:13px; color:#92400e; display:flex; align-items:center; gap:8px; }
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
        <a href="complaints_map.php" class="active">Map</a>
        <a href="reports.php">Reports</a>
        <a href="../logout.php">Sign out</a>
    </div>
</nav>

<div class="page-wrap">

    <!-- Page heading -->
    <div>
        <h1 style="font-size:20px;font-weight:500;color:var(--navy);margin-bottom:3px;">Complaints Map</h1>
        <p style="font-size:13px;color:var(--muted);font-weight:300;">Showing <?php echo $totals['total']; ?> pinned complaint<?php echo $totals['total'] !== 1 ? 's' : ''; ?> on the map. Complaints submitted without a location pin are not shown.</p>
    </div>

    <!-- Summary cards -->
    <div class="summary-bar">
        <div class="stat-card"><div class="stat-dot all"></div><div><div class="stat-num"><?php echo $totals['total']; ?></div><div class="stat-label">Total pinned</div></div></div>
        <div class="stat-card"><div class="stat-dot pending"></div><div><div class="stat-num"><?php echo $totals['Pending']; ?></div><div class="stat-label">Pending</div></div></div>
        <div class="stat-card"><div class="stat-dot progress"></div><div><div class="stat-num"><?php echo $totals['In Progress']; ?></div><div class="stat-label">In Progress</div></div></div>
        <div class="stat-card"><div class="stat-dot resolved"></div><div><div class="stat-num"><?php echo $totals['Resolved']; ?></div><div class="stat-label">Resolved</div></div></div>
    </div>

    <!-- Filter + legend -->
    <div class="filter-bar">
        <label>Filter by status:</label>
        <select id="statusFilter" onchange="filterMarkers()">
            <option value="all">All statuses</option>
            <option value="Pending">Pending</option>
            <option value="In Progress">In Progress</option>
            <option value="Resolved">Resolved</option>
        </select>
        <label style="margin-left:8px;">Category:</label>
        <select id="catFilter" onchange="filterMarkers()">
            <option value="all">All categories</option>
            <option value="Road Issues">Road Issues</option>
            <option value="Water Supply">Water Supply</option>
            <option value="Electricity">Electricity</option>
            <option value="Sanitation">Sanitation</option>
            <option value="Public Safety">Public Safety</option>
            <option value="Other">Other</option>
        </select>
        <div class="filter-sep"></div>
        <div class="legend">
            <div class="legend-item"><div class="legend-pin" style="background:#d97706;"></div> Pending</div>
            <div class="legend-item"><div class="legend-pin" style="background:#2563eb;"></div> In Progress</div>
            <div class="legend-item"><div class="legend-pin" style="background:#16a34a;"></div> Resolved</div>
        </div>
    </div>

    <?php if ($totals['total'] === 0): ?>
    <div class="no-location-note">
        ⚠ No complaints with location data yet. Citizens need to pin a location when submitting a complaint for it to appear here.
    </div>
    <?php endif; ?>

    <!-- Map -->
    <div class="map-container">
        <div id="admin-map"></div>
    </div>

</div>

<!-- Pass PHP data to JS safely -->
<script>
const complaintsData = <?php echo json_encode($complaints, JSON_HEX_TAG | JSON_HEX_AMP); ?>;
</script>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
/* ── Admin complaints map ──────────────────────────────────── */

// Centre on Kathmandu by default; will auto-fit to markers if any exist
const map = L.map('admin-map').setView([27.7172, 85.3240], 12);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    maxZoom: 19
}).addTo(map);

// Status → pin colour mapping
const pinColors = {
    'Pending':     '#d97706',
    'In Progress': '#2563eb',
    'Resolved':    '#16a34a',
};

// Category → emoji mapping
const catIcons = {
    'Road Issues':   '🛣️',
    'Water Supply':  '💧',
    'Electricity':   '⚡',
    'Sanitation':    '🗑️',
    'Public Safety': '🚨',
    'Other':         '📝',
};

function makeIcon(status) {
    const color = pinColors[status] || '#0b1f3a';
    return L.divIcon({
        className: '',
        html: `<div style="
            width:28px; height:28px;
            background:${color};
            border:3px solid #fff;
            border-radius:50% 50% 50% 0;
            transform:rotate(-45deg);
            box-shadow:0 2px 8px rgba(0,0,0,0.35);
        "></div>`,
        iconSize:    [28, 28],
        iconAnchor:  [14, 28],
        popupAnchor: [0, -30]
    });
}

// Create a Leaflet layer group for easy filtering
const markerLayer = L.layerGroup().addTo(map);
let allMarkers = [];

function buildMarkers(data) {
    markerLayer.clearLayers();
    allMarkers = [];
    const bounds = [];

    data.forEach(c => {
        const lat = parseFloat(c.latitude);
        const lng = parseFloat(c.longitude);
        if (isNaN(lat) || isNaN(lng)) return;

        const marker = L.marker([lat, lng], { icon: makeIcon(c.status) });

        const badgeClass = 'badge-' + c.status.replace(' ','.');
        const catEmoji   = catIcons[c.category] || '📝';
        const date       = new Date(c.created_at).toLocaleDateString('en-GB', {day:'numeric',month:'short',year:'numeric'});

        marker.bindPopup(`
            <div class="map-popup" style="min-width:200px;font-family:'DM Sans',sans-serif;">
                <h4>${catEmoji} ${escHtml(c.title)}</h4>
                <div class="meta">
                    <strong>Submitted by:</strong> ${escHtml(c.full_name)}<br>
                    <strong>Category:</strong> ${escHtml(c.category)}<br>
                    <strong>Date:</strong> ${date}<br>
                    <strong>Tracking ID:</strong> <code>${escHtml(c.tracking_number)}</code>
                </div>
                <span class="badge ${badgeClass}">${escHtml(c.status)}</span>
                <a href="complaint_detail.php?id=${c.id}">View full complaint →</a>
            </div>
        `, { maxWidth: 260 });

        marker._complaintData = c;
        markerLayer.addLayer(marker);
        allMarkers.push(marker);
        bounds.push([lat, lng]);
    });

    // Auto-zoom to fit all markers
    if (bounds.length > 0) {
        map.fitBounds(bounds, { padding: [40, 40], maxZoom: 15 });
    }
}

function filterMarkers() {
    const statusVal = document.getElementById('statusFilter').value;
    const catVal    = document.getElementById('catFilter').value;

    const filtered = complaintsData.filter(c => {
        const statusOk = statusVal === 'all' || c.status === statusVal;
        const catOk    = catVal    === 'all' || c.category === catVal;
        return statusOk && catOk;
    });

    buildMarkers(filtered);
}

function escHtml(str) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(str || ''));
    return d.innerHTML;
}

// Initial render
buildMarkers(complaintsData);
/* ──────────────────────────────────────────────────────────── */
</script>
</body>
</html>