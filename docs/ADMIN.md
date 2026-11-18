# Administration

All settings are in Administration settings, section TimeGuardian (status block, then sections with a help text per field).
The same values can be read and written through `GET/POST /apps/timeguardian/api/admin/config` (see API.md).

**Managers can configure the app themselves.** The page "Configuration" in the app (menu entry for managers) offers every
setting below with explanations, so the person who runs the time tracking does not need Nextcloud admin rights. Only the three
group names (manager, employee, kiosk) stay with Nextcloud administrators: managers see them, but cannot change them. Members of
the groups are changed under Users -> Groups.

| Setting | Meaning | Default |
|---|---|---|
| Manager group | Members are managers: they decide absences and corrections, see the team, set targets, export team data | `tg-chef` |
| Employee group | Members may use the time tracking. Empty: all users | `tg-team` |
| Kiosk group | May open the kiosk page in addition to managers and admins | empty |
| Federal state | Public holidays (target 0 on those days) | BE |
| Time zone | Days and rule checks are calculated in this zone | Europe/Berlin |
| Default weekly hours / working days | Used until a manager sets a personal target | 40 h, Mon-Fri |
| Retention (days) | Older data is deleted by the daily job | 730 |
| Projects | One per line. Empty: free text | empty |
| Self approval | Managers may approve their own requests | off |
| Live warnings | 9 h 30, 5 h 45 without break, 12 h without clock-out | on |
| Integrations | Notifications, calendar "Working time", absences in the personal calendar, Deck card, Talk message | notifications and absence calendar on |
| Webhook | URL and secret; events `punch`, `violation`, `absence.approved` | off |

Nextcloud administrators are not managers automatically. To act as a manager an admin must be in the manager group.

## Roles in short

* Employee: sees only their own data, requests absences and corrections.
* Manager: sees all team members (employee group plus managers), approves and rejects, sets weekly targets, exports.
* Admin: configures. Can open the kiosk page. Sees no time data unless also manager.

## Phones and the installed app

`/apps/timeguardian/go` is the start page of the installed app. It needs no Nextcloud login: a paired phone shows its status and the
scanner, an unpaired phone shows how to pair. Everybody sees an "Install as an app" card on the start page, the devices page and
`/go` (install button on Chrome/Edge/Android, illustrated steps on iPhone/iPad). On iPhone the installed app is separate from Safari:
pair it again inside the app: tap "Scan pairing QR" (the scanner inside the app redeems the QR directly) or type the short code
(`ABCD-EFGH`) that the Devices page shows next to the QR. The code is valid for 5 minutes and works once; after 10 wrong attempts from
one IP address the code entry is locked for an hour (the QR is not affected).

`/go`, `/s/{code}` and `/pair/{token}` are served as standalone HTML documents, not inside the Nextcloud layout, so that "Add to
Home Screen" picks up the app's own manifest, icon (180x180 PNG) and name. They send their own Content-Security-Policy and allow
the camera (`Permissions-Policy: camera=(self)`); if a reverse proxy rewrites these headers, the scanner on these pages stops working.

## Kiosk

`/apps/timeguardian/kiosk` shows the rotating QR code (60 second windows, the server accepts the current and the previous one).
The page needs a logged in session of a manager, admin or kiosk group member; use a dedicated account that is only in the kiosk group.

## Background jobs

| Job | Interval | Purpose |
|---|---|---|
| LiveWarningsJob | 5 min | notifications for people who are working |
| WeeklyReportJob | hourly | Friday after 15:00 local time, once per ISO week: team report to managers |
| RetentionJob | daily | deletes data older than the retention period |

## Webhook

Each call is a JSON POST with the headers `X-TG-Event`, `X-TG-Timestamp` and `X-TG-Signature: sha256=<hex>`, where the signature
is HMAC-SHA256 over `"<timestamp>.<body>"` with the secret. Verify the signature and reject timestamps older than 5 minutes.
Nextcloud blocks requests to local addresses by default (`allow_local_remote_servers` in config.php).

## Deck and Talk

Both are optional and best effort: Deck needs the stack ID where violation cards are created; Talk needs the token of an existing
room. Failures are logged and never affect a booking. Notifications are the reliable channel.

## Operations notes

* Instance secret for the QR codes: app config key `qr_secret`, created on first use. Rotating it (delete the key) invalidates all codes at once.
* Disabling a user revokes all of their device tokens; deleting a user deletes all of their data.
