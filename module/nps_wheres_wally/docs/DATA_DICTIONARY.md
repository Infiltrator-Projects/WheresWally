# Data dictionary

| Display field | Primary source | Fallback or transformation |
|---|---|---|
| Event | Zabbix `logeventid` | Only 6272 and 6273 are accepted |
| Time | Windows event timestamp | Zabbix receipt time when unavailable |
| Account | First `Account Name` | Blank when absent |
| Domain | First `Account Domain` | Blank when absent |
| Name | Final component of `Fully Qualified Account Name` | Account value |
| Access point | MAC portion of `Called Station Identifier` | Full called-station value for unknown formats |
| Location | Current matched Zabbix host inventory `location` | Current Zabbix visible host name; `Not found in Zabbix` when unresolved |
| Device MAC | `Calling Station Identifier` | Blank when absent |
| IP address | NPS `Client IP Address` | `NAS IPv4 Address`; used as exact Zabbix host-interface fallback for AP correlation |
| Result | Event 6272 or 6273 | 6272 = Grant; 6273 = Deny |
| Details: raw message | Zabbix history `value` | Original NPS friendly name/NAS metadata retained unchanged |

## AP identity semantics

`Client Friendly Name` is not treated as authoritative current location. It is NPS/RADIUS configuration metadata and can become stale after an AP is renamed, replaced or moved.

WHERE'S WALLY correlates the AP-side BSSID against Zabbix `macaddress_a` / `macaddress_b` using separator-insensitive exact comparison. When exact MAC inventory is unavailable, the event's NPS client/NAS IP is matched exactly against current monitored Zabbix host interfaces. If neither path resolves a current Zabbix host, the main Location field reports `Not found in Zabbix` rather than presenting stale NPS metadata as fact.

No approximate BSSID-to-chassis-MAC arithmetic is performed.
