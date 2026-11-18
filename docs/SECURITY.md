# Security

## Model

| Asset | Protection |
|---|---|
| Kiosk QR code | `<window>.<mac>`, window = unix time / 60, mac = first 128 bit of HMAC-SHA256 with a random per-instance secret over `tg-qr|<window>`. The server accepts the current and the previous window, compared with `hash_equals`. A screenshot is useless after two minutes |
| Pairing token | 32 random bytes, valid 5 minutes, single use (atomic update), only SHA-256 stored |
| Device token | 32 random bytes, only SHA-256 stored, lookup by hash and `hash_equals`, 180 days, renewed on use, revocable, revoked automatically when the user is disabled |
| Device token scope | Only `POST /api/punch` and `GET /api/status`. No Nextcloud access, no other API |
| Rate limits | `AnonRateLimit` / `UserRateLimit` on public and punch endpoints, 30 punches per hour per device token, at most 10 active devices per user, minimum 2 minutes between two punches |
| Permissions | One `AccessService`: own data, or manager of the team. Unknown and forbidden ids both answer 404. Admin is not manager. No self approval unless enabled |
| Input | Strict validation of dates, enumerations, lengths; text is stripped of tags and control characters; all SQL goes through the query builder with bound parameters |
| Output | JSON only; CSV cells starting with `=`, `+`, `-` or `@` are prefixed with an apostrophe; settings pages escape every value |
| CSRF | Session endpoints use Nextcloud's request token. The punch endpoint is exempt only so that device tokens work; a session based punch is checked with `passesCSRFCheck()` |
| Audit | Append-only log of every change (the mapper has no update path) |
| Side effects | Notifications, calendar, Deck, Talk and webhook run in their own `try/catch` and cannot fail a booking |
| Webhook | HMAC-SHA256 signature with timestamp, secret never returned by the API |

## Residual risks

* Anyone who photographs the kiosk screen can use the code for up to two minutes and needs a paired phone or a session to use it.
* A stolen unlocked phone with a paired token can punch for its owner until the device is revoked (settings or app, "Devices").
* The kiosk account must be protected like any account that can open the kiosk page.

## Reporting a vulnerability

Please report security problems privately to the maintainers of your distribution of this app, not in a public issue.
