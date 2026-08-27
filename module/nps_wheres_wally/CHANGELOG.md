# Changelog

## 1.1.9 — 2026-08-27

- Added an explicit visible build identity to the widget footer.
- Identifies the Zabbix module as Generic / architecture-independent because it is PHP/JavaScript rather than CPU-native machine code.
- Added matching Debian package metadata without changing monitoring behaviour.

## 1.1.8 — 2026-08-25

- Fixed two v1.1.7 runtime symbol typos that caused the Zabbix widget request to fail and remain on the loading spinner.
- Corrected `AccessPointIdentity::hwstHasMac()` to `AccessPointIdentity::hostHasMac()`.
- Corrected `MAXIMUM_MAD_FALLBACK_LOOKUPS` to `MAXIMUM_MAC_FALLBACK_LOOKUPS`.
- Added a source-contract regression test that verifies every `AccessPointIdentity::method()` reference exists and every `self::CONSTANT` used by `WidgetView` is declared.
- Kept the v1.1.7 AP-correlation design unchanged: exact inventory MAC first, exact current interface IP as fallback, and no approximate MAC guessing.

## 1.1.7 — 2026-08-25

- Fixed stale AP/location identity caused by presenting NPS `Client Friendly Name` as current location.
- Added current Zabbix host correlation for every displayed NPS event.
- Treats the Called-Station-Identifier BSSID/MAC as primary identity evidence when it exactly matches Zabbix host inventory.
- Uses exact monitored Zabbix host-interface IP as a bounded fallback when MAC inventory is unavailable.
- Performs bounded inventory-MAC searches only when IP is absent or conflicts with populated MAC inventory, preserving one-second LIVE performance.
- Never guesses approximate/chassis-adjacent MACs; unresolved APs are shown as `Not found in Zabbix` instead of a stale NPS label.
- Added format-neutral MAC normalisation and regression tests for access-point identity matching.
- Preserved the original NPS event unchanged in Details for forensic evidence.

## 1.1.6 — 2026-08-25

- Completed a forensic source, comments, lifecycle, packaging and interface review.
- Expanded source commentary around Zabbix lifecycle ownership, LIVE/HOLD/SEARCH invariants, parser fallbacks, receipt-time semantics and installer rollback behaviour.
- Removed the obsolete parser-side synthetic search field left behind after retained-history search moved into the Zabbix History API.
- Prevented Zabbix's native refresh interval from running alongside the module-owned one-second LIVE scheduler after a successful render.
- Suspended one-second LIVE requests while the browser tab is hidden and resume automatically when it becomes visible.
- Added an explicit Search button while preserving Enter-to-search behaviour.
- Polished the toolbar, LIVE/HOLD/SEARCH status badge, Auto-scroll switch, table scanning, result badges, detail panel and responsive layout.
- Added visible event/Grant/Deny counts and a browser-local last-updated indicator.
- Made CSV headings follow the rendered/translated table and extended formula neutralisation to whitespace-prefixed payloads.
- Made `manifest.json` the single release-version source for build scripts, tests and CI to prevent package/manifest drift.
- Expanded DST and client regression coverage and modernised GitHub Actions packaging.
- Corrected the historical comprehensive-refactor changelog heading from 1.1.1 to 1.1.0.

## 1.1.5 — 2026-08-25

- Restored the intended Auto-scroll semantics: checked means live feed; unchecked means hold the current display.
- Added a widget-owned one-second near-live polling loop instead of relying on Zabbix's minimum 10-second dashboard refresh interval.
- Auto-scroll OFF now stops the live poll, aborts any in-flight live request and prevents periodic Zabbix refreshes from replacing the held rows.
- Re-enabling Auto-scroll triggers an immediate update and resumes one-second polling.
- Historical searches remain explicit Enter-key requests and automatically suspend live polling.
- Added LIVE / HOLD / SEARCH status handling and regression tests for live-hold request gating.

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

## 1.1.0 — 2026-07-31

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
