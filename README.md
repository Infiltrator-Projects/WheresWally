# WHERE'S WALLY — Zabbix NPS Event Monitor

**A native Zabbix 7.0 LTS dashboard widget for monitoring Microsoft Network Policy Server authentication events.**

**Authors and maintainers:** Shannon Smith and Carlo Cunanan  
**Release:** 1.1.5  
**Platform:** Zabbix 7.0 LTS  
**Type:** Zabbix dashboard widget/module  
**Licence:** GNU General Public License v3.0 or later

WHERE'S WALLY is designed specifically for Zabbix. It installs as a Zabbix frontend module and uses the Zabbix History API to monitor Microsoft NPS authentication events 6272 (granted) and 6273 (denied).

Version 1.1.5 restores the intended live-console behaviour: Auto-scroll ON performs a one-second near-live poll and follows the newest events, while Auto-scroll OFF holds the current event list in place. Retained-history search remains explicit and server-side.

## Installation

### Debian / Ubuntu / Linux Mint

Build the native Debian package:

```bash
./tools/build-deb.sh
```

Install it with:

```bash
sudo apt install ./dist/nps-wheres-wally-zabbix_1.1.5_all.deb
```

### Portable installer

Build the self-extracting installer:

```bash
./tools/build-installer.sh
```

Run the resulting `dist/nps-wheres-wally-zabbix-1.1.5.run` as root.

Both installers place the widget under the Zabbix module directory, normally:

```text
/usr/share/zabbix/modules/nps_wheres_wally
```

Then open **Zabbix → Administration → General → Modules → Scan directory**, enable WHERE'S WALLY, and refresh the browser.

## Live / hold behaviour

With search and receipt-date fields blank, **Auto-scroll** is the live-feed switch:

- checked: the widget checks Zabbix for new NPS events every second and keeps the newest rows in view;
- unchecked: the current display is held in place and periodic refreshes do not replace it.

Historical searches are always user-driven. Press Enter to execute or refresh a retained-history search.

## Search behaviour

Blank search/date fields show the newest configured events. Free-text searches execute server-side through `history.get` and return at most 200 matching rows.

The **Received from** and **Received to** controls deliberately filter on Zabbix receipt time because `history.get` defines `time_from` and `time_till` on the receipt clock. The primary **Time** column remains the original Windows event timestamp when supplied by the event log; Zabbix receipt time is shown in Details.

Exact searches for `6272`, `grant`, `granted`, `6273`, `deny`, or `denied` are converted to exact event-ID filters instead of text scans.

Free-text search uses Zabbix's case-insensitive History API search against the retained event message. On extremely large retention sets, adding a receipt-date range is recommended to reduce database work.

## Validation

```bash
./tools/test.sh
```

The suite checks PHP and JavaScript syntax, parser behaviour, History API query construction, CSV formula neutralisation, installer scripts and—where `dpkg-deb` is available—the Debian package build.
