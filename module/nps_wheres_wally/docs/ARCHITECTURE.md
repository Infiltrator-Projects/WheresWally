# Architecture

## 1. Purpose

WHERE'S WALLY converts Microsoft NPS authentication audit records already collected by Zabbix into an operationally useful event table. Version 1.1.3 separates lightweight live viewing from retained-history investigation.

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
        ├── normal view: newest rows only
        │
        └── search/date criteria
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

Defines persistent dashboard configuration: optional source item and returned-row count, bounded to 10–200 by the controller.

### `actions/WidgetView.php`

Resolves the source item and performs all Zabbix history access. With no search criteria it retrieves the newest rows. With search/date criteria it passes `search`, `filter`, `time_from`, and `time_till` options to `history.get`. The History API performs the filtering before PHP receives the result set.

### `includes/NpsEventParser.php`

Deterministically extracts NPS fields from English Windows event messages.

### `views/widget.view.php`

Builds the search/date toolbar, semantic table and expandable detail rows.

### `assets/js/class.widget.js`

Maintains transient search/date state, submits text/date criteria only when Enter is pressed, suppresses repeated periodic historical queries, preserves non-destructive Clear state, and implements CSV export/details.

## 4. Search model

Search scope and result size are separate concepts. The search is allowed to examine all retained records for the configured item, but every response is bounded to at most 200 rows. This avoids transferring or rendering a very large history set.

Search text is submitted only when Enter is pressed, then matched case-insensitively against the log `value` through the Zabbix History API. Exact searches for `6272`, `grant`, or `granted` use the log-event ID filter 6272; `6273`, `deny`, or `denied` use 6273.

The optional date range is converted in the browser to epoch timestamps and supplied as `time_from`/`time_till`.

## 5. Refresh model

Normal live mode follows the configured Zabbix dashboard refresh interval. Historical queries are deliberately user-driven: changing search text or dates performs one query, and ordinary periodic widget refreshes do not repeat that same large-history search. Resetting the criteria returns to live mode.

## 6. Source-item resolution

The controller uses: explicit widget item; otherwise the canonical item on `Server NPS`; otherwise the first accessible exact-name match. Only log-valued web items are eligible.

## 7. Clear semantics

Clear records the newest fixed-width receipt key currently displayed and hides rows at or below that boundary in the current browser instance. It does not alter Zabbix history or the Windows event log.

## 8. Constraints

- Parsing is based on English Windows event labels.
- A request returns at most 200 rows.
- Search can span all retained history, which is still bounded by Zabbix history-retention/housekeeping settings.
- The module uses the Zabbix History API rather than separate database credentials or direct SQL.
