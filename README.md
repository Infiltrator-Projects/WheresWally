# WHERE'S WALLY — Zabbix NPS Event Monitor

**A native Zabbix 7.0 LTS dashboard widget for monitoring Microsoft Network Policy Server authentication events.**

**Authors and maintainers:** Shannon Smith and Carlo Cunanan  
**Release:** 1.1.3  
**Platform:** Zabbix 7.0 LTS  
**Type:** Zabbix dashboard widget/module  
**Licence:** GNU General Public License v3.0 or later

WHERE'S WALLY is designed specifically for Zabbix. It installs as a Zabbix frontend module and uses the Zabbix API and retained log history to monitor Microsoft NPS authentication events 6272 (granted) and 6273 (denied).

Version 1.1.3 provides bounded server-side search across retained Zabbix log history, optional date-range filtering, CSV export, expandable event details and explicit Enter-to-search behaviour while preserving the lightweight live view.

## Zabbix integration

The production module lives in `module/nps_wheres_wally/` and is installed under the Zabbix module directory, normally:

```text
/usr/share/zabbix/modules/nps_wheres_wally
```

After installation, enable it from:

```text
Zabbix → Administration → General → Modules → Scan directory
```

The widget reads NPS event data through the Zabbix History API rather than connecting directly to the Zabbix database.

## Repository layout

```text
module/nps_wheres_wally/   Production Zabbix widget module
tests/                     Parser fixtures and regression harness
tools/test.sh              Static and unit test runner
tools/build-installer.sh   Self-extracting installer builder
```

The installed module includes operational and technical documentation under `module/nps_wheres_wally/docs`.

## Validate the release

```bash
./tools/test.sh
```

## Build the automatic installer

```bash
./tools/build-installer.sh
```

The resulting installer is written to:

```text
dist/nps-wheres-wally-zabbix-1.1.3.run
```

## Release behaviour

With search and date fields blank, the widget shows the newest configured events. Historical searches are executed server-side through the Zabbix History API and return at most 200 rows. Search and date fields execute on Enter rather than querying while the operator is typing.

See `module/nps_wheres_wally/README.md` for full functional documentation.
