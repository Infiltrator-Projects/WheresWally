# Changelog

## 1.1.4 — 2026-08-25

- Added mandatory server-side 6272/6273 filtering before the History API result limit.
- Made date inputs explicit Zabbix receipt-date filters, matching `history.get` semantics.
- Moved date-to-epoch conversion from browser JavaScript to PHP so boundaries use the active Zabbix frontend timezone.
- Preserved the Windows source event timestamp as the primary event time and exposed Zabbix receipt time in Details.
- Changed the row-limit configuration field to Zabbix's bounded integer field type (10–200).
- Added CSV formula-prefix neutralisation for spreadsheet-safe exports.
- Added independently testable History API query construction and client-side CSV regression coverage.
- Added a native Debian/Ubuntu/Linux Mint `.deb` package builder.
- Added GitHub Actions validation for tests and both installer formats.
- Updated documentation for the unavoidable cost of unbounded free-text History API searches.

## 1.1.3 — 2026-08-19

- Changed retained-history search submission to explicit Enter-key execution.
- Removed the 500 ms typing debounce so no database query is issued merely because typing stops.
- Prevented Enter from submitting or reloading the dashboard page; it triggers only the widget AJAX update.
- Search and date fields can all submit the same bounded 200-row retained-history query.

## 1.1.2 — 2026-08-19

- Moved text filtering from the loaded browser rows to server-side Zabbix History API search.
- Searches now span all retained history for the selected NPS log item while returning at most 200 newest matches.
- Added optional From/To date-range constraints for large historical investigations.
- Added 500 ms text-search debounce and made active historical searches user-driven instead of repeating on every dashboard refresh.
- Preserved the normal live view as a lightweight newest-events request.
- Added exact shortcuts for 6272/Grant and 6273/Deny searches.
- Preserved CSV export, Clear, Details and automatic item discovery.
- Standardised project attribution to Shannon Smith and Carlo Cunanan.

## 1.1.1 — 2026-07-31

- Removed the install-time dependency on the PHP command-line interpreter.
- Retained strict manifest identity checks using standard appliance shell tools.
- Made PHP syntax validation conditional: it runs when PHP CLI is present and is reported as skipped when it is not.
- Added installer regression coverage for Zabbix appliances that provide PHP-FPM without a `php` executable.
- Preserved the module identifier so existing dashboards continue to use the upgraded widget.

## 1.1.1 — 2026-07-31

- Standardised project attribution and distributable metadata.
- Refactored Windows event parsing into an independently testable class.
- Added strict PHP typing and explicit component responsibility boundaries.
- Added defensive item-ID normalisation and bounded history requests.
- Added connection-request policy to expanded event details.
- Improved accessibility with table semantics, focus visibility, live row counts and disclosure state.
- Improved CSV interoperability with UTF-8 BOM output.
- Documented non-destructive Clear semantics.
- Added architecture, installation, operations, data dictionary, testing, security and development documentation.
- Added automated static validation and parser regression tests.
- Reworked the installer to validate staged content and preserve rollback backups.

## 1.0.0 — 2026-07-31

- Initial working Zabbix 7.0 dashboard widget.
- Added live NPS 6272/6273 table, filtering, CSV export, Clear and details view.
