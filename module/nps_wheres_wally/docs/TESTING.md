# Testing strategy

## Static validation

- `manifest.json` parses and identifies version 1.1.3 and the two project authors.
- Every PHP file passes `php -l`.
- JavaScript passes `node --check`.
- Installer/build shell scripts pass `bash -n`.

## Parser regression

The standalone parser harness validates representative 6272 and 6273 messages and the existing extraction/normalisation contract.

## Historical-search acceptance

1. Confirm normal mode displays newest events and continues to refresh.
2. Search for a known account that is older than the normal 200-row live window.
3. Confirm the matching old record is returned, demonstrating that search is server-side rather than limited to loaded rows.
4. Search `6272` and confirm only Grant records.
5. Search `6273` and confirm only Deny records.
6. Apply From/To dates and confirm results fall inside the range.
7. Confirm no more than 200 event rows are returned.
8. Leave SEARCH mode open through several dashboard refresh intervals and confirm the displayed result set is not repeatedly replaced.
9. Choose Reset search and confirm normal live updating resumes.
10. Verify Clear, Details and CSV Export still operate.

## Running source tests

```bash
./tools/test.sh
```
