# Livewire Module Pages

**Status:** Migration design and 19-ticket breakdown approved by the user; all 19 individual tickets published with `ready-for-agent` status. Application implementation has not started.

## Goal and scope

Move the Dashboard, Inventory, POS, Tax Compliance, Accounting, and Settings screens out of the dashboard monolith into separately routed Livewire pages grouped by module. This is a page and interaction migration, not delivery of the broader persisted business application.

Preserve the current green-and-gold appearance, responsive behavior, and existing working interactions. Use Livewire for forms, filters, modals, cart actions, and existing demo journal actions. Retain narrowly scoped JavaScript for charts, printing, and image viewing. Do not redesign the interface or invent accounting behavior.

## Relationship to the earlier effort

Track this effort separately from the existing Livewire application/structure tickets; leave those tickets unchanged. Their persisted POS/settings/reporting work remains separate. This effort explicitly permits clearly labeled demonstration pages, including existing Accounting and VAT Return Preparation interfaces, but does not authorize production accounting, statutory returns, BIR filing, or a complete-application release. The earlier production release restrictions remain in force.

## Observed current state

- Livewire 3.8.10 is installed, with class-based full-page components and Blade component templates.
- Dashboard still renders the monolithic Blade view.
- Inventory already has a working Livewire management page and persisted nullable selling prices.
- The current inventory JSON CRUD endpoints and the management page are authenticated.
- POS checkout changes browser state only; company settings are not durable business records; VAT and sales charts include illustrative fixtures; Accounting mixes sample reference accounts, demo journal interactions, and unavailable actions.
- No shared authenticated layout for all six modules currently exists.
- A runtime `php artisan route:list --except-vendor` check showed the existing homepage, dashboard, management, inventory JSON, login, and logout routes. This was a current-state check, not verification of migrated pages.

## Page inventory: 18 authenticated screens

| Module | Separate pages |
| --- | --- |
| Dashboard | Dashboard; Reports |
| Inventory | Inventory overview; Inventory management |
| POS | Point of sale |
| Tax Compliance | VAT Records; VAT Summary; Tax Report; VAT Return Preparation (2550Q demonstration) |
| Accounting | Accounting Overview; Chart of Accounts; Journal Entry; Accounts Payable; Cash Disbursements; General Ledger; Trial Balance; Financial Statements |
| Settings | Company settings, retaining the existing illustrative team-member list |

Each screen gets its own authenticated named route. Sidebar entries and accounting quick actions navigate to routes rather than switching hidden sections. Direct entry, refresh, browser Back/Forward, active navigation, and expanded parent navigation must work. Do not render every module's content or script on every page.

## Conventional organization

Group classes under `app/Livewire/Dashboard`, `Inventory`, `Pos`, `TaxCompliance`, `Accounting`, and `Settings`. Mirror the groups using conventional lowercase/kebab-case template directories under `resources/views/livewire/`.

Reuse one authenticated Blade layout with the existing sidebar, branding, employee identity, responsive shell, and logout form. Extract shared styles into Vite-managed assets; keep module-specific script initialization local to the pages that need it. Reuse the existing Inventory management component's working behavior instead of creating a second implementation.

Keep `/dashboard` and its `dashboard` route name. Keep `/inventory/manage` and `inventory.management`. Keep the existing inventory JSON CRUD paths and response contracts intact; use a non-conflicting URL such as `/inventory/overview` for the overview page. Leave homepage and authentication functionality outside this effort except for shared authenticated navigation and demo-state cleanup at logout.

## Demo state and data boundaries

