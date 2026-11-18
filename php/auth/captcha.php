<?php
declare(strict_types=1);

// Load security headers and start the session.
require_once dirname(__DIR__) . '/header.php';
require_once dirname(__DIR__) . '/session.php';

// Support separate CAPTCHA challenges for registration and login.
$purpose = $_GET['purpose'] ?? 'registration';

if (!in_array($purpose, ['registration', 'login', 'forgot_password'], true)) {
    http_response_code(400);
    exit;
}

// Use a separate session key for each purpose.
$sessionKey = 'captcha_answer_hash_' . $purpose;

// Prevent browsers from caching the CAPTCHA image.
header('Content-Type: image/png');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Generate six random characters.
// Ambiguous characters such as I, O, 0, and 1 are excluded.
$characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
$answer = '';

for ($i = 0; $i < 6; $i++) {
    $index = random_int(0, strlen($characters) - 1);
    $answer .= $characters[$index];
}

// Store only the hashed answer in the session.
$_SESSION[$sessionKey] = password_hash(
    $answer,
    PASSWORD_DEFAULT
);

// Create the CAPTCHA image.
$width = 190;
$height = 65;

$image = imagecreatetruecolor($width, $height);

if ($image === false) {
    http_response_code(500);
    exit;
}

// Background and text colors.
$background = imagecolorallocate($image, 245, 247, 250);
$textColor = imagecolorallocate($image, 35, 45, 60);
$noiseColor = imagecolorallocate($image, 170, 180, 195);
$lineColor = imagecolorallocate($image, 190, 200, 215);

// Fill the background.
imagefilledrectangle($image, 0, 0, $width, $height, $background);

// Add random noise lines.
for ($i = 0; $i < 7; $i++) {
    imageline(
        $image,
        random_int(0, $width),
        random_int(0, $height),
        random_int(0, $width),
        random_int(0, $height),
        $lineColor
    );
}

// Add random noise dots.
for ($i = 0; $i < 100; $i++) {
    imagesetpixel(
        $image,
        random_int(0, $width - 1),
        random_int(0, $height - 1),
        $noiseColor
    );
}

// Draw each CAPTCHA character with a slightly different position.
$font = 5;
$startX = 24;

for ($i = 0; $i < 6; $i++) {
    $x = $startX + ($i * 25);
    $y = random_int(18, 30);

    imagestring(
        $image,
        $font,
        $x,
        $y,
        $answer[$i],
        $textColor
    );
}

// Output the PNG image.
imagepng($image);
imagedestroy($image);
