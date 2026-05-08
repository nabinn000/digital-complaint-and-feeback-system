<?php
// Place this file in: admin/complaint_detail.php
include("../includes/auth.php");
include("../includes/db.php");
include("../includes/email_notification.php");
requireRole("admin");

$admin_municipality = $_SESSION['municipality'] ?? '';

$complaint_id = (int)($_GET['id'] ?? 0);
if ($complaint_id <= 0) { header("Location: manage_complaints.php"); exit(); }

$toast = "";

// Handle status update
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_status"])) {
    csrf_verify();
    $new_status = $_POST["status"] ?? '';
    $allowed = ["Pending","In Progress","Resolved"];
    if (in_array($new_status, $allowed)) {
        $s = $conn->prepare("UPDATE complaints SET status = ? WHERE id = ?");
        $s->bind_param("si", $new_status, $complaint_id);
        $s->execute();
        $toast = "Status updated to \"" . htmlspecialchars($new_status) . "\".";

        // Send status update email to citizen
        $eStmt = $conn->prepare(
            "SELECT complaints.title, complaints.tracking_number,
                    users.email, users.full_name
             FROM complaints
             JOIN users ON complaints.user_id = users.id
             WHERE complaints.id = ?"
        );
        $eStmt->bind_param("i", $complaint_id);
        $eStmt->execute();
        $eRow = $eStmt->get_result()->fetch_assoc();
        if ($eRow) {
            notifyStatusChanged(
                $eRow['email'],
                $eRow['full_name'],
                $eRow['title'],
                $eRow['tracking_number'],
                $new_status
            );
        }
    }
}

// Handle adding a note
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_note"])) {
    csrf_verify();
    $note = trim($_POST["note"] ?? '');
    if (!empty($note)) {
        $admin_id = (int)$_SESSION["user_id"];
        $n = $conn->prepare("INSERT INTO complaint_notes (complaint_id, admin_id, note) VALUES (?,?,?)");
        $n->bind_param("iis", $complaint_id, $admin_id, $note);
        $n->execute();
        $toast = "Note added successfully.";

        // Send note notification email to citizen
        $eStmt = $conn->prepare(
            "SELECT complaints.title, complaints.tracking_number, complaints.status,
                    users.email, users.full_name
             FROM complaints
             JOIN users ON complaints.user_id = users.id
             WHERE complaints.id = ?"
        );
        $eStmt->bind_param("i", $complaint_id);
        $eStmt->execute();
        $eRow = $eStmt->get_result()->fetch_assoc();
        if ($eRow) {
            notifyStatusChanged(
                $eRow['email'],
                $eRow['full_name'],
                $eRow['title'],
                $eRow['tracking_number'],
                $eRow['status']
            );
        }
    }
}

// Fetch complaint
$stmt = $conn->prepare(
    "SELECT complaints.*, users.full_name, users.email, users.phone, users.address
     FROM complaints JOIN users ON complaints.user_id = users.id
     WHERE complaints.id = ?"
     . ($admin_municipality ? " AND complaints.municipality = ?" : "")
);
if ($admin_municipality) {
    $stmt->bind_param("is", $complaint_id, $admin_municipality);
} else {
    $stmt->bind_param("i", $complaint_id);
}
$stmt->execute();
$complaint = $stmt->get_result()->fetch_assoc();
// If complaint not found or belongs to another municipality, redirect
if (!$complaint) { header("Location: manage_complaints.php"); exit(); }

// Fetch notes
$notes_stmt = $conn->prepare(
    "SELECT complaint_notes.*, users.full_name as admin_name
     FROM complaint_notes
     JOIN users ON complaint_notes.admin_id = users.id
     WHERE complaint_notes.complaint_id = ?
     ORDER BY complaint_notes.created_at ASC"
);
$notes_stmt->bind_param("i", $complaint_id);
$notes_stmt->execute();
$notes = $notes_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$status_steps = ['Pending','In Progress','Resolved'];
$status_index = array_search($complaint['status'], $status_steps);
$icons = ['Road Issues'=>'🛣️','Water Supply'=>'💧','Electricity'=>'⚡','Sanitation'=>'🗑️','Public Safety'=>'🚨','Other'=>'📝'];

