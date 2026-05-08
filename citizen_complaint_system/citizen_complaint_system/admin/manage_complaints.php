<?php
include("../includes/auth.php");
include("../includes/db.php");
require_once("../includes/email_notification.php");
requireRole("admin");

$toast      = "";
$toast_type = "success";

// Handle status update
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update"])) {
    $complaint_id = (int) $_POST["complaint_id"];
    $new_status   = $_POST["status"];
    $allowed = ["Pending", "In Progress", "Resolved"];
    if (in_array($new_status, $allowed) && $complaint_id > 0) {
        $stmt = $conn->prepare("UPDATE complaints SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $complaint_id);
        $stmt->execute();
        $toast = "Status updated to \"" . htmlspecialchars($new_status) . "\" successfully.";
        // Fetch citizen details and send notification email
        $notif_stmt = $conn->prepare(
            "SELECT complaints.title, complaints.tracking_number, users.email, users.full_name
             FROM complaints JOIN users ON complaints.user_id = users.id
             WHERE complaints.id = ?"
        );
        $notif_stmt->bind_param("i", $complaint_id);
        $notif_stmt->execute();
        $notif_data = $notif_stmt->get_result()->fetch_assoc();
        if ($notif_data) {
            notifyStatusChanged(
                $notif_data['email'],
                $notif_data['full_name'],
                $notif_data['title'],
                $notif_data['tracking_number'],
                $new_status
            );
        }
    }
}

