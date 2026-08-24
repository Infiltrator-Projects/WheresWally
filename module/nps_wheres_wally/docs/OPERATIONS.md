# Operations guide

## Normal live operation

With Search, From and To blank, WHERE'S WALLY displays the newest NPS events and follows the Zabbix dashboard refresh interval. Recommended Rows value: `200`.

## Historical search

Type into **Search retained history...**, then press Enter. The module submits the query to `WidgetView.php`, which searches the retained NPS log history through Zabbix `history.get`. Only the newest matching rows are returned, with an enforced maximum of 200.

Use **From** and **To** to restrict the search to a date range. Date-only searches are supported.

Useful exact searches:

```text
6272 / grant / granted   Grant events only
6273 / deny / denied    Deny events only
```

Other text searches match the raw NPS event message, which contains the account, name/FQAN, access-point identifiers, MAC address, IP address, policies and authentication details used to build the displayed columns.

While SEARCH mode is active, the historical query is not repeated on every dashboard refresh. Change the search text/date and press Enter to run another query, or choose **Reset search** to return to live mode.

## Other toolbar controls

**Export** downloads the currently displayed rows as UTF-8 CSV.

**Clear** hides the currently displayed rows in that browser instance; it never deletes monitoring history.

**Auto-scroll** returns the table to the newest row after a content refresh.

**Details** expands the raw event and parsed authentication metadata.

## Fault isolation

If the widget cannot locate the NPS item, edit the widget and explicitly select the log item. Confirm the item type is `Log`.

If live mode has no rows, check `Monitoring → Latest data → Server NPS → History` and confirm events 6272/6273 are arriving.

If a historical search returns nothing, clear the date range and retry a known account/MAC/IP value. Remember that Zabbix can only search history that remains inside its configured retention period.

After module upgrades, use `Administration → General → Modules → Scan directory`, confirm the module is enabled, then press `Ctrl+F5`.
