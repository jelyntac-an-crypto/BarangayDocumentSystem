<?php
declare(strict_types=1);

// Prevent the website from being displayed inside an iframe.
header('X-Frame-Options: DENY');

// Prevent browsers from guessing the content type.
header('X-Content-Type-Options: nosniff');

// Control how much referrer information is shared.
header('Referrer-Policy: strict-origin-when-cross-origin');

// Disable unnecessary browser features.
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
