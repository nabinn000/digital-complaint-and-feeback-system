<?php
include("includes/auth.php");
include("includes/db.php");
requireRole("user");

$user_id = (int) $_SESSION["user_id"];

// ── Handle feedback submission ────────────────────────────────────
$feedback_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    csrf_verify();
    $fb_complaint_id = (int) ($_POST['complaint_id'] ?? 0);
    $fb_rating       = (int) ($_POST['rating']       ?? 0);
    $fb_comment      = trim($_POST['comment']         ?? '');

    // Validate: rating 1-5, complaint must belong to this user and be Resolved, not yet rated
    if ($fb_rating >= 1 && $fb_rating <= 5 && $fb_complaint_id > 0) {
        $check = $conn->prepare(
            "SELECT id FROM complaints
             WHERE id = ? AND user_id = ? AND status = 'Resolved' AND feedback_rating IS NULL"
        );
        $check->bind_param("ii", $fb_complaint_id, $user_id);
        $check->execute();
        if ($check->get_result()->num_rows === 1) {
            $upd = $conn->prepare(
                "UPDATE complaints SET feedback_rating = ?, feedback_comment = ? WHERE id = ?"
            );
            $upd->bind_param("isi", $fb_rating, $fb_comment, $fb_complaint_id);
            $upd->execute();
            $feedback_msg = 'Thank you for your feedback!';
        }
    }
}

// ── Fetch complaints ──────────────────────────────────────────────
$search  = trim($_GET['search'] ?? '');
$statusF = $_GET['status'] ?? '';

$query  = "SELECT * FROM complaints WHERE user_id = ?";
$params = [$user_id];
$types  = "i";

if ($search !== '') {
    $query   .= " AND (title LIKE ? OR tracking_number LIKE ?)";
    $like     = "%$search%";
    $params[] = $like; $params[] = $like;
    $types   .= "ss";
}
if ($statusF !== '') {
    $query   .= " AND status = ?";
    $params[] = $statusF;
    $types   .= "s";
}
$query .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

