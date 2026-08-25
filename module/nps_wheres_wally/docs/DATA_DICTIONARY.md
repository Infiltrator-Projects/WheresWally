# Data dictionary

| Display field | Primary source | Fallback or transformation |
|---|---|---|
| Event | Zabbix `logeventid` | Only 6272 and 6273 are accepted |
| Time | Windows event timestamp | Zabbix receipt time when unavailable |
| Account | First `Account Name` | Blank when absent |
| Domain | First `Account Domain` | Blank when absent |
| Name | Final component of `Fully Qualified Account Name` | Account value |
| Access point | MAC portion of `Called Station Identifier` | Full called-station value for unknown formats |
| Location | `Client Friendly Name` | `NAS Identifier`, then SSID suffix |
| Device MAC | `Calling Station Identifier` | Blank when absent |
| IP address | `Client IP Address` | `NAS IPv4 Address` |
| Result | Event 6272 or 6273 | 6272 = Grant; 6273 = Deny |
| Details: Received | Zabbix history `clock` | Formatted in the active Zabbix frontend timezone |
| Details: SSID | Suffix of `Called Station Identifier` | Blank when absent |
| Details: Network policy | `Network Policy Name` | Blank when absent |
| Details: Connection policy | `Connection Request Policy Name` | Blank when absent |
| Details: Authentication | `Authentication Type` | Blank when absent |
| Details: EAP | `EAP Type` | Blank when absent |
| Details: Reason code | `Reason Code` | Applicable mainly to event 6273 |
| Details: Reason | `Reason` | Blank when absent |
| Details: raw message | Zabbix history `value` | Line endings normalised to LF |

## Time semantics

The primary **Time** value represents the Windows source event timestamp when the event log supplied one. The **Received** value is the Zabbix history receipt clock. Historical **Received from/Received to** filtering uses the receipt clock because that is the time domain supported by `history.get` date bounds.

## Duplicate labels

Windows NPS messages contain repeated labels in different sections, particularly `Account Name`. The parser intentionally uses the first occurrence because it belongs to the authenticating User section. Client-computer identity remains available in the raw event.

## Empty markers

Windows commonly represents an unavailable value as a single hyphen. The parser converts that marker to an empty string. It does not convert other values, because vendor-specific attributes may legitimately contain unusual text.
