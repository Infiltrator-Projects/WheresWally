# Operations guide

## Normal live operation

With Search, Received from and Received to blank, WHERE'S WALLY displays the newest NPS events. With **Auto-scroll** enabled it polls once per second and follows the newest event. Disable **Auto-scroll** to hold the current display completely still. Recommended Rows value: `200`.

## Historical search

Type into **Search retained history...**, then press Enter. The module submits the query to `WidgetView.php`, which searches retained NPS log history through Zabbix `history.get`. Every query is restricted server-side to event IDs 6272 and 6273 before the enforced 200-row result limit.

Use **Received from** and **Received to** to restrict the query by the time Zabbix received the log record. These controls intentionally do not filter on the Windows source event timestamp because the History API date bounds operate on receipt time. Zabbix receipt time is also visible in Details.

Useful exact searches:

```text
6272 / grant / granted   Grant events only
6273 / deny / denied    Deny events only
```

Other text searches match the raw NPS event message, which contains the account, name/FQAN, access-point identifiers, MAC address, IP address, policies and authentication details used to build the displayed columns. On very large retention sets, use a receipt-date range to reduce database work.

While SEARCH mode is active, the historical query is not repeated on every dashboard refresh. Change the search text/date and press Enter or choose **Search** to run another query, or choose **Reset** to return to live/hold mode.

## Other toolbar controls

**Export** downloads the currently displayed rows as UTF-8 CSV. Formula-like leading characters are neutralised so spreadsheet applications do not evaluate event data as formulas.

**Clear view** hides the currently displayed rows in that browser instance; it never deletes monitoring history.

**Auto-scroll** is the live/hold switch. Checked = one-second live updates and follow the newest row. Unchecked = HOLD the current rows in place without periodic replacement. Hidden browser tabs skip one-second live requests and refresh again after the tab becomes visible.

**Details** expands the raw event and parsed authentication metadata, including Zabbix receipt time. The footer also shows visible event, Grant and Deny counts plus the browser-local time of the last successful render.

## Fault isolation

If the widget cannot locate the NPS item, edit the widget and explicitly select a log item. A broader Security-log item is supported; the controller still filters the History API query to 6272/6273.

If live mode has no rows, check `Monitoring → Latest data → Server NPS → History` and confirm events 6272/6273 are arriving.

If a historical search returns nothing, clear the receipt-date range and retry a known account/MAC/IP value. Remember that Zabbix can only search history that remains inside its configured retention period.

After module upgrades, use `Administration → General → Modules → Scan directory`, confirm the module is enabled, then press `Ctrl+F5`.
