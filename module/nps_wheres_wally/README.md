# WHERE'S WALLY — NPS Event Monitor

**Authors and maintainers:** Shannon Smith and Carlo Cunanan  
**Version:** 1.1.3  
**Target platform:** Zabbix 7.0 LTS  
**Licence:** GNU General Public License v3.0 or later

WHERE'S WALLY is a custom Zabbix dashboard widget for Microsoft Network Policy Server authentication events. It reads an existing Zabbix log item containing Windows Security events 6272 and 6273, parses the human-readable NPS message, and displays one authentication decision per row.

Event 6272 is shown as **Grant**. Event 6273 is shown as **Deny**, including the NPS reason code when available.

## Capabilities

The widget provides:

- one row per NPS authentication event;
- event time sourced from the Windows event timestamp when available;
- account, domain and resolved display name;
- access-point identifier, location, device MAC address and IP address;
- colour-coded Grant and Deny results;
- normal live view of the newest events;
- server-side search across all retained history for the configured NPS item;
- optional From/To date-range filtering;
- a maximum of 200 rows returned to the browser for any live or search request;
- CSV export of currently visible rows;
- non-destructive Clear behaviour;
- expandable raw Windows event details;
- automatic source-item discovery or explicit item selection.

## Search behaviour

With the search field and date range blank, the widget requests the newest configured number of events, up to 200.

When search text is entered, pressing Enter sends the query to the server. `WidgetView.php` passes the search to Zabbix `history.get`, which searches the retained log-history `value` for the configured NPS item and returns the newest matching rows, capped at 200. The browser does not download the complete history table.

`6272`, `grant`, or `granted` search directly for grant events. `6273`, `deny`, or `denied` search directly for deny events.

The optional **From** and **To** controls add `time_from` and `time_till` constraints to the same History API request. While a historical search is active, the query is user-driven rather than repeatedly re-running on every dashboard refresh. Clearing the search/date controls returns the widget to normal live refresh.

## Required Zabbix item

The default automatic discovery rule expects a log item named:

```text
NPS authentication events 6272 and 6273
```

The recommended item configuration is:

```text
Type:                Zabbix agent (active)
Key:                 eventlog[Security,,,,^(6272|6273)$,,skip]
Type of information: Log
```

The preferred host name is `Server NPS`. A differently named host is supported when the item is selected explicitly in the widget configuration.

## Documentation

Detailed documentation is installed with the module:

- `docs/ARCHITECTURE.md` — component boundaries, data flow and design rationale;
- `docs/INSTALLATION.md` — installation, upgrade, rollback and removal;
- `docs/OPERATIONS.md` — normal administration and fault isolation;
- `docs/DATA_DICTIONARY.md` — source-to-column field mapping;
- `docs/TESTING.md` — verification strategy and test cases;
- `docs/SECURITY.md` — trust boundaries, permissions and data handling;
- `docs/DEVELOPMENT.md` — coding standards and extension guidance.

## Important behavioural notes

The **Clear** button hides events currently visible in that browser widget instance. It does not delete Zabbix history or Windows Security log records.

The **LIVE** indicator applies to the normal unfiltered dashboard view. Historical searches are deliberately user-driven: enter text and/or dates, then press Enter to run the query. This avoids database searches on every keystroke and avoids repeatedly scanning a large retained-history set on the dashboard refresh interval.

The parser currently targets English-language Windows NPS event labels. The raw event is always retained in the Details view, and a missing parsed field is displayed as blank rather than guessed.
