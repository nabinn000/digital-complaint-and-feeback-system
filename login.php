<?php
// ── Gmail SMTP Configuration ─────────────────────────────────────────────────
// 1. Use a Gmail account dedicated to the system (not your personal account)
// 2. Enable 2-Step Verification on that Gmail account
// 3. Go to: Google Account → Security → App Passwords
// 4. Generate an App Password for "Mail" → paste it as MAIL_PASS below
// 5. Never commit this file to a public repository

define('MAIL_HOST',     'smtp.gmail.com');
define('MAIL_PORT',     587);
define('MAIL_SECURE',   'tls');
define('MAIL_USER',     'n16105895@gmail.com');   // ← change this
define('MAIL_PASS',     'bzog rkhz zjiq jowq');    // ← paste 16-char App Password
define('MAIL_FROM',     'n16105895@gmail.com');   // ← same as MAIL_USER
define('MAIL_FROMNAME', 'Citizen Complaint Portal');