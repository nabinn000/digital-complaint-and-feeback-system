<?php
include("includes/auth.php");
include("includes/db.php");
include("includes/email_notification.php");
requireRole("user");

$message       = "";
$success       = false;
$tracking_show = "";
$old = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_verify();

    $old = [
        'title'       => trim($_POST['title'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'category'    => $_POST['category'] ?? '',
    ];
    $user_id       = (int) $_SESSION["user_id"];
    $evidence_name = "";

    // Sanitise and validate coordinates
    $latitude  = null;
    $longitude = null;
    if (!empty($_POST['latitude']) && !empty($_POST['longitude'])) {
        $lat = (float) $_POST['latitude'];
        $lng = (float) $_POST['longitude'];
        // Valid lat/lng ranges
        if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
            $latitude  = $lat;
            $longitude = $lng;
        }
    }

    // File upload — size, extension AND real MIME check
    if (isset($_FILES["evidence"]) && $_FILES["evidence"]["error"] === 0) {
        $max_bytes     = 5 * 1024 * 1024;
        $allowed_exts  = ["jpg","jpeg","png","pdf"];
        $allowed_mimes = ["image/jpeg","image/png","application/pdf"];

        if ($_FILES["evidence"]["size"] > $max_bytes) {
            $message = "File is too large. Maximum allowed size is 5 MB.";
        } else {
            $ext       = strtolower(pathinfo($_FILES["evidence"]["name"], PATHINFO_EXTENSION));
            $finfo     = new finfo(FILEINFO_MIME_TYPE);
            $real_mime = $finfo->file($_FILES["evidence"]["tmp_name"]);

            if (!in_array($ext, $allowed_exts) || !in_array($real_mime, $allowed_mimes)) {
                $message = "Invalid file. Only JPG, PNG and PDF files up to 5 MB are allowed.";
            } else {
                $evidence_name = time() . "_" . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES["evidence"]["name"]));
                if (!move_uploaded_file($_FILES["evidence"]["tmp_name"], "uploads/" . $evidence_name)) {
                    $message = "File upload failed. Please try again.";
                    $evidence_name = "";
                }
            }
        }
    }

    if (empty($message)) {
        if (empty($old['title']) || empty($old['description']) || empty($old['category'])) {
            $message = "Please fill in all required fields.";
        } else {
            $tracking_number = "CMP" . time() . rand(100, 999);
            $stmt = $conn->prepare(
                "INSERT INTO complaints (user_id, title, description, category, tracking_number, evidence, latitude, longitude)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("isssssdd", $user_id, $old['title'], $old['description'], $old['category'], $tracking_number, $evidence_name, $latitude, $longitude);
            if ($stmt->execute()) {
                $success       = true;
                $tracking_show = $tracking_number;

                // Save before clearing $old
                $submitted_title    = $old['title'];
                $submitted_category = $old['category'];
                $old                = [];

                // Send confirmation email to citizen
                $uStmt = $conn->prepare("SELECT full_name, email FROM users WHERE id = ?");
                $uStmt->bind_param("i", $user_id);
                $uStmt->execute();
                $uRow = $uStmt->get_result()->fetch_assoc();
                if ($uRow) {
                    notifyComplaintSubmitted(
                        $uRow['email'],
                        $uRow['full_name'],
                        $tracking_number,
                        $submitted_title,
                        $submitted_category
                    );
                }
            } else {
                $message = "Something went wrong. Please try again.";
            }
        }
    }
}

