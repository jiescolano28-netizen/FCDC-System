Status: ready-for-agent

# Livewire Application and Structure

## Problem Statement

Users currently encounter a Laravel application whose homepage, authentication pages, and large dashboard are implemented as separate Blade pages with embedded styles and JavaScript. Inventory is the only dashboard feature with database-backed CRUD; POS, VAT, reports, and settings are mostly browser-side demonstrations, while accounting pages have placeholders. The application has no Livewire dependency, and its current screens do not share a consistent structure or styling system. The user wants a maintainable, conventionally organized application with real, persisted behavior for the approved business features.

## Solution

Install and integrate Livewire into the existing Laravel 12 application. Reorganize the interface using conventional Laravel locations, named routes for individual screens, shared Blade layouts, and Tailwind/Vite styling unified around the company’s existing green-and-gold identity.

Convert the homepage, custom Employee-backed login and registration forms, and approved dashboard screens to the new application structure. Persist inventory, paid POS sales, VAT summaries, reports, and company settings. Add a selling price distinct from inventory unit cost; existing inventory records remain unsaleable until a user enters a price. Checkout supports paid cash or card sales. Apply a clearly labeled, configurable indicative 12% VAT rate to sales, without presenting the result as statutory tax compliance.

Keep demo records isolated in a visibly marked demo environment and exclude them from production activity and totals. Accounting behavior and screens are deferred until approved accounting rules are supplied. Do not release the requested full application until that prerequisite is met.

## User Stories

1. As a public visitor, I want to open the company homepage, so that I can learn about the company and its services.
2. As a public visitor, I want the homepage navigation and interactive content to work after the styling and page restructuring, so that I can still use the public site.
3. As a prospective employee, I want to register using the existing registration fields, so that I can create an Employee account.
4. As a prospective employee, I want registration validation errors to be shown beside the relevant form, so that I can correct invalid or duplicate values.
5. As a registered Employee, I want to log in with my email and password, so that I can access the authenticated application.
6. As a registered Employee, I want the existing remember-me behavior to remain available, so that I can choose whether my session is remembered.
7. As an authenticated Employee, I want to log out and have my session invalidated, so that my account is no longer accessible from that session.
8. As a guest, I want protected application screens to redirect me to login, so that authenticated business information remains protected.
9. As an authenticated Employee, I want each supported application screen to have a named route and a navigable page, so that I can access it directly and use browser navigation.
10. As an authenticated Employee, I want a shared, responsive interface using the company’s green-and-gold identity, so that pages feel consistent and remain usable across screen sizes.
11. As an inventory manager, I want to view persisted inventory items and their quantities, unit costs, selling prices, and stock status, so that I can manage the materials available for sale.
12. As an inventory manager, I want to create an inventory item with its existing identifying, category, quantity, unit, cost, reorder, image, and selling-price information, so that it can be managed and sold appropriately.
13. As an inventory manager, I want to edit and delete inventory items, so that the inventory listing reflects current business records.
14. As an inventory manager, I want to search and filter inventory, so that I can find the right item quickly.
15. As an inventory manager, I want pre-existing inventory records to retain their data while their selling price remains unset, so that migration does not silently convert cost into a customer price.
16. As an inventory manager, I want checkout blocked for items without a selling price, so that the application cannot create a sale at an unapproved price.
17. As a cashier, I want to search saleable inventory and build a cart, so that I can prepare a customer purchase.
18. As a cashier, I want the application to prevent a completed sale from exceeding available inventory, so that recorded sales and stock levels remain consistent.
19. As a cashier, I want the cart to show subtotal, indicative VAT, and total, so that I can review the amount before checkout.
20. As a cashier, I want to select cash or card and record a paid sale, so that each checkout has an explicit tender type.
21. As a cashier, I want a successful sale to be persisted and deducted from inventory, so that the transaction remains available after navigation or reload and stock reflects the sale.
22. As a cashier, I want checkout validation or persistence failures to leave no partial sale or stock deduction, so that inventory and sales records remain consistent.
23. As a business user, I want sale records to retain the item, quantity, price, tender, date, tax amount, and total used at checkout, so that later summaries do not depend on current inventory prices.
24. As a business user, I want a clearly labeled configurable 12% indicative VAT calculation applied to sales, so that the application can show internal tax-related summaries without claiming filing compliance.
25. As a business user, I want VAT transaction records and monthly or quarterly summaries derived from persisted sales, so that displayed totals reflect actual application records rather than browser fixtures.
26. As a business user, I want internal tax reports to be filterable by period and printable, so that I can review operational summaries.
27. As a business user, I want internal tax screens to state that they are not statutory returns and do not file with a tax authority, so that indicative summaries are not mistaken for compliant filings.
28. As a business user, I want dashboard sales, inventory, low-stock, and reports views to derive from persisted records, so that operational views do not show fabricated sample activity.
29. As a business user, I want reports to support the existing weekly, monthly, and yearly sales views, so that I can inspect activity over the displayed time ranges.
30. As a business user, I want to save company name, address, phone, indicative tax rate, and currency settings, so that company details used by receipts and reports persist between visits.
31. As an application maintainer, I want demo examples available only in a distinct, visibly marked demo environment, so that the demo can be explored without inserting sample activity into production records or totals.
32. As an application maintainer, I want Livewire classes, templates, layouts, routes, and assets to follow Laravel conventions, so that future changes are straightforward to locate and maintain.
33. As an application maintainer, I want large embedded page styles and scripts moved into the Vite-managed frontend structure where appropriate, so that page templates focus on presentation rather than containing the whole application implementation.
34. As an application maintainer, I want accounting navigation and routes withheld until approved accounting behavior is specified, so that users are not offered fake accounting functionality.
35. As an application owner, I want the requested full application release blocked until the accounting specification is supplied and implemented, so that the release does not claim completeness while accounting remains deferred.

