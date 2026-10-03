# 08: VAT Summary

**What to build:** A separate VAT Summary demonstration page preserving the monthly/quarterly selector, available periods, summary cards, and reporting totals.

**Blocked by:** 07: VAT Records.

**Status:** ready-for-agent

- [ ] VAT Summary has its own authenticated named route and Tax Compliance-grouped Livewire page/template using the shared shell and current appearance.
- [ ] Livewire monthly/quarterly switching updates the period options and selected summary. Selecting a known period displays the existing illustrative taxable sales, VAT, and total values for that period.
- [ ] Reuse the established illustrative VAT data/conventions; preserve the existing summary behavior rather than adding statutory calculations, persisted sales aggregation, or automatic ingestion of POS demo sales.
- [ ] The page visibly identifies the summary as illustrative/internal and not evidence of actual taxable activity or a filed return. No tax or sales business records are written.
- [ ] Smoke both period modes and multiple known periods, direct entry, refresh, navigation back to VAT Records, and guest protection in the actual browser.