$categories = ["Road Issues","Water Supply","Electricity","Sanitation","Public Safety","Other"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Complaint — Citizen Complaint Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">

    <!-- Leaflet.js CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>

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
        .page-header { margin-bottom: 28px; }
        .page-header h1 { font-size: 20px; font-weight: 500; color: var(--navy); margin-bottom: 3px; }
        .page-header p { font-size: 13px; color: var(--muted); font-weight: 300; }
        .form-grid { display: grid; grid-template-columns: 1fr 340px; gap: 24px; align-items: start; }
        .panel { background: var(--white); border: 1px solid var(--border); border-radius: 12px; }
        .panel-header { padding: 18px 22px 14px; border-bottom: 1px solid var(--border); }
        .panel-header h3 { font-size: 14px; font-weight: 500; color: var(--navy); }
        .panel-body { padding: 22px; }
        .alert { border-radius: 8px; padding: 12px 16px; font-size: 13px; margin-bottom: 20px; }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; animation: shake 0.35s ease; }
        .field { margin-bottom: 18px; }
        .field label { display: block; font-size: 12px; font-weight: 500; color: #374151; margin-bottom: 6px; }
        .field label .req { color: #ef4444; margin-left: 2px; }
        .field input, .field textarea, .field select { width: 100%; padding: 0 14px; border: 1.5px solid var(--border); border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 14px; color: var(--text); outline: none; transition: border-color 0.2s, box-shadow 0.2s; background: var(--white); }
        .field input, .field select { height: 44px; }
        .field textarea { padding: 12px 14px; resize: vertical; }
        .field input:focus, .field textarea:focus, .field select:focus { border-color: var(--navy); box-shadow: 0 0 0 3px rgba(11,31,58,0.07); }
        .cat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
        .cat-option { display: none; }
        .cat-label { display: flex; flex-direction: column; align-items: center; gap: 5px; padding: 12px 8px; border: 1.5px solid var(--border); border-radius: 9px; cursor: pointer; transition: all 0.15s; text-align: center; }
        .cat-label .cat-icon { font-size: 20px; }
        .cat-label .cat-name { font-size: 11.5px; color: var(--muted); font-weight: 400; }
        .cat-option:checked + .cat-label { border-color: var(--navy); background: #f0f4f9; }
        .cat-option:checked + .cat-label .cat-name { color: var(--navy); font-weight: 500; }
        .file-drop { border: 2px dashed var(--border); border-radius: 9px; padding: 24px 16px; text-align: center; cursor: pointer; transition: border-color 0.15s, background 0.15s; position: relative; }
        .file-drop:hover, .file-drop.dragover { border-color: var(--navy); background: #f8fafc; }
        .file-drop input { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
        .file-drop .fd-icon { font-size: 24px; margin-bottom: 6px; }
        .file-drop .fd-text { font-size: 13px; color: var(--muted); font-weight: 300; }
        .file-drop .fd-text strong { color: var(--navy); font-weight: 500; }
        .file-drop .fd-types { font-size: 11px; color: #9ca3af; margin-top: 4px; }
        .file-selected { font-size: 12.5px; color: #15803d; margin-top: 8px; display: none; }
        .btn-submit { width: 100%; height: 46px; background: var(--navy); color: #fff; border: none; border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 14px; font-weight: 500; cursor: pointer; transition: background 0.2s; }
        .btn-submit:hover { background: #122848; }
        .btn-back { display: inline-flex; align-items: center; gap: 5px; font-size: 13px; color: var(--muted); text-decoration: none; margin-top: 12px; transition: color 0.15s; }
        .btn-back:hover { color: var(--navy); }
        .success-card { background: var(--white); border: 1px solid var(--border); border-radius: 12px; padding: 40px 28px; text-align: center; max-width: 480px; }
        .success-icon { font-size: 40px; margin-bottom: 16px; }
        .success-card h2 { font-size: 18px; font-weight: 500; color: var(--navy); margin-bottom: 8px; }
        .success-card p { font-size: 13px; color: var(--muted); font-weight: 300; margin-bottom: 20px; line-height: 1.6; }
        .tracking-box { background: #f0f4f9; border-radius: 8px; padding: 14px 20px; margin-bottom: 24px; }
        .tracking-box .t-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; color: #9ca3af; margin-bottom: 4px; }
        .tracking-box .t-id { font-family: monospace; font-size: 18px; font-weight: 500; color: var(--navy); }
        .success-actions { display: flex; flex-direction: column; gap: 10px; }
        .btn-primary-sm { height: 42px; padding: 0 24px; background: var(--navy); color: #fff; border: none; border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 13.5px; font-weight: 500; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; transition: background 0.15s; }
        .btn-primary-sm:hover { background: #122848; }
        .btn-ghost { height: 42px; padding: 0 24px; background: transparent; color: var(--muted); border: 1.5px solid var(--border); border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 13.5px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; transition: border-color 0.15s; }
        .tips-list { display: flex; flex-direction: column; gap: 12px; }
        .tip { display: flex; gap: 10px; align-items: flex-start; }
        .tip .tip-icon { font-size: 16px; flex-shrink: 0; margin-top: 1px; }
        .tip .tip-text { font-size: 13px; color: var(--muted); font-weight: 300; line-height: 1.5; }
        .tip .tip-text strong { color: var(--text); font-weight: 500; }

        /* ── Map styles ─────────────────────────────────────── */
        .map-section { margin-bottom: 18px; }
        .map-section label { display: block; font-size: 12px; font-weight: 500; color: #374151; margin-bottom: 6px; }
        .map-section .map-hint { font-size: 12px; color: var(--muted); font-weight: 300; margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }

        /* Search bar */
        .map-search-row { display: flex; gap: 8px; margin-bottom: 8px; position: relative; }
        .map-search-row input { flex: 1; height: 40px; padding: 0 14px; border: 1.5px solid var(--border); border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 13.5px; color: var(--text); outline: none; transition: border-color 0.2s, box-shadow 0.2s; }
        .map-search-row input:focus { border-color: var(--navy); box-shadow: 0 0 0 3px rgba(11,31,58,0.07); }
        .map-search-row input::placeholder { color: #9ca3af; }
        .btn-search-loc { height: 40px; padding: 0 18px; background: var(--navy); color: #fff; border: none; border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 13px; font-weight: 500; cursor: pointer; white-space: nowrap; transition: background 0.15s; display: flex; align-items: center; gap: 6px; }
        .btn-search-loc:hover { background: #122848; }
        .btn-search-loc:disabled { background: #9ca3af; cursor: not-allowed; }
        .btn-my-loc { height: 40px; padding: 0 14px; background: #f0f4f9; color: var(--navy); border: 1.5px solid var(--border); border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 13px; cursor: pointer; white-space: nowrap; transition: background 0.15s; display: flex; align-items: center; gap: 5px; }
        .btn-my-loc:hover { background: #e2eaf4; }

        /* Autocomplete dropdown */
        .search-results { position: absolute; top: 44px; left: 0; right: 0; background: var(--white); border: 1.5px solid var(--border); border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,0.12); z-index: 9999; max-height: 220px; overflow-y: auto; display: none; }
        .search-results.open { display: block; }
        .search-result-item { padding: 10px 14px; font-size: 13px; color: var(--text); cursor: pointer; border-bottom: 1px solid #f3f4f6; display: flex; align-items: flex-start; gap: 8px; transition: background 0.1s; }
        .search-result-item:last-child { border-bottom: none; }
        .search-result-item:hover { background: #f0f4f9; }
        .search-result-item .sri-icon { font-size: 15px; flex-shrink: 0; margin-top: 1px; }
        .search-result-item .sri-name { font-weight: 500; color: var(--navy); line-height: 1.3; }
        .search-result-item .sri-detail { font-size: 11.5px; color: var(--muted); margin-top: 1px; }
        .search-no-results { padding: 14px; font-size: 13px; color: var(--muted); text-align: center; }
        .search-loading { padding: 14px; font-size: 13px; color: var(--muted); text-align: center; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .search-error { padding: 14px; font-size: 13px; color: #b91c1c; text-align: center; }

        /* Spinner */
        @keyframes spin { to { transform: rotate(360deg); } }
        .spinner { width: 14px; height: 14px; border: 2px solid #e5e7eb; border-top-color: var(--navy); border-radius: 50%; animation: spin 0.6s linear infinite; }

        #complaint-map { width: 100%; height: 280px; border-radius: 10px; border: 1.5px solid var(--border); z-index: 1; cursor: crosshair; }
        .map-coords { display: flex; gap: 10px; margin-top: 8px; }
        .map-coords input { flex: 1; height: 36px; padding: 0 10px; border: 1.5px solid var(--border); border-radius: 7px; font-size: 12px; font-family: monospace; color: var(--muted); background: #f9fafb; }
        .map-pin-info { margin-top: 8px; font-size: 12px; color: #15803d; font-weight: 500; display: none; }
        .map-pin-info.visible { display: flex; align-items: center; gap: 5px; }
        .btn-clear-pin { font-size: 11px; color: #ef4444; background: none; border: none; cursor: pointer; margin-left: auto; padding: 0; font-family: 'DM Sans', sans-serif; }
        /* ─────────────────────────────────────────────────── */

        @keyframes shake { 0%,100%{transform:translateX(0)} 25%{transform:translateX(-5px)} 75%{transform:translateX(5px)} }
        @keyframes modalIn { from { opacity:0; transform:scale(0.95); } to { opacity:1; transform:scale(1); } }
        @media (max-width: 960px) { .form-grid { grid-template-columns: 1fr; } }
        @media (max-width: 700px) { .sidebar { display: none; } .main { padding: 20px 16px; } .cat-grid { grid-template-columns: repeat(2,1fr); } }
    </style>
</head>
<body>
<nav>
    <a class="nav-brand" href="dashboard.php"><span style="font-size:18px">🏛</span><span>Citizen Complaint Portal</span></a>
    <div class="nav-right">
        <a href="dashboard.php">Dashboard</a>
        <a href="submit_complaint.php" class="active">Submit</a>
        <a href="view_complaints.php">My Complaints</a>
        <a href="#" onclick="showLogoutModal('logout.php')">Sign out</a>
    </div>
</nav>
<div class="page">
    <aside class="sidebar">
        <div class="sidebar-label">Menu</div>
        <a href="dashboard.php"><span class="si">📊</span> Dashboard</a>
        <div class="sidebar-sep"></div>
        <div class="sidebar-label">Complaints</div>
        <a href="submit_complaint.php" class="active"><span class="si">✏️</span> Submit complaint</a>
        <a href="view_complaints.php"><span class="si">📋</span> My complaints</a>
        <div class="sidebar-sep"></div>
        <a href="#" onclick="showLogoutModal('logout.php')"><span class="si">🚪</span> Sign out</a>
    </aside>
    <main class="main">
        <div class="page-header">
            <h1>Submit a complaint</h1>
            <p>Report a public service issue to local government authorities</p>
        </div>

        <?php if ($success): ?>
        <div class="success-card">
            <div class="success-icon">✅</div>
            <h2>Complaint submitted!</h2>
            <p>Your complaint has been received and assigned a unique tracking ID. Use it to monitor progress.</p>
            <div class="tracking-box">
                <div class="t-label">Your tracking ID</div>
                <div class="t-id"><?php echo htmlspecialchars($tracking_show); ?></div>
            </div>
            <div class="success-actions">
                <a href="view_complaints.php" class="btn-primary-sm">View my complaints</a>
                <a href="submit_complaint.php" class="btn-ghost">Submit another</a>
            </div>
        </div>
        <?php else: ?>
        <div class="form-grid">
            <div>
                <?php if (!empty($message)): ?>
                <div class="alert alert-error">⚠ <?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
                <form method="POST" enctype="multipart/form-data" id="complaintForm">
                    <?php echo csrf_field(); ?>

                    <!-- Hidden lat/lng fields populated by the map -->
                    <input type="hidden" name="latitude"  id="lat_input">
                    <input type="hidden" name="longitude" id="lng_input">

                    <div class="field" style="margin-bottom:22px">
                        <label>Category <span class="req">*</span></label>
                        <div class="cat-grid">
                            <?php
                            $icons = ['Road Issues'=>'🛣️','Water Supply'=>'💧','Electricity'=>'⚡','Sanitation'=>'🗑️','Public Safety'=>'🚨','Other'=>'📝'];
                            foreach ($categories as $cat):
                                $checked = ($old['category'] ?? '') === $cat ? 'checked' : '';
                            ?>
                            <div>
                                <input type="radio" name="category" id="cat_<?php echo md5($cat); ?>" value="<?php echo htmlspecialchars($cat); ?>" class="cat-option" <?php echo $checked; ?> required>
                                <label for="cat_<?php echo md5($cat); ?>" class="cat-label">
                                    <span class="cat-icon"><?php echo $icons[$cat]; ?></span>
                                    <span class="cat-name"><?php echo $cat; ?></span>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="field">
                        <label for="title">Complaint title <span class="req">*</span></label>
                        <input type="text" id="title" name="title" placeholder="e.g. Large pothole on Ward 5 main road" value="<?php echo htmlspecialchars($old['title'] ?? ''); ?>" required>
                    </div>

                    <div class="field">
                        <label for="description">Description <span class="req">*</span></label>
                        <textarea id="description" name="description" rows="5" placeholder="Describe the issue in detail — location, how long it has been a problem, who is affected…" required><?php echo htmlspecialchars($old['description'] ?? ''); ?></textarea>
                    </div>

                    <!-- ── Location picker map ───────────────────────── -->
                    <div class="map-section">
                        <label>📍 Pin the location <span style="font-weight:300;color:var(--muted)">(optional but recommended)</span></label>
                        <div class="map-hint">
                            <span>🔍</span> Search for a place, or click directly on the map to drop a pin
                        </div>

                        <!-- Search bar -->
                        <div class="map-search-row" id="searchWrapper">
                            <input
                                type="text"
                                id="locationSearch"
                                placeholder="Search for a street, landmark or area in Nepal…"
                                autocomplete="off"
                                onkeydown="searchKeydown(event)"
                                oninput="onSearchInput()"
                            >
                            <button type="button" class="btn-search-loc" id="searchBtn" onclick="searchLocation()">
                                🔍 Search
                            </button>
                            <button type="button" class="btn-my-loc" onclick="useMyLocation()" title="Use my current GPS location">
                                📡 My location
                            </button>
                            <!-- Dropdown results -->
                            <div class="search-results" id="searchResults"></div>
                        </div>

                        <div id="complaint-map"></div>
                        <div class="map-coords">
                            <input type="text" id="lat_display"  placeholder="Latitude"  readonly>
                            <input type="text" id="lng_display"  placeholder="Longitude" readonly>
                        </div>
                        <div class="map-pin-info" id="pin-info">
                            📍 Location pinned successfully
                            <button type="button" class="btn-clear-pin" onclick="clearPin()">✕ Remove pin</button>
                        </div>
                    </div>
                    <!-- ─────────────────────────────────────────────── -->

                    <div class="field">
                        <label>Evidence <span style="font-weight:300;color:var(--muted)">(optional — JPG, PNG or PDF, max 5 MB)</span></label>
                        <div class="file-drop" id="fileDrop">
                            <input type="file" name="evidence" id="evidence" accept=".jpg,.jpeg,.png,.pdf" onchange="fileChosen(this)">
                            <div class="fd-icon">📎</div>
                            <div class="fd-text"><strong>Click to upload</strong> or drag and drop</div>
                            <div class="fd-types">JPG, PNG or PDF — max 5 MB</div>
                        </div>
                        <div class="file-selected" id="fileSelected">📄 <span id="fileName"></span></div>
                    </div>

                    <button type="button" class="btn-submit" onclick="showPreview()">Preview complaint →</button>
                </form>
                <a href="dashboard.php" class="btn-back">← Back to dashboard</a>
            </div>

            <div class="panel">
                <div class="panel-header"><h3>Tips for a good complaint</h3></div>
                <div class="panel-body">
                    <div class="tips-list">
                        <div class="tip"><span class="tip-icon">📍</span><div class="tip-text"><strong>Pin the location</strong> — use the map to mark exactly where the issue is.</div></div>
                        <div class="tip"><span class="tip-icon">📸</span><div class="tip-text"><strong>Attach evidence</strong> — a photo of the issue helps authorities assess it faster.</div></div>
                        <div class="tip"><span class="tip-icon">📝</span><div class="tip-text"><strong>Be specific</strong> — describe how long the problem has existed and who is affected.</div></div>
                        <div class="tip"><span class="tip-icon">🔖</span><div class="tip-text"><strong>Save your tracking ID</strong> — after submission, use it to check the status at any time.</div></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Preview overlay -->
        <div id="previewOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;padding:20px;">
            <div style="background:#fff;border-radius:16px;width:100%;max-width:560px;max-height:90vh;overflow-y:auto;box-shadow:0 16px 60px rgba(0,0,0,0.2);animation:modalIn 0.2s ease;">
                <div style="padding:22px 26px 18px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;">
                    <div>
                        <div style="font-size:16px;font-weight:500;color:#0b1f3a;">Review your complaint</div>
                        <div style="font-size:12px;color:#9ca3af;font-weight:300;margin-top:2px;">Check everything looks right before submitting</div>
                    </div>
                    <button onclick="closePreview()" style="background:none;border:none;font-size:20px;color:#9ca3af;cursor:pointer;">✕</button>
                </div>
                <div style="padding:22px 26px;">
                    <div style="margin-bottom:18px;">
                        <div style="font-size:11px;font-weight:500;letter-spacing:0.08em;text-transform:uppercase;color:#9ca3af;margin-bottom:6px;">Category</div>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <span id="prev-cat-icon" style="font-size:20px;"></span>
                            <span id="prev-cat" style="font-size:14px;font-weight:500;color:#0b1f3a;background:#f0f4f9;padding:4px 12px;border-radius:8px;"></span>
                        </div>
                    </div>
                    <div style="margin-bottom:18px;">
                        <div style="font-size:11px;font-weight:500;letter-spacing:0.08em;text-transform:uppercase;color:#9ca3af;margin-bottom:6px;">Title</div>
                        <div id="prev-title" style="font-size:15px;font-weight:500;color:#111827;"></div>
                    </div>
                    <div style="margin-bottom:18px;">
                        <div style="font-size:11px;font-weight:500;letter-spacing:0.08em;text-transform:uppercase;color:#9ca3af;margin-bottom:6px;">Description</div>
                        <div id="prev-desc" style="font-size:13.5px;color:#374151;font-weight:300;line-height:1.7;white-space:pre-wrap;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:12px 14px;"></div>
                    </div>
                    <div style="margin-bottom:18px;">
                        <div style="font-size:11px;font-weight:500;letter-spacing:0.08em;text-transform:uppercase;color:#9ca3af;margin-bottom:6px;">Location</div>
                        <div id="prev-location" style="font-size:13px;color:#6b7280;"></div>
                    </div>
                    <div style="margin-bottom:24px;">
                        <div style="font-size:11px;font-weight:500;letter-spacing:0.08em;text-transform:uppercase;color:#9ca3af;margin-bottom:6px;">Evidence</div>
                        <div id="prev-evidence" style="font-size:13px;color:#6b7280;"></div>
                    </div>
                    <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:11px 14px;font-size:12.5px;color:#92400e;margin-bottom:20px;">
                        ⚠ Once submitted, your complaint cannot be edited. Make sure all details are correct.
                    </div>
                    <div style="display:flex;gap:10px;">
                        <button onclick="closePreview()" style="flex:1;height:46px;background:transparent;color:#6b7280;border:1.5px solid #e5e7eb;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:14px;cursor:pointer;">← Edit complaint</button>
                        <button onclick="submitForm()" style="flex:2;height:46px;background:#0b1f3a;color:#fff;border:none;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:14px;font-weight:500;cursor:pointer;">✓ Confirm &amp; submit</button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>
</div>

<!-- Logout modal -->
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

<!-- Leaflet.js -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
/* ── Leaflet map — location picker with search ──────────── */
const defaultLat  = 27.7172;
const defaultLng  = 85.3240;
const defaultZoom = 13;

const map = L.map('complaint-map', { zoomControl: true }).setView([defaultLat, defaultLng], defaultZoom);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    maxZoom: 19
}).addTo(map);

let marker      = null;
let searchTimer = null;

// Custom navy pin icon
const pinIcon = L.divIcon({
    className: '',
    html: `<div style="
        width:32px; height:32px;
        background:#0b1f3a;
        border:3px solid #fff;
        border-radius:50% 50% 50% 0;
        transform:rotate(-45deg);
        box-shadow:0 2px 8px rgba(0,0,0,0.35);
    "></div>`,
    iconSize:   [32, 32],
    iconAnchor: [16, 32],
    popupAnchor:[0, -34]
});

// Click on map to drop pin
map.on('click', function(e) {
    placePin(e.latlng.lat, e.latlng.lng);
    closeDropdown();
});

function placePin(lat, lng, label) {
    if (marker) map.removeLayer(marker);
    marker = L.marker([lat, lng], { icon: pinIcon, draggable: true }).addTo(map);
    if (label) marker.bindPopup(`<b style="font-family:'DM Sans',sans-serif;font-size:13px;">${label}</b>`).openPopup();
    marker.on('dragend', function(e) {
        const pos = e.target.getLatLng();
        updateCoords(pos.lat, pos.lng);
    });
    updateCoords(lat, lng);
}

function updateCoords(lat, lng) {
    const latR = parseFloat(lat).toFixed(6);
    const lngR = parseFloat(lng).toFixed(6);
    document.getElementById('lat_input').value   = latR;
    document.getElementById('lng_input').value   = lngR;
    document.getElementById('lat_display').value = latR;
    document.getElementById('lng_display').value = lngR;
    document.getElementById('pin-info').classList.add('visible');
}

function clearPin() {
    if (marker) { map.removeLayer(marker); marker = null; }
    ['lat_input','lng_input','lat_display','lng_display'].forEach(id => {
        document.getElementById(id).value = '';
    });
    document.getElementById('pin-info').classList.remove('visible');
}

/* ── Geocoding search (Nominatim / OpenStreetMap) ────────── */
const searchInput   = document.getElementById('locationSearch');
const searchResults = document.getElementById('searchResults');
const searchBtn     = document.getElementById('searchBtn');

// Typing debounce — search automatically after 500ms pause
function onSearchInput() {
    clearTimeout(searchTimer);
    const q = searchInput.value.trim();
    if (q.length < 3) { closeDropdown(); return; }
    searchTimer = setTimeout(() => geocode(q), 500);
}

// Enter key or button click
function searchKeydown(e) {
    if (e.key === 'Enter') { e.preventDefault(); searchLocation(); }
    if (e.key === 'Escape') closeDropdown();
    // Arrow key navigation
    if (e.key === 'ArrowDown') { e.preventDefault(); focusResult(1); }
    if (e.key === 'ArrowUp')   { e.preventDefault(); focusResult(-1); }
}
function searchLocation() {
    const q = searchInput.value.trim();
    if (!q) return;
    geocode(q);
}

function geocode(query) {
    showLoading();
    searchBtn.disabled = true;

    // Bias results toward Nepal using countrycodes=np
    const url = `https://nominatim.openstreetmap.org/search?` +
        `q=${encodeURIComponent(query)}` +
        `&countrycodes=np` +
        `&format=json` +
        `&addressdetails=1` +
        `&limit=6` +
        `&accept-language=en`;

    fetch(url, {
        headers: { 'Accept-Language': 'en' }
    })
    .then(r => r.json())
    .then(data => {
        searchBtn.disabled = false;
        if (!data || data.length === 0) {
            showNoResults(query);
        } else {
            showResults(data);
        }
    })
    .catch(() => {
        searchBtn.disabled = false;
        showError();
    });
}

function showLoading() {
    searchResults.innerHTML = `
        <div class="search-loading">
            <div class="spinner"></div> Searching…
        </div>`;
    openDropdown();
}

function showNoResults(query) {
    searchResults.innerHTML = `
        <div class="search-no-results">
            No results found for "<strong>${escHtml(query)}</strong>" in Nepal.<br>
            <span style="font-size:11.5px;">Try a different spelling or click directly on the map.</span>
        </div>`;
    openDropdown();
}

function showError() {
    searchResults.innerHTML = `
        <div class="search-error">
            ⚠ Search unavailable. Please click directly on the map to pin your location.
        </div>`;
    openDropdown();
}

function showResults(data) {
    const typeIcons = {
        'road': '🛣️', 'residential': '🏠', 'highway': '🛣️',
        'school': '🏫', 'hospital': '🏥', 'place': '📍',
        'water': '💧', 'park': '🌳', 'office': '🏢',
        'shop': '🏪', 'amenity': '📍', 'building': '🏗️',
    };

    const html = data.map((item, i) => {
        const name    = item.display_name.split(',')[0];
        const detail  = item.display_name.split(',').slice(1, 4).join(',').trim();
        const type    = item.type || item.class || '';
        const icon    = typeIcons[type] || '📍';
        return `
            <div class="search-result-item"
                 tabindex="0"
                 onclick="selectResult(${item.lat}, ${item.lon}, '${escAttr(name)}')"
                 onkeydown="if(event.key==='Enter') selectResult(${item.lat}, ${item.lon}, '${escAttr(name)}')">
                <span class="sri-icon">${icon}</span>
                <div>
                    <div class="sri-name">${escHtml(name)}</div>
                    <div class="sri-detail">${escHtml(detail)}</div>
                </div>
            </div>`;
    }).join('');

    searchResults.innerHTML = html;
    openDropdown();
}

function selectResult(lat, lng, label) {
    map.flyTo([lat, lng], 16, { duration: 1 });
    placePin(lat, lng, label);
    searchInput.value = label;
    closeDropdown();
}

function focusResult(direction) {
    const items = searchResults.querySelectorAll('.search-result-item');
    if (!items.length) return;
    const focused = document.activeElement;
    const arr     = Array.from(items);
    const idx     = arr.indexOf(focused);
    const next    = idx + direction;
    if (next >= 0 && next < arr.length) arr[next].focus();
    else if (next < 0) searchInput.focus();
}

function openDropdown()  { searchResults.classList.add('open'); }
function closeDropdown() { searchResults.classList.remove('open'); }

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    if (!document.getElementById('searchWrapper').contains(e.target)) {
        closeDropdown();
    }
});

/* ── Use my current GPS location ─────────────────────────── */
function useMyLocation() {
    if (!navigator.geolocation) {
        alert('Geolocation is not supported by your browser.');
        return;
    }
    const btn = document.querySelector('.btn-my-loc');
    btn.textContent = '⏳ Locating…';
    btn.disabled = true;

    navigator.geolocation.getCurrentPosition(
        function(pos) {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;
            map.flyTo([lat, lng], 16, { duration: 1 });
            placePin(lat, lng, 'My current location');
            btn.textContent = '📡 My location';
            btn.disabled = false;
        },
        function(err) {
            btn.textContent = '📡 My location';
            btn.disabled = false;
            if (err.code === 1) {
                alert('Location access was denied. Please allow location access in your browser settings, or pin your location manually on the map.');
            } else {
                alert('Could not determine your location. Please pin manually on the map.');
            }
        },
        { timeout: 8000, maximumAge: 60000 }
    );
}

/* ── Helpers ─────────────────────────────────────────────── */
function escHtml(str) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(String(str || '')));
    return d.innerHTML;
}
function escAttr(str) {
    return String(str || '').replace(/'/g, "\\'");
}
/* ──────────────────────────────────────────────────────────── */

const catIcons = {'Road Issues':'🛣️','Water Supply':'💧','Electricity':'⚡','Sanitation':'🗑️','Public Safety':'🚨','Other':'📝'};

function showPreview() {
    const category  = document.querySelector('input[name="category"]:checked');
    const title     = document.getElementById('title').value.trim();
    const desc      = document.getElementById('description').value.trim();
    const fileInput = document.getElementById('evidence');
    const lat       = document.getElementById('lat_input').value;
    const lng       = document.getElementById('lng_input').value;

    if (!category) { alert('Please select a category.'); return; }
    if (!title)    { document.getElementById('title').style.borderColor='#ef4444'; document.getElementById('title').focus(); return; }
    if (!desc)     { document.getElementById('description').style.borderColor='#ef4444'; document.getElementById('description').focus(); return; }

    document.getElementById('title').style.borderColor       = '';
    document.getElementById('description').style.borderColor = '';

    document.getElementById('prev-cat-icon').textContent = catIcons[category.value] || '📝';
    document.getElementById('prev-cat').textContent      = category.value;
    document.getElementById('prev-title').textContent    = title;
    document.getElementById('prev-desc').textContent     = desc;

    const locEl = document.getElementById('prev-location');
    locEl.textContent = lat && lng ? `📍 ${lat}, ${lng}` : 'No location pinned';
    locEl.style.color = lat && lng ? '#15803d' : '#9ca3af';

    const ev = document.getElementById('prev-evidence');
    if (fileInput.files && fileInput.files[0]) {
        ev.innerHTML = '📎 <strong>' + fileInput.files[0].name + '</strong> (' + (fileInput.files[0].size/1024).toFixed(1) + ' KB)';
    } else { ev.textContent = 'No file attached'; }

    document.getElementById('previewOverlay').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closePreview() { document.getElementById('previewOverlay').style.display='none'; document.body.style.overflow=''; }
function submitForm()   { document.getElementById('complaintForm').submit(); }
document.getElementById('previewOverlay').addEventListener('click', function(e) { if(e.target===this) closePreview(); });

function fileChosen(input) {
    const sel  = document.getElementById('fileSelected');
    const name = document.getElementById('fileName');
    if (input.files && input.files[0]) { name.textContent = input.files[0].name; sel.style.display = 'block'; }
}
const drop = document.getElementById('fileDrop');
if (drop) {
    drop.addEventListener('dragover', e => { e.preventDefault(); drop.classList.add('dragover'); });
    drop.addEventListener('dragleave', () => drop.classList.remove('dragover'));
    drop.addEventListener('drop', e => { e.preventDefault(); drop.classList.remove('dragover'); });
}
function showLogoutModal(href) { document.getElementById('logoutConfirmBtn').href=href; document.getElementById('logoutModal').style.display='flex'; }
function closeLogoutModal()    { document.getElementById('logoutModal').style.display='none'; }
document.getElementById('logoutModal').addEventListener('click', function(e) { if(e.target===this) closeLogoutModal(); });
</script>
</body>
</html>