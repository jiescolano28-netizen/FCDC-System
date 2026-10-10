# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Accounting and operations staff working with the company's operational, sales, inventory, tax, and administrative workflows. Specific job titles and role-specific responsibilities are not established.

## Product Purpose

A web system for Fabellion Construction and Development Corp. company operations. The confirmed goal is supporting live company work; parts of the current application are not yet production-ready.

## Positioning

No distinct product mechanism or market positioning has been established.

## Operating Context

The application includes workflows for point of sale, inventory management, dashboard and reports, VAT records and preparation, accounting, employees and roles, activity logs, and company settings. Staff authenticate to the application; permissions gate multiple workflows. Specific operating procedures and deployment context are not established.

## Capabilities and Constraints

- The codebase is a Laravel 12, PHP, and Livewire web application.
- Inventory records are persisted and support management and overview workflows; the POS provides cart and checkout interactions.
- Employee and role administration uses an explicit permission catalog. Employee records are soft-deleted, and administrator protections are enforced.
- Current accounting screens state that real accounting balances, postings, and reports are not implemented. Demo journal sessions and POS sales are not accounting postings.
- VAT Records persists one immutable record per completed POS sale, including historical POS sales; browsing and details require `tax.view` and report Manila completion dates. VAT Summary aggregates those saved records by Manila month or calendar quarter, with a POS-only payable estimate. Tax Report provides POS-sourced monthly, quarterly or inclusive custom-range transaction reports, details, CSV export and browser printing. VAT Return Preparation remains illustrative/demo-backed. These tax screens are not complete company results, official returns or filed submissions.
- Current product purpose is live operations, but the repository's sample-data and unimplemented-module limits remain in force until those workflows are made production-ready.
- The app uses Philippine VAT terminology and a 12% default tax rate in company settings. Tax/legal compliance requirements beyond the repository's current examples are not established.
- The current company settings and application navigation identify Fabellion Construction and Development Corp.; a tax-return page contains a differing spelling that is not treated as authoritative.
- Specific production deployment, data-retention, and accessibility requirements are not established.

## Brand Commitments

The company name is Fabellion Construction and Development Corp. The application includes a company logo at `public/image/company-logo.png`. The repository presents an existing web identity; no additional binding voice or brand requirements were provided.

## Evidence on Hand

- Repository workflows and route definitions for dashboard/reporting, POS, inventory, VAT/tax, accounting, employee/role administration, activity log, and settings.
- Company logo at `public/image/company-logo.png` and construction image at `public/image/construction-bg.jpg`.
- Several accounting views explicitly disclose that real accounting calculations or records are unavailable. VAT Return Preparation and sales-report series remain illustrative; the POS Tax Report uses recorded POS VAT only.
- Do not fabricate verified financial results, VAT filings, customer evidence, or accounting records from the sample content.

## Product Principles

- Keep operational workflows useful to accounting and operations staff.
- Clearly distinguish persisted operational records from session-only, illustrative, and unavailable data.
- Do not represent sample or incomplete accounting and tax outputs as verified company results.
- Preserve permission boundaries and the protected administrator safeguards.

