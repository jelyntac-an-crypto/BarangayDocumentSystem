<?php
declare(strict_types=1);

// Block framing everywhere, except the static privacy notice when it is embedded
// in the same-origin registration dialog.
$isEmbeddedPrivacyNotice = basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) === 'privacy_notice.php'
    && (($_GET['embed'] ?? '') === '1');
header('X-Frame-Options: ' . ($isEmbeddedPrivacyNotice ? 'SAMEORIGIN' : 'DENY'));

// Prevent browsers from guessing the content type.
header('X-Content-Type-Options: nosniff');

// Control how much referrer information is shared.
header('Referrer-Policy: strict-origin-when-cross-origin');

// Disable unnecessary browser features.
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

// Prevent browsers from caching protected pages.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');