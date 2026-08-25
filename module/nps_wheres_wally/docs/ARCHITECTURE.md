# Architecture

## 1. Purpose

WHERE'S WALLY converts Microsoft NPS authentication audit records already collected by Zabbix into an operationally useful event table. Version 1.1.4 keeps lightweight live viewing separate from retained-history investigation while making the History API query semantics explicit and testable.

## 2. Data flow

```text
Windows Security log
        │ Event IDs 6272 and 6273
        ▼
Zabbix Agent 2 active eventlog item
        │ Log history records
        ▼
Zabbix retained history
        │
        ├── normal view: newest NPS rows only
        │
        └── text/receipt-date criteria
                ▼
        HistoryQueryBuilder
                │ mandatory 6272/6273 filter
                ▼
        Zabbix History API
                │ server-side filtering, max 200 returned
                ▼
        WidgetView controller
                ▼
        NpsEventParser
                ▼
        widget.view.php
                ▼
        class.widget.js
                ▼
        Zabbix dashboard
```

## 3. Component responsibilities

### `Widget.php`

Registers the default widget name.

### `includes/WidgetForm.php`

Defines persistent dashboard configuration: optional source item and returned-row count. The row count uses Zabbix's integer widget field and is bounded to 10–200.

### `includes/HistoryQueryBuilder.php`

Builds the History API request independently of the Zabbix runtime. Every request applies a server-side `logeventid` restriction to 6272/6273 before the result limit. Exact Grant/Deny searches narrow this to one event ID; other text searches target the retained log `value`.

### `includes/ReceiptDateRange.php`

Validates `YYYY-MM-DD` controls and creates inclusive receipt-time boundaries in the active Zabbix frontend timezone. Day boundaries are timezone-aware and therefore remain correct across daylight-saving transitions.

### `actions/WidgetView.php`

Resolves the source item, converts the receipt-date controls, executes `history.get`, parses returned NPS rows and formats both the Windows source event time and Zabbix receipt time.

### `includes/NpsEventParser.php`

Deterministically extracts NPS fields from English Windows event messages.

### `views/widget.view.php`

Builds the search/receipt-date toolbar, semantic table and expandable detail rows.

### `assets/js/class.widget.js`

Maintains transient search/date state, submits criteria only when Enter is pressed, suppresses repeated periodic historical queries, preserves non-destructive Clear state, and implements Details and spreadsheet-safe CSV export. Date strings are sent to PHP without browser-local epoch conversion.

## 4. Search and date model

Search scope and response size are separate concepts. A free-text search may examine all retained records for the configured item, but the browser receives at most 200 rows. Because Zabbix History API free-text search is inherently a retained-message search, a receipt-date range is useful on very large retention sets.

Exact searches for `6272`, `grant`, or `granted` use event ID 6272; `6273`, `deny`, or `denied` use 6273. Even when a broader Security log item is selected, unrelated event IDs cannot consume the bounded result window.

`history.get` defines `time_from` and `time_till` against Zabbix receipt time, so the controls are labelled **Received from** and **Received to**. The primary **Time** column remains the Windows event source timestamp when available. Receipt time is shown in Details.

## 5. Refresh model

Normal live mode follows the configured Zabbix dashboard refresh interval. Historical queries are deliberately user-driven: changing search text or dates and pressing Enter performs one query, and ordinary periodic widget refreshes do not repeat that same retained-history search. Resetting the criteria returns to live mode.

## 6. Source-item resolution

The controller uses: explicit widget item; otherwise the canonical item on `Server NPS`; otherwise the first accessible exact-name match. Only log-valued web items are eligible.

## 7. Clear semantics

Clear records the newest fixed-width Zabbix receipt key currently displayed and hides rows at or below that boundary in the current browser instance. It does not alter Zabbix history or the Windows event log.

## 8. Constraints

- Parsing is based on English Windows event labels.
- A request returns at most 200 rows.
- Free-text search can span all retained history and therefore remains bounded by Zabbix retention/housekeeping and database performance.
- The module uses the Zabbix History API rather than separate database credentials or direct SQL.
