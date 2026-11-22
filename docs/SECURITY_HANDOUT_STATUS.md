# Security 1 handout implementation status

This is a working checklist for the Barangay Document Request and Tracking System. It records implementation evidence and items that require a live demonstration or institutional approval. Do not mark those items complete until evidence is added.

## User-confirmed seed values

The user confirmed the selected seeds are **D1 = 5** and **D2 = 8**. The application uses an 8-character password minimum (D2=8). D1=5 is recorded in the implementation comments for the progressive lockout configuration (20, 40, and 80 seconds). The username policy is 8–19 characters and is a separate setting.

| Requirement | Current configured value |
|---|---:|
| D1 seed | 5; progressive lockout steps are 20/40/80 seconds |
| D2 seed | 8; password minimum is 8 characters |
| Username length | 8–19 characters |
| Password maximum | 64 characters |
| Forgot-password availability | after 3 failed attempts |
| OTP validity | 5 minutes |
| Idle-session timeout | 5 minutes |
| Monitor IP threshold | more than 16 failures within 10 minutes |
| Password history | latest 3 passwords |

## Implementation evidence to verify in the demo

- Account registration and field validation, including CAPTCHA and privacy consent.
- Password hashing and password-history checks; answers and one-time codes are hashed. National ID data is encrypted.
- Login, OTP verification/resend, lockout, forgot-password recovery, and reset flows.
- Role-protected resident, staff, and security-admin pages.
- CSRF checks on state-changing forms, session timeout behavior, and security event logging.
- Security dashboard filters, event summaries, alert flags, and auto-refresh behavior.
- External CLI monitor at `C:/xampp/private/BarangayDocumentSystem/scripts/monitor.php`; keep its configuration outside `htdocs` and run it only from the command line.
- Local XAMPP check: homepage returned HTTP 200; `/php/` directory request returned 403; direct `.git/HEAD`, `database/schema.sql`, and `.bak` requests returned 403 under the new root `.htaccess`. Recheck on any deployment using a different Apache configuration.
- All PHP files passed `php -l`. Browser workflows still need manual acceptance testing. JavaScript syntax tooling (`node`) is unavailable in this environment.

## Evidence still needed

- Run and record a complete live acceptance walkthrough using test accounts and a non-production database.
- Attach screenshots or instructor sign-off for required demonstrations.
- Add actual development-journal entries with dates, work performed, and genuine commit IDs. Do not create retrospective or fabricated entries.
- Complete and approve the official contact and record-retention details in `PRIVACY_NOTICE_DRAFT.md` before publishing the notice.
- Obtain required proposal approval and signatures before marking those items complete.

## Deployment-specific configuration

The private config and monitoring files are outside this web root. Keep credentials out of this repository and do not paste them into documentation, screenshots, or logs. Verify the private config file permissions and the Apache/PHP settings on the target machine.
