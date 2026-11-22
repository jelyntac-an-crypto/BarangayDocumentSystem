<?php
declare(strict_types=1);

require_once __DIR__ . '/php/init.php';
require_once __DIR__ . '/php/layout.php';

$isLoggedIn = isset($_SESSION['user_id']);
$role = $isLoggedIn ? strtolower((string) ($_SESSION['role'] ?? '')) : 'guest'; // security_admin | staff | resident | guest

$loginUrl     = 'php/auth/login.php';
$registerUrl  = 'php/auth/register.php';
$requestUrl   = $role === 'resident' ? 'php/resident/new_request.php' : $loginUrl;
$trackUrl     = $role === 'resident' ? 'php/resident/my_requests.php' : $loginUrl;
$dashboardUrl = match ($role) {
    'security_admin' => 'php/admin/dashboard.php',
    'staff'          => 'php/staff/dashboard.php',
    default          => $loginUrl,
};
$isStaffSide = $role === 'security_admin' || $role === 'staff';

$documents = [];

try {
    $documentStmt = $pdo->query(
        "SELECT document_name, description
         FROM document_types
         WHERE is_active = 1
         ORDER BY document_name ASC"
    );
    $documents = $documentStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $error) {
    error_log('Homepage document list error: ' . $error->getMessage());
}

$steps = [
    ['Request', 'Choose a document and fill out the form.'],
    ['Review',  'The barangay checks what you submitted.'],
    ['Pickup',  'Bring your reference number when you collect it.'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Document Request and Tracking System</title>

    <link rel="stylesheet" href="css/layout.css">
    <link rel="stylesheet" href="css/home.css">
</head>
<body class="home">

    <?php renderSiteHeader('home'); ?>

    <main>
        <section class="hero">
            <!-- Decorative scene. Ibutang ang imong litrato sa images/hero.jpg aron mapuli kini. -->
            <svg class="hero__scene" viewBox="0 0 1200 560" preserveAspectRatio="xMaxYMax slice" aria-hidden="true">
                <circle cx="820" cy="170" r="70" fill="#fff3d6" opacity=".85"/>
                <path d="M200 560 480 190l110 130 120-170 250 410z" fill="#a8785a" opacity=".35"/>
                <path d="M0 560 260 270l130 150 190-250 330 390z" fill="#7a5640" opacity=".45"/>
                <path d="M300 560 700 330l200 120 300-170v280z" fill="#4e3a2e" opacity=".55"/>
            </svg>

            <div class="hero__top">
                <div>
                    <p class="hero__kicker">Certificates &mdash; Clearances &mdash; Permits</p>
                    <h1>Barangay Document<br>Request and <span>Tracking System</span></h1>
                    <p class="hero__lead">
                        Request your barangay documents online, follow every step,
                        and visit the barangay hall only when it's ready.
                    </p>

                    <div class="hero__actions">
                        <?php if ($isStaffSide): ?>
                            <a class="pill pill--dark" href="<?= htmlspecialchars($dashboardUrl) ?>">
                                <span>Go to dashboard</span>
                                <span class="pill__dot"><?= siteIcon('arrow') ?></span>
                            </a>
                        <?php else: ?>
                            <a class="pill pill--dark" href="<?= htmlspecialchars($requestUrl) ?>">
                                <span>Request a Document</span>
                                <span class="pill__dot"><?= siteIcon('arrow') ?></span>
                            </a>
                            <a class="pill pill--ghost" href="<?= htmlspecialchars($trackUrl) ?>">
                                <span class="pill__dot"><?= siteIcon('track') ?></span>
                                <span>Track my requests</span>
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php if (!$isLoggedIn): ?>
                        <p class="hero__note">No account yet? <a href="<?= htmlspecialchars($registerUrl) ?>">Register here</a>.</p>
                    <?php endif; ?>
                </div>

                <ul class="hero__tags" aria-hidden="true">
                    <li>Secure</li>
                    <li>Online</li>
                    <li>Trackable</li>
                    <li>Timely</li>
                </ul>
            </div>

            <div class="hero__cards">
                <article class="glass slip" aria-label="Sample tracking slip">
                    <div class="slip__thumb"><?= siteIcon('request') ?></div>
                    <div>
                        <p class="slip__label">Sample tracking slip</p>
                        <h3>Barangay Clearance</h3>
                        <p class="slip__ref">BRGY-2026-00184</p>
                        <div class="slip__bar" aria-hidden="true"><i class="on"></i><i class="on"></i><i class="now"></i></div>
                        <p class="slip__status">Ready for pickup</p>
                    </div>
                </article>

                <div class="glass stats">
                    <div><?= siteIcon('request') ?><strong><?= count($documents) ?></strong><span>Document types</span></div>
                    <div><?= siteIcon('track') ?><strong>3</strong><span>Simple steps</span></div>
                    <div><?= siteIcon('home') ?><strong>24/7</strong><span>Online access</span></div>
                </div>

                <div class="glass cta">
                    <div>
                        <h3>Need a document?</h3>
                        <p>Start your request in minutes.</p>
                    </div>
                    <a href="<?= htmlspecialchars($isStaffSide ? $dashboardUrl : $requestUrl) ?>" aria-label="Start a request"><?= siteIcon('arrow') ?></a>
                </div>
            </div>
        </section>

        <section class="section">
            <h2>Which document do <span>you need?</span></h2>
            <ul class="docs">
                <?php foreach ($documents as $document): ?>
                    <li>
                        <a class="glass" href="<?= htmlspecialchars($requestUrl) ?>">
                            <?= siteIcon('request') ?>
                            <strong><?= htmlspecialchars((string) $document['document_name']) ?></strong>
                            <span><?= htmlspecialchars(trim((string) ($document['description'] ?? '')) ?: 'Available for online request.') ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="section">
            <h2>Requesting takes <span>just a few minutes</span></h2>
            <ol class="how">
                <?php foreach ($steps as $i => [$title, $text]): ?>
                    <li class="glass">
                        <span class="how__num"><?= $i + 1 ?></span>
                        <div>
                            <strong><?= htmlspecialchars($title) ?></strong>
                            <p><?= htmlspecialchars($text) ?></p>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>
    </main>

    <?php renderSiteFooter(); ?>

</body>
</html>