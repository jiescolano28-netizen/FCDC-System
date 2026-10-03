# 13: Journal Entry

**What to build:** A standalone Journal Entry demonstration using Livewire for the existing line editing and balancing checks, with draft/history retained temporarily across routes and refresh.

**Blocked by:** 12: Chart of Accounts.

**Status:** ready-for-agent

- [ ] Journal Entry has its own authenticated named route and Accounting-grouped Livewire page/template using the shared shell and current form/history appearance.
- [ ] Date, reference, source, description, account selection, line descriptions, debit/credit editing, add/remove-line limits, totals, and Clear retain their existing working behavior through Livewire.
- [ ] Preserve existing rejection of missing required header fields, conflicting debit/credit on a line, and unbalanced/non-positive totals. A valid balanced demonstration appears in session history; do not invent additional accounting policies.
- [ ] Draft and demo history survive navigation/refresh in the signed-in session and clear at logout. A subsequent employee does not inherit them; tabs follow the shared session convention.
- [ ] Action feedback and history distinguish demo journal entries from real posted entries. No account, payable, disbursement, ledger, or other accounting business record is written, and reports do not imply real postings occurred.
- [ ] Smoke invalid and valid balanced entries, line operations, clearing, history after navigation/refresh, direct entry, guest protection, and logout/relogin in the actual browser.