## Implementation Decisions

- Add Livewire to the existing Laravel 12 project; do not replace the application with a starter kit.
- Preserve the custom Employee-backed authentication contract. Registration and login continue to use the existing Employee model and configured authentication provider. Implement login and registration interactions with Livewire, retaining session regeneration, logout invalidation, validation, and remember-me behavior.
- Use named routes for individual public and authenticated screens. Keep authenticated business pages behind the existing authentication boundary.
- Use conventional Laravel structure for Livewire page components, models, routes, Blade layouts and templates, reusable Blade presentation components, and Vite-managed CSS/JavaScript. Avoid a custom feature framework or unnecessary abstraction.
- Unify the existing homepage, authentication pages, and application interface around the green-and-gold company identity using Tailwind and Vite.
- Extend Inventory with a distinct selling price. Do not backfill existing records from `unit_cost`; leave their selling price unset and prevent their use at checkout until manually priced.
- Persist sales with the checkout-time item price and tax/total amounts. A completed paid sale uses cash or card tender and reduces the sold inventory quantities. Treat checkout persistence and inventory deduction as one consistent operation.
- Use a configurable, clearly labeled indicative 12% rate for the agreed internal VAT summaries. Do not implement statutory filing, official-return preparation, or tax-compliance claims. Existing tax-rate and currency settings should persist with the other company settings.
- Derive dashboard and operational sales reports from persisted application records. Do not import existing illustrative sales, VAT, or accounting values as production activity.
- Keep demo examples isolated in a separate demo environment, visibly label that environment, and prevent demo records from appearing in production activity, totals, or exports.
- Defer accounting pages, routes, and behavior. No chart of accounts, automated postings, payable/disbursement lifecycle, ledger, trial balance, or financial statements may be invented. Full application release requires an approved chart of accounts, posting rules, payable and disbursement rules, and report definitions.
- Migrate the current in-app inventory interactions from browser-driven requests to Livewire behavior. Before changing existing JSON inventory route contracts, verify whether clients outside the inspected application consume them; preserve any confirmed external contract or coordinate an explicit migration rather than silently breaking it.

## Testing Decisions

- Use one high-level application seam: Laravel feature tests that exercise named HTTP routes and Livewire actions with a real test database. Assert user-visible responses and persistent outcomes, not component internals or source text.
- Cover guest access and authenticated access to protected routes; Employee registration followed by login; validation failures; logout; and remember-me behavior where the session test harness supports it.
- Cover inventory creation, editing, deletion, search/filter results, existing records with no selling price, and the checkout price boundary.
- Cover checkout with cash and card, persisted sale details, inventory reduction, unavailable-stock rejection, and the invariant that a failed checkout leaves both sale records and stock unchanged.
- Cover indicative VAT values and period summaries from actual sales, reports derived from persisted data, and saved company settings after a subsequent request.
- Verify that production views/totals exclude demo records and that demo mode is visibly distinguished. Do not test with production data.
- Keep accounting assertions out of this implementation until its business rules are approved.
- The repository uses Pest with Laravel feature tests. Existing prior art is a minimal feature test for the homepage response; there are no existing Livewire or business-flow tests to extend. Add behavior-focused feature coverage for the new flows rather than pinning internal implementation details.
- After automated checks, smoke the actual application through representative guest, authenticated, inventory, and checkout interactions; automated tests alone do not establish that the Livewire screens render and navigate correctly.

## Out of Scope

- Accounting functionality and a full release before approved accounting rules are supplied.
- Statutory tax filing, generation of official returns, tax-authority submission, or claims of tax compliance.
- Importing browser fixture values as real historical transactions.
- A payment gateway, card processor integration, credit sales, split tenders, refunds, or cash-change workflows; checkout records paid cash/card tender only.
- Replacing the custom authentication system with a Laravel starter kit.
- User roles, permissions, or employee/team administration; the current team-member list is illustrative and does not define an approved management workflow.
- Redesigning or implementing unrelated domain capabilities not represented by the approved current screens.

## Further Notes

- The approved design is documented separately in the project’s Livewire setup and structure proposal. This specification is the implementation contract for that approved scope.
- The project currently has only minimal Pest examples and no glossary or architecture decision records relevant to these features.
- Financial rounding details for the indicative VAT and currency display are not defined by the current approved decisions. Keep the rate explicitly non-statutory; do not present the result as suitable for official accounting or filing. Any statutory or accounting use requires the corresponding approved rules.
- The existing `/inventory` routes currently serve JSON to the in-app dashboard. Their consumers must be checked before migration changes route responses or removes an endpoint.
