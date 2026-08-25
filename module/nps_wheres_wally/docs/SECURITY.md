# Security and data handling

## Trust boundaries

The widget crosses three trust boundaries:

1. Windows NPS writes authentication events.
2. Zabbix Agent 2 transports those events into Zabbix history.
3. The Zabbix frontend presents history to an authenticated user.

The widget does not create a new network listener, privileged helper or database credential.

## Authorisation

History and item metadata are requested through the Zabbix API. Results are therefore constrained by the current Zabbix user's frontend permissions. Historical search also uses the History API; the module does not store separate database credentials or bypass Zabbix authorisation with direct SQL access.

Every History API query is restricted server-side to NPS event IDs 6272 and 6273 before the result limit is applied, including when an administrator selects a broader Security log item.

## Output handling

Event values are passed through Zabbix UI tag objects as text. The widget does not deliberately emit raw event content as HTML. This reduces the risk that crafted RADIUS attributes become executable markup.

## Data sensitivity

NPS events may contain usernames, device identifiers, network locations, IP addresses and authentication failure reasons. Dashboard access and CSV exports should be restricted according to the organisation's existing Zabbix role and data-handling requirements.

## CSV export

Export occurs entirely in the user's browser. The module does not write exported files to the Zabbix server. The generated CSV reflects the currently visible live/search result rows only; historical queries are bounded to at most 200 returned rows.

Spreadsheet applications may interpret cells beginning with formula markers such as `=`, `+`, `-` or `@`. Version 1.1.4 and later neutralise those prefixes with a leading apostrophe before CSV quoting so event data is opened as text rather than evaluated as a spreadsheet formula.

## Clear operation

Clear is intentionally non-destructive. It does not alter Zabbix history or Windows logs, preventing a dashboard operator from inadvertently destroying evidence.

## Installation permissions

The portable installer sets directories to mode 755 and files to mode 644, owned by root. The Debian package records root ownership through `dpkg-deb --root-owner-group`. No module file is made writable by the web-server account.

## Reporting security defects

Record the Zabbix version, module version, browser version, relevant source event with sensitive values redacted, and exact reproduction steps. Do not include production credentials or unredacted private keys.
