# Testing strategy

## Static validation

- `manifest.json` parses and identifies version 1.1.4 and the two project authors.
- Every PHP file passes `php -l`.
- Production and test JavaScript pass `node --check`.
- Installer/build shell scripts pass `bash -n`.

## Parser regression

`tests/NpsEventParserTest.php` validates representative 6272 and 6273 messages and the extraction/normalisation contract.

## History-query regression

`tests/HistoryQueryBuilderTest.php` verifies that every History API request is restricted to event IDs 6272 and 6273 before the result limit is applied. It also verifies exact Grant/Deny shortcuts, free-text search construction and receipt-time bounds.

## Receipt-date and timezone regression

`tests/ReceiptDateRangeTest.php` validates date parsing, reversed ranges, invalid dates and timezone-aware inclusive day boundaries. It includes the Melbourne daylight-saving transition to ensure the implementation does not assume every local day is exactly 86,400 seconds.

## Client regression

`tests/WidgetClientTest.js` executes the widget class in a minimal Node test harness. It verifies spreadsheet-formula neutralisation in CSV cells and confirms date strings are sent to PHP without browser-local epoch conversion.

## Package validation

When `dpkg-deb` is available, `tools/test.sh` builds the `.deb`, validates its metadata and confirms the Zabbix module manifest is installed at the expected path. GitHub Actions also builds both the `.run` and `.deb` installers.

## Historical-search acceptance

1. Confirm normal mode displays newest events and continues to refresh.
2. Search for a known account older than the normal 200-row live window.
3. Confirm the matching old record is returned, demonstrating server-side retained-history search.
4. Search `6272` and confirm only Grant records.
5. Search `6273` and confirm only Deny records.
6. Select a broader Security log item and confirm unrelated event IDs cannot consume the 200-row result window.
7. Apply **Received from/Received to** dates and confirm filtering follows Zabbix receipt time while the main Time column retains the Windows source timestamp.
8. Confirm no more than 200 event rows are returned.
9. Leave SEARCH mode open through several dashboard refresh intervals and confirm the displayed result set is not repeatedly replaced.
10. Choose Reset search and confirm normal live updating resumes.
11. Verify Clear, Details and CSV Export still operate.

## Running source tests

```bash
./tools/test.sh
```