function isImage($f) { return in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), ['jpg','jpeg','png','gif','webp']); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complaint Detail — Citizen Complaint Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root { --navy:#0b1f3a; --bg:#eef2f7; --white:#fff; --border:#e5e7eb; --muted:#6b7280; --text:#111827; }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; flex-direction: column; }
        nav { background: var(--navy); height: 58px; padding: 0 28px; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
        .nav-brand { display: flex; align-items: center; gap: 9px; text-decoration: none; }
        .nav-brand .name { font-size: 14px; font-weight: 500; color: #fff; }
        .nav-badge { font-size: 10px; background: rgba(255,255,255,0.15); color: rgba(255,255,255,0.8); padding: 3px 9px; border-radius: 10px; margin-left: 4px; }
        .nav-right { display: flex; gap: 6px; }
        .nav-right a { font-size: 12.5px; color: rgba(255,255,255,0.7); text-decoration: none; padding: 6px 13px; border-radius: 6px; transition: background 0.15s; }
        .nav-right a:hover, .nav-right a.active { background: rgba(255,255,255,0.12); color: #fff; }
        .page { display: flex; flex: 1; }
        .sidebar { width: 220px; background: var(--white); border-right: 1px solid var(--border); padding: 24px 0; flex-shrink: 0; }
        .sidebar-label { font-size: 10px; font-weight: 500; letter-spacing: 0.12em; text-transform: uppercase; color: #9ca3af; padding: 0 20px; margin-bottom: 8px; }
        .sidebar a { display: flex; align-items: center; gap: 10px; font-size: 13.5px; color: var(--muted); text-decoration: none; padding: 9px 20px; border-left: 3px solid transparent; transition: all 0.15s; }
        .sidebar a:hover { color: var(--navy); background: #f8fafc; }
        .sidebar a.active { color: var(--navy); font-weight: 500; border-left-color: var(--navy); background: #f0f4f9; }
        .sidebar .si { font-size: 15px; width: 20px; text-align: center; }
        .sidebar-sep { height: 1px; background: var(--border); margin: 12px 0; }
        .main { flex: 1; padding: 32px 28px; overflow-y: auto; }
        .back-link { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--muted); text-decoration: none; margin-bottom: 20px; transition: color 0.15s; }
        .back-link:hover { color: var(--navy); }
        .page-title { font-size: 20px; font-weight: 500; color: var(--navy); margin-bottom: 3px; }
        .page-sub { font-size: 13px; color: var(--muted); font-weight: 300; margin-bottom: 24px; }
        .toast { border-radius: 8px; padding: 10px 16px; font-size: 13px; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }
        .content-grid { display: grid; grid-template-columns: 1fr 360px; gap: 20px; align-items: start; }
        .panel { background: var(--white); border: 1px solid var(--border); border-radius: 12px; margin-bottom: 20px; }
        .panel-header { padding: 18px 22px 14px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .panel-header h3 { font-size: 14px; font-weight: 500; color: var(--navy); }
        .panel-body { padding: 20px 22px; }
        .detail-row { margin-bottom: 18px; }
        .detail-row:last-child { margin-bottom: 0; }
        .detail-label { font-size: 11px; font-weight: 500; letter-spacing: 0.08em; text-transform: uppercase; color: #9ca3af; margin-bottom: 5px; }
        .detail-value { font-size: 14px; color: var(--text); line-height: 1.6; }
        .detail-value.desc { font-weight: 300; background: #f9fafb; border: 1px solid var(--border); border-radius: 8px; padding: 12px 14px; }
        .cat-pill { font-size: 13px; background: #f0f4f9; color: var(--navy); padding: 4px 12px; border-radius: 8px; display: inline-block; }
        .tracking-tag { font-family: monospace; font-size: 13px; background: #f3f4f6; color: #374151; padding: 4px 10px; border-radius: 6px; display: inline-block; }
        .badge { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 10px; font-size: 12px; }
        .badge-pending  { background: #fef9c3; color: #854d0e; }
        .badge-progress { background: #dbeafe; color: #1e40af; }
        .badge-resolved { background: #dcfce7; color: #166534; }
        .timeline { display: flex; align-items: center; margin: 4px 0; }
        .t-step { display: flex; flex-direction: column; align-items: center; flex: 1; }
        .t-dot { width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 500; flex-shrink: 0; }
        .t-dot.done    { background: #22c55e; color: #fff; }
        .t-dot.active  { background: var(--navy); color: #fff; }
        .t-dot.waiting { background: #f3f4f6; color: #9ca3af; border: 1.5px solid #e5e7eb; }
        .t-name { font-size: 11px; color: var(--muted); margin-top: 5px; text-align: center; }
        .t-name.active { color: var(--navy); font-weight: 500; }
        .t-line { flex: 1; height: 2px; margin-bottom: 18px; }
        .t-line.done { background: #22c55e; }
        .t-line.waiting { background: #e5e7eb; }
        .evidence-thumb { width: 100%; max-width: 200px; border-radius: 8px; border: 1px solid var(--border); display: block; margin-top: 6px; }
        .evidence-link { font-size: 13px; color: #3b82f6; text-decoration: none; }
        .evidence-link:hover { text-decoration: underline; }
        /* Status update form */
        .status-form { display: flex; gap: 10px; align-items: center; }
        .status-select { flex: 1; height: 40px; padding: 0 12px; border: 1.5px solid var(--border); border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 13.5px; color: var(--text); background: var(--white); outline: none; cursor: pointer; }
        .status-select:focus { border-color: var(--navy); }
        .btn-update { height: 40px; padding: 0 18px; background: var(--navy); color: #fff; border: none; border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 13px; font-weight: 500; cursor: pointer; white-space: nowrap; transition: background 0.15s; }
        .btn-update:hover { background: #122848; }
        /* Notes */
        .notes-list { display: flex; flex-direction: column; gap: 12px; margin-bottom: 20px; }
        .note-item { background: #f8fafc; border: 1px solid var(--border); border-radius: 10px; padding: 14px 16px; }
        .note-meta { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; }
        .note-author { font-size: 12px; font-weight: 500; color: var(--navy); }
        .note-date { font-size: 11.5px; color: #9ca3af; }
        .note-text { font-size: 13.5px; color: var(--text); font-weight: 300; line-height: 1.6; }
        .no-notes { font-size: 13px; color: #9ca3af; text-align: center; padding: 20px 0; font-weight: 300; }
        /* Add note form */
        .note-textarea { width: 100%; padding: 12px 14px; border: 1.5px solid var(--border); border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 14px; color: var(--text); outline: none; resize: vertical; min-height: 100px; transition: border-color 0.2s; }
        .note-textarea:focus { border-color: var(--navy); box-shadow: 0 0 0 3px rgba(11,31,58,0.07); }
        .btn-add-note { width: 100%; height: 42px; background: var(--navy); color: #fff; border: none; border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 13.5px; font-weight: 500; cursor: pointer; margin-top: 10px; transition: background 0.15s; }
        .btn-add-note:hover { background: #122848; }
        @keyframes modalIn { from { opacity:0; transform:scale(0.95); } to { opacity:1; transform:scale(1); } }
        @media (max-width: 900px) { .content-grid { grid-template-columns: 1fr; } .sidebar { display: none; } }
        /* Pinned location map */
        #detail-map { width: 100%; height: 260px; border-radius: 8px; border: 1.5px solid var(--border); z-index: 1; }
        .map-coords-display { font-size: 11.5px; color: var(--muted); font-family: monospace; margin-top: 8px; display: flex; align-items: center; gap: 6px; }
        .map-coords-display a { color: #3b82f6; text-decoration: none; font-family: 'DM Sans', sans-serif; font-size: 11.5px; }
        .map-coords-display a:hover { text-decoration: underline; }
        .no-location-note { background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 10px 14px; font-size: 13px; color: #92400e; display: flex; align-items: center; gap: 8px; }
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
        <a href="manage_complaints.php" class="active">Complaints</a>
        <a href="complaints_map.php">Map</a>
        <a href="reports.php">Reports</a>
        <a href="#" onclick="showLogoutModal('../logout.php')">Sign out</a>
    </div>
</nav>

<div class="page">
    <aside class="sidebar">
        <div class="sidebar-label">Overview</div>
        <a href="admin_dashboard.php"><span class="si">📊</span> Dashboard</a>
        <div class="sidebar-sep"></div>
        <div class="sidebar-label">Management</div>
        <a href="manage_complaints.php" class="active"><span class="si">📋</span> Complaints</a>
        <a href="complaints_map.php"><span class="si">🗺️</span> Map</a>
        <a href="reports.php"><span class="si">📈</span> Reports</a>
        <div class="sidebar-sep"></div>
        <a href="#" onclick="showLogoutModal('../logout.php')"><span class="si"></span> Sign out</a>
    </aside>

    <main class="main">
        <a href="manage_complaints.php" class="back-link">← Back to complaints</a>

        <h1 class="page-title"><?php echo ($icons[$complaint['category']] ?? '📝'); ?> <?php echo htmlspecialchars($complaint['title']); ?></h1>
        <p class="page-sub">Submitted by <?php echo htmlspecialchars($complaint['full_name']); ?> on <?php echo date('d M Y', strtotime($complaint['created_at'])); ?></p>

        <?php if ($toast): ?>
        <div class="toast">✓ <?php echo $toast; ?></div>
        <?php endif; ?>

        <div class="content-grid">

            <!-- Left: Complaint details -->
            <div>
                <div class="panel">
                    <div class="panel-header"><h3>Complaint details</h3></div>
                    <div class="panel-body">
                        <div class="detail-row">
                            <div class="detail-label">Tracking ID</div>
                            <div class="detail-value"><span class="tracking-tag"><?php echo htmlspecialchars($complaint['tracking_number']); ?></span></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Category</div>
                            <div class="detail-value"><span class="cat-pill"><?php echo htmlspecialchars($complaint['category']); ?></span></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Description</div>
                            <div class="detail-value desc"><?php echo nl2br(htmlspecialchars($complaint['description'])); ?></div>
                        </div>
                        <?php if (!empty($complaint['evidence'])): ?>
                        <div class="detail-row">
                            <div class="detail-label">Evidence</div>
                            <?php if (isImage($complaint['evidence'])): ?>
                                <a href="../uploads/<?php echo htmlspecialchars($complaint['evidence']); ?>" target="_blank">
                                    <img class="evidence-thumb" src="../uploads/<?php echo htmlspecialchars($complaint['evidence']); ?>" alt="Evidence">
                                </a>
                            <?php else: ?>
                                <a class="evidence-link" href="../uploads/<?php echo htmlspecialchars($complaint['evidence']); ?>" target="_blank">📄 View uploaded file</a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Pinned location map -->
                <?php if (!empty($complaint['latitude']) && !empty($complaint['longitude'])): ?>
                <div class="panel">
                    <div class="panel-header"><h3>📍 Pinned location</h3></div>
                    <div class="panel-body">
                        <div id="detail-map"></div>
                        <div class="map-coords-display">
                            📌 <?php echo htmlspecialchars($complaint['latitude']); ?>, <?php echo htmlspecialchars($complaint['longitude']); ?>
                            &nbsp;·&nbsp;
                            <a href="https://www.google.com/maps?q=<?php echo (float)$complaint['latitude']; ?>,<?php echo (float)$complaint['longitude']; ?>" target="_blank">Open in Google Maps ↗</a>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="panel">
                    <div class="panel-header"><h3>📍 Pinned location</h3></div>
                    <div class="panel-body">
                        <div class="no-location-note">⚠ No location was pinned for this complaint.</div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Notes section -->
                <div class="panel">
                    <div class="panel-header"><h3>Admin notes &amp; responses</h3></div>
                    <div class="panel-body">
                        <?php if (empty($notes)): ?>
                        <p class="no-notes">No notes added yet. Add one below to communicate progress.</p>
                        <?php else: ?>
                        <div class="notes-list">
                            <?php foreach ($notes as $note): ?>
                            <div class="note-item">
                                <div class="note-meta">
                                    <span class="note-author">👤 <?php echo htmlspecialchars($note['admin_name']); ?></span>
                                    <span class="note-date"><?php echo date('d M Y, H:i', strtotime($note['created_at'])); ?></span>
                                </div>
                                <div class="note-text"><?php echo nl2br(htmlspecialchars($note['note'])); ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <form method="POST">
                            <?php echo csrf_field(); ?>
                            <textarea name="note" class="note-textarea" placeholder="Add a note or response visible to the admin team. Describe what action was taken, who is assigned, or any relevant updates…" required></textarea>
                            <button type="submit" name="add_note" class="btn-add-note">Add note</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right: Citizen info + Status -->
            <div>
                <div class="panel">
                    <div class="panel-header"><h3>Citizen information</h3></div>
                    <div class="panel-body">
                        <div class="detail-row">
                            <div class="detail-label">Name</div>
                            <div class="detail-value"><?php echo htmlspecialchars($complaint['full_name']); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Email</div>
                            <div class="detail-value"><?php echo htmlspecialchars($complaint['email']); ?></div>
                        </div>
                        <?php if (!empty($complaint['phone'])): ?>
                        <div class="detail-row">
                            <div class="detail-label">Phone</div>
                            <div class="detail-value"><?php echo htmlspecialchars($complaint['phone']); ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($complaint['address'])): ?>
                        <div class="detail-row" style="margin-bottom:0">
                            <div class="detail-label">Address</div>
                            <div class="detail-value"><?php echo htmlspecialchars($complaint['address']); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header"><h3>Status</h3></div>
                    <div class="panel-body">
                        <div class="timeline" style="margin-bottom:20px;">
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
                        <form method="POST" class="status-form" onsubmit="return confirm('Update status for this complaint?')">
                            <?php echo csrf_field(); ?>
                            <select name="status" class="status-select">
                                <?php foreach ($status_steps as $s): ?>
                                <option <?php echo $complaint['status']===$s?'selected':''; ?>><?php echo $s; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" name="update_status" class="btn-update">Update</button>
                        </form>
                    </div>
                </div>
                <!-- ── Citizen satisfaction feedback panel ── -->
                <div class="panel">
                    <div class="panel-header"><h3>⭐ Citizen satisfaction</h3></div>
                    <div class="panel-body">
                        <?php if ($complaint['status'] !== 'Resolved'): ?>
                            <p style="font-size:13px;color:var(--muted);font-weight:300;">
                                Satisfaction feedback is collected once this complaint is marked as
                                <strong>Resolved</strong>.
                            </p>
                        <?php elseif (empty($complaint['feedback_rating'])): ?>
                            <div style="display:flex;align-items:flex-start;gap:10px;padding:12px 14px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;">
                                <span style="font-size:20px;flex-shrink:0;">⏳</span>
                                <div>
                                    <div style="font-size:13px;font-weight:500;color:#92400e;">Awaiting citizen feedback</div>
                                    <div style="font-size:12px;color:#b45309;margin-top:3px;line-height:1.5;">The citizen has not yet submitted a satisfaction rating for this resolved complaint.</div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div style="margin-bottom:14px;">
                                <div style="font-size:11px;font-weight:500;letter-spacing:0.08em;text-transform:uppercase;color:#9ca3af;margin-bottom:8px;">Rating</div>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <div style="font-size:22px;letter-spacing:3px;">
                                        <?php
                                        $rating = (int) $complaint['feedback_rating'];
                                        for ($i = 1; $i <= 5; $i++) {
                                            echo $i <= $rating ? '⭐' : '<span style="color:#d1d5db;">★</span>';
                                        }
                                        ?>
                                    </div>
                                    <div style="font-size:13px;font-weight:500;color:var(--navy);"><?php echo $rating; ?> / 5</div>
                                </div>
                            </div>
                            <?php if (!empty($complaint['feedback_comment'])): ?>
                            <div>
                                <div style="font-size:11px;font-weight:500;letter-spacing:0.08em;text-transform:uppercase;color:#9ca3af;margin-bottom:6px;">Citizen comment</div>
                                <div style="font-size:13px;color:var(--text);font-weight:300;font-style:italic;background:#f9fafb;border:1px solid var(--border);border-radius:8px;padding:12px 14px;line-height:1.6;">
                                    "<?php echo nl2br(htmlspecialchars($complaint['feedback_comment'])); ?>"
                                </div>
                            </div>
                            <?php else: ?>
                            <p style="font-size:12.5px;color:#9ca3af;margin-top:4px;">No written comment was provided.</p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- ─────────────────────────────────────────── -->

            </div>

        </div>
    </main>
</div>

<!-- Logout modal -->
<div id="logoutModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:14px;padding:32px 36px;width:100%;max-width:380px;box-shadow:0 8px 40px rgba(0,0,0,0.18);text-align:center;animation:modalIn 0.2s ease;">
        <div style="font-size:36px;margin-bottom:14px;"></div>
        <h3 style="font-size:17px;font-weight:500;color:#0b1f3a;margin-bottom:8px;">Sign out?</h3>
        <p style="font-size:13px;color:#6b7280;font-weight:300;margin-bottom:24px;line-height:1.6;">You will be returned to the login page.</p>
        <div style="display:flex;gap:10px;justify-content:center;">
            <button onclick="closeLogoutModal()" style="height:42px;padding:0 24px;background:transparent;color:#6b7280;border:1.5px solid #e5e7eb;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13.5px;cursor:pointer;">Cancel</button>
            <a id="logoutConfirmBtn" href="#" style="height:42px;padding:0 24px;background:#0b1f3a;color:#fff;border:none;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13.5px;font-weight:500;text-decoration:none;display:inline-flex;align-items:center;cursor:pointer;">Sign out</a>
        </div>
    </div>
</div>
<script>
function showLogoutModal(href) { document.getElementById('logoutConfirmBtn').href=href; document.getElementById('logoutModal').style.display='flex'; }
function closeLogoutModal() { document.getElementById('logoutModal').style.display='none'; }
document.getElementById('logoutModal').addEventListener('click',function(e){ if(e.target===this) closeLogoutModal(); });
</script>

<?php if (!empty($complaint['latitude']) && !empty($complaint['longitude'])): ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function() {
    var lat = <?php echo (float)$complaint['latitude']; ?>;
    var lng = <?php echo (float)$complaint['longitude']; ?>;
    var map = L.map('detail-map').setView([lat, lng], 16);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19
    }).addTo(map);
    var pinIcon = L.divIcon({
        className: '',
        html: '<div style="width:22px;height:22px;background:#0b1f3a;border:3px solid #fff;border-radius:50% 50% 50% 0;transform:rotate(-45deg);box-shadow:0 2px 6px rgba(0,0,0,0.35);"></div>',
        iconSize: [22, 22],
        iconAnchor: [11, 22]
    });
    L.marker([lat, lng], { icon: pinIcon }).addTo(map)
        .bindPopup('<strong>Complaint location</strong>').openPopup();
})();
</script>
<?php endif; ?>
</body>
</html>