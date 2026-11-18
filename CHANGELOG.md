# Changelog

## [0.1.4] - 2026-11-18

App Store metadata: screenshots, repository, bug tracker and documentation links, release archive as tar.gz. No functional change.

## [0.1.3] - 2026-11-18

iPhone: the installed home-screen app now opens the stamp app and can be paired inside the app.

* Cause of the broken iPhone install: `/go`, `/s/{code}` and `/pair/{token}` were rendered in Nextcloud's guest layout. Its
  `<head>` carries Nextcloud's own manifest (`start_url` "/" = Nextcloud login), Nextcloud's touch icon and the title "Nextcloud";
  our manifest was only swapped in by JavaScript afterwards, which iOS Safari ignores for "Add to Home Screen"
* The three public pages are now complete standalone HTML documents (`lib/Http/StandalonePageResponse.php`, `lib/Domain/StandalonePage.php`)
  with their own `<head>`: exactly one manifest link, 180x180 `apple-touch-icon`, localized title and
  `apple-mobile-web-app-*` tags, `viewport-fit=cover`, safe-area padding, no Nextcloud core JavaScript. Own CSP (own files only, no
  nonce duty) and `Feature-Policy`/`Permissions-Policy` with `camera=(self)` (Nextcloud sends `camera 'none'` otherwise). Main page
  and kiosk stay in the Nextcloud layout. The CSRF token for session punches is passed as `data-requesttoken`
* Pairing in the installed app (iOS home-screen apps have their own storage; a QR scanned with the camera always opens Safari):
  new short one-time code `ABCD-EFGH` (8 characters, no `0 O 1 I`, 5 minutes, only a keyed hash stored). `POST /api/pair` returns
  `code`, `POST /api/pair/redeem` accepts `{token}` or `{code}`. Strict rate limit on the code path: 10 wrong attempts per IP and
  hour. New migration (`code_hash`, table `tg_pairfail`)
* Devices page shows the code large next to the QR. `/go` shows the steps for an unpaired phone (add to Home Screen, open the app,
  scan the pairing QR or enter the code); the scanner inside `/go` redeems a pairing QR directly. `/pair/{token}` in an iPhone
  browser tells people with the installed app to continue there; pairing in the browser without installing still works
* Scanner on iOS: `playsinline`, `muted`, `autoplay`, fallback to any camera when the rear camera constraint fails, clear hint for a
  denied camera permission (Settings / Safari / Camera), jsQR fallback as before
* Cache version raised (`tg-shell-v4`); the service worker still never touches navigations

## [0.1.2] - 2026-11-18

Cache bump so browsers reload the fixed assets of 0.1.1.

## [0.1.1] - 2026-11-18

Fixes and orientation. The app is now installable as a PWA and explains itself.

* Fix: the service worker no longer intercepts page navigations and never shows an "Offline" page while online. It only
  touches `js`, `css` and `img` files of the app; cache version raised, old workers are replaced at once
* PWA: the pages replace Nextcloud's own `<link rel="manifest">` with the app manifest (`js/tg-boot.js`, runs first on all four
  pages and keeps exactly one manifest link in the head). The manifest has `id`, localized name, `start_url` `/go`, scope,
  standalone display, icons 192/512/maskable and theme colour
* New public start page `GET /go` for the installed app: status ("clocked in since ..."), big "Scan QR code" button, pause /
  resume / end by mode, instructions for pairing an unpaired phone, link to the full reports
* "Install as an app" card on the start page, devices page, `/go` and after pairing: install button for Chrome/Edge/Android,
  illustrated steps for iPhone/iPad Safari, hidden when the app already runs standalone. The scanner on `/go` also reads the pairing QR
* Start page: role tile ("You are: manager and employee"), live setup checklist for managers (`GET /api/setup`), "How it works" in three
  steps, call to pair a phone
* New page "Configuration" for managers (`GET/PUT /api/config`): every uncritical setting with a help text; the three group names stay
  with Nextcloud administrators but are visible. New page "Help" with FAQ and privacy summary
* Administration settings restructured: status block, sections with explanations, links to the app, kiosk and `/go`
* Consistent name per language: de Zeit-Wächter, en TimeGuardian, it Guardiano ore (info.xml, menu, page titles, manifest)
* Tooltips on every menu entry, explanatory empty states, friendlier errors

## [0.1.0] - 2026-11-17

First release.

* Punching by rotating kiosk QR code, QR login pairing of phones, device tokens
* ArbZG rules DAY10, DAY8, BREAK30, BREAK45, REST11, SUN, WEEK48, ABSENT with de/en/it texts
* Time account, public holidays of all 16 federal states, part-time targets
* Absence and correction workflows with notifications, calendar entries and an append-only audit log
* Live warnings, weekly manager report, retention job
* Team overview, CSV (detail and payroll) and JSON export
* Dashboard widget, admin and personal settings, PWA manifest and service worker
* Optional integrations: calendar, Deck, Talk, signed webhook
