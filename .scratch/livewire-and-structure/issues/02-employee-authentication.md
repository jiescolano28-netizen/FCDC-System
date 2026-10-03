# 02: Employee Authentication and Protected Application Shell

**What to build:** Employees with existing Employee accounts can sign in, reach authenticated application screens, and log out, while guests are redirected to login. Public registration is unavailable; employee creation is deferred to a future internal workflow.

**Blocked by:** 01: Livewire Foundation and Public Homepage.

**Status:** ready-for-agent

- [x] Login uses the existing Employee account and authentication provider; public registration is unavailable.
- [ ] Registration validation and internal employee creation are deferred.
- [x] Login preserves remember-me behavior; successful login regenerates the session, and logout invalidates it.
- [x] Protected application routes require authentication and have named routes.
- [x] The authenticated shell uses the shared responsive green-and-gold interface and navigates between supported screens.
- [x] Feature coverage verifies login, failed credentials, remember-me where supported, logout, guest protection, and absence of public registration.
