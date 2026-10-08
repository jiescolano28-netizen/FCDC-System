# Audit only persisted changes with safe field diffs

The application records successful changes to persisted Inventory, Employee, and Role data in one centralized activity log; illustrative/session-only workflows and page views are excluded so the audit trail cannot imply that demos are recorded business transactions. View access is granted globally through `activity-log.view`, while field-level before/after values are retained for ordinary attributes but credentials and tokens are never recorded. This favors useful change history over metadata-only logging without storing secrets.
