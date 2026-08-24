# WHERE'S WALLY — NPS Event Monitor source package

**Authors and maintainers:** Shannon Smith and Carlo Cunanan  
**Release:** 1.1.3  
**Platform:** Zabbix 7.0 LTS  
**Licence:** GNU General Public License v3.0 or later

This repository contains the production Zabbix module, regression tests, documentation and installer tooling for WHERE'S WALLY — NPS Event Monitor.

The widget monitors Microsoft Network Policy Server authentication events 6272 and 6273. Version 1.1.3 adds bounded server-side search across retained Zabbix log history, optional date-range filtering and explicit Enter-to-search behaviour while preserving the lightweight live view.

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

The resulting installer is written to `dist/nps-wheres-wally-zabbix-1.1.3.run`.

## Release behaviour

With search and date fields blank, the widget shows the newest configured events. Historical searches are executed server-side through the Zabbix History API and return at most 200 rows. Search and date fields execute on Enter rather than querying while the operator is typing.

See `module/nps_wheres_wally/README.md` for full functional documentation.
