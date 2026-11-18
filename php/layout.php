<?php
declare(strict_types=1);

function renderSiteHeader(
    string $pageType = 'default',
    string $rootPrefix = ''
): void {
    $isLoggedIn = isset($_SESSION['user_id']);

    echo '<div class="site-brand">';
    echo '<p>Barangay Document Request and Tracking System</p>';
    echo '</div>';

    echo '<header class="site-header">';
    echo '<nav class="site-nav" aria-label="Main navigation">';

    echo '<a href="' . e($rootPrefix . 'index.php') . '">Home</a>';

    if ($isLoggedIn) {
        echo '<form method="POST" action="' .
            e($rootPrefix . 'php/auth/logout.php') .
            '" class="logout-form">';

        echo '<input type="hidden" name="csrf_token" value="' .
            e(generateCsrfToken()) . '">';

        echo '<button type="submit" class="site-nav-button">Log-out</button>';
        echo '</form>';
    } elseif ($pageType === 'login') {
        echo '<a href="' . e($rootPrefix . 'php/auth/register.php') .
            '">Register</a>';
    } elseif ($pageType === 'register') {
        echo '<a href="' . e($rootPrefix . 'php/auth/login.php') .
            '">Log-in</a>';
    } else {
        echo '<a href="' . e($rootPrefix . 'php/auth/register.php') .
            '">Register</a>';

        echo '<a href="' . e($rootPrefix . 'php/auth/login.php') .
            '">Log-in</a>';
    }

    echo '</nav>';
    echo '</header>';

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