# Manual Acceptance Checklist

Use only the existing test accounts and test documents. Do not send passwords, OTPs, national ID values, or real resident data in chat. Mark each item PASS/FAIL and note the date and any screenshot/evidence needed for the handout.

| # | Scenario | Expected result | Result / evidence |
|---:|---|---|---|
| 1 | Open registration and submit invalid required fields | Inline errors are clear; invalid data is not registered | PASS — user confirmed Purok/Street validation appears as plain red text (2026-11-22) |
| 2 | Open Privacy Notice from registration with some fields filled; close it | Notice opens in a modal; original values remain; no form reload | PASS — user confirmed sample values remained and notice loaded (2026-11-22) |
| 3 | Register a test resident with valid values and CAPTCHA | Account is created in pending/verification state; OTP email is sent | PASS — user confirmed OTP arrived, was verified, and flow redirected to login (2026-11-22) |
| 4 | Verify registration OTP; try an incorrect/expired code if the handout requires it | Correct OTP activates the account; invalid/expired OTP is rejected and attempts are limited | PASS ? correct registration OTP activation was confirmed in test 3; user confirmed incorrect-code attempts reached the maximum-attempt message (2026-11-22) |
| 5 | Sign in with correct credentials and OTP | Resident reaches the resident main dashboard | PASS — user confirmed successful login reached Resident Dashboard (2026-11-22) |
| 6 | Use wrong passwords of at least 8 characters with valid CAPTCHA on a disposable account until lockout | Error/lockout appears after 3 server-counted failures; cooldown is shown; account becomes usable after timeout | PASS — user confirmed lockout triggered, cooldown elapsed, and account returned to Resident Dashboard (2026-11-22) |
| 7 | Request password recovery using the registered recovery questions and email OTP | Recovery does not reveal whether an account exists; correct answers and valid OTP allow reset; resend limit works | PASS ? user confirmed password changed, new password login succeeded, cooldown and 15-minute resend-limit countdowns appeared, and a new code arrived after the limit expired (2026-11-22) |
| 8 | Try reusing one of the last three passwords | System rejects a password in the stored history | PASS ? user confirmed the reused recent password was rejected (2026-11-22) |
| 9 | Leave a resident session idle for five minutes | Warning appears before logout; session redirects after timeout | PASS ? user confirmed warning/countdown appeared and the session redirected after timeout (2026-11-22) |
| 10 | Submit a document request and inspect My Requests / View Details | Request is saved, status is visible, and details match the submitted test data | PASS ? user confirmed request details and status matched the test submission (2026-11-22) |
| 11 | Open notification bell, view one notice, then mark all read | Notification details open; unread count updates; notices remain available | PASS ? user confirmed notification actions now work after granting UPDATE permission on notifications.is_read (2026-11-22) |
| 12 | As staff, move a request through valid statuses and attempt rejection without a reason | Only allowed transitions work; rejection reason is required; history and resident notification update | PASS ? user confirmed rejection requires a reason, valid status changes worked, history updated, and the resident received the status notification (2026-11-22) |
| 13 | As security admin, inspect cards, chart, filters, flags, recent events, and refresh | Counts/filter results update from the database and refresh does not break the page | PASS ? user confirmed dashboard cards, chart, filters, flags/events, and refresh worked (2026-11-22) |
| 14 | Generate controlled test security events and run the external monitor from CLI | Monitor reports the expected matching alerts; no web route can run the CLI script | PASS ? CLI detected the 16-failure IP event and three off-hours login events confirmed by user as their own tests; direct web URL returned Apache 404 (2026-11-22) |
| 15 | Visit protected resident/staff/admin URLs while logged out or with the wrong role | User is redirected or receives 403; no protected data is shown | PASS ? resident received Access denied on staff/admin pages; logged-out direct visits redirected to login (2026-11-22) |
| 16 | Check mobile/tablet widths on registration, resident, staff, and admin pages | Controls and navigation remain usable without horizontal overlap | PASS ? user confirmed no overlap/clipping at mobile and 768 px tablet width (2026-11-22) |

## Already checked automatically in this workspace

- `php -l` passed for all PHP files.
- Local homepage returned HTTP 200.
- Local Apache returned 403 for `/php/`, `.git/HEAD`, `database/schema.sql`, and a `.bak` URL after the root `.htaccess` hardening.
- The embedded privacy-notice endpoint returned HTTP 200 with `X-Frame-Options: SAMEORIGIN`; other pages retain `DENY`.
- JavaScript syntax could not be linted because Node.js is not installed in this environment.

## Completion evidence still to collect

- Mark all relevant scenarios PASS with dates and evidence; note any FAIL and fix it before sign-off.
- Keep the test run in a non-production database and use test-only records.
- Obtain any required instructor demonstration, signature, or approval.
- Fill and approve the official privacy contact and retention schedule before publishing the privacy notice.
- Add actual dates and genuine commit IDs to the development journal; do not fabricate history.
