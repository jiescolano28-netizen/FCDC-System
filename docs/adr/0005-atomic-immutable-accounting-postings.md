# Atomic immutable source-linked accounting postings

Supported source completion and its exactly-once balanced accounting entry must succeed or fail together, rather than allowing completed POS sales, supplier invoices or disbursements to wait in a separate accountant-posting queue. Missing approved mappings or failed validation block completion; posted entries cannot be edited/deleted, and linked reversals/replacements preserve history instead of privileged rewrites. AP and Inventory are control accounts: openings, payments, purchase corrections and valuation changes update their supporting schedules and GL atomically, while preparation/posting permissions allow authorized accountants to post their own manual journals without mandatory maker-checker approval.

This is a confirmed design decision, not evidence of implementation. Period closure and dependency-aware reopening rules are defined in [the accounting workflow design](../accounting-workflow-design.md).
