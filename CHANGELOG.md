# Changelog

## 0.1.2

Cache bump so browsers reload the fixed assets of 0.1.1.

## 0.1.1

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

## 0.1.0

First release.

* Punching by rotating kiosk QR code, QR login pairing of phones, device tokens
* ArbZG rules DAY10, DAY8, BREAK30, BREAK45, REST11, SUN, WEEK48, ABSENT with de/en/it texts
* Time account, public holidays of all 16 federal states, part-time targets
* Absence and correction workflows with notifications, calendar entries and an append-only audit log
* Live warnings, weekly manager report, retention job
* Team overview, CSV (detail and payroll) and JSON export
* Dashboard widget, admin and personal settings, PWA manifest and service worker
* Optional integrations: calendar, Deck, Talk, signed webhook
