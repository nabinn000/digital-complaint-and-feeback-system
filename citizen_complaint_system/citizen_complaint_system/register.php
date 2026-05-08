<?php
session_start();

if (isset($_SESSION["user_id"])) {
    header("Location: " . ($_SESSION["role"] == "admin" ? "admin/admin_dashboard.php" : "dashboard.php"));
    exit();
}

include("includes/db.php");

// CSRF helpers (inline)
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

$message = "";
$success = false;
$old = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    csrf_verify();

    $old = [
        'full_name' => trim($_POST["full_name"] ?? ''),
        'email'     => trim($_POST["email"] ?? ''),
        'phone'     => trim($_POST["phone"] ?? ''),
        'address'   => trim($_POST["address"] ?? ''),
    ];
    $password = trim($_POST["password"] ?? '');
    $confirm  = trim($_POST["confirm"] ?? '');

    // Length guard — prevent oversized inputs before any other check
    if (strlen($old['full_name']) > 100 || strlen($old['email']) > 100 || strlen($old['address']) > 500 || strlen($old['phone']) > 20) {
        $message = "One or more fields exceed the maximum allowed length.";
    } elseif (empty($old['full_name']) || empty($old['email']) || empty($old['phone']) || empty($old['address']) || empty($password) || empty($confirm)) {
        $message = "Please fill in all fields and pin your address on the map.";
    } elseif (!preg_match('/^[\p{L} \'\-\.]+$/u', $old['full_name'])) {
        $message = "Please enter a valid full name (letters only).";
    } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
    } elseif (!preg_match('/^[0-9+\-\s()]{7,20}$/', $old['phone'])) {
        $message = "Please enter a valid phone number.";
    } elseif (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $message = "Password must be at least 8 characters and include one uppercase letter and one number.";
    } elseif ($password !== $confirm) {
        $message = "Passwords do not match.";
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $old['email']);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
            $message = "That email address is already registered.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, address, password) VALUES (?,?,?,?,?)");
            $stmt->bind_param("sssss", $old['full_name'], $old['email'], $old['phone'], $old['address'], $hashed);
            if ($stmt->execute()) { $success = true; }
            else { $message = "Something went wrong. Please try again."; }
        }
    }
}

