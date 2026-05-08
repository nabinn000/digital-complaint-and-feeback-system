<?php
// ── Email Notification Helper ─────────────────────────────────────────────────
// Requires: vendor/autoload.php (PHPMailer) + includes/email_config.php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/email_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

define('SITE_URL', 'http://localhost/citizen_complaint_system');

function sendEmail(string $to_email, string $to_name, string $subject, string $html_body): bool {
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USER;
        $mail->Password   = MAIL_PASS;
        $mail->SMTPSecure = MAIL_SECURE;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom(MAIL_FROM, MAIL_FROMNAME);
        $mail->addAddress($to_email, $to_name);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html_body;
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Email failed to ' . $to_email . ': ' . $e->getMessage());
        return false;
    }
}

function emailTemplate(string $heading, string $subheading, string $body_html, string $accent = '#0b1f3a'): string {
    $year = date('Y');
    return "<!DOCTYPE html>
<html lang='en'>
<head>
<meta charset='UTF-8'>
<meta name='viewport' content='width=device-width,initial-scale=1'>
<title>{$heading}</title>
</head>
<body style='margin:0;padding:0;background:#eef2f7;font-family:Arial,Helvetica,sans-serif;'>
<table width='100%' cellpadding='0' cellspacing='0' border='0' style='background:#eef2f7;padding:32px 16px;'>
<tr><td align='center'>
<table width='560' cellpadding='0' cellspacing='0' border='0'
       style='max-width:560px;width:100%;background:#ffffff;border-radius:16px;
              overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.10);'>

  <!-- Header -->
  <tr>
    <td style='background:linear-gradient(135deg,{$accent} 0%,#1a4a7a 100%);padding:0;'>
      <table width='100%' cellpadding='0' cellspacing='0' border='0'>
        <tr><td style='background:rgba(255,255,255,0.08);height:4px;font-size:0;line-height:0;'>&nbsp;</td></tr>
      </table>
      <table width='100%' cellpadding='0' cellspacing='0' border='0'>
        <tr>
          <td style='padding:28px 36px 24px;'>
            <table cellpadding='0' cellspacing='0' border='0'>
              <tr>
                <td style='font-size:28px;line-height:1;padding-right:10px;vertical-align:middle;'>&#127963;</td>
                <td style='vertical-align:middle;'>
                  <div style='color:#ffffff;font-size:15px;font-weight:700;letter-spacing:0.03em;'>Citizen Complaint Portal</div>
                  <div style='color:rgba(255,255,255,0.65);font-size:11px;margin-top:2px;letter-spacing:0.05em;text-transform:uppercase;'>Government of Nepal</div>
                </td>
              </tr>
            </table>
            <table width='100%' cellpadding='0' cellspacing='0' border='0'>
              <tr><td style='height:20px;'></td></tr>
              <tr><td style='background:rgba(255,255,255,0.15);height:1px;font-size:0;'></td></tr>
              <tr><td style='height:20px;'></td></tr>
            </table>
            <div style='color:#ffffff;font-size:22px;font-weight:700;line-height:1.3;'>{$heading}</div>
            <div style='color:rgba(255,255,255,0.75);font-size:13px;margin-top:6px;'>{$subheading}</div>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- Body -->
  <tr>
    <td style='padding:32px 36px;'>
      {$body_html}
    </td>
  </tr>

  <!-- Footer -->
  <tr>
    <td style='background:#f8fafc;border-top:1px solid #e5e7eb;padding:20px 36px;'>
      <table width='100%' cellpadding='0' cellspacing='0' border='0'>
        <tr>
          <td style='text-align:center;'>
            <p style='margin:0 0 6px;font-size:12px;color:#9ca3af;'>
              This is a secure, automated message from the Citizen Complaint Portal.
            </p>
            <p style='margin:0;font-size:11px;color:#c4c9d4;'>
              &copy; {$year} Government of Nepal &middot; Do not reply to this email
            </p>
          </td>
        </tr>
      </table>
    </td>
  </tr>

</table>
</td></tr>
</table>
</body>
</html>";
}

function emailStatusBadge(string $status): string {
    $styles = [
        'Pending'     => 'background:#fef9c3;color:#92400e;border:1px solid #fde68a;',
        'In Progress' => 'background:#dbeafe;color:#1e40af;border:1px solid #bfdbfe;',
        'Resolved'    => 'background:#dcfce7;color:#166534;border:1px solid #86efac;',
    ];
    $icons = [
        'Pending'     => '&#128336;',
        'In Progress' => '&#128260;',
        'Resolved'    => '&#9989;',
    ];
    $style = $styles[$status] ?? 'background:#f3f4f6;color:#374151;border:1px solid #e5e7eb;';
    $icon  = $icons[$status]  ?? '';
    return "<span style='display:inline-block;padding:5px 14px;border-radius:20px;font-size:13px;font-weight:700;{$style}'>"
        . $icon . ' ' . htmlspecialchars($status) . "</span>";
}

function emailTrackingBox(string $tracking, string $accent = '#0b1f3a'): string {
    return "
    <table width='100%' cellpadding='0' cellspacing='0' border='0' style='margin:20px 0;'>
      <tr>
        <td style='background:#f0f4f9;border-radius:10px;padding:18px 24px;text-align:center;border:2px dashed #c7d8ef;'>
          <div style='font-size:11px;text-transform:uppercase;letter-spacing:0.12em;color:#9ca3af;margin-bottom:6px;'>Tracking ID</div>
          <div style='font-family:Courier New,Courier,monospace;font-size:24px;font-weight:700;color:{$accent};letter-spacing:0.05em;'>"
            . htmlspecialchars($tracking) .
          "</div>
          <div style='font-size:11px;color:#9ca3af;margin-top:6px;'>Keep this ID to track your complaint</div>
        </td>
      </tr>
    </table>";
}

