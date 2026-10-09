# 01: Request an employee password-reset link

**What to build:** An employee who cannot sign in can submit their email address and receive a password-reset link, while the confirmation does not reveal whether an account exists.

**Blocked by:** None (can start immediately).

**Status:** ready-for-agent

- [ ] The login page links to a forgot-password page with an email form.
- [ ] Submitting the form sends a reset email to an existing employee account with a password-reset link.
- [ ] Reset tokens are stored hashed and expire after 30 minutes.
- [ ] Existing and unknown email addresses receive the same generic confirmation.
- [ ] The email includes a branded layout and the reset link.