function statusBadge($s) {
    $map = ['Pending'=>['badge-pending','⏳'],'In Progress'=>['badge-progress','🔄'],'Resolved'=>['badge-resolved','✅']];
    [$cls, $icon] = $map[$s] ?? ['badge-pending','⏳'];
    return '<span class="badge '.$cls.'">'.$icon.' '.htmlspecialchars($s).'</span>';
}
function isImage($f) {
    return in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), ['jpg','jpeg','png','gif','webp']);
}
function starDisplay($rating) {
    $out = '';
    for ($i = 1; $i <= 5; $i++) {
        $out .= $i <= $rating ? '⭐' : '☆';
    }
    return $out;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Complaints — Citizen Complaint Portal</title>
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
        .nav-right a:hover, .nav-right a.active { background: rgba(255,255,255,0.12); color: #fff; }

        .page { display: flex; flex: 1; }
        .sidebar { width: 220px; background: var(--white); border-right: 1px solid var(--border); padding: 24px 0; flex-shrink: 0; }
        .sidebar-label { font-size: 10px; font-weight: 500; letter-spacing: 0.12em; text-transform: uppercase; color: #9ca3af; padding: 0 20px; margin-bottom: 8px; }
        .sidebar a { display: flex; align-items: center; gap: 10px; font-size: 13.5px; color: var(--muted); text-decoration: none; padding: 9px 20px; border-left: 3px solid transparent; transition: all 0.15s; }
        .sidebar a:hover { color: var(--navy); background: #f8fafc; }
        .sidebar a.active { color: var(--navy); font-weight: 500; border-left-color: var(--navy); background: #f0f4f9; }
        .sidebar .si { font-size: 15px; width: 20px; text-align: center; }
        .sidebar-sep { height: 1px; background: var(--border); margin: 12px 0; }
        .main { flex: 1; padding: 32px 28px; }

        .page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 22px; flex-wrap: wrap; gap: 12px; }
        .page-header h1 { font-size: 20px; font-weight: 500; color: var(--navy); margin-bottom: 3px; }
        .page-header p { font-size: 13px; color: var(--muted); font-weight: 300; }
        .header-right { display: flex; align-items: center; gap: 10px; }
        .count-badge { background: var(--navy); color: #fff; font-size: 12px; font-weight: 500; padding: 5px 13px; border-radius: 8px; }
        .btn-submit-new { height: 38px; padding: 0 18px; background: var(--navy); color: #fff; border: none; border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 13px; font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; cursor: pointer; transition: background 0.15s; }
        .btn-submit-new:hover { background: #122848; }

        /* Filter bar */
        .filter-bar { background: var(--white); border: 1px solid var(--border); border-radius: 12px; padding: 16px 20px; margin-bottom: 20px; }
        .filter-row { display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; }
        .ff { display: flex; flex-direction: column; gap: 5px; }
        .ff label { font-size: 11px; font-weight: 500; color: #374151; letter-spacing: 0.05em; text-transform: uppercase; }
        .ff input, .ff select { height: 38px; padding: 0 12px; border: 1.5px solid var(--border); border-radius: 7px; font-family: 'DM Sans', sans-serif; font-size: 13px; color: var(--text); background: var(--white); outline: none; transition: border-color 0.15s; }
        .ff input:focus, .ff select:focus { border-color: var(--navy); }
        .ff.grow { flex: 1; min-width: 180px; }
        .ff.grow input { width: 100%; }
        .btn-filter { height: 38px; padding: 0 20px; background: var(--navy); color: #fff; border: none; border-radius: 7px; font-family: 'DM Sans', sans-serif; font-size: 13px; font-weight: 500; cursor: pointer; }
        .btn-filter:hover { background: #122848; }
        .btn-clear { height: 38px; padding: 0 14px; background: transparent; color: var(--muted); border: 1.5px solid var(--border); border-radius: 7px; font-size: 13px; text-decoration: none; display: inline-flex; align-items: center; transition: border-color 0.15s; }
        .btn-clear:hover { border-color: #9ca3af; color: var(--text); }

        /* Global feedback success banner */
        .feedback-banner { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; border-radius: 10px; padding: 12px 18px; margin-bottom: 20px; font-size: 13.5px; display: flex; align-items: center; gap: 8px; }

        /* Cards grid */
        .cards-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 16px; }
        .complaint-card { background: var(--white); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; transition: box-shadow 0.15s; }
        .complaint-card:hover { box-shadow: 0 3px 12px rgba(0,0,0,0.07); }
        .card-body { padding: 20px; }
        .card-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 10px; }
        .card-title { font-size: 14px; font-weight: 500; color: var(--navy); line-height: 1.35; }
        .card-desc { font-size: 13px; color: var(--muted); font-weight: 300; line-height: 1.5; margin-bottom: 14px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .card-meta { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 12px; }
        .meta-cat { font-size: 12px; background: #f0f4f9; color: var(--navy); padding: 3px 9px; border-radius: 7px; }
        .meta-date { font-size: 12px; color: #9ca3af; }
        .card-footer { display: flex; align-items: center; justify-content: space-between; padding-top: 12px; border-top: 1px solid #f3f4f6; }
        .tracking-tag { font-family: monospace; font-size: 11px; background: #f3f4f6; color: #374151; padding: 3px 8px; border-radius: 5px; }
        .evidence-link { font-size: 12px; color: #3b82f6; text-decoration: none; display: flex; align-items: center; gap: 4px; }
        .evidence-link:hover { text-decoration: underline; }

        /* Status badges */
        .badge { display: inline-flex; align-items: center; gap: 3px; padding: 3px 9px; border-radius: 10px; font-size: 11.5px; white-space: nowrap; }
        .badge-pending  { background: #fef9c3; color: #854d0e; }
        .badge-progress { background: #dbeafe; color: #1e40af; }
        .badge-resolved { background: #dcfce7; color: #166534; }

        /* ── Satisfaction feedback panel ─────────────────────── */
        .feedback-panel {
            background: #fffbeb;
            border-top: 1px solid #fde68a;
            padding: 16px 20px;
        }
        .feedback-panel .fp-title {
            font-size: 13px;
            font-weight: 500;
            color: #92400e;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        /* Star rating */
        .star-row {
            display: flex;
            gap: 4px;
            margin-bottom: 10px;
            flex-direction: row-reverse;
            justify-content: flex-end;
        }
        .star-row input[type="radio"] { display: none; }
        .star-row label {
            font-size: 28px;
            cursor: pointer;
            color: #d1d5db;
            transition: color 0.1s;
            line-height: 1;
        }
        /* Highlight selected and everything to the right (visually left in reversed row) */
        .star-row input[type="radio"]:checked ~ label,
        .star-row label:hover,
        .star-row label:hover ~ label { color: #f59e0b; }

        .feedback-comment {
            width: 100%;
            padding: 9px 12px;
            border: 1.5px solid #fde68a;
            border-radius: 8px;
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            color: var(--text);
            background: #fff;
            resize: vertical;
            outline: none;
            transition: border-color 0.15s;
            margin-bottom: 10px;
        }
        .feedback-comment:focus { border-color: #f59e0b; }
        .btn-feedback {
            height: 36px;
            padding: 0 20px;
            background: #92400e;
            color: #fff;
            border: none;
            border-radius: 7px;
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.15s;
        }
        .btn-feedback:hover { background: #78350f; }

        /* Already-rated display */
        .feedback-done {
            background: #f0fdf4;
            border-top: 1px solid #bbf7d0;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .feedback-done .fd-stars { font-size: 16px; }
        .feedback-done .fd-text  { font-size: 12.5px; color: #15803d; }
        .feedback-done .fd-comment { font-size: 12px; color: var(--muted); font-style: italic; margin-top: 2px; }
        /* ─────────────────────────────────────────────────────── */

        /* Empty state */
        .empty-state { text-align: center; padding: 64px 20px; background: var(--white); border: 1px solid var(--border); border-radius: 12px; }
        .empty-state .emoji { font-size: 40px; margin-bottom: 14px; }
        .empty-state h3 { font-size: 16px; font-weight: 500; color: var(--navy); margin-bottom: 8px; }
        .empty-state p { font-size: 13px; color: var(--muted); font-weight: 300; margin-bottom: 20px; }
        .btn-empty { height: 42px; padding: 0 24px; background: var(--navy); color: #fff; border: none; border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 13.5px; font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; cursor: pointer; transition: background 0.15s; }
        .btn-empty:hover { background: #122848; }

        @keyframes modalIn { from { opacity:0; transform:scale(0.95); } to { opacity:1; transform:scale(1); } }
        @media (max-width: 700px) { .sidebar { display: none; } .main { padding: 20px 16px; } .cards-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<nav>
    <a class="nav-brand" href="dashboard.php">
        <span style="font-size:18px">🏛</span>
        <span>Citizen Complaint Portal</span>
    </a>
    <div class="nav-right">
        <a href="dashboard.php">Dashboard</a>
        <a href="submit_complaint.php">Submit</a>
        <a href="view_complaints.php" class="active">My Complaints</a>
        <a href="#" onclick="showLogoutModal('logout.php')">Sign out</a>
    </div>
</nav>

<div class="page">
    <aside class="sidebar">
        <div class="sidebar-label">Menu</div>
        <a href="dashboard.php"><span class="si">📊</span> Dashboard</a>
        <div class="sidebar-sep"></div>
        <div class="sidebar-label">Complaints</div>
        <a href="submit_complaint.php"><span class="si">✏️</span> Submit complaint</a>
        <a href="view_complaints.php" class="active"><span class="si">📋</span> My complaints</a>
        <div class="sidebar-sep"></div>
        <a href="#" onclick="showLogoutModal('logout.php')"><span class="si">🚪</span> Sign out</a>
    </aside>

    <main class="main">

        <div class="page-header">
            <div>
                <h1>My complaints</h1>
                <p>Track the status and progress of everything you've submitted</p>
            </div>
            <div class="header-right">
                <span class="count-badge"><?php echo count($rows); ?> complaint<?php echo count($rows) !== 1 ? 's' : ''; ?></span>
                <a href="submit_complaint.php" class="btn-submit-new">+ New complaint</a>
            </div>
        </div>

        <?php if ($feedback_msg): ?>
        <div class="feedback-banner">✅ <?php echo htmlspecialchars($feedback_msg); ?></div>
        <?php endif; ?>

        <!-- Filter bar -->
        <div class="filter-bar">
            <form method="GET" action="view_complaints.php">
                <div class="filter-row">
                    <div class="ff grow">
                        <label>Search</label>
                        <input type="text" name="search" placeholder="Search by title or tracking ID…" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="ff">
                        <label>Status</label>
                        <select name="status">
                            <option value="">All statuses</option>
                            <?php foreach (['Pending','In Progress','Resolved'] as $s): ?>
                            <option <?php echo $statusF===$s?'selected':''; ?>><?php echo $s; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-filter">Filter</button>
                    <?php if ($search || $statusF): ?>
                    <a href="view_complaints.php" class="btn-clear">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Cards or empty state -->
        <?php if (empty($rows)): ?>
        <div class="empty-state">
            <div class="emoji"><?php echo ($search || $statusF) ? '🔍' : '📭'; ?></div>
            <h3><?php echo ($search || $statusF) ? 'No complaints found' : 'No complaints yet'; ?></h3>
            <p><?php echo ($search || $statusF) ? 'Try adjusting your search or filters.' : "You haven't submitted any complaints yet. Get started below."; ?></p>
            <?php if (!$search && !$statusF): ?>
            <a href="submit_complaint.php" class="btn-empty">Submit your first complaint</a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="cards-grid">
            <?php foreach ($rows as $row): ?>
            <div class="complaint-card">
                <div class="card-body">
                    <div class="card-top">
                        <div class="card-title"><?php echo htmlspecialchars($row['title']); ?></div>
                        <?php echo statusBadge($row['status']); ?>
                    </div>
                    <div class="card-desc"><?php echo htmlspecialchars($row['description']); ?></div>
                    <div class="card-meta">
                        <span class="meta-cat"><?php echo htmlspecialchars($row['category']); ?></span>
                        <span class="meta-date"><?php echo date('d M Y', strtotime($row['created_at'])); ?></span>
                    </div>
                    <div class="card-footer">
                        <span class="tracking-tag"><?php echo htmlspecialchars($row['tracking_number']); ?></span>
                        <?php if (!empty($row['evidence'])): ?>
                            <?php if (isImage($row['evidence'])): ?>
                            <a class="evidence-link" href="uploads/<?php echo htmlspecialchars($row['evidence']); ?>" target="_blank">🖼 View photo</a>
                            <?php else: ?>
                            <a class="evidence-link" href="uploads/<?php echo htmlspecialchars($row['evidence']); ?>" target="_blank">📄 View file</a>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="font-size:12px;color:#d1d5db">No evidence</span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($row['status'] === 'Resolved'): ?>
                    <?php if ($row['feedback_rating'] !== null): ?>
                    <!-- Already rated — show the submitted rating -->
                    <div class="feedback-done">
                        <div>
                            <div class="fd-stars"><?php echo starDisplay((int)$row['feedback_rating']); ?></div>
                            <?php if (!empty($row['feedback_comment'])): ?>
                            <div class="fd-comment">"<?php echo htmlspecialchars($row['feedback_comment']); ?>"</div>
                            <?php endif; ?>
                        </div>
                        <div class="fd-text" style="margin-left:auto;">Feedback submitted ✓</div>
                    </div>
                    <?php else: ?>
                    <!-- Not yet rated — show the satisfaction prompt -->
                    <div class="feedback-panel">
                        <div class="fp-title">⭐ How satisfied are you with the resolution?</div>
                        <form method="POST" action="view_complaints.php<?php echo ($search||$statusF) ? '?search='.urlencode($search).'&status='.urlencode($statusF) : ''; ?>">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="complaint_id" value="<?php echo $row['id']; ?>">
                            <input type="hidden" name="submit_feedback" value="1">

                            <!-- Star rating — rendered right-to-left so CSS sibling selector works -->
                            <div class="star-row" id="stars-<?php echo $row['id']; ?>">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                <input type="radio" name="rating" id="star-<?php echo $row['id'].'-'.$i; ?>" value="<?php echo $i; ?>" required>
                                <label for="star-<?php echo $row['id'].'-'.$i; ?>" title="<?php echo $i; ?> star<?php echo $i>1?'s':''; ?>">★</label>
                                <?php endfor; ?>
                            </div>

                            <textarea
                                name="comment"
                                class="feedback-comment"
                                rows="2"
                                placeholder="Optional — any comments about the resolution? (e.g. how quickly it was handled, what was done)"
                            ></textarea>

                            <button type="submit" class="btn-feedback">Submit feedback</button>
                        </form>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>

            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </main>
</div>

<!-- Logout confirmation modal -->
<div id="logoutModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:14px;padding:32px 36px;width:100%;max-width:380px;box-shadow:0 8px 40px rgba(0,0,0,0.18);text-align:center;animation:modalIn 0.2s ease;">
        <div style="font-size:36px;margin-bottom:14px;">🚪</div>
        <h3 style="font-size:17px;font-weight:500;color:#0b1f3a;margin-bottom:8px;">Sign out?</h3>
        <p style="font-size:13px;color:#6b7280;font-weight:300;margin-bottom:24px;line-height:1.6;">You will be returned to the login page. Any unsaved changes will be lost.</p>
        <div style="display:flex;gap:10px;justify-content:center;">
            <button onclick="closeLogoutModal()" style="height:42px;padding:0 24px;background:transparent;color:#6b7280;border:1.5px solid #e5e7eb;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13.5px;cursor:pointer;">Cancel</button>
            <a id="logoutConfirmBtn" href="#" style="height:42px;padding:0 24px;background:#0b1f3a;color:#fff;border:none;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13.5px;font-weight:500;text-decoration:none;display:inline-flex;align-items:center;cursor:pointer;">Sign out</a>
        </div>
    </div>
</div>

<script>
function showLogoutModal(href) {
    document.getElementById('logoutConfirmBtn').href = href;
    document.getElementById('logoutModal').style.display = 'flex';
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