function emailInfoRow(string $label, string $value): string {
    return "
    <tr>
      <td style='padding:10px 14px;background:#f8fafc;font-size:12px;font-weight:700;color:#6b7280;
                 text-transform:uppercase;letter-spacing:0.06em;border-bottom:1px solid #e5e7eb;
                 width:35%;vertical-align:top;'>" . htmlspecialchars($label) . "</td>
      <td style='padding:10px 14px;font-size:14px;color:#111827;border-bottom:1px solid #e5e7eb;
                 vertical-align:top;'>{$value}</td>
    </tr>";
}

function emailCtaButton(string $label, string $url, string $accent = '#0b1f3a'): string {
    return "
    <table width='100%' cellpadding='0' cellspacing='0' border='0' style='margin:24px 0 0;'>
      <tr>
        <td align='center'>
          <a href='{$url}'
             style='display:inline-block;padding:14px 36px;background:{$accent};color:#ffffff;
                    text-decoration:none;font-size:14px;font-weight:700;border-radius:8px;
                    letter-spacing:0.02em;box-shadow:0 2px 8px rgba(11,31,58,0.25);'>
            {$label}
          </a>
        </td>
      </tr>
    </table>";
}

// ── Notification: Complaint Submitted ─────────────────────────────────────────
function notifyComplaintSubmitted(string $email, string $name, string $title, string $category, string $tracking): void {
    $track_url = SITE_URL . '/track.php';

    $body = "
    <p style='margin:0 0 16px;font-size:15px;color:#111827;'>
      Dear <strong>" . htmlspecialchars($name) . "</strong>,
    </p>
    <p style='margin:0 0 20px;font-size:14px;color:#374151;line-height:1.7;'>
      Your complaint has been <strong>successfully submitted</strong> to the Citizen Complaint Portal.
      Our team will review it and take the necessary action. You will receive another email when the status changes.
    </p>

    " . emailTrackingBox($tracking) . "

    <table width='100%' cellpadding='0' cellspacing='0' border='0'
           style='border-radius:10px;overflow:hidden;border:1px solid #e5e7eb;margin-bottom:8px;'>
      " . emailInfoRow('Complaint', htmlspecialchars($title)) . "
      " . emailInfoRow('Category', htmlspecialchars($category)) . "
      " . emailInfoRow('Status', emailStatusBadge('Pending')) . "
      " . emailInfoRow('Submitted', date('d M Y, h:i A')) . "
    </table>

    " . emailCtaButton('&#128269; Track My Complaint', $track_url) . "";

    $subject = "Complaint Received — Tracking ID: {$tracking}";
    sendEmail($email, $name, $subject, emailTemplate(
        'Complaint Submitted Successfully',
        'We have received your complaint and will act on it shortly.',
        $body
    ));
}

// ── Notification: Status Changed ──────────────────────────────────────────────
function notifyStatusChanged(string $email, string $name, string $title, string $tracking, string $new_status): void {
    $track_url = SITE_URL . '/track.php';

    $messages = [
        'Pending'     => 'Your complaint has been placed back in the queue and will be reviewed by our team soon.',
        'In Progress' => 'Your complaint is now being <strong>actively reviewed and worked on</strong> by our team. We will keep you updated on the progress.',
        'Resolved'    => 'Your complaint has been <strong>marked as resolved</strong>. We hope the issue has been addressed to your satisfaction. If the problem persists, please submit a new complaint referencing your original tracking ID.',
    ];

    $headings = [
        'Pending'     => 'Complaint Back in Queue',
        'In Progress' => 'Your Complaint is In Progress',
        'Resolved'    => 'Complaint Resolved',
    ];

    $accents = [
        'Pending'     => '#b45309',
        'In Progress' => '#1e40af',
        'Resolved'    => '#166634',
    ];

    $accent  = $accents[$new_status]  ?? '#0b1f3a';
    $heading = $headings[$new_status] ?? 'Status Update';
    $message = $messages[$new_status] ?? '';

    $body = "
    <p style='margin:0 0 16px;font-size:15px;color:#111827;'>
      Dear <strong>" . htmlspecialchars($name) . "</strong>,
    </p>
    <p style='margin:0 0 20px;font-size:14px;color:#374151;line-height:1.7;'>
      The status of your complaint has been updated. Here are the latest details:
    </p>

    " . emailTrackingBox($tracking, $accent) . "

    <table width='100%' cellpadding='0' cellspacing='0' border='0'
           style='border-radius:10px;overflow:hidden;border:1px solid #e5e7eb;margin-bottom:8px;'>
      " . emailInfoRow('Complaint', htmlspecialchars($title)) . "
      " . emailInfoRow('New Status', emailStatusBadge($new_status)) . "
      " . emailInfoRow('Updated On', date('d M Y, h:i A')) . "
    </table>

    <p style='margin:0 0 8px;font-size:14px;color:#374151;line-height:1.7;'>{$message}</p>

    " . emailCtaButton('&#128203; Track My Complaint', $track_url, $accent) . "";

    $subject = "{$heading} — Tracking ID: {$tracking}";
    sendEmail($email, $name, $subject, emailTemplate(
        $heading,
        'Status updated on ' . date('d M Y \a\t h:i A'),
        $body,
        $accent
    ));
}