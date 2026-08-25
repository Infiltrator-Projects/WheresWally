# WHERE'S WALLY — NPS Event Monitor

**Authors and maintainers:** Shannon Smith and Carlo Cunanan  
**Version:** 1.1.5  
**Target platform:** Zabbix 7.0 LTS  
**Licence:** GNU General Public License v3.0 or later

WHERE'S WALLY is a custom Zabbix dashboard widget for Microsoft Network Policy Server authentication events. It reads an existing Zabbix log item containing Windows Security events 6272 and 6273, parses the human-readable NPS message, and displays one authentication decision per row.

Event 6272 is shown as **Grant**. Event 6273 is shown as **Deny**, including the NPS reason code when available.

## Capabilities

- one row per NPS authentication event;
- event time sourced from the Windows event timestamp when available;
- Zabbix receipt time retained separately and shown in Details;
- account, domain and resolved display name;
- access-point identifier, location, device MAC address and IP address;
- colour-coded Grant and Deny results;
- one-second near-live view of the newest events while **Auto-scroll** is enabled;
- true hold behaviour when **Auto-scroll** is disabled;
- server-side retained-history search;
- optional **Received from/Received to** filtering in the active Zabbix frontend timezone;
- hard server-side restriction to event IDs 6272 and 6273 before the result limit is applied;
- maximum 200 rows returned to the browser;
- CSV export with spreadsheet-formula neutralisation;
- non-destructive Clear behaviour;
- expandable raw Windows event details;
- automatic source-item discovery or explicit item selection.

## Search and date semantics

With all search controls blank, **Auto-scroll** controls live operation. Enabled, the widget checks for new NPS events once per second and follows the newest rows. Disabled, the current rows remain fixed until Auto-scroll is enabled again or an explicit operation requests a snapshot.

Free text is sent to Zabbix `history.get` as a case-insensitive search against the retained log `value`. Exact Grant/Deny shortcuts are translated to event-ID filters.

Zabbix defines `time_from` and `time_till` on the time a value was **received**, not on the source event timestamp. Accordingly, the date controls are explicitly labelled **Received from** and **Received to**. Date strings are converted to timestamps in PHP using the active Zabbix frontend timezone. The Windows event timestamp remains the main displayed event time.

For very large retention sets, a receipt-date range reduces the work required by a free-text History API search.

## Required Zabbix item

Automatic discovery expects a log item named:

```text
NPS authentication events 6272 and 6273
```

Recommended configuration:

```text
Type:                Zabbix agent (active)
Key:                 eventlog[Security,,,,^(6272|6273)$,,skip]
Type of information: Log
```

The preferred host name is `Server NPS`. A differently named host or item can be selected explicitly in widget configuration; server-side event-ID filtering still guarantees that only 6272/6273 records are displayed.
