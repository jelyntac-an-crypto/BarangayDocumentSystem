<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/layout.php';
$isPrivacyEmbed = isset($_GET['embed']) && $_GET['embed'] === '1';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Notice | Barangay Document Request and Tracking System</title>

    <link rel="stylesheet" href="../../css/layout.css">
    <link rel="stylesheet" href="../../css/auth/privacy_notice.css">
</head>
<body class="privacy-page<?= $isPrivacyEmbed ? ' privacy-page--embedded' : '' ?>">

    <?php if (!$isPrivacyEmbed) renderSiteHeader('default', '../../'); ?>
    <main class="privacy-container">
        <article class="privacy-content">

            <h1>Privacy Notice</h1>

            <h2>Barangay Document Request and Tracking System</h2>

            <p>
                In accordance with the Data Privacy Act of 2012
                (Republic Act No. 10173), this notice explains how the
                system collects, uses, stores, shares, and protects
                your personal information.
            </p>

            <section>
                <h2>1. Personal Information Collected</h2>

                <p>
                    The system may collect the following personal
                    information during account registration and
                    document requests:
                </p>

                <ul>
                    <li>ID Number</li>
                    <li>Full name, including optional middle name and name extension</li>
                    <li>Birthdate and calculated age</li>
                    <li>Sex</li>
                    <li>Contact number</li>
                    <li>Email address</li>
                    <li>Residential address</li>
                    <li>Civil status</li>
                    <li>National ID Number (PCN)</li>
                    <li>Username and password credential (password stored as a hash)</li>
                    <li>Security-question answers (stored as hashes)</li>
                    <li>Document request details</li>
                    <li>Uploaded supporting requirements</li>
                    <li>Authentication and security records, including OTP/CAPTCHA attempts, timestamps, IP address, and browser details</li>
                </ul>
            </section>

            <section>
                <h2>2. Purpose of Collection</h2>

                <p>
                    Your personal information is collected for the
                    following purposes:
                </p>

                <ul>
                    <li>
                        <strong>ID Number:</strong>
                        Identifies each resident account and helps
                        prevent duplicate registration.
                    </li>

                    <li>
                        <strong>Full Name:</strong>
                        Identifies the resident on barangay document
                        requests and issued documents.
                    </li>

                    <li>
                        <strong>Birthdate and Age:</strong>
                        Helps verify age requirements for applicable
                        barangay documents.
                    </li>

                    <li>
                        <strong>Sex:</strong>
                        Provides personal identification information
                        when required for document processing.
                    </li>

                    <li>
                        <strong>Contact Number:</strong>
                        Allows authorized barangay personnel to contact
                        residents regarding their requests.
                    </li>

                    <li>
                        <strong>Email Address:</strong>
                        Used for account verification, notifications,
                        and important account-related messages.
                    </li>

                    <li>
                        <strong>Residential Address:</strong>
                        Helps verify whether the resident lives within
                        the barangay.
                    </li>

                    <li>
                        <strong>Civil Status:</strong>
                        Provides information when required for
                        applicable document requests.
                    </li>

                    <li>
                        <strong>National ID Number (PCN):</strong>
                        Supports identity verification when necessary.
                    </li>

                    <li>
                        <strong>Username and Password:</strong>
                        Allow residents to securely access their
                        accounts.
                    </li>

                    <li>
                        <strong>Security Question Answers:</strong>
                        Provide an additional way to verify account
                        ownership when the system's account recovery
                        feature is used.
                    </li>

                    <li>
                        <strong>Document Request Details:</strong>
                        Allow authorized barangay personnel to process
                        and track document applications.
                    </li>

                    <li>
                        <strong>Uploaded Supporting Requirements:</strong>
                        Help verify the eligibility and completeness
                        of document applications.
                    </li>
                </ul>

                <p>
                    The system also processes account activity, OTP/CAPTCHA attempts, IP address, browser details, and timestamps to provide status updates, protect accounts, prevent abuse, and investigate security events.
                </p>
            </section>

            <section>
                <h2>3. Data Protection</h2>

                <p>
                    The system hashes passwords, security answers, and one-time codes, encrypts National ID information in the database, and uses access controls and prepared database statements. These safeguards reduce risk but cannot guarantee that unauthorized access will never occur.
                </p>

                <p>
                    Only authorized barangay personnel may access
                    information necessary for their assigned duties.
                </p>
            </section>

            <section>
                <h2>4. Data Retention</h2>
                <p>
                    The system owner has not yet supplied an approved retention
                    schedule. The Barangay must set and publish a retention period
                    for each record category and securely dispose of or archive
                    records when that period ends, subject to applicable laws and
                    official records requirements.
                </p>
                <p><strong>Retention schedule:</strong> Pending Barangay approval.</p>
            </section>

            <section>
                <h2>5. Data Sharing</h2>
                <p>
                    Account and request information may be accessed by authorized
                    barangay personnel for account support, identity and eligibility
                    checks, document processing, and security administration.
                    Information may be disclosed when required by law. Any outside
                    service provider or additional recipient must be approved by
                    the Barangay and covered by appropriate safeguards.
                </p>
            </section>

            <section>
                <h2>6. Your Rights</h2>
                <p>
                    Subject to applicable law, you may ask to be informed about the
                    processing of your personal information and request access,
                    correction, objection, blocking, or erasure where applicable.
                    You may also raise a concern with the National Privacy
                    Commission.
                </p>
            </section>

            <section>
                <h2>7. Contact Information</h2>
                <p>
                    For privacy questions or requests, contact the Barangay Office
                    through its official channels and ask for the designated Data
                    Protection Officer or privacy contact. The Barangay must add
                    its official name and contact details here before publishing
                    this notice.
                </p>
                <ul>
                    <li>Barangay: [OFFICIAL BARANGAY NAME TO BE ADDED]</li>
                    <li>Privacy contact: [OFFICIAL DPO / CONTACT TO BE ADDED]</li>
                    <li>Email / telephone: [OFFICIAL CONTACT DETAILS TO BE ADDED]</li>
                </ul>
            </section>

            <section>
                <h2>8. Consent and lawful processing</h2>
                <p>
                    The registration form records your privacy-consent choice and
                    the time it was given. The Barangay should identify the lawful
                    basis for each purpose of processing; consent is not necessarily
                    the only lawful basis for every system function.
                </p>
            </section>

            <p class="last-updated">
                <strong>Last updated:</strong> November 2026
            </p>
            <?php if (!$isPrivacyEmbed): ?>
                <a class="back-link" href="register.php">
                    Back to Registration
                </a>
            <?php endif; ?>

        </article>
    </main>
    <?php if (!$isPrivacyEmbed) renderSiteFooter(); ?>

</body>
</html>
