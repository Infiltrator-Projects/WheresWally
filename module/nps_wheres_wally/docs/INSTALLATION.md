# Installation, upgrade and removal

## 1. Debian / Ubuntu / Linux Mint package

Build the native package with:

```bash
./tools/build-deb.sh
```

Install version 1.1.6 with:

```bash
sudo apt install ./dist/nps-wheres-wally-zabbix_1.1.6_all.deb
```

The package is architecture-independent and installs the module under:

```text
/usr/share/zabbix/modules/nps_wheres_wally
```

It declares a dependency on the Zabbix PHP frontend and does not modify the Zabbix database, the monitored NPS host, or retained history.

## 2. Portable automatic installation

Copy the `.run` installer to the Zabbix appliance, normally under `/tmp`, then execute it as root:

```bash
chmod +x /tmp/nps-wheres-wally-zabbix-1.1.6.run
/tmp/nps-wheres-wally-zabbix-1.1.6.run
```

The portable installer does **not** require PHP CLI. This is deliberate: a Zabbix appliance can run the frontend through PHP-FPM without providing a `php` command. When PHP CLI is present, the installer performs an additional syntax check; otherwise it reports the check as skipped and continues.

The portable installer:

1. validates the Zabbix module directory;
2. extracts the new module to a temporary staging directory;
3. validates the manifest identity and, when PHP CLI is available, validates PHP syntax;
4. backs up an existing `nps_wheres_wally` installation;
5. moves the new version into place;
6. applies restrictive ownership and normal web-readable permissions;
7. restores SELinux context when `restorecon` is available.

After either installation method, open:

```text
Administration → General → Modules
```

Click **Scan directory**, then enable **WHERE'S WALLY — NPS Event Monitor**. Refresh the browser after an upgrade so the new JavaScript and CSS are loaded.

## 3. Upgrade

For package installations, install the newer `.deb` with `apt` and then refresh the frontend. For portable installations, run the newer `.run`; the existing module is renamed to a timestamped backup before the new version is installed.

## 4. Rollback of a portable installation

The `.run` installer prints the backup directory name. To roll back:

```bash
cd /usr/share/zabbix/modules
mv nps_wheres_wally nps_wheres_wally.failed
mv nps_wheres_wally.backup.YYYYMMDD-HHMMSS.PID nps_wheres_wally
restorecon -RF nps_wheres_wally 2>/dev/null || true
```

Then refresh the browser with `Ctrl+F5`.

## 5. Manual installation

Copy the module directory to:

```text
/usr/share/zabbix/modules/nps_wheres_wally
```

Apply permissions:

```bash
chown -R root:root /usr/share/zabbix/modules/nps_wheres_wally
find /usr/share/zabbix/modules/nps_wheres_wally -type d -exec chmod 755 {} +
find /usr/share/zabbix/modules/nps_wheres_wally -type f -exec chmod 644 {} +
restorecon -RF /usr/share/zabbix/modules/nps_wheres_wally 2>/dev/null || true
```

Then scan and enable the module in the Zabbix frontend.

## 6. Removal

If installed through the Debian package:

```bash
sudo apt remove nps-wheres-wally-zabbix
```

For a portable or manual installation, remove the widget from dashboards, disable the module, then delete its directory:

```bash
rm -rf /usr/share/zabbix/modules/nps_wheres_wally
```

Scan the module directory again in the frontend. Removing the module does not remove the NPS log item or its retained history.