function val($old, $key) { return htmlspecialchars($old[$key] ?? ''); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — Citizen Complaint Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', sans-serif; background: #eef2f7; min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 32px 20px; }
        .brand { display: flex; align-items: center; gap: 10px; margin-bottom: 24px; animation: fadeUp 0.5s ease both; }
        .brand-icon { width: 40px; height: 40px; background: #0b1f3a; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .brand-text { font-size: 13px; font-weight: 500; color: #0b1f3a; line-height: 1.4; }
        .brand-text span { display: block; font-size: 11px; font-weight: 300; color: #6b7280; }
        .card { background: #fff; border-radius: 14px; padding: 36px 40px; width: 100%; max-width: 460px; box-shadow: 0 2px 16px rgba(0,0,0,0.07); animation: fadeUp 0.5s 0.08s ease both; }
        .card-title { font-size: 20px; font-weight: 500; color: #0b1f3a; margin-bottom: 4px; }
        .card-sub { font-size: 13px; color: #6b7280; font-weight: 300; margin-bottom: 28px; }
        .alert { border-radius: 8px; padding: 11px 14px; font-size: 13px; margin-bottom: 20px; }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; animation: shake 0.35s ease; }
        .alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 0 16px; }
        .field { margin-bottom: 16px; }
        .field label { display: block; font-size: 12px; font-weight: 500; color: #374151; margin-bottom: 6px; }
        .field input, .field textarea { width: 100%; padding: 0 14px; border: 1.5px solid #e5e7eb; border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 14px; color: #111827; outline: none; transition: border-color 0.2s, box-shadow 0.2s; background: #fff; }
        .field input { height: 44px; }
        .field textarea { height: 88px; padding: 11px 14px; resize: vertical; }
        .field input:focus, .field textarea:focus { border-color: #0b1f3a; box-shadow: 0 0 0 3px rgba(11,31,58,0.07); }
        .pw-wrap { position: relative; }
        .pw-wrap input { padding-right: 40px; }
        .toggle-pw { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 14px; color: #9ca3af; padding: 0; }
        .strength-bar { height: 4px; border-radius: 4px; margin-top: 6px; background: #e5e7eb; overflow: hidden; }
        .strength-fill { height: 100%; width: 0; border-radius: 4px; transition: width 0.3s, background 0.3s; }
        .strength-label { font-size: 11px; color: #9ca3af; margin-top: 4px; min-height: 16px; }
        .match-hint { font-size: 11px; margin-top: 4px; min-height: 16px; }
        .field-hint { font-size: 11px; color: #9ca3af; margin-top: 4px; }
        .divider { height: 1px; background: #f3f4f6; margin: 4px 0 20px; }
        .btn { width: 100%; height: 46px; background: #0b1f3a; color: #fff; border: none; border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 14px; font-weight: 500; cursor: pointer; transition: background 0.2s; }
        .btn:hover { background: #122848; }
        .footer-link { margin-top: 20px; text-align: center; font-size: 13px; color: #6b7280; }
        .footer-link a { color: #0b1f3a; font-weight: 500; text-decoration: none; }
        .footer-link a:hover { text-decoration: underline; }
        .page-footer { margin-top: 20px; font-size: 11px; color: #9ca3af; text-align: center; }
        /* Address map picker */
        .map-section { margin-bottom: 16px; }
        .map-section label { display: block; font-size: 12px; font-weight: 500; color: #374151; margin-bottom: 6px; }
        .map-search-row { display: flex; gap: 8px; margin-bottom: 8px; position: relative; }
        .map-search-row input { flex: 1; height: 40px; padding: 0 14px; border: 1.5px solid #e5e7eb; border-radius: 8px; font-family: "DM Sans", sans-serif; font-size: 13.5px; color: #111827; outline: none; transition: border-color 0.2s; }
        .map-search-row input:focus { border-color: #0b1f3a; box-shadow: 0 0 0 3px rgba(11,31,58,0.07); }
        .btn-search { height: 40px; padding: 0 14px; background: #0b1f3a; color: #fff; border: none; border-radius: 8px; font-size: 13px; cursor: pointer; white-space: nowrap; transition: background 0.15s; }
        .btn-search:hover { background: #122848; }
        .btn-my-loc { height: 40px; padding: 0 12px; background: #fff; color: #374151; border: 1.5px solid #e5e7eb; border-radius: 8px; font-size: 12px; cursor: pointer; white-space: nowrap; transition: all 0.15s; }
        .btn-my-loc:hover { border-color: #0b1f3a; color: #0b1f3a; }
        #reg-map { width: 100%; height: 220px; border-radius: 8px; border: 1.5px solid #e5e7eb; z-index: 1; cursor: crosshair; }
        .search-results { display: none; position: absolute; top: 44px; left: 0; right: 0; background: #fff; border: 1.5px solid #e5e7eb; border-radius: 8px; box-shadow: 0 4px 16px rgba(0,0,0,0.1); z-index: 9999; max-height: 220px; overflow-y: auto; }
        .search-results.open { display: block; }
        .search-result-item { padding: 10px 14px; cursor: pointer; font-size: 13px; border-bottom: 1px solid #f3f4f6; display: flex; gap: 10px; align-items: flex-start; }
        .search-result-item:last-child { border-bottom: none; }
        .search-result-item:hover, .search-result-item:focus { background: #f0f4f9; outline: none; }
        .sri-name { font-weight: 500; color: #111827; }
        .sri-detail { font-size: 11.5px; color: #9ca3af; margin-top: 2px; }
        .search-msg { padding: 12px 14px; font-size: 13px; color: #9ca3af; text-align: center; }
        .map-pin-info { margin-top: 8px; font-size: 12px; color: #15803d; font-weight: 500; display: none; align-items: center; gap: 5px; }
        .map-pin-info.visible { display: flex; }
        .address-display { margin-top: 8px; font-size: 12px; color: #374151; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px 10px; display: none; line-height: 1.5; }
        .address-display.visible { display: block; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes shake { 0%,100% { transform: translateX(0); } 25% { transform: translateX(-5px); } 75% { transform: translateX(5px); } }
        @media (max-width: 480px) { .card { padding: 28px 24px; } .row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="brand">
        <div class="brand-icon">🏛</div>
        <div class="brand-text">Citizen Complaint Portal<span>Government of Nepal — Ministry of Public Services</span></div>
    </div>
    <div class="card">
        <h2 class="card-title">Create an account</h2>
        <p class="card-sub">Register to submit and track your complaints</p>
        <?php if ($success): ?>
            <div class="alert alert-success">✓ Account created! Redirecting to sign in…</div>
            <script>setTimeout(() => window.location.href = 'login.php', 2000);</script>
        <?php elseif (!empty($message)): ?>
            <div class="alert alert-error">⚠ <?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if (!$success): ?>
        <form method="POST" action="register.php" novalidate>
            <?php echo csrf_field(); ?>
            <div class="row">
                <div class="field">
                    <label for="full_name">Full name</label>
                    <input type="text" id="full_name" name="full_name" placeholder="Ram Bahadur" value="<?php echo val($old,'full_name'); ?>" required autocomplete="name" maxlength="100">
                </div>
                <div class="field">
                    <label for="phone">Phone number</label>
                    <input type="tel" id="phone" name="phone" placeholder="98XXXXXXXX" value="<?php echo val($old,'phone'); ?>" required autocomplete="tel" maxlength="20">
                </div>
            </div>
            <div class="field">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" placeholder="you@example.com" value="<?php echo val($old,'email'); ?>" required autocomplete="email" maxlength="100">
            </div>
            <!-- Address map picker -->
            <div class="map-section">
                <label>📍 Your address / location <span style="font-weight:300;color:#9ca3af">(search or click the map)</span></label>
                <div class="map-search-row" id="addrSearchWrapper">
                    <input type="text" id="addrSearch" placeholder="Search your ward, street or area…"
                           oninput="onAddrInput()" onkeydown="addrKeydown(event)" autocomplete="off">
                    <button type="button" class="btn-search" onclick="searchAddress()">Search</button>
                    <button type="button" class="btn-my-loc" onclick="useMyLoc()">📡 My location</button>
                    <div class="search-results" id="addrResults"></div>
                </div>
                <div id="reg-map"></div>
                <div class="map-pin-info" id="addr-pin-info">📍 Location pinned</div>
                <div class="address-display" id="addr-display"></div>
                <!-- Hidden fields submitted with the form -->
                <input type="hidden" name="address" id="addr_hidden">
            </div>
            <div class="divider"></div>
            <div class="row">
                <div class="field">
                    <label for="password">Password</label>
                    <div class="pw-wrap">
                        <input type="password" id="password" name="password" placeholder="Min. 8 characters" required autocomplete="new-password" maxlength="128">
                        <button type="button" class="toggle-pw" onclick="togglePw('password','eye1')"><span id="eye1">👁</span></button>
                    </div>
                    <div class="strength-bar"><div class="strength-fill" id="strength-fill"></div></div>
                    <div class="strength-label" id="strength-label"></div>
                </div>
                <div class="field">
                    <label for="confirm">Confirm password</label>
                    <div class="pw-wrap">
                        <input type="password" id="confirm" name="confirm" placeholder="Repeat password" required autocomplete="new-password" maxlength="128">
                        <button type="button" class="toggle-pw" onclick="togglePw('confirm','eye2')"><span id="eye2">👁</span></button>
                    </div>
                    <div class="match-hint" id="match-hint"></div>
                </div>
            </div>
            <button type="submit" class="btn">Create account</button>
        </form>
        <?php endif; ?>
        <div class="footer-link">Already have an account? <a href="login.php">Sign in</a></div>
    </div>
    <p class="page-footer">Official Government Portal &nbsp;·&nbsp; All Rights Reserved &copy; 2026</p>
    <script>
        function togglePw(inputId, eyeId) {
            const input = document.getElementById(inputId);
            const eye   = document.getElementById(eyeId);
            input.type  = input.type === 'password' ? 'text' : 'password';
            eye.textContent = input.type === 'password' ? '👁' : '🙈';
        }

        // Password strength meter
        document.getElementById('password').addEventListener('input', function () {
            const val = this.value;
            let score = 0;
            if (val.length >= 8)            score++;
            if (/[A-Z]/.test(val))          score++;
            if (/[0-9]/.test(val))          score++;
            if (/[^A-Za-z0-9]/.test(val))   score++;
            const fill  = document.getElementById('strength-fill');
            const label = document.getElementById('strength-label');
            const levels = [
                { w: '0%',   bg: '#e5e7eb', text: '' },
                { w: '33%',  bg: '#ef4444', text: 'Weak' },
                { w: '66%',  bg: '#f59e0b', text: 'Fair' },
                { w: '90%',  bg: '#3b82f6', text: 'Good' },
                { w: '100%', bg: '#22c55e', text: 'Strong' },
            ];
            const l = levels[score];
            fill.style.width      = l.w;
            fill.style.background = l.bg;
            label.textContent     = l.text;
            label.style.color     = l.bg;
            // Re-check confirm match whenever password changes
            checkMatch();
        });

        // Live confirm password match feedback
        function checkMatch() {
            const pw      = document.getElementById('password').value;
            const confirm = document.getElementById('confirm');
            const hint    = document.getElementById('match-hint');
            if (confirm.value.length === 0) {
                hint.textContent     = '';
                confirm.style.borderColor = '';
                return;
            }
            const match = confirm.value === pw;
            confirm.style.borderColor = match ? '#22c55e' : '#ef4444';
            hint.textContent          = match ? '✓ Passwords match' : '✗ Passwords do not match';
            hint.style.color          = match ? '#16a34a' : '#ef4444';
        }
        document.getElementById('confirm').addEventListener('input', checkMatch);
    </script>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
/* ── Register page address map picker ──────────────────── */
const REG_LAT = 27.7172, REG_LNG = 85.3240;
const regMap  = L.map('reg-map', { zoomControl: true }).setView([REG_LAT, REG_LNG], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    maxZoom: 19
}).addTo(regMap);

let regMarker = null, addrTimer = null;

const pinIcon = L.divIcon({
    className: '',
    html: `<div style="width:28px;height:28px;background:#0b1f3a;border:3px solid #fff;border-radius:50% 50% 50% 0;transform:rotate(-45deg);box-shadow:0 2px 8px rgba(0,0,0,0.35);"></div>`,
    iconSize: [28, 28], iconAnchor: [14, 28]
});

regMap.on('click', function(e) {
    placeRegPin(e.latlng.lat, e.latlng.lng);
    closeAddrDropdown();
    reverseGeocode(e.latlng.lat, e.latlng.lng);
});

function placeRegPin(lat, lng) {
    if (regMarker) regMap.removeLayer(regMarker);
    regMarker = L.marker([lat, lng], { icon: pinIcon, draggable: true }).addTo(regMap);
    regMarker.on('dragend', function(e) {
        const p = e.target.getLatLng();
        reverseGeocode(p.lat, p.lng);
    });
    document.getElementById('addr-pin-info').classList.add('visible');
}

function setAddress(text) {
    document.getElementById('addr_hidden').value  = text;
    document.getElementById('addr-display').textContent = text;
    document.getElementById('addr-display').classList.add('visible');
}

function reverseGeocode(lat, lng) {
    const url = `https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lng}&format=json&accept-language=en`;
    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (data && data.display_name) {
                const addr = data.display_name;
                document.getElementById('addrSearch').value = addr.split(',').slice(0, 3).join(',').trim();
                setAddress(addr);
            }
        })
        .catch(() => {});
}

/* ── Search ───────────────────────────────────────────── */
function onAddrInput() {
    clearTimeout(addrTimer);
    const q = document.getElementById('addrSearch').value.trim();
    if (q.length < 3) { closeAddrDropdown(); return; }
    addrTimer = setTimeout(() => geocodeAddr(q), 500);
}
function addrKeydown(e) {
    if (e.key === 'Enter') { e.preventDefault(); searchAddress(); }
    if (e.key === 'Escape') closeAddrDropdown();
}
function searchAddress() {
    const q = document.getElementById('addrSearch').value.trim();
    if (q) geocodeAddr(q);
}
function geocodeAddr(query) {
    const res = document.getElementById('addrResults');
    res.innerHTML = `<div class="search-msg">Searching…</div>`;
    openAddrDropdown();
    const url = `https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(query)}&countrycodes=np&format=json&addressdetails=1&limit=6&accept-language=en`;
    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (!data || data.length === 0) {
                res.innerHTML = `<div class="search-msg">No results. Try a different term or click the map.</div>`;
            } else {
                res.innerHTML = data.map(item => {
                    const name   = escH(item.display_name.split(',')[0]);
                    const detail = escH(item.display_name.split(',').slice(1,3).join(',').trim());
                    return `<div class="search-result-item" tabindex="0"
                        onclick="selectAddr(${item.lat},${item.lon},'${escA(item.display_name)}')"
                        onkeydown="if(event.key==='Enter') selectAddr(${item.lat},${item.lon},'${escA(item.display_name)}')">
                        <div><div class="sri-name">${name}</div><div class="sri-detail">${detail}</div></div>
                    </div>`;
                }).join('');
            }
            openAddrDropdown();
        })
        .catch(() => {
            res.innerHTML = `<div class="search-msg">Search unavailable. Click the map to pin.</div>`;
        });
}
function selectAddr(lat, lng, label) {
    regMap.flyTo([lat, lng], 16, { duration: 1 });
    placeRegPin(lat, lng);
    document.getElementById('addrSearch').value = label.split(',').slice(0, 3).join(',').trim();
    setAddress(label);
    closeAddrDropdown();
}
function openAddrDropdown()  { document.getElementById('addrResults').classList.add('open'); }
function closeAddrDropdown() { document.getElementById('addrResults').classList.remove('open'); }
document.addEventListener('click', function(e) {
    if (!document.getElementById('addrSearchWrapper').contains(e.target)) closeAddrDropdown();
});

/* ── GPS ─────────────────────────────────────────────── */
function useMyLoc() {
    if (!navigator.geolocation) { alert('Geolocation not supported by your browser.'); return; }
    const btn = document.querySelector('.btn-my-loc');
    btn.textContent = '⏳ Locating…'; btn.disabled = true;
    navigator.geolocation.getCurrentPosition(
        function(pos) {
            const lat = pos.coords.latitude, lng = pos.coords.longitude;
            regMap.flyTo([lat, lng], 16, { duration: 1 });
            placeRegPin(lat, lng);
            reverseGeocode(lat, lng);
            btn.textContent = '📡 My location'; btn.disabled = false;
        },
        function(err) {
            btn.textContent = '📡 My location'; btn.disabled = false;
            alert('Could not get your location. Please pin it manually on the map.');
        },
        { timeout: 10000 }
    );
}

function escH(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function escA(s) { return String(s).replace(/'/g,"\'").replace(/"/g,'&quot;'); }
</script>
</body>
</html>