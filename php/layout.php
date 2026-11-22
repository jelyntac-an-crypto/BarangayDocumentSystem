<?php
declare(strict_types=1);

/**
 * Inline SVG icons used by the site header.
 */
function siteIcon(string $name): string
{
    $paths = [
        'home'      => '<path d="M3 11.5 12 4l9 7.5"/><path d="M5 10v10h14V10"/><path d="M10 20v-6h4v6"/>',
        'request'   => '<path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5"/><path d="M10 13h6M10 17h6"/>',
        'track'     => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/><path d="M8.5 11l2 2 3.5-4"/>',
        'bell'      => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/>',
        'dashboard' => '<rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/>',
        'login'     => '<path d="M14 4h5v16h-5"/><path d="M4 12h10"/><path d="m10 8 4 4-4 4"/>',
        'user'      => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1-4 4-6 8-6s7 2 8 6"/>',
        'logout'    => '<path d="M10 4H5v16h5"/><path d="M20 12H10"/><path d="m16 8 4 4-4 4"/>',
        'arrow'     => '<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>',
        'menu'      => '<path d="M4 7h16M4 12h16M4 17h16"/>',
    ];

    return '<svg class="sh-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
        . 'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($paths[$name] ?? '') . '</svg>';
}

/**
 * Render the site header.
 *
 * $pageType   highlights the matching nav item. Use: home, dashboard, requests,
 *             new_request, my_requests. 'login' and 'register' only change the
 *             account buttons.
 * $rootPrefix path back to the project root, for example '../../'.
 */
