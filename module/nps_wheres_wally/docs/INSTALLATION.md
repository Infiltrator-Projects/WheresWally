# Installation, upgrade and removal

## 1. Automatic installation

Copy the `.run` installer to the Zabbix appliance, normally under `/tmp`, then execute it as root:

```bash
chmod +x /tmp/nps-wheres-wally-zabbix-1.1.3.run
/tmp/nps-wheres-wally-zabbix-1.1.3.run
```

The installer:

The installer does **not** require PHP CLI. This is deliberate: a Zabbix appliance can run the frontend through PHP-FPM without providing a `php` command. When PHP CLI is present, the installer performs an additional syntax check; otherwise it reports the check as skipped and continues.

1. validates the Zabbix module directory;
2. extracts the new module to a temporary staging directory;
3. validates the manifest identity and, when PHP CLI is available, validates PHP syntax;
4. backs up an existing `nps_wheres_wally` installation;
5. moves the new version into place;
6. applies restrictive ownership and normal web-readable permissions;
7. restores SELinux context when `restorecon` is available.

After installation, open:

```text
Administration → General → Modules
```

Click **Scan directory**, then enable **WHERE'S WALLY — NPS Event Monitor**.

Create or edit a dashboard, add the widget, select the source item when automatic discovery is not appropriate, and save the dashboard.

## 2. Upgrade

Run the newer automatic installer. The existing module is renamed to a timestamped backup before the new version is installed.

After upgrading:

1. refresh the browser with `Ctrl+F5` to bypass cached JavaScript and CSS;
2. confirm the module remains enabled;
3. open the dashboard and verify new events appear;
4. retain the backup until verification is complete.

## 3. Rollback

The installer prints the backup directory name. To roll back:

```bash
cd /usr/share/zabbix/modules
mv nps_wheres_wally nps_wheres_wally.failed
mv nps_wheres_wally.backup.YYYYMMDD-HHMMSS nps_wheres_wally
restorecon -RF nps_wheres_wally 2>/dev/null || true
```

Then refresh the browser with `Ctrl+F5`.

## 4. Manual installation

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

## 5. Removal

Remove the widget from dashboards, disable the module, then delete its directory:

```bash
rm -rf /usr/share/zabbix/modules/nps_wheres_wally
```

Scan the module directory again in the frontend. Removing the module does not remove the NPS log item or its history.
