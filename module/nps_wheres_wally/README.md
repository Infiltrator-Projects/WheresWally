# WHERE'S WALLY — NPS Event Monitor

**Authors and maintainers:** Shannon Smith and Carlo Cunanan  
**Version:** 1.1.7  
**Target platform:** Zabbix 7.0 LTS  
**Licence:** GNU General Public License v3.0 or later

WHERE'S WALLY is a custom Zabbix dashboard widget for Microsoft NPS authentication events 6272 and 6273.

## Capabilities

- one row per NPS authentication event;
- Windows source event time plus separate Zabbix receipt time;
- account, domain, resolved display name, AP/BSSID, device MAC and RADIUS/NAS IP;
- current Zabbix AP identity enrichment instead of trusting stale NPS friendly names;
- exact BSSID/MAC inventory correlation when available, with exact monitored-host interface IP fallback;
- explicit unresolved state when no current Zabbix host can be correlated;
- one-second LIVE view with true HOLD when Auto-scroll is disabled;
- retained-history search and receipt-date filtering;
- maximum 200 returned rows;
- spreadsheet-safe CSV export, non-destructive Clear and expandable raw event details.

## Access-point identity

NPS `Client Friendly Name` is configuration metadata and can outlive an AP rename or replacement. WHERE'S WALLY therefore does not use it as authoritative current location.

The controller first checks whether the NPS BSSID exactly matches `macaddress_a` or `macaddress_b` on the Zabbix host owning the event's current interface IP. If IP is unavailable or conflicts with populated MAC inventory, a bounded exact inventory-MAC lookup is attempted. If no MAC match is available, the exact current Zabbix interface-IP match is used. Approximate or vendor-offset MAC guessing is intentionally forbidden.

If the matched Zabbix host has an inventory `location`, that is displayed. Otherwise its current visible Zabbix host name is displayed. The original NPS message remains unchanged in Details.

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
