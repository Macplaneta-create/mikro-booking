# WP.org Release Checklist

Uzupełnienie do `RELEASE_CHECKLIST.md` z naciskiem na wymagania repozytorium WordPress.org.

## Security hardening
- [ ] Ensure no maintenance/emergency scripts are auto-loaded in runtime.
- [ ] Remove or gate debug endpoints and local repair scripts from release package.
- [ ] Keep public endpoints protected with rate limiting + captcha.
- [ ] Verify all privileged actions use capability checks and nonce verification.
- [ ] Verify no sensitive data is written to public web paths without deny rules.

## Packaging
- [ ] Exclude `vendor/bin`, test fixtures, local scripts, and any `node_modules` from release zip.
- [ ] Ensure built admin assets exist in `assets/admin/` for production.
- [ ] Verify plugin header version matches `readme.txt` stable tag.
- [ ] Ensure `readme.txt` follows WordPress.org format.

## Code quality
- [ ] Run `composer test` (or at least critical integration tests).
- [ ] Run PHPCS against WordPress standard if used in CI.
- [ ] Validate activation/deactivation/uninstall flows.

## Final manual checks
- [ ] Fresh install on clean WP instance.
- [ ] Upgrade test from previous plugin version.
- [ ] Public booking flow end-to-end test.
- [ ] Admin export/download and settings flow test.