- Keep demo carts, completed demo sales, simulated POS stock reductions, journal draft/history, and company-setting edits in the signed-in session across page navigation and refresh.
- Tabs in the same signed-in session share this state. Clear it at logout and prevent a subsequent employee from inheriting it. It is temporary demonstration state, not business-record persistence.
- Preserve existing illustrative fixtures as fixtures. Never seed their values into business tables or represent them as recorded activity. Keep fixed illustrative charts and VAT fixtures distinct from session demo sales; this migration does not add a real reporting pipeline.
- Inventory overview, Inventory management, and inventory quantities/value/low-stock metrics on Dashboard and Reports use persisted Inventory records.
- Example: with 10 persisted units, selling 2 in demo POS leaves the database and real Inventory screens at 10. POS may show a session-only simulated remainder of 8. Demo checkout must not call real inventory writes.
- Preserve existing real Inventory create/edit/delete, searches, category filters, images, and selling-price behavior.
- Retain the existing demo checkout calculation rather than adding a paid-sale or payment workflow. Show temporary/demo status on checkout feedback and printed receipts.
- Dashboard and Reports may display clearly labeled illustrative/session demo sales; these are not recorded business sales. Inventory metrics must remain clearly distinct from demo sales metrics.
- Company settings are temporary session values. Save feedback must say so, and existing receipt use of those details must continue across routes. Do not add company-settings tables or employee-management functionality.
- Preserve existing VAT search, period/date filtering, record details, monthly/quarterly summary selection, internal report printing, and return-preparation demonstration. Keep demo/non-filing limitations visible on screens and printed documents. Do not claim the printed 2550Q demonstration is a complete or official return.
- Preserve existing demo journal line editing, balancing/validation behavior, draft clearing, and history in the session. Do not invent ledger posting, payable/disbursement lifecycles, computed financial statements, or automated POS accounting.
- Unsupported Add/Edit Account, Add Payable, Add Disbursement, and similar placeholder actions remain visibly unavailable with an explanation, rather than clickable backend-integration alerts. Existing empty Accounting views explain their non-operational status; do not present zero balances as real financial results.

## Verification requirements for implementation tickets

Every completed slice must be independently navigable and verifiable, not a component scaffold. Exercise its actual browser surface with an authenticated employee and verify guest protection for new pages.

Cover relevant consumer-visible boundaries: Inventory CRUD persists; demo checkout leaves persisted quantities untouched; demo state survives navigation/refresh and clears across logout/account changes; journal validation/history remain demo-only; filters select the expected records; shared charts initialize only on their own pages; printed documents retain their content and demo disclaimers.

Use existing behavior-focused test conventions where regression coverage is needed. Do not test implementation wiring, copied markup, or exact wording. Check existing tests affected by route/component moves. End with an actual cross-module browser smoke, including narrow-screen navigation and print preview. An intermediate ticket is not proof of complete migration.

## Completion boundary

All 18 screens are individually routed and module-grouped, including the existing management page. Every migrated screen uses the shared shell and appropriate Livewire interactions. Remove obsolete monolith sections, global screen-switching code, unused modals/assets, and unused layout duplicates after all consumers migrate. Preserve the live inventory JSON contracts and existing authentication contracts; do not introduce permanent compatibility shims.

No persisted sale, company-settings, VAT, or accounting feature is added by this effort. No existing broader ticket or parent issue is closed or rewritten. Publish the approved new tickets as individual local Markdown files with explicit blockers and `ready-for-agent` status.

## Approved ticket structure and delivery

Publish one ticket per screen, plus one final cutover ticket: 19 tickets total. The Settings slice establishes the shared authenticated shell and session/logout conventions. Keep application changes on an integration branch until final cross-module verification; each intermediate page must nevertheless be directly navigable and independently verifiable. Never claim the full migration is complete from intermediate page checks.

The approved numbering and blocking edges are:

| Ticket | Page / delivery | Blocked by |
| --- | --- | --- |
| 01 | Settings and shared shell | None |
| 02 | Inventory Management module migration | 01 |
| 03 | Inventory Overview | 02 |
| 04 | POS | 01 |
| 05 | Dashboard | 04 |
| 06 | Reports | 04 |
| 07 | VAT Records | 01 |
| 08 | VAT Summary | 07 |
| 09 | Tax Report | 07 |
| 10 | VAT Return Preparation | 07 |
| 11 | Accounting Overview | 01 |
| 12 | Chart of Accounts | 01 |
| 13 | Journal Entry | 12 |
| 14 | Accounts Payable | 01 |
| 15 | Cash Disbursements | 01 |
| 16 | General Ledger | 12 |
| 17 | Trial Balance | 01 |
| 18 | Financial Statements | 01 |
| 19 | Complete cutover and verification | 03, 05, 06, 08, 09, 10, 11, 13, 14, 15, 16, 17, 18 |
