<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/bootstrap.php';

// Create a configured PHPMailer instance.
function createMailer(): PHPMailer
{
    global $config;
    $mailConfig = $config['mail'];
    $mail = new PHPMailer(true);


    $mail->isSMTP();
    $mail->Host = $mailConfig['host'];
    $mail->SMTPAuth = true;
    $mail->Username = $mailConfig['username'];
    $mail->Password = $mailConfig['password'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = (int) $mailConfig['port'];

    $mail->CharSet = 'UTF-8';
    $mail->setFrom(
        $mailConfig['from_email'],
        $mailConfig['from_name']
    );

    return $mail;

}