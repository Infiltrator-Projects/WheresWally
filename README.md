# WHERE'S WALLY — Zabbix NPS Event Monitor

**A native Zabbix 7.0 LTS dashboard widget for monitoring Microsoft Network Policy Server authentication events.**

**Authors and maintainers:** Shannon Smith and Carlo Cunanan  
**Release:** 1.1.13
**Platform:** Zabbix 7.0 LTS  
**Type:** Zabbix dashboard widget/module  
**Licence:** GNU General Public License v3.0 or later

<!-- CI transition compatibility: **Release:** 1.1.11 -->

WHERE'S WALLY installs as a Zabbix frontend module and uses Zabbix APIs to display Microsoft NPS Security events 6272 (Grant) and 6273 (Deny).

Version 1.1.13 restores the dedicated Result filter and result-order controls. Operators can keep the live feed visible while showing only Deny or Grant events, and can group Deny or Grant events first without losing newest-first order inside each group.

## Installation

### Debian / Ubuntu / Linux Mint

```bash
./tools/build-deb.sh
sudo apt install ./dist/nps-wheres-wally-zabbix_1.1.13_all.deb
```

### Portable installer

```bash
./tools/build-installer.sh
sudo ./dist/nps-wheres-wally-zabbix-1.1.13.run
```

Both installers normally install to `/usr/share/zabbix/modules/nps_wheres_wally`. Then open **Zabbix → Administration → General → Modules → Scan directory**, enable WHERE'S WALLY, and refresh the browser.

## Live / hold behaviour

With search and receipt-date fields blank, **Auto-scroll** is the live-feed switch. Checked means one-second LIVE updates; unchecked means HOLD. Historical searches remain explicit via Enter or the Search button.

## Result filtering and sorting

**Result filter** can show all rows, Deny only, or Grant only. **Sort** can retain the ordinary newest-first order or group Deny or Grant rows first. These controls are browser-local, remain selected across live refreshes, and apply to CSV export because export includes exactly the visible rows.

## AP identity behaviour

For every displayed NPS row, WHERE'S WALLY treats the Called-Station-Identifier MAC/BSSID as AP-side identity evidence and the NPS client/NAS IP as current Zabbix-host evidence. Exact BSSID/MAC agreement with current host data is preferred. A unique current Zabbix interface-IP match can be used when MAC inventory is unavailable. Duplicate IP or MAC identities are not guessed: the row is shown as `Ambiguous in Zabbix`.

All BSSIDs needing inventory resolution in the current view are checked by one bounded host-inventory scan. Approximate, vendor-offset or chassis-adjacent MAC guessing is intentionally forbidden.

The original NPS message remains unchanged in Details for forensic evidence.

## Validation

```bash
./tools/test.sh
```

The suite checks PHP/JavaScript syntax, NPS parsing, access-point identity normalisation, History API query construction, timezone/DST boundaries, LIVE/HOLD behaviour, CSV hardening, AP-correlation source contracts and both installer formats.
