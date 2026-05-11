# QA Testing Checklist

This checklist defines formal QA coverage for production-readiness testing of the CMMS application.
It includes authentication, license activation, purchase request, inventory, and work order workflows.

## 1. Authentication

- [ ] Verify login page loads over HTTPS and displays no debug information.
- [ ] Confirm form includes CSRF protection hidden input.
- [ ] Validate valid credentials allow access and initiate secure session.
- [ ] Verify invalid credentials are rejected with a generic error.
- [ ] Confirm locked/inactive accounts cannot log in.
- [ ] Ensure logout destroys session and removes cookies.
- [ ] Verify `session.cookie_secure`, `session.cookie_httponly`, and `session.cookie_samesite` are active in production.

## 2. License Activation

- [ ] Confirm the license gate page renders and accepts a 16-character license key.
- [ ] Validate malformed license key input produces an appropriate error.
- [ ] Validate inactive or non-existent license keys are rejected.
- [ ] Confirm licensed users are activated and session state is updated.
- [ ] Confirm license activation is audited in `license_audit_log`.
- [ ] Ensure developer bypass flags are disabled in production.

## 3. Purchase Request Flow

- [ ] Verify purchase request creation with valid item lines and required fields.
- [ ] Confirm purchase request saves as draft when requested.
- [ ] Validate the submission workflow places PRs into pending approval.
- [ ] Confirm approval requires permission and unauthorized attempts are blocked.
- [ ] Validate approval action updates status and audit data correctly.
- [ ] Confirm PR details and line items are retrievable by the request ID.

## 4. Inventory Workflow

- [ ] Confirm inventory items and vendors can be selected for purchase request creation.
- [ ] Verify inventory quantity, part cost, and metadata fields populate correctly.
- [ ] Confirm purchase order creation from a PR is successful and links to the correct PO/PR.
- [ ] Validate work order references and metadata are preserved on inventory transactions.
- [ ] Confirm stock reservation and issuance correctly update inventory balances.

## 5. Work Order Workflow

- [ ] Verify work order creation, editing, and completion flows work end-to-end.
- [ ] Confirm work orders can be linked to purchase requests and inventory reservations.
- [ ] Validate approval and completion actions update work order status correctly.
- [ ] Confirm completed work orders trigger spare issuance and inventory reduction.
- [ ] Check that technicians without approval rights cannot update restricted workflows.
- [ ] Verify work order reporting and dashboard metrics reflect the workflow state.

## 6. Automated Test Coverage

- [ ] Run `php tests/run_tests.php` and confirm all checks pass.
- [ ] Run `php tools/lint_all.php` to verify repository syntax coverage.
- [ ] Confirm CI workflow `.github/workflows/php-ci.yml` passes on push/pull requests.

## 7. Release Readiness

- [ ] Confirm documentation is up-to-date in `README.md`, `PRODUCTION_DEPLOYMENT_CHECKLIST.md`, and `QA_TESTING_CHECKLIST.md`.
- [ ] Validate backup and restore scripts before deployment.
- [ ] Ensure staging environment mirrors production config and QA results before production release.
