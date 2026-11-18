# Installation

Requirements: Nextcloud 31 to 35, PHP 8.1 or newer (8.4 for Nextcloud 35), any database supported by Nextcloud, working
background jobs (cron is strongly recommended: live warnings run every 5 minutes).

## From the release archive

```
cd /var/www/nextcloud/custom_apps        # or any apps_paths entry of config.php
unzip timeguardian-<version>.zip             # creates the folder timeguardian
chown -R www-data:www-data timeguardian
sudo -u www-data php /var/www/nextcloud/occ app:enable timeguardian
```

Enabling runs the database migration (tables `tg_*`). Check with `occ app:list | grep timeguardian`.

## First steps

1. Create the two groups and add people (names are the defaults, they can be changed in the admin settings):
   ```
   occ group:add tg-chef
   occ group:add tg-team
   occ group:adduser tg-chef <manager>
   occ group:adduser tg-team <employee>
   ```
2. Open the app: as a manager the start page shows a setup checklist. Check federal state (public holidays), time zone and the default weekly hours on the page "Configuration" (managers need no admin rights). Groups are set by a Nextcloud admin in Administration settings, section TimeGuardian.
3. Open `/apps/timeguardian/kiosk` on the tablet at the entrance with a manager or admin account and keep it open. Use the browser's full screen mode.
4. Employees open the app, choose "Pair phone", scan the shown QR code with the phone camera, done. From then on scanning the kiosk code clocks in and out. Optional: install the app (`/apps/timeguardian/go` is its start page).

## Update

Unpack the new archive over the old folder, then `occ upgrade` (migrations run automatically). Settings and data are kept.

## Uninstall

`occ app:remove timeguardian` removes the code. The tables `tg_*` and the app config stay until you drop them manually.
