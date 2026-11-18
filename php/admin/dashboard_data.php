<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';

// Return JSON responses only.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// This endpoint accepts GET requests only.
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

// Require an authenticated session.
if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    http_response_code(401);
    exit;
}

// Only security administrators may retrieve dashboard data.
if ($_SESSION['role'] !== 'security_admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied.']);
    exit;
}

try {
    // Use Philippine time for dashboard queries.
    date_default_timezone_set('Asia/Manila');
    $pdo->exec("SET time_zone = '+08:00'");

    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
    $since = $now->modify('-24 hours');

    $today = $now->format('Y-m-d');
    $sinceSql = $since->format('Y-m-d H:i:s');

    // Validate optional date filters.
    $fromDate = $_GET['from'] ?? '';
    $toDate = $_GET['to'] ?? '';
    $eventType = trim((string) ($_GET['event_type'] ?? ''));
    $username = trim((string) ($_GET['username'] ?? ''));
    $ipFilter = trim((string) ($_GET['ip'] ?? ''));

    $validDate = static function (string $value): bool {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
            return false;
        }

        $parts = explode('-', $value);

        return checkdate(
            (int) $parts[1],
            (int) $parts[2],
            (int) $parts[0]
        );
    };

    if ($fromDate !== '' && !$validDate($fromDate)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid start date.']);
        exit;
    }

    if ($toDate !== '' && !$validDate($toDate)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid end date.']);
        exit;
    }

    if ($fromDate !== '' && $toDate !== '' && $fromDate > $toDate) {
        http_response_code(400);
        echo json_encode(['error' => 'End date cannot be earlier than start date.']);
        exit;
    }

    // Limit filter lengths.
    $eventType = substr($eventType, 0, 100);
    $username = substr($username, 0, 100);
    $ipFilter = substr($ipFilter, 0, 45);

    // Today's security summary.
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM activity_logs
         WHERE event_type = 'LOGIN_SUCCESS'
           AND created_at >= ?"
    );
    $stmt->execute([$today . ' 00:00:00']);
    $successfulLogins = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM login_attempts
         WHERE attempt_status IN ('FAILED', 'CAPTCHA_FAILED', 'OTP_FAILED')
           AND attempted_at >= ?"
    );
    $stmt->execute([$today . ' 00:00:00']);
    $failedLogins = (int) $stmt->fetchColumn();

    $stmt = $pdo->query(
        "SELECT COUNT(*)
         FROM users
         WHERE locked_until > NOW()"
    );
    $lockedAccounts = (int) $stmt->fetchColumn();

    $stmt = $pdo->query(
        "SELECT COUNT(*)
         FROM ip_blocks
         WHERE expires_at > NOW()"
    );
    $blockedIps = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM activity_logs
         WHERE event_type = 'OTP_SENT'
           AND created_at >= ?"
    );
    $stmt->execute([$today . ' 00:00:00']);
    $otpsSent = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM activity_logs
         WHERE event_type = 'OTP_FAILED'
           AND created_at >= ?"
    );
    $stmt->execute([$today . ' 00:00:00']);
    $otpFailures = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM activity_logs
         WHERE event_type = 'ALERT_EMAIL_SENT'
           AND created_at >= ?"
    );
    $stmt->execute([$today . ' 00:00:00']);
    $alertEmailsSent = (int) $stmt->fetchColumn();

    // Prepare 24 hourly chart labels, including hours with zero failures.
    $hourStart = $now->setTime(
        (int) $now->format('H'),
        0,
        0
    )->modify('-23 hours');

    $chartLabels = [];
    $chartCounts = [];

    for ($i = 0; $i < 24; $i++) {
        $hour = $hourStart->modify("+{$i} hours");
        $key = $hour->format('Y-m-d H');

        $chartLabels[] = $hour->format('M j H:00');
        $chartCounts[$key] = 0;
    }

    // Read failed attempts from the last 24 hours.
    $stmt = $pdo->prepare(
        "SELECT attempted_at
         FROM login_attempts
         WHERE attempted_at >= ?
           AND attempt_status IN ('FAILED', 'CAPTCHA_FAILED', 'OTP_FAILED')"
    );
    $stmt->execute([$sinceSql]);

    foreach ($stmt->fetchAll() as $attempt) {
        $attemptTime = new DateTimeImmutable(
            (string) $attempt['attempted_at'],
            new DateTimeZone('Asia/Manila')
        );

        $key = $attemptTime->format('Y-m-d H');

        if (array_key_exists($key, $chartCounts)) {
            $chartCounts[$key]++;
        }
    }

    // Get the five IP addresses with the most failures in 24 hours.
    $stmt = $pdo->prepare(
        "SELECT
            la.ip_address,
            COUNT(*) AS failure_count,
            EXISTS (
                SELECT 1
                FROM ip_blocks ib
                WHERE ib.ip_address = la.ip_address
                  AND ib.expires_at > NOW()
            ) AS is_blocked
         FROM login_attempts la
         WHERE la.attempted_at >= ?
           AND la.attempt_status IN ('FAILED', 'CAPTCHA_FAILED', 'OTP_FAILED')
         GROUP BY la.ip_address
         ORDER BY failure_count DESC
         LIMIT 5"
    );
    $stmt->execute([$sinceSql]);
    $topIps = $stmt->fetchAll();

    // Get the latest security monitoring flags.
    $stmt = $pdo->query(
        "SELECT
            sf.id,
            sf.user_id,
            u.username,
            sf.ip_address,
            sf.flag_type,
            sf.description,
            sf.severity,
            sf.detected_at
         FROM security_flags sf
         LEFT JOIN users u ON u.id_number = sf.user_id
         ORDER BY sf.detected_at DESC, sf.id DESC
         LIMIT 10"
    );
    $securityFlags = $stmt->fetchAll();

    // Build filters for the recent activity table.
    $where = [];
    $params = [];

    if ($fromDate !== '') {
        $where[] = 'al.created_at >= ?';
        $params[] = $fromDate . ' 00:00:00';
    }

    if ($toDate !== '') {
        $where[] = 'al.created_at < DATE_ADD(?, INTERVAL 1 DAY)';
        $params[] = $toDate . ' 00:00:00';
    }

    if ($eventType !== '') {
        $where[] = 'al.event_type = ?';
        $params[] = $eventType;
    }

    if ($username !== '') {
        $where[] = '(u.username LIKE ? OR al.username_typed LIKE ? OR al.description LIKE ?)';
        $searchUsername = '%' . $username . '%';
        $params[] = $searchUsername;
        $params[] = $searchUsername;
        $params[] = $searchUsername;
    }

    if ($ipFilter !== '') {
        $where[] = 'al.ip_address LIKE ?';
        $params[] = '%' . $ipFilter . '%';
    }

    $sql = "
        SELECT
            al.id,
            al.user_id,
            u.username,
            al.username_typed,
            al.event_type,
            al.description,
            al.ip_address,
            al.created_at
        FROM activity_logs al
        LEFT JOIN users u ON u.id_number = al.user_id
    ";

    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY al.created_at DESC, al.id DESC LIMIT 50';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $recentEvents = $stmt->fetchAll();

    // Return all dashboard information as JSON.
    echo json_encode([
        'generated_at' => $now->format('Y-m-d H:i:s'),
        'summary' => [
            'successful_logins' => $successfulLogins,
            'failed_logins' => $failedLogins,
            'locked_accounts' => $lockedAccounts,
            'blocked_ips' => $blockedIps,
            'otps_sent' => $otpsSent,
            'otp_failures' => $otpFailures,
            'alert_emails_sent' => $alertEmailsSent
        ],
        'failed_logins_chart' => [
            'labels' => $chartLabels,
            'values' => array_values($chartCounts)
        ],
        'top_ips' => $topIps,
        'security_flags' => $securityFlags,
        'recent_events' => $recentEvents
    ], JSON_THROW_ON_ERROR);

} catch (Throwable $error) {
    error_log('Admin dashboard data error: ' . $error->getMessage());

    http_response_code(500);
    echo json_encode([
        'error' => 'Unable to retrieve dashboard data.'
    ]);
}