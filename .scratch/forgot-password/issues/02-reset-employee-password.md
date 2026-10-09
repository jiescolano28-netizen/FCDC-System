# 02: Reset an employee password from a valid link

**What to build:** An employee can follow a valid reset link, choose a new password, and return to the login page; invalid or expired links cannot change credentials.

**Blocked by:** 01 — Request an employee password-reset link.

**Status:** ready-for-agent

- [ ] Opening a valid reset link renders new-password and confirmation fields; invalid or expired links show a clear error.
- [ ] Passwords must match and contain at least 8 characters.
- [ ] A successful reset updates the employee password and remember token, consumes the reset token, and redirects to `/login`.
- [ ] A consumed token cannot be used to reset the password a second time.
