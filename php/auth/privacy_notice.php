<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/layout.php';
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
<body class="privacy-page">

    <?php renderSiteHeader('default', '../../'); ?>
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
                    <li>Username and password</li>
                    <li>Security question answers</li>
                    <li>Document request details</li>
                    <li>Uploaded supporting requirements</li>
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
                    The system also processes necessary personal
                    information to provide request status updates,
                    protect accounts against unauthorized access,
                    and maintain security and activity records.
                </p>
            </section>

            <section>
                <h2>3. Data Protection</h2>

                <p>
                    The system uses password hashing, encryption for
                    National ID information, access controls, prepared
                    database statements, and security measures to help
                    protect stored personal information.
                </p>

                <p>
                    Only authorized barangay personnel may access
                    information necessary for their assigned duties.
                </p>
            </section>

            <section>
                <h2>4. Data Retention</h2>

                <p>
                    Personal information will be retained only for as
                    long as necessary for document processing, security,
                    legal obligations, and applicable barangay
                    recordkeeping requirements.
                </p>

                <p>
                    Retention and disposal of personal information
                    will follow the barangay's approved records
                    retention and disposal policies.
                </p>
            </section>

            <section>
                <h2>5. Data Sharing</h2>

                <p>
                    Personal information will not be disclosed to
                    unauthorized persons. Information may be accessed
                    by authorized barangay personnel or disclosed
                    when required by applicable law.
                </p>
            </section>

            <section>
                <h2>6. Your Rights</h2>

                <p>
                    Subject to applicable law, you have the right to
                    be informed about the processing of your personal
                    information and may request access to or correction
                    of your personal data.
                </p>

                <p>
                    You may also exercise other applicable rights
                    under the Data Privacy Act of 2012, including
                    the right to object, request blocking or erasure
                    where legally applicable, and lodge a complaint
                    with the National Privacy Commission.
                </p>

                <p>
                    For privacy-related concerns, you may contact
                    the Barangay Office through its official
                    contact channels.
                </p>
            </section>

            <section>
                <h2>7. Contact Information</h2>

                <p>
                    For questions, concerns, or requests regarding
                    your personal information, please contact the
                    Barangay Office or its designated Data Protection
                    Officer through the barangay's official contact
                    channels.
                </p>
            </section>

            <section>
                <h2>8. Consent</h2>

                <p>
                    By checking the consent box during registration,
                    you confirm that you have read this Privacy Notice
                    and agree to the collection and processing of your
                    personal information for the purposes explained
                    above.
                </p>
            </section>

            <p class="last-updated">
                <strong>Last updated:</strong> November 2026
            </p>

            <a class="back-link" href="register.php">
                Back to Registration
            </a>

        </article>
    </main>
    <?php renderSiteFooter(); ?>

</body>
</html>