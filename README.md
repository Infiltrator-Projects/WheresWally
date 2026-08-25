# WHERE'S WALLY — Zabbix NPS Event Monitor

**A native Zabbix 7.0 LTS dashboard widget for monitoring Microsoft Network Policy Server authentication events.**

**Authors and maintainers:** Shannon Smith and Carlo Cunanan  
**Release:** 1.1.7  
**Platform:** Zabbix 7.0 LTS  
**Type:** Zabbix dashboard widget/module  
**Licence:** GNU General Public License v3.0 or later

WHERE'S WALLY installs as a Zabbix frontend module and uses Zabbix APIs to display Microsoft NPS Security events 6272 (Grant) and 6273 (Deny).

Version 1.1.7 fixes access-point identity. NPS `Client Friendly Name` is no longer treated as authoritative location data. WHERE'S WALLY correlates the AP-side BSSID and RADIUS/NAS IP against current monitored Zabbix hosts. Exact MAC inventory matches take precedence; current host-interface IP is the bounded fallback. If no current Zabbix host resolves, the main table says so rather than showing a stale NPS label as fact. The complete original NPS event remains available in Details.

## Installation

### Debian / Ubuntu / Linux Mint

```bash
./tools/build-deb.sh
sudo apt install ./dist/nps-wheres-wally-zabbix_1.1.7_all.deb
```

### Portable installer

```bash
./tools/build-installer.sh
sudo ./dist/nps-wheres-wally-zabbix-1.1.7.run
```

Both installers normally install to `/usr/share/zabbix/modules/nps_wheres_wally`. Then open **Zabbix → Administration → General → Modules → Scan directory**, enable WHERE'S WALLY, and refresh the browser.

## Live / hold behaviour

With search and receipt-date fields blank, **Auto-scroll** is the live-feed switch. Checked means one-second LIVE updates; unchecked means HOLD. Historical searches remain explicit via Enter or the Search button.

## AP identity behaviour

For every displayed NPS row, WHERE'S WALLY treats the Called-Station-Identifier MAC/BSSID as AP-side identity evidence and the NPS client/NAS IP as current Zabbix-host evidence. A Zabbix host whose inventory MAC exactly matches the BSSID wins. If inventory MAC is unavailable, an exact current Zabbix interface-IP match is used as a fallback. No approximate MAC guessing is performed.

This deliberately allows current Zabbix naming to replace stale NPS labels such as an old `Client Friendly Name` after an AP has been renamed, replaced or moved.

## Validation

```bash
./tools/test.sh
```

The suite checks PHP/JavaScript syntax, NPS parsing, access-point identity normalisation, History API query construction, timezone/DST boundaries, LIVE/HOLD behaviour, CSV hardening and both installer formats.
