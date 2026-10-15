# 02: Seed the complete proposed chart safely

**What to build:** On a verified isolated demo target, an administrator can explicitly seed all 54 proposed accounts and inspect them through the existing Chart of Accounts. Repeated setup preserves legitimate account state and never fabricates business activity or approvals.

**Blocked by:** 01: Verify demo database isolation

**Status:** implementation-complete; verified isolated target provisioning is still required

- [x] The seed operation adds exactly the 54 specified codes and preserves the PDF names, categories, qualifications, and pending-treatment explanations; it does not add supporting accounts.
- [x] New accounts are inactive, unapproved, non-postable, and have no approver, approval timestamp, usage timestamp, mappings, opening balances, or transactions.
- [x] Exact existing definitions are reported without changing operational state or history; conflicting definitions, including unsafe state on held accounts, are reported before mutation and leave the batch unchanged.
- [x] Missing accounts are inserted transactionally; an unchanged rerun creates no accounts and changes no existing account or transaction metadata.
- [x] The result distinguishes created accounts, exact matches, conflicts, and preserved unrelated/supporting accounts; ordinary application seeding does not invoke this demo seeder.

**Implementation:** `php artisan demo:seed-proposed-chart` verifies isolation before writes and reports the seed result. The proposed chart seeder is not included in `DatabaseSeeder`. README documents the explicit setup path.

**Verification:** `php artisan test --compact tests/Feature/ProposedChartOfAccountsSeederTest.php` — 7 passed, 51 assertions. `php vendor/bin/pint --test app/Console/Commands/SeedProposedChartOfAccounts.php database/seeders/ProposedChartOfAccountsSeeder.php tests/Feature/ProposedChartOfAccountsSeederTest.php` passed. Full suite: 275 passed, 4 failed in existing chart empty-state wording, accounting activation/cutover, and `stockItemId` coverage. The live seed command refused the current unallowlisted environment; no persistent demo target was seeded or visually inspected.
