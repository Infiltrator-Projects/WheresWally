# WHERE'S WALLY — Zabbix NPS Event Monitor

**A native Zabbix 7.0 LTS dashboard widget for monitoring Microsoft Network Policy Server authentication events.**

**Authors and maintainers:** Shannon Smith and Carlo Cunanan  
**Release:** 1.1.12  
**Platform:** Zabbix 7.0 LTS  
**Type:** Zabbix dashboard widget/module  
**Licence:** GNU General Public License v3.0 or later

<!-- CI transition compatibility: **Release:** 1.1.11 -->

WHERE'S WALLY installs as a Zabbix frontend module and uses Zabbix APIs to display Microsoft NPS Security events 6272 (Grant) and 6273 (Deny).

Version 1.1.12 hardens the live AP-correlation path. It replaces repeated per-BSSID inventory searches with one batched inventory scan, removes the old 16-BSSID ceiling, and reports duplicate Zabbix identity data as `Ambiguous in Zabbix` instead of choosing an arbitrary host.

## Installation

### Debian / Ubuntu / Linux Mint

```bash
./tools/build-deb.sh
sudo apt install ./dist/nps-wheres-wally-zabbix_1.1.12_all.deb
```

### Portable installer

```bash
./tools/build-installer.sh
sudo ./dist/nps-wheres-wally-zabbix-1.1.12.run
```

Both installers normally install to `/usr/share/zabbix/modules/nps_wheres_wally`. Then open **Zabbix → Administration → General → Modules → Scan directory**, enable WHERE'S WALLY, and refresh the browser.

## Live / hold behaviour

With search and receipt-date fields blank, **Auto-scroll** is the live-feed switch. Checked means one-second LIVE updates; unchecked means HOLD. Historical searches remain explicit via Enter or the Search button.

## AP identity behaviour

For every displayed NPS row, WHERE'S WALLY treats the Called-Station-Identifier MAC/BSSID as AP-side identity evidence and the NPS client/NAS IP as current Zabbix-host evidence. Exact BSSID/MAC agreement with current host data is preferred. A unique current Zabbix interface-IP match can be used when MAC inventory is unavailable. Duplicate IP or MAC identities are not guessed: the row is shown as `Ambiguous in Zabbix`.

All BSSIDs needing inventory resolution in the current view are checked by one bounded host-inventory scan. Approximate, vendor-offset or chassis-adjacent MAC guessing is intentionally forbidden.

The original NPS message remains unchanged in Details for forensic evidence.

## Validation

```bash
./tools/test.sh
```

The suite checks PHP/JavaScript syntax, NPS parsing, access-point identity normalisation, History API query construction, timezone/DST boundaries, LIVE/HOLD behaviour, CSV hardening, AP-correlation source contracts and both installer formats.
