# TimeGuardian HTTP API

Base path: `/apps/timeguardian` (with `index.php/` in front when the server has no pretty URLs; always build URLs
with Nextcloud's `generateUrl('/apps/timeguardian/...')`). All API responses are JSON (`Content-Type: application/json`)
unless stated otherwise.

## Conventions

| Topic | Rule |
|---|---|
| Field names | camelCase |
| Instants | ISO 8601 UTC with `Z`, e.g. `2026-11-17T07:02:00Z` |
| Days | `YYYY-MM-DD`, in the configured time zone (default `Europe/Berlin`) |
| Minutes of day | `startHM` / `endHM` are local `HH:MM` strings (convenience, already time-zone correct) |
| Durations | integers in minutes, suffix `Min` |
| Errors | HTTP status + `{"error": "<code>", "message": "<localized text>"}` |
| Languages | `de`, `en`, `it`; text fields (`text`, `message`, `name` of holidays) are localized for the caller |
| CSRF | Session endpoints need the normal Nextcloud header `requesttoken: <OC.requestToken>`. `POST /api/punch` and `GET /api/status` also accept a device token (see below); with a session they check the CSRF token themselves |

Roles: **user** (logged in, member of the team group, or any user when the team group is empty), **manager**
(member of the configured manager group, "Chef"), **admin** (Nextcloud admin). Admin is not manager.
A user never sees another user's data; a manager sees team members only.

### Error codes

| code | HTTP | meaning |
|---|---|---|
| `unauthorized` | 401 | no session and no valid device token |
| `forbidden` | 403 | role/ownership check failed |
| `not_in_team` | 403 | user is not in the team group |
| `not_found` | 404 | unknown id (also used when the caller may not see it) |
| `validation` | 400 | input invalid, field list in `fields` (array of codes, see below) |
| `invalid_code` | 400 | QR code expired, forged or malformed |
| `code_required` | 400 | punching in needs a scanned code |
| `invalid_transition` | 409 | action not allowed in current state (`state` is included) |
| `too_fast` | 429 | less than 2 minutes after the last punch |
| `rate_limited` | 429 | device rate limit (30 punches/hour) or IP rate limit |
| `locked` | 409 | entry older than 7 days: file a correction instead |
| `overlap` | 409 | overlaps another entry |
| `already_decided` | 409 | absence/correction already approved or rejected |
| `token_invalid` / `token_expired` / `token_used` | 400/410 | pairing token problems |
| `csrf` | 412 | session punch without valid CSRF token |
| `disabled` | 409 | feature switched off by the admin |

Validation field codes (`fields`): `day`, `day_future`, `day_old`, `start`, `end`, `order`, `future`, `pause`,
`pause_long`, `project`, `kind`, `date`, `range`, `long`, `reason`, `entryId`, `mode`, `lang`, `weeklyMin`, `daysMask`.

## Object shapes

### Entry
```json
{
  "id": 41, "uid": "anna", "day": "2026-11-17",
  "start": "2026-11-17T07:02:00Z", "end": "2026-11-17T15:31:00Z",
  "startHM": "08:02", "endHM": "16:31",
  "pauseMin": 30, "netMin": 479, "running": false,
  "project": "Support", "note": null, "source": "scan",
  "createdAt": "2026-11-17T07:02:11Z", "updatedAt": "2026-11-17T15:31:04Z",
  "editable": true
}
```
`end`/`endHM` are `null` for an open entry (`running: true`); `netMin` then counts up to now.
`source`: `scan | manual | correction | seed`. `editable`: own entry, day not older than 7 days.

### Violation
```json
{ "uid": "anna", "day": "2026-11-12", "rule": "DAY10", "level": "verstoss",
  "value": 640, "text": "Mehr als 10 Stunden gearbeitet (10,7 h) · § 3 ArbZG" }
```
Rules: `DAY10 DAY8 BREAK30 BREAK45 REST11 SUN WEEK48 ABSENT`; `level`: `verstoss` (violation) or `hinweis` (hint).
`value` is minutes (net minutes, break minutes for BREAK*, rest minutes for REST11).

### Day (inside week / month / person)
```json
{ "day": "2026-11-17", "dow": 1, "isToday": true, "holiday": null,
  "absence": null, "sollMin": 480, "netMin": 479, "pauseMin": 30, "creditMin": 0, "deltaMin": -1,
  "firstHM": "08:02", "lastHM": "16:31", "entries": [ /* Entry */ ], "violations": [ /* Violation */ ] }
```
`dow`: Monday = 0. `holiday`: `{"key":"newyear","name":"Neujahr"}` or null. `absence`: `{"id":3,"kind":"vacation"}` (approved only) or null.
`creditMin` = minutes credited by approved vacation/sick/business trip (flex time is not credited).

### State
```json
{ "state": "working", "since": "2026-11-17T07:02:00Z", "openEntryId": 41, "pauseSince": null, "now": "2026-11-17T09:30:12Z" }
```
`state`: `none | working | pause | done`. `since` is the start of the current working stretch (state `working`) or the start of the
open entry (state `pause`). `pauseSince` is set in state `pause` and is the instant the break began. `now` is the server clock; clients
use it to correct their own clock for live durations (`GET /api/status` carries it at top level as well).

## Pages (HTML)

| Route | Auth | Notes |
|---|---|---|
| `GET /` | session | template `main` (layout `user`) |
| `GET /kiosk` | manager, kiosk group member or admin | template `kiosk` (layout `user`); others are redirected to `/` |
| `GET /go` | public | start page of the installed app (`start_url` of the manifest). A paired phone shows its status and the QR scanner, an unpaired one the pairing steps (add to Home Screen, open the app, scan the pairing QR or enter the code). Needs no Nextcloud login. Delivered as a standalone HTML document (own `<head>` with the app manifest, `apple-touch-icon` and title; no Nextcloud layout, no core JavaScript) with its own CSP and `camera=(self)` |
| `GET /s/{code}` | public | standalone page (same document type as `/go`), parameter `code` (string, raw from URL) |
| `GET /pair/{token}` | public | standalone page (same document type as `/go`), parameter `token` (string, raw from URL) |
| `GET /manifest` | public | PWA manifest JSON (`application/manifest+json`): `id`, localized `name`/`short_name` (de Zeit-Wächter, en TimeGuardian, it Guardiano ore, chosen by `Accept-Language`), `start_url` = `<base>go`, `scope` = `<base>`, `display: standalone`, icons 192, 512, maskable 512 and SVG, `theme_color`. The pages replace Nextcloud's own `<link rel="manifest">` with this one (`js/tg-boot.js`) |
| `GET /sw.js` | public | contents of `js/tg-sw.js`, header `Service-Worker-Allowed` set to the app base path |

## Own data

### `GET /api/me`
```json
{
  "uid": "anna", "name": "Anna Weber",
  "isChef": false, "isKiosk": false, "isAdmin": false,
  "lang": "de", "timezone": "Europe/Berlin", "today": "2026-11-17",
  "conf": { "weeklyMin": 2400, "daysMask": 31, "mode": "simple", "lang": "de", "canSetTarget": false },
  "state": { "state": "working", "since": "2026-11-17T07:02:00Z", "openEntryId": 41, "pauseSince": null },
  "todaySummary": { "day": "2026-11-17", "netMin": 212, "pauseMin": 0, "sollMin": 480, "entries": [ /* Entry */ ] },
  "week": { /* see GET /api/week */ },
  "account": { "balanceMin": 95, "asOf": "2026-11-16", "todaySollMin": 480, "todayNetMin": 212 },
  "deviceCount": 1,
  "projects": ["Support", "Interna"],
  "features": { "calendar": true, "deck": false, "talk": false, "webhook": false, "liveWarnings": true },
  "pendingDecisions": { "absences": 0, "corrections": 0 }
}
```
`pendingDecisions` is only non-zero for managers.

### `GET /api/setup`
Roles for everybody in the team, the setup checklist for managers only (`checklist` is `null` for others).
```json
{
  "roles": { "isChef": true, "isAdmin": false, "chefs": ["Anna Weber"], "chefGroup": "tg-chef", "teamGroup": "tg-team", "kioskGroup": "",
             "memberCount": 4, "members": ["..."], "chefGroupExists": true, "teamGroupExists": true },
  "checklist": {
    "steps": [
      { "id": "roles",   "kind": "auto", "done": true,  "params": { "chefs": 1, "members": 4 } },
      { "id": "region",  "kind": "auto", "done": false, "params": {} },
      { "id": "kiosk",   "kind": "auto", "done": false, "params": { "seenAt": null } },
      { "id": "phone",   "kind": "auto", "done": false, "params": { "devices": 0 } },
      { "id": "install", "kind": "hint", "done": null,  "params": {} },
      { "id": "punch",   "kind": "auto", "done": false, "params": { "punches": 0 } }
    ],
    "required": 5, "doneCount": 1, "complete": false
  }
}
```
`memberCount`, `members`, `chefGroupExists` and `teamGroupExists` are only present for managers. `region` is done when
the federal state and the default weekly hours were stored once (not just defaults). `kiosk` is done once a kiosk page
polled `GET /api/kiosk/code`. `install` can only be judged by the browser, so the server reports `done: null`.

### `GET /api/config` / `PUT /api/config` (manager)
The configuration page for managers (who are not necessarily Nextcloud admins). `GET` returns
```json
{ "config": { /* same object as GET /api/admin/config */ }, "locked": ["chefGroup", "teamGroup", "kioskGroup"],
  "canEditGroups": false, "roles": { /* as in GET /api/setup */ },
  "thresholds": { "warnDayMin": 570, "warnBreakMin": 345, "forgottenMin": 720, "limitDayMin": 600 } }
```
`PUT` takes any subset of the keys of `config` (see `POST /api/admin/config`) and returns the same shape as `GET`.
Managers may write everything except the keys in `locked`; sending one of those answers `403 forbidden` with the keys
in `fields`, unless the caller is also a Nextcloud admin. Employees get `403 forbidden` for both methods.

### `GET /api/settings` / `PUT /api/settings`
`PUT {"mode":"simple|pause","lang":"de|en|it"}` (both optional) -> `conf` object as above. The weekly target is
set by managers via `PUT /api/team/person/{uid}/target`.

### `GET /api/entries?from=YYYY-MM-DD&to=YYYY-MM-DD`
Own entries, at most 366 days. -> `{"entries":[Entry,...]}` sorted by start.

### `POST /api/entries`
Manual booking. Body (start/end are local `HH:MM` together with `day`, or ISO instants for overnight work):
```json
{ "day": "2026-11-16", "start": "08:00", "end": "16:30", "pauseMin": 30, "project": "Support", "note": "x" }
```
Days up to 31 days back, not in the future, no overlap. -> `201 {"entry": Entry, "newViolations": [Violation], "violations": [Violation]}`
(`violations` = all of that day). Error `validation` with `fields`.

### `PUT /api/entries/{id}`
Same fields, all optional. Own entries only, day not older than 7 days (else `locked`). -> `{"entry","newViolations","violations"}`.

### `DELETE /api/entries/{id}`
Own entry, not older than 7 days. -> `{"deleted": true}`. Every change is written to the audit log.

### `GET /api/week?date=YYYY-MM-DD`
Default today. ISO week Monday to Sunday containing `date`.
```json
{
  "from": "2026-11-16", "to": "2026-11-22", "kw": 47, "year": 2026,
  "targetMin": 2400, "limitMin": 2880,
  "sollMin": 1920, "netMin": 1240, "creditMin": 0, "deltaMin": -680,
  "days": [ /* 7 x Day */ ], "violations": [ /* Violation, the whole week */ ]
}
```

### `GET /api/month?ym=YYYY-MM`
Same structure with `ym`, `from`, `to`, `days` (one per calendar day), totals `sollMin netMin creditMin deltaMin`, `violations`,
and `byProject`: `[{"project":"Support","netMin":900}]`.

### `GET /api/account`
```json
{ "balanceMin": 95, "asOf": "2026-11-16", "since": "2026-02-02",
  "history": [ { "ym": "2026-10", "sollMin": 10080, "istMin": 10200, "creditMin": 480, "deltaMin": 600, "balanceMin": 90 } ] }
```
`balanceMin` = cumulative (net + credits - target) up to and including yesterday. `history` = last 12 months, oldest first,
`balanceMin` is the running balance at the end of that month. Target: weekly minutes divided over the working days mask;
public holidays of the configured federal state have target 0; vacation, sick leave and business trips credit the target
for the day, flex time does not.

### `GET /api/violations?from=&to=`
-> `{"violations":[Violation,...]}` newest first (max 366 days).

## Punching

### Authentication of the two punch endpoints
Either the Nextcloud session (+ CSRF `requesttoken`) or the header `X-TG-Device: <deviceToken>`.
A device token gives access to nothing else.

### `POST /api/punch`
```json
{ "code": "29123456.8f3c...", "action": "in|out|pause|resume", "project": "Support", "note": "optional" }
```
* `code` is the raw code from the scanned URL `/s/<code>`. It is **required** for `in` and when no `action` is given.
* `action` is optional. Without it the next step is derived: `none/done -> in`; `working -> out` (mode `simple`) or `pause` (mode `pause`); `pause -> resume`.
* `out` is also allowed while on a break. A break of up to 90 minutes is booked as taken and the entry ends now. A longer break is assumed forgotten: the entry ends where the break began and the break is not booked (the net time stays right; the end can be corrected afterwards). `resume` books the whole break as `pauseMin`.
* In mode `simple` a second block on the same day is allowed; the gap becomes the break (gaps of 15 minutes or more count as break).
* Server time is used; clients cannot send a time.

Response:
```json
{
  "ok": true, "action": "in", "name": "Anna Weber", "mode": "simple",
  "state": { "state": "working", "since": "2026-11-17T07:02:00Z", "openEntryId": 41, "pauseSince": null },
  "entry": { /* Entry */ },
  "netMinToday": 0, "weekNetMin": 1240,
  "newViolations": []
}
```
Errors: `invalid_code`, `code_required`, `invalid_transition` (409, with `state`), `too_fast`, `rate_limited`, `unauthorized`, `csrf`.

### `GET /api/status`
```json
{ "name": "Anna Weber", "mode": "simple", "state": "working", "since": "2026-11-17T07:02:00Z",
  "pauseSince": null, "netMinToday": 212, "weekNetMin": 1240, "targetMin": 2400, "limitMin": 2880 }
```

## Pairing

### `POST /api/pair` (session)
-> `201 {"token": "<64 hex>", "code": "ABCD-EFGH", "url": "https://host/apps/timeguardian/pair/<token>", "qrText": "<same as url>", "expires": "2026-11-17T07:07:00Z", "expiresInSec": 300}`.
Token and `code` are single use (redeeming either one burns both) and live 5 minutes. Only hashes are stored (SHA-256 of the token, a
keyed HMAC of the code). The short `code` has 8 characters from `ABCDEFGHJKLMNPQRSTUVWXYZ23456789` (no `0 O 1 I`), shown as `ABCD-EFGH`.
It exists because an iPhone home-screen app has its own storage: a QR scanned with the camera always opens Safari, so the person
types the code (or scans the QR with the scanner inside the app).

### `POST /api/pair/redeem` (public, rate limited)
`{"token": "<64 hex>", "label": "iPhone"}` **or** `{"code": "ABCD-EFGH", "label": "iPhone"}` (case, spaces and the dash are ignored) -> `{"deviceToken": "<64 hex>", "name": "Anna Weber", "expiresAt": "2027-05-16T07:03:00Z"}`.
Store `deviceToken` in `localStorage` and send it as `X-TG-Device`. The token is valid 180 days and renews on use.
Errors: `token_invalid` (400), `token_expired` (410), `token_used` (410), `rate_limited` (429).
The `code` path is rate limited strictly: every wrong, expired or already used code counts as a failure for the caller's IP address;
after 10 failures within an hour further code attempts answer `rate_limited` until the oldest failure is an hour old. The long token path is not affected.

### `GET /api/devices` (session)
`{"devices":[{"id":5,"label":"iPhone","createdAt":"...Z","lastUsed":"...Z","expiresAt":"...Z","revoked":false}]}`

### `DELETE /api/devices/{id}` (session, own devices) -> `{"revoked": true}`

## Absences

### `GET /api/absences?scope=mine|pending|team&from=&to=`
`mine` (default): own. `pending`: managers, requests waiting for a decision. `team`: managers, all team absences in the range.
```json
{ "absences": [ { "id": 3, "uid": "anna", "name": "Anna Weber", "kind": "vacation", "fromDay": "2026-12-21",
  "toDay": "2026-12-31", "days": 7, "status": "pending", "decidedBy": null, "decidedAt": null, "note": null,
  "createdAt": "...Z" } ] }
```
`kind`: `vacation | sick | flex | trip`; `status`: `pending | approved | rejected`; `days` = working days in the range (mask, no holidays).

### `POST /api/absences`
`{"kind":"vacation","fromDay":"2026-12-21","toDay":"2026-12-31","note":"optional"}` -> `201 {"absence": {...}}`. Managers are notified.
Range: from at most 90 days back, to at most 365 days ahead, at most 60 days.

### `DELETE /api/absences/{id}` own, pending or approved (removes the calendar entry) -> `{"deleted": true}`

### `POST /api/absences/{id}/decide` (manager)
`{"decision":"approve|reject","note":"optional"}` -> `{"absence": {...}}`. The requester is notified; on approval a calendar
entry is created (if enabled). A manager cannot decide their own request unless the admin allows self approval.

## Corrections

### `GET /api/corrections?scope=mine|pending&status=`
```json
{ "corrections": [ { "id": 2, "uid": "anna", "name": "Anna Weber", "entryId": 41, "day": "2026-11-02",
  "current": { "start": "...Z", "end": "...Z", "startHM": "08:02", "endHM": "16:31", "pauseMin": 30 },
  "proposed": { "start": "...Z", "end": "...Z", "startHM": "08:00", "endHM": "16:30", "pauseMin": 45 },
  "reason": "Forgot to clock out", "status": "pending", "decidedBy": null, "decidedAt": null, "createdAt": "...Z" } ] }
```

### `POST /api/corrections`
`{"entryId":41,"start":"08:00","end":"16:30","pauseMin":45,"reason":"..."}` (`start`/`end` local `HH:MM` on the entry's day, or ISO;
all optional but at least one; `reason` 3-500 characters) -> `201 {"correction": {...}}`.

### `POST /api/corrections/{id}/decide` (manager)
`{"decision":"approve|reject","note":"optional"}` -> `{"correction": {...}, "entry": Entry|null}`. Approval rewrites the entry
(source `correction`) and re-checks the rules; the old and new values land in the audit log.

## Manager

### `GET /api/team/overview?week=YYYY-MM-DD`
```json
{
  "week": { "from": "2026-11-16", "to": "2026-11-22", "kw": 47, "year": 2026 },
  "people": [ { "uid": "anna", "name": "Anna Weber", "state": "working", "since": "...Z", "netMin": 1240, "sollMin": 1920,
      "balanceMin": 95, "days": [ { "day": "2026-11-16", "netMin": 480 } ], "violations": { "verstoss": 0, "hinweis": 1 } } ],
  "presentNow": ["anna"],
  "violations": [ /* Violation + "name" */ ],
  "topOvertime": [ { "uid": "ben", "name": "Ben Keller", "balanceMin": 1260 } ],
  "pending": { "absences": 1, "corrections": 0 }
}
```
`days` always has 7 items (Monday to Sunday).

### `GET /api/team/person/{uid}?from=&to=`
`{"user":{"uid","name","state":State},"entries":[Entry],"days":[Day],"violations":[Violation],"account":{...as /api/account}}` (max 366 days). Team members only.

### `PUT /api/team/person/{uid}/target` (manager)
`{"weeklyMin":1200,"daysMask":7}` -> `{"conf": {...}}`. `daysMask`: bit 0 = Monday ... bit 6 = Sunday (Mon-Fri = 31).

### `GET /api/team/export.csv?ym=YYYY-MM&format=detail|payroll`
CSV download (UTF-8 with BOM, RFC 4180, formula injection protected). Managers only.

### `GET /api/audit?uid=&from=&to=`
`{"audit":[{"id":9,"at":"...Z","actor":"boss","uid":"anna","kind":"entry.update","entity":"entry","entityId":41,"before":{...},"after":{...}}]}`
Managers (team members) or the user themself. Append-only.

## Export (own data)
* `GET /api/export.csv?ym=YYYY-MM&format=detail|payroll` -> CSV download
* `GET /api/export.json` -> JSON download with every record stored about the caller (GDPR art. 15/20)

## Kiosk

### `GET /api/kiosk/code` (manager, kiosk group member or admin)
```json
{ "url": "https://host/apps/timeguardian/s/29123456.8f3c...", "qrText": "https://host/apps/timeguardian/s/29123456.8f3c...",
  "window": 29123456, "expiresInSec": 37, "windowSec": 60 }
```
The code changes every 60 seconds; the server accepts the current and the previous window. Poll every 20 s.

## Admin

### `GET /api/admin/config` (Nextcloud admin) / `POST /api/admin/config`
```json
{
  "chefGroup": "tg-chef", "teamGroup": "tg-team", "kioskGroup": "",
  "state": "BE", "timezone": "Europe/Berlin",
  "defaultWeeklyMin": 2400, "defaultDaysMask": 31, "retentionDays": 730,
  "projects": ["Support", "Interna"],
  "selfApproval": false, "liveWarnings": true,
  "integrations": { "notifications": true, "calendar": false, "absenceCalendar": true, "deck": false, "talk": false },
  "deckStackId": null, "talkRoomToken": "",
  "webhookUrl": "", "hasWebhookSecret": false
}
```
`POST` accepts any subset of these keys plus `webhookSecret` (write only; empty string clears it). The secret is never returned.
Unknown keys are ignored; invalid values answer `validation`.
