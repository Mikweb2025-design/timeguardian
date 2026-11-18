# Privacy

TimeGuardian stores working time data of employees. This document lists what is stored, why, and for how long. It is a
technical description, not legal advice; employers remain responsible for their own data protection documentation.

## Data catalog

| Table | Content | Purpose |
|---|---|---|
| `tg_entry` | user ID, day, start, end, break minutes, optional project and note, source | working time record (ArbZG § 16 (2)) |
| `tg_stamp` | user ID, instant, action (in, out, pause, resume), device ID, source, keyed hash of the IP address | audit trail of punches, rate limiting |
| `tg_device` | user ID, SHA-256 hash of the device token, label, creation, last use, expiry, revoked flag | QR login of phones; the token itself is never stored |
| `tg_pair` | user ID, SHA-256 hash of the pairing token, expiry, used flag | one-time pairing |
| `tg_absence` | user ID, kind (vacation, sick, flex, trip), period, status, decider, note | absence management. "Sick" is health related data: restrict the manager group accordingly |
| `tg_correction` | user ID, entry, proposed values, reason, status, decider | correction workflow |
| `tg_audit` | actor, user ID, instant, kind, old and new values | append-only change log |
| `tg_user_conf` | user ID, weekly target, working days, mode, language, warning state | personal settings |
| `tg_violation` | user ID, day, rule, level, value | materialized rule hits for fast display |

IP addresses are never stored in clear text: only an HMAC with the instance secret is kept, which allows comparing but not reading them.
Tokens are never stored or logged in clear text.

## Who sees what

* Employees see only their own data.
* Managers (members of the configured manager group) see the data of team members.
* Nextcloud administrators see nothing through the app unless they are also managers. (They can of course read the database.)
* The optional webhook sends events (user ID, display name, action, rule hits) to the URL the admin configures.
* The optional Talk, Deck and calendar integrations place names, days and rule hits into those apps, where their own permissions apply.

## Legal basis

Typically the employment relationship and the statutory duty to record working time beyond 8 hours per day and to keep the records for
two years (§ 16 (2) ArbZG; § 26 BDSG in Germany, Art. 6 (1) (b) and (c) GDPR). Check the works agreement or other local requirements.

## Retention and deletion

* The daily job deletes entries, stamps, absences, violations, corrections and audit rows older than the retention period (default 730 days, configurable 30 to 3650).
* Deleting a Nextcloud user deletes all of their time data immediately.
* Disabling a user keeps the data (statutory retention) but revokes all device tokens.

## Data subject rights

* Access and portability (Art. 15, 20): every user can download a JSON export of everything stored about them (`/apps/timeguardian/api/export.json`) and CSV files per month.
* Rectification: through correction requests, every change is logged.
* Erasure: see above; for earlier deletion delete the user or lower the retention period.
