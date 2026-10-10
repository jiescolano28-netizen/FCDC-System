# 03: Post and correct persistent manual journals

**What to build:** Staff can create, edit and delete persistent drafts, post balanced manual journals, find posted entries, and correct them by linked reversal/replacement without rewriting history.

**Blocked by:** 02: Review the cutover and opening accounting books.

**Status:** ready-for-agent

- [ ] Capture accounting date, unique journal identifier/reference, manual source, external reference where applicable, description and debit/credit account lines; support detail, search and date/source filtering.
- [ ] Drafts survive logout and have no accounting effects; only drafts can be edited/deleted. Posted entries are immutable and corrections retain reason, actor and distinct linked identifiers.
- [ ] Each posted line has exactly one positive debit or credit; require at least two valid lines, a positive total and exact equality in PHP centavos, with no floating tolerance or automatic plug.
- [ ] Server-side posting requires posting permission, approved active accounts, supported cutover coverage, a nonfuture completed-event date and an open Manila accounting period; preparers cannot impersonate operational sources.
- [ ] Block bare AP/Inventory journals; allow approved GL-only AR and card settlement/fee journals without introducing customer-AR or processor workflows.
- [ ] Authorized posters may post their own entries; automatic source effects later follow source-action authorization rather than requiring accounting-page access.
- [ ] Demonstrate draft persistence, an unequal-entry rejection, a valid posting and a linked correction; keep deterministic tests for balanced/invalid lines, permissions, closed dates and immutable history.