function renderSiteHeader(
    string $pageType = 'default',
    string $rootPrefix = ''
): void {
    $isLoggedIn = isset($_SESSION['user_id']);
    $role = $isLoggedIn ? strtolower((string) ($_SESSION['role'] ?? '')) : 'guest';
    global $pdo;
    $headerNotifications = [];
    $headerUnreadCount = 0;
    $returnPage = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $allowedReturnPages = ['dashboard.php', 'my_requests.php', 'new_request.php', 'view_request.php'];
    $returnTo = in_array($returnPage, $allowedReturnPages, true) ? $returnPage : 'dashboard.php';
    if ($returnPage === 'view_request.php' && isset($_GET['id']) && ctype_digit((string) $_GET['id'])) {
        $returnTo .= '?id=' . (int) $_GET['id'];
    }

    if ($isLoggedIn && $role === 'resident' && isset($pdo)) {
        try {
            $notificationQuery = $pdo->prepare(
                "SELECT id, title, message, is_read, created_at
                 FROM notifications
                 WHERE user_id = ?
                 ORDER BY created_at DESC, id DESC"
            );
            $notificationQuery->execute([(string) $_SESSION['user_id']]);
            $headerNotifications = $notificationQuery->fetchAll(PDO::FETCH_ASSOC);

            $unreadQuery = $pdo->prepare(
                'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0'
            );
            $unreadQuery->execute([(string) $_SESSION['user_id']]);
            $headerUnreadCount = (int) $unreadQuery->fetchColumn();
        } catch (Throwable $error) {
            error_log('Header notifications error: ' . $error->getMessage());
        }
    }
    $p = static fn (string $path): string => e($rootPrefix . $path);

    $loginPath = 'php/auth/login.php';

    $homePath = match ($role) {
        'resident' => 'php/resident/dashboard.php',
        'staff' => 'php/staff/dashboard.php',
        'security_admin' => 'php/admin/dashboard.php',
        default => 'index.php'
    };
    $nav = ['home' => ['Home', $homePath, 'home']];

    if ($role === 'staff') {
        $nav['requests'] = ['Requests', 'php/staff/requests.php', 'request'];
    } elseif ($role === 'resident') {
        $nav['new_request'] = ['New Request', 'php/resident/new_request.php', 'request'];
        $nav['my_requests'] = ['My Requests', 'php/resident/my_requests.php', 'track'];
    } elseif ($role === 'guest') {
        $nav['new_request'] = ['Request Document', $loginPath, 'request'];
        $nav['my_requests'] = ['Track Request', $loginPath, 'track'];
    }
    ?>
    <link rel="stylesheet" href="<?= $p('css/header.css') ?>">
    <link rel="stylesheet" href="<?= $p('css/session.css') ?>">

    <header class="sh">
        <div class="sh__bar">
            <a class="sh__brand" href="<?= $p('index.php') ?>"
               aria-label="Barangay Document Request and Tracking System, home">
                <svg class="sh__seal" viewBox="0 0 48 48" aria-hidden="true">
                    <path d="M24 3 42 9v14c0 11-7.5 18.5-18 22C13.5 41.5 6 34 6 23V9z" fill="#2a2622"/>
                    <path d="M24 3 42 9v14c0 11-7.5 18.5-18 22C13.5 41.5 6 34 6 23V9z" fill="none" stroke="#c9a27a" stroke-width="2.5"/>
                    <path d="M17 14h10l5 5v15H17z" fill="#fff"/>
                    <path d="M27 14v5h5" fill="none" stroke="#2a2622" stroke-width="1.6"/>
                    <path d="M20.5 25h8M20.5 29h8" stroke="#2a2622" stroke-width="1.8" stroke-linecap="round"/>
                    <circle cx="33" cy="34" r="6" fill="#c9a27a"/>
                    <path d="m30.2 34 2 2 3.2-3.8" fill="none" stroke="#14232b" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span class="sh__name">
                    <strong>Barangay Document</strong>
                    <span>Request and Tracking System</span>
                </span>
            </a>

            <input type="checkbox" id="sh-toggle" class="sh__check">
            <label for="sh-toggle" class="sh__burger" aria-label="Toggle menu"><?= siteIcon('menu') ?></label>

            <nav class="sh__nav" aria-label="Main navigation">
                <ul class="sh__links">
                    <?php foreach ($nav as $key => [$label, $path, $icon]): ?>
                        <?php $isActive = $pageType === $key || ($key === 'home' && $pageType === 'default' && $returnPage === basename($homePath)); ?>
                        <li>
                            <a href="<?= $p($path) ?>"
                               class="sh__link<?= $isActive ? ' is-active' : '' ?>"
                               <?= $isActive ? 'aria-current="page"' : '' ?>>
                                <?= siteIcon($icon) ?><span><?= e($label) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="sh__account">
                    <?php if ($isLoggedIn && $role === 'resident'): ?>
                        <details class="sh__notifications">
                            <summary aria-label="Notifications, <?= $headerUnreadCount ?> unread">
                                <?= siteIcon('bell') ?>
                                <?php if ($headerUnreadCount > 0): ?>
                                    <span class="sh__notification-badge"><?= $headerUnreadCount > 99 ? '99+' : $headerUnreadCount ?></span>
                                <?php endif; ?>
                            </summary>
                            <div class="sh__notification-panel">
                                <div class="sh__notification-heading">
                                    <strong>Notifications</strong>
                                    <span><?= $headerUnreadCount ?> unread</span>
                                </div>
                                <?php if (empty($headerNotifications)): ?>
                                    <p class="sh__notification-empty">You have no notifications yet.</p>
                                <?php else: ?>
                                    <div class="sh__notification-list">
                                        <?php foreach ($headerNotifications as $notification): ?>
                                            <form method="POST" action="<?= $p('php/resident/mark_notifications_read.php') ?>" class="sh__notification-form sh__notification-entry">
                                                <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                                                <input type="hidden" name="notification_id" value="<?= (int) $notification['id'] ?>">
                                                <button type="submit" class="sh__notification-item<?= (int) $notification['is_read'] === 0 ? ' is-unread' : '' ?>">
                                                    <strong><?= e((string) $notification['title']) ?></strong>
                                                    <span><?= e((string) $notification['message']) ?></span>
                                                    <small><?= e(date('M d, Y h:i A', strtotime((string) $notification['created_at']))) ?></small>
                                                </button>
                                            </form>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <a class="sh__notification-all" href="<?= $p('php/resident/my_requests.php') ?>">View my requests</a>
                                <?php if ($headerUnreadCount > 0): ?>
                                    <form method="POST" action="<?= $p('php/resident/mark_notifications_read.php') ?>" class="sh__notification-form">
                                        <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                                        <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
                                        <button type="submit">Mark all as read</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </details>
                    <?php endif; ?>

                    <?php if ($isLoggedIn): ?>
                        <form method="POST" action="<?= $p('php/auth/logout.php') ?>" class="sh__logout logout-form">
                            <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                            <button type="submit" class="sh__btn sh__btn--dark"><span>Log out</span><span class="sh__arrow"><?= siteIcon('arrow') ?></span></button>
                        </form>
                    <?php elseif ($pageType === 'login'): ?>
                        <a class="sh__btn sh__btn--glass" href="<?= $p('php/auth/register.php') ?>"><?= siteIcon('user') ?><span>Register</span></a>
                    <?php elseif ($pageType === 'register'): ?>
                        <a class="sh__btn sh__btn--dark" href="<?= $p($loginPath) ?>"><span>Log in</span><span class="sh__arrow"><?= siteIcon('arrow') ?></span></a>
                    <?php else: ?>
                        <a class="sh__btn sh__btn--glass" href="<?= $p('php/auth/register.php') ?>"><?= siteIcon('user') ?><span>Register</span></a>
                        <a class="sh__btn sh__btn--dark" href="<?= $p($loginPath) ?>"><span>Log in</span><span class="sh__arrow"><?= siteIcon('arrow') ?></span></a>
                    <?php endif; ?>
                </div>
            </nav>
        </div>
    </header>
    <?php
    // Load the session idle timeout script.
    echo '<script src="' . e($rootPrefix . 'js/session.js') . '" defer></script>';
}

function renderSiteFooter(): void
{
    echo '<footer class="site-footer">';
    echo '<p>&copy; ' . date('Y') .
        ' Barangay Document Request and Tracking System. All rights reserved.</p>';
    echo '</footer>';
}