// Handle delete
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete"])) {
    $complaint_id = (int) $_POST["complaint_id"];
    if ($complaint_id > 0) {
        // Delete evidence file from disk if it exists
        $file_stmt = $conn->prepare("SELECT evidence FROM complaints WHERE id = ?");
        $file_stmt->bind_param("i", $complaint_id);
        $file_stmt->execute();
        $file_row = $file_stmt->get_result()->fetch_assoc();
        if (!empty($file_row['evidence'])) {
            $file_path = "../uploads/" . $file_row['evidence'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        $del = $conn->prepare("DELETE FROM complaints WHERE id = ?");
        $del->bind_param("i", $complaint_id);
        $del->execute();
        $toast = "Complaint deleted successfully.";
        $toast_type = "error";
    }
}

// Build filtered query
$query  = "SELECT complaints.*, users.full_name, users.email
           FROM complaints
           JOIN users ON complaints.user_id = users.id
           WHERE 1=1";
$params = [];
$types  = "";

$search   = trim($_GET['search'] ?? '');
$cat      = $_GET['category'] ?? '';
$statusF  = $_GET['status'] ?? '';

if ($search !== '') {
    $query   .= " AND (tracking_number LIKE ? OR complaints.title LIKE ? OR users.full_name LIKE ?)";
    $like     = "%$search%";
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types   .= "sss";
}
if ($cat !== '') {
    $query   .= " AND category = ?";
    $params[] = $cat;
    $types   .= "s";
}
if ($statusF !== '') {
    $query   .= " AND status = ?";
    $params[] = $statusF;
    $types   .= "s";
}

$query .= " ORDER BY complaints.created_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$rows = $result->fetch_all(MYSQLI_ASSOC);
$count = count($rows);

function statusBadge($s) {
    $map = [
        'Pending'     => ['badge-pending',  '⏳'],
        'In Progress' => ['badge-progress', '🔄'],
        'Resolved'    => ['badge-resolved', '✅'],
    ];
    [$cls, $icon] = $map[$s] ?? ['badge-pending', '⏳'];
    return '<span class="badge '.$cls.'">'.$icon.' '.htmlspecialchars($s).'</span>';
}

function isImage($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, ['jpg','jpeg','png','gif','webp']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Complaints — Citizen Complaint Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
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
        .sidebar .icon { font-size: 15px; width: 20px; text-align: center; }
        .sidebar-sep { height: 1px; background: var(--border); margin: 12px 0; }
        .main { flex: 1; padding: 28px; overflow-y: auto; }

        /* Header */
        .page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 22px; }
        .page-header h1 { font-size: 20px; font-weight: 500; color: var(--navy); margin-bottom: 3px; }
        .page-header p { font-size: 13px; color: var(--muted); font-weight: 300; }
        .count-badge { background: var(--navy); color: #fff; font-size: 12px; font-weight: 500; padding: 5px 13px; border-radius: 8px; white-space: nowrap; margin-top: 4px; }

        /* Toast */
        .toast { border-radius: 8px; padding: 10px 16px; font-size: 13px; margin-bottom: 18px; display: flex; align-items: center; gap: 8px; animation: fadeUp 0.3s ease; }
        .toast-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }
        .toast-error   { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; }

        /* Filter bar */
        .filter-bar { background: var(--white); border: 1px solid var(--border); border-radius: 12px; padding: 18px 20px; margin-bottom: 20px; }
        .filter-row { display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; }
        .filter-field { display: flex; flex-direction: column; gap: 5px; }
        .filter-field label { font-size: 11px; font-weight: 500; color: #374151; letter-spacing: 0.05em; text-transform: uppercase; }
        .filter-field input, .filter-field select { height: 38px; padding: 0 12px; border: 1.5px solid var(--border); border-radius: 7px; font-family: 'DM Sans', sans-serif; font-size: 13px; color: var(--text); outline: none; background: var(--white); transition: border-color 0.15s; }
        .filter-field input:focus, .filter-field select:focus { border-color: var(--navy); }
        .filter-field.grow { flex: 1; min-width: 180px; }
        .btn-filter { height: 38px; padding: 0 20px; background: var(--navy); color: #fff; border: none; border-radius: 7px; font-family: 'DM Sans', sans-serif; font-size: 13px; font-weight: 500; cursor: pointer; transition: background 0.15s; white-space: nowrap; }
        .btn-filter:hover { background: #122848; }
        .btn-clear { height: 38px; padding: 0 16px; background: transparent; color: var(--muted); border: 1.5px solid var(--border); border-radius: 7px; font-family: 'DM Sans', sans-serif; font-size: 13px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; transition: border-color 0.15s; white-space: nowrap; }
        .btn-clear:hover { border-color: #9ca3af; color: var(--text); }

        /* Table panel */
        .panel { background: var(--white); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 720px; }
        thead th { font-size: 11px; font-weight: 500; letter-spacing: 0.07em; text-transform: uppercase; color: #9ca3af; padding: 13px 16px; text-align: left; background: #fafafa; border-bottom: 1px solid var(--border); }
        tbody tr { border-bottom: 1px solid #f3f4f6; transition: background 0.1s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #fafafa; }
        td { padding: 13px 16px; font-size: 13px; vertical-align: middle; }
        .td-user { font-weight: 500; color: var(--navy); }
        .td-sub { font-size: 11.5px; color: var(--muted); font-weight: 300; margin-top: 1px; }
        .td-tracking { font-family: monospace; font-size: 11.5px; background: #f3f4f6; color: #374151; padding: 3px 8px; border-radius: 5px; }
        .td-desc { max-width: 200px; color: var(--muted); font-weight: 300; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .td-cat { font-size: 12px; background: #f0f4f9; color: var(--navy); padding: 3px 9px; border-radius: 8px; white-space: nowrap; }

        /* Evidence */
        .evidence-thumb { width: 52px; height: 52px; object-fit: cover; border-radius: 6px; border: 1px solid var(--border); display: block; }
        .evidence-link { font-size: 12px; color: #3b82f6; text-decoration: none; }
        .evidence-link:hover { text-decoration: underline; }
        .no-evidence { font-size: 12px; color: #d1d5db; }

        /* Status badges */
        .badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 9px; border-radius: 10px; font-size: 11.5px; font-weight: 400; white-space: nowrap; }
        .badge-pending  { background: #fef9c3; color: #854d0e; }
        .badge-progress { background: #dbeafe; color: #1e40af; }
        .badge-resolved { background: #dcfce7; color: #166534; }

        /* Inline update form */
        .update-form { display: flex; align-items: center; gap: 6px; }
        .status-select { height: 34px; padding: 0 10px; border: 1.5px solid var(--border); border-radius: 6px; font-family: 'DM Sans', sans-serif; font-size: 12.5px; color: var(--text); outline: none; background: var(--white); cursor: pointer; transition: border-color 0.15s; }
        .status-select:focus { border-color: var(--navy); }
        .btn-update { height: 34px; padding: 0 13px; background: var(--navy); color: #fff; border: none; border-radius: 6px; font-family: 'DM Sans', sans-serif; font-size: 12px; font-weight: 500; cursor: pointer; transition: background 0.15s; }
        .btn-update:hover { background: #122848; }
        .btn-delete { height: 34px; padding: 0 13px; background: transparent; color: #dc2626; border: 1.5px solid #fca5a5; border-radius: 6px; font-family: 'DM Sans', sans-serif; font-size: 12px; font-weight: 500; cursor: pointer; transition: all 0.15s; white-space: nowrap; }
        .btn-delete:hover { background: #fef2f2; border-color: #dc2626; }

        /* Empty state */
        .empty-state { text-align: center; padding: 56px 20px; color: var(--muted); }
        .empty-state .emoji { font-size: 36px; margin-bottom: 14px; }
        .empty-state p { font-size: 13.5px; font-weight: 300; }

        @keyframes fadeUp { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:translateY(0); } }
        @media (max-width: 900px) { .sidebar { display: none; } }
        @media (max-width: 600px) { .main { padding: 16px; } }
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
        <a href="#" onclick="showLogoutModal('../logout.php')" >Sign out</a>
    </div>
</nav>

<div class="page">

    <aside class="sidebar">
        <div class="sidebar-label">Overview</div>
        <a href="admin_dashboard.php"><span class="icon">📊</span> Dashboard</a>
        <div class="sidebar-sep"></div>
        <div class="sidebar-label">Management</div>
        <a href="manage_complaints.php" class="active"><span class="icon">📋</span> Complaints</a>
        <a href="complaints_map.php"><span class="icon">🗺️</span> Map</a>
        <a href="reports.php"><span class="icon">📈</span> Reports</a>
        <div class="sidebar-sep"></div>
        <a href="#" onclick="showLogoutModal('../logout.php')" ><span class="icon">🚪</span> Sign out</a>
    </aside>

    <main class="main">

        <div class="page-header">
            <div>
                <h1>Manage Complaints</h1>
                <p>Review, filter and update citizen complaint statuses</p>
            </div>
            <div class="count-badge"><?php echo $count; ?> result<?php echo $count !== 1 ? 's' : ''; ?></div>
        </div>

        <?php if ($toast): ?>
        <div class="toast toast-<?php echo $toast_type; ?>">
            <?php echo $toast_type === 'success' ? '✓' : '🗑'; ?> <?php echo $toast; ?>
        </div>
        <?php endif; ?>

        <!-- Filter bar -->
        <div class="filter-bar">
            <form method="GET" action="manage_complaints.php">
                <div class="filter-row">
                    <div class="filter-field grow">
                        <label>Search</label>
                        <input type="text" name="search" placeholder="Tracking ID, title or citizen name…" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-field">
                        <label>Category</label>
                        <select name="category">
                            <option value="">All categories</option>
                            <?php foreach (['Road Issues','Water Supply','Electricity','Sanitation','Public Safety','Other'] as $c): ?>
                            <option <?php echo $cat===$c?'selected':''; ?>><?php echo $c; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-field">
                        <label>Status</label>
                        <select name="status">
                            <option value="">All statuses</option>
                            <?php foreach (['Pending','In Progress','Resolved'] as $s): ?>
                            <option <?php echo $statusF===$s?'selected':''; ?>><?php echo $s; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-filter">Filter</button>
                    <?php if ($search || $cat || $statusF): ?>
                    <a href="manage_complaints.php" class="btn-clear">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="panel">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Citizen</th>
                            <th>Tracking ID</th>
                            <th>Title & Category</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Evidence</th>
                            <th>Update status</th>
                            <th>Delete</th>
                            <th>Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="9">
                            <div class="empty-state">
                                <div class="emoji">🔍</div>
                                <p>No complaints match your current filters.</p>
                            </div>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                        <tr>
                            <td>
                                <div class="td-user"><?php echo htmlspecialchars($row['full_name']); ?></div>
                                <div class="td-sub"><?php echo htmlspecialchars($row['email']); ?></div>
                            </td>
                            <td><span class="td-tracking"><?php echo htmlspecialchars($row['tracking_number']); ?></span></td>
                            <td>
                                <div><?php echo htmlspecialchars($row['title']); ?></div>
                                <div><span class="td-cat"><?php echo htmlspecialchars($row['category']); ?></span></div>
                            </td>
                            <td class="td-desc" title="<?php echo htmlspecialchars($row['description']); ?>">
                                <?php echo htmlspecialchars($row['description']); ?>
                            </td>
                            <td><?php echo statusBadge($row['status']); ?></td>
                            <td>
                                <?php if (!empty($row['evidence'])): ?>
                                    <?php if (isImage($row['evidence'])): ?>
                                        <a href="../uploads/<?php echo htmlspecialchars($row['evidence']); ?>" target="_blank">
                                            <img class="evidence-thumb" src="../uploads/<?php echo htmlspecialchars($row['evidence']); ?>" alt="Evidence">
                                        </a>
                                    <?php else: ?>
                                        <a class="evidence-link" href="../uploads/<?php echo htmlspecialchars($row['evidence']); ?>" target="_blank">📄 View file</a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="no-evidence">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" class="update-form" id="updateForm_<?php echo (int)$row['id']; ?>">
                                    <input type="hidden" name="complaint_id" value="<?php echo (int)$row['id']; ?>">
                                    <select name="status" class="status-select" id="statusSelect_<?php echo (int)$row['id']; ?>">
                                        <?php foreach (['Pending','In Progress','Resolved'] as $s): ?>
                                        <option <?php echo $row['status']===$s?'selected':''; ?>><?php echo $s; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" name="update" class="btn-update"
                                        onclick="showUpdateModal(<?php echo (int)$row['id']; ?>, '<?php echo htmlspecialchars($row['title'], ENT_QUOTES); ?>')">Save</button>
                                </form>
                            </td>
                            <td>
                                <form method="POST" id="deleteForm_<?php echo (int)$row['id']; ?>">
                                    <input type="hidden" name="complaint_id" value="<?php echo (int)$row['id']; ?>">
                                    <button type="button" class="btn-delete"
                                        onclick="showDeleteModal(<?php echo (int)$row['id']; ?>, '<?php echo htmlspecialchars($row['title'], ENT_QUOTES); ?>')">🗑 Delete</button>
                                </form>
                            </td>
                            <td>
                                <a href="complaint_detail.php?id=<?php echo (int)$row['id']; ?>"
                                   style="font-size:12.5px;color:#3b82f6;text-decoration:none;white-space:nowrap;">
                                    View →
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>


<!-- Update confirmation modal -->
<div id="updateModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:14px;padding:32px 36px;width:100%;max-width:380px;box-shadow:0 8px 40px rgba(0,0,0,0.18);text-align:center;animation:modalIn 0.2s ease;">
        <div style="font-size:36px;margin-bottom:14px;">🔄</div>
        <h3 style="font-size:17px;font-weight:500;color:#0b1f3a;margin-bottom:8px;">Update status?</h3>
        <p id="updateModalText" style="font-size:13px;color:#6b7280;font-weight:300;margin-bottom:24px;line-height:1.6;"></p>
        <div style="display:flex;gap:10px;justify-content:center;">
            <button onclick="closeUpdateModal()" style="height:42px;padding:0 24px;background:transparent;color:#6b7280;border:1.5px solid #e5e7eb;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13.5px;cursor:pointer;">Cancel</button>
            <button id="updateConfirmBtn" style="height:42px;padding:0 24px;background:#0b1f3a;color:#fff;border:none;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13.5px;font-weight:500;cursor:pointer;">Update</button>
        </div>
    </div>
</div>

<!-- Delete confirmation modal -->
<div id="deleteModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:14px;padding:32px 36px;width:100%;max-width:380px;box-shadow:0 8px 40px rgba(0,0,0,0.18);text-align:center;animation:modalIn 0.2s ease;">
        <div style="font-size:36px;margin-bottom:14px;">🗑</div>
        <h3 style="font-size:17px;font-weight:500;color:#0b1f3a;margin-bottom:8px;">Delete complaint?</h3>
        <p id="deleteModalText" style="font-size:13px;color:#6b7280;font-weight:300;margin-bottom:24px;line-height:1.6;"></p>
        <div style="display:flex;gap:10px;justify-content:center;">
            <button onclick="closeDeleteModal()" style="height:42px;padding:0 24px;background:transparent;color:#6b7280;border:1.5px solid #e5e7eb;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13.5px;cursor:pointer;">Cancel</button>
            <button id="deleteConfirmBtn" style="height:42px;padding:0 24px;background:#dc2626;color:#fff;border:none;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13.5px;font-weight:500;cursor:pointer;">Delete</button>
        </div>
    </div>
</div>

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
function showUpdateModal(id, title) {
    document.getElementById('updateModalText').textContent =
        'Update the status of "' + title + '"? This will be visible to the citizen immediately.';
    document.getElementById('updateConfirmBtn').onclick = function() {
        const form = document.getElementById('updateForm_' + id);
        const hidden = document.createElement('input');
        hidden.type = 'hidden'; hidden.name = 'update'; hidden.value = '1';
        form.appendChild(hidden);
        form.submit();
    };
    const modal = document.getElementById('updateModal');
    modal.style.display = 'flex';
}
function closeUpdateModal() {
    document.getElementById('updateModal').style.display = 'none';
}
document.getElementById('updateModal').addEventListener('click', function(e) {
    if (e.target === this) closeUpdateModal();
});

function showDeleteModal(id, title) {
    document.getElementById('deleteModalText').textContent =
        'Permanently delete "' + title + '"? This cannot be undone and will also remove any uploaded evidence.';
    document.getElementById('deleteConfirmBtn').onclick = function() {
        const form = document.getElementById('deleteForm_' + id);
        const hidden = document.createElement('input');
        hidden.type = 'hidden'; hidden.name = 'delete'; hidden.value = '1';
        form.appendChild(hidden);
        form.submit();
    };
    const modal = document.getElementById('deleteModal');
    modal.style.display = 'flex';
}
function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});

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