/* Node-Tests für reine Frontend-Logik: node tests/frontend/run.js */
'use strict';
const fs = require('fs'), path = require('path'), assert = require('assert');
const core = require('../../js/tg-core.js'), i18n = require('../../js/tg-i18n.js');
let n = 0, failed = 0;
function test(name, fn) { n++; try { fn(); } catch (e) { failed++; console.log('FAIL ' + name + '\n  ' + (e && e.message)); } }
const JS = path.join(__dirname, '../../js');
const sources = fs.readdirSync(JS).filter((f) => /^tg-.*\.js$/.test(f) && f !== 'tg-i18n.js').map((f) => fs.readFileSync(path.join(JS, f), 'utf8'));

/* ---- i18n ---- */
test('i18n: de/en/it haben identische Schlüssel', () => {
  const k = (l) => Object.keys(i18n.dict[l]).sort();
  assert.deepStrictEqual(k('en'), k('de')); assert.deepStrictEqual(k('it'), k('de'));
});
test('i18n: keine leeren Texte, Platzhalter konsistent', () => {
  Object.keys(i18n.dict.de).forEach((key) => {
    ['de', 'en', 'it'].forEach((l) => { const s = i18n.dict[l][key]; assert.ok(s && s.length, l + ':' + key); });
    const ph = (s) => (s.match(/\{\w+\}/g) || []).sort().join();
    assert.strictEqual(ph(i18n.dict.en[key]), ph(i18n.dict.de[key]), 'Platzhalter ' + key);
    assert.strictEqual(ph(i18n.dict.it[key]), ph(i18n.dict.de[key]), 'Platzhalter it ' + key);
  });
});
test('i18n: alle im Code verwendeten Schlüssel existieren', () => {
  const used = new Set();
  sources.forEach((s) => { const re = /\bt\('([a-z0-9_]+)'(?!\s*\+)/g; let m; while ((m = re.exec(s))) used.add(m[1]); });
  const fm = /FIELD_MSG = \{([^}]*)\}/.exec(sources.join('\n')); if (fm) { fm[1].replace(/'(f_[a-z_]+)'/g, (a, k) => used.add(k)); }
  ['nav_today', 'nav_week', 'nav_month', 'nav_account', 'nav_absences', 'nav_corrections', 'nav_team', 'nav_devices', 'nav_config', 'nav_settings', 'nav_help'].forEach((k) => used.add(k));
  const missing = [...used].filter((k) => i18n.dict.de[k] === undefined);
  assert.deepStrictEqual(missing, []);
});
test('i18n: dynamische Schlüsselfamilien vollständig', () => {
  const fam = { state_: ['none', 'working', 'pause', 'done'], res_: ['in', 'out', 'pause', 'resume'], rule_: ['DAY10', 'DAY8', 'BREAK30', 'BREAK45', 'REST11', 'SUN', 'WEEK48', 'ABSENT'], rule_short_: ['DAY10', 'DAY8', 'BREAK30', 'BREAK45', 'REST11', 'SUN', 'WEEK48', 'ABSENT'],
    kind_: ['vacation', 'sick', 'flex', 'trip'], status_: ['pending', 'approved', 'rejected'], src_: ['manual', 'correction', 'seed', 'scan'], mode_: ['simple', 'pause', 'simple_d', 'pause_d'], heat_: [1, 2, 3, 4, 5], pair_step: [1, 2, 3],
    f_abs_: ['kind', 'date', 'range', 'long'],
    tip_: ['today', 'week', 'month', 'account', 'absences', 'corrections', 'team', 'devices', 'config', 'settings', 'help', 'kiosk'],
    chk_: ['roles', 'region', 'kiosk', 'phone', 'install', 'punch', 'roles_d', 'roles_t', 'region_d', 'region_t', 'kiosk_d', 'kiosk_t', 'phone_d', 'phone_t', 'install_d', 'install_t', 'punch_d', 'punch_t'],
    how_: ['1_t', '1_p', '2_t', '2_p', '3_t', '3_p'], go_pair_: [1, 2, 3, 'a1', 'a2'], help_priv_: [1, 2, 3],
    faq_: [1, 2, 3, 4, 5, 6, 7, 8, 9].reduce((a, n) => a.concat([n + '_q', n + '_a']), []) };
  Object.keys(fam).forEach((p) => fam[p].forEach((s) => assert.ok(i18n.dict.de[p + s], p + s)));
});
test('i18n: Parameter-Ersetzung und Fallback', () => {
  i18n.setLang('en'); assert.strictEqual(i18n.t('hello', { name: 'X' }), 'Hello X'); assert.strictEqual(i18n.t('nope_key'), 'nope_key');
  i18n.setLang('xx'); assert.strictEqual(i18n.lang(), 'de'); assert.strictEqual(i18n.detect('it'), 'it');
});
test('i18n: Wochentage 7 je Sprache', () => { ['de', 'en', 'it'].forEach((l) => assert.strictEqual(i18n.dict[l].daynames.split(',').length, 7)); });

/* ---- Zeit ---- */
test('Dauerformate', () => {
  assert.strictEqual(core.fmtHM(450), '7 h 30 min'); assert.strictEqual(core.fmtClockDur(65), '1:05'); assert.strictEqual(core.fmtH(450, 'de'), '7,5 h'); assert.strictEqual(core.fmtH(450, 'en'), '7.5 h');
  assert.strictEqual(core.fmtSignedHM(-30), '−0:30 h'); assert.strictEqual(core.fmtSignedHM(95), '+1:35 h'); assert.strictEqual(core.fmtSignedHM(0), '0:00 h');
  assert.strictEqual(core.fmtStopwatch(3725), '01:02:05');
});
test('parseClock/clockOf/localToIso', () => {
  assert.strictEqual(core.parseClock('08:05'), 485); assert.strictEqual(core.parseClock('24:00'), null); assert.strictEqual(core.parseClock('8:5'), null); assert.strictEqual(core.clockOf(485), '08:05');
  assert.ok(/^2026-11-17T/.test(core.localToIso('2026-11-17', '08:00')));
});
test('Datum: Wochen, Monate, ISO-KW', () => {
  assert.strictEqual(core.monday('2026-11-18'), '2026-11-16'); assert.strictEqual(core.dow('2026-11-22'), 6); assert.strictEqual(core.addDays('2026-12-31', 1), '2027-01-01');
  assert.deepStrictEqual(core.isoWeek('2026-11-17'), { year: 2026, week: 47 }); assert.deepStrictEqual(core.isoWeek('2027-01-01'), { year: 2026, week: 53 }); assert.deepStrictEqual(core.isoWeek('2024-12-30'), { year: 2025, week: 1 });
  assert.strictEqual(core.addMonths('2026-12', 1), '2027-01'); assert.strictEqual(core.addMonths('2026-01', -1), '2025-12'); assert.strictEqual(core.daysInMonth('2024-02'), 29);
  assert.strictEqual(core.parseDay('2026-02-30'), null); assert.strictEqual(core.rangeDays('2026-11-01', '2026-11-03').length, 3);
  const g = core.monthGrid('2026-11'); assert.strictEqual(g[0][6], '2026-11-01'); assert.ok(g.every((w) => w.length === 7));
});
test('Netto/Live-Zähler', () => {
  assert.strictEqual(core.entryMinutes({ start: '2026-11-17T07:00:00Z', end: '2026-11-17T15:30:00Z', pauseMin: 30 }), 480);
  assert.strictEqual(core.liveNet(100, 'working', 0, 5 * 60000 + 100), 105); assert.strictEqual(core.liveNet(100, 'pause', 0, 9e9), 100);
});
test('Intl-Formate', () => { assert.strictEqual(core.clockTz('2026-11-17T07:02:00Z', 'Europe/Berlin'), '08:02'); assert.ok(/November/.test(core.fmtMonth('2026-11', 'de'))); assert.ok(/novembre/.test(core.fmtMonth('2026-11', 'it'))); });

/* ---- Charts ---- */
test('Chart: Ring-Geometrie und Grenzstufen', () => {
  const g = core.ringGeometry(50, 0.5); assert.ok(Math.abs(g.dash - Math.PI * 50) < 1e-9); assert.strictEqual(core.ringGeometry(50, 3).dash, g.circumference);
  assert.strictEqual(core.limitLevel(500, 600, 570), 'ok'); assert.strictEqual(core.limitLevel(580, 600, 570), 'warn'); assert.strictEqual(core.limitLevel(601, 600, 570), 'bad'); assert.strictEqual(core.ratio(5, 0), 0);
});
test('Chart: Balkenlayout', () => {
  const L = core.barLayout([{ value: 480 }, { value: 0 }, { value: 700 }], 300, 200, { minMax: 600 });
  assert.strictEqual(L.top, 720); assert.strictEqual(L.bars[1].h, 0); assert.ok(L.bars[2].h > L.bars[0].h); assert.ok(L.bars.every((b) => b.x >= L.padL && b.x + b.w <= 300));
  assert.ok(Math.abs(L.y(0) - (L.padT + L.ih)) < 1e-9); assert.strictEqual(core.niceMax(10, 120), 240);
});
test('Chart: Liniendiagramm', () => {
  const L = core.lineLayout([{ value: -300 }, { value: 0 }, { value: 600 }], 400, 200); assert.strictEqual(L.pts.length, 3); assert.ok(L.pts[2].y < L.pts[0].y); assert.ok(/^M/.test(L.d)); assert.ok(L.zeroY > L.pts[2].y && L.zeroY < L.pts[0].y);
  assert.ok(core.lineLayout([], 100, 100).pts.length === 0);
});
test('Chart: Timeline/Heatmap', () => {
  const t = core.timelineSegments([{ startMin: 450, endMin: 720 }, { startMin: 750, endMin: 1200, open: true }]); assert.strictEqual(t.lo, 360); assert.strictEqual(t.hi, 1320); assert.ok(t.segs[1].open && t.segs[1].left > t.segs[0].left);
  assert.strictEqual(core.heatLevel(0), 0); assert.strictEqual(core.heatLevel(480), 4); assert.strictEqual(core.heatLevel(650), 5); assert.strictEqual(core.heatLevel(100), 1);
});

/* ---- QR-Text ---- */
const CODE = '29123456.8f3c1a2b9d4e5f60';
test('QR: Punch-URL, nackter Code, Pair-URL', () => {
  assert.deepStrictEqual(core.parseScanText('https://cloud.example.org/apps/timeguardian/s/' + CODE), { kind: 'punch', code: CODE });
  assert.deepStrictEqual(core.parseScanText('https://cloud.example.org/index.php/apps/timeguardian/s/' + CODE + '?x=1'), { kind: 'punch', code: CODE });
  assert.deepStrictEqual(core.parseScanText('  ' + CODE + ' '), { kind: 'punch', code: CODE });
  const tok = 'a'.repeat(64); assert.deepStrictEqual(core.parseScanText('https://c.example/apps/timeguardian/pair/' + tok), { kind: 'pair', token: tok });
});
test('QR: fremde/ungültige Texte werden abgelehnt', () => {
  ['https://evil.example/login', 'javascript:alert(1)', '', '12345', 'https://c.example/apps/timeguardian/s/x', 'https://c.example/apps/timeguardian/pair/short', 'a'.repeat(700), null, 42, 'https://c.example/apps/files/s/' + CODE].forEach((x) => assert.strictEqual(core.parseScanText(x), null, String(x).slice(0, 30)));
});

/* ---- Regelvorschau ---- */
test('Regelvorschau', () => {
  const r = (b, pe, d) => core.previewRules(b, pe, d == null ? 1 : d).map((x) => x.rule).join();
  assert.strictEqual(r([{ startMin: 480, endMin: 990, pauseMin: 30 }]), '');
  assert.strictEqual(r([{ startMin: 480, endMin: 1200, pauseMin: 45 }]), 'DAY10');
  assert.strictEqual(r([{ startMin: 480, endMin: 1000, pauseMin: 30 }]), 'DAY8');
  assert.strictEqual(r([{ startMin: 480, endMin: 960, pauseMin: 10 }]), 'DAY8,BREAK30'.replace('DAY8,', ''));
  assert.strictEqual(r([{ startMin: 480, endMin: 1100, pauseMin: 30 }]), 'DAY8,BREAK45');
  assert.strictEqual(r([{ startMin: 480, endMin: 700, pauseMin: 0 }, { startMin: 760, endMin: 1000, pauseMin: 0 }]), '');
  assert.strictEqual(r([{ startMin: 420, endMin: 600, pauseMin: 0 }], -200), 'REST11');
  assert.strictEqual(r([{ startMin: 480, endMin: 600, pauseMin: 0 }], null, 6), 'SUN');
  assert.ok(core.blocksOverlap({ startMin: 100, endMin: 200 }, [{ startMin: 150, endMin: 300 }])); assert.ok(!core.blocksOverlap({ startMin: 100, endMin: 150 }, [{ startMin: 150, endMin: 300 }]));
});
test('Sonstiges: Initialen, clampStr', () => { assert.strictEqual(core.initials('Anna Maria Weber'), 'AW'); assert.strictEqual(core.initials(''), '?'); assert.strictEqual(core.clampStr('abcdef', 3), 'abc'); });


/* ---- PWA / Boot ---- */
const boot = require('../../js/tg-boot.js');
test('Boot: Plattform-Erkennung iPhone/iPad/Android/Desktop', () => {
  const ipSafari = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';
  const ipChrome = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/120.0 Mobile/15E148 Safari/604.1';
  const android = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36';
  const mac = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15';
  assert.deepStrictEqual([boot.detect(ipSafari, 'iPhone', 5).ios, boot.detect(ipSafari, 'iPhone', 5).safari], [true, true]);
  assert.deepStrictEqual([boot.detect(ipChrome, 'iPhone', 5).ios, boot.detect(ipChrome, 'iPhone', 5).safari], [true, false]);
  assert.strictEqual(boot.detect(mac, 'MacIntel', 5).ios, true, 'iPadOS gibt sich als Mac aus');
  assert.strictEqual(boot.detect(mac, 'MacIntel', 0).ios, false);
  const a = boot.detect(android, 'Linux armv8l', 5); assert.deepStrictEqual([a.android, a.chromium, a.ios, a.safari], [true, true, false, false]);
  assert.deepStrictEqual(boot.detect('', '', 0), { ios: false, android: false, safari: false, chromium: false, mobile: false });
});
test('Boot: fremde Manifest-Links werden entfernt, unseres steht genau einmal im Head', () => {
  // minimales DOM, nur was fixManifest braucht
  const mk = (rel, href, parent) => ({ rel, href, parentNode: parent, getAttribute: (k) => (k === 'href' ? href : rel) });
  const head = { children: [], querySelector: () => null, querySelectorAll: () => [], appendChild(n) { n.parentNode = head; head.children.push(n); }, removeChild(n) { head.children.splice(head.children.indexOf(n), 1); n.parentNode = null; } };
  const body = { children: [], removeChild(n) { body.children.splice(body.children.indexOf(n), 1); n.parentNode = null; } };
  const foreign = mk('manifest', '/apps/theming/manifest/timeguardian?v=1', head); head.children.push(foreign);
  const inBody = mk('manifest', '/apps/timeguardian/manifest', body); body.children.push(inBody);
  const el = { getAttribute: (k) => (k === 'data-manifest' ? '/apps/timeguardian/manifest' : null) };
  const doc = { head, readyState: 'complete', addEventListener() {}, documentElement: {}, getElementById: () => el, querySelectorAll: () => head.children.concat(body.children).filter((n) => n.rel === 'manifest'),
    createElement: () => { const o = { attrs: {}, parentNode: null, setAttribute(k, v) { o.attrs[k] = v; if (k === 'rel') { o.rel = v; } if (k === 'href') { o.href = v; } }, getAttribute: (k) => o.attrs[k] }; return o; } };
  const src = fs.readFileSync(path.join(JS, 'tg-boot.js'), 'utf8');
  const sandbox = { document: doc, URL, navigator: {}, location: { href: 'https://c.example/apps/timeguardian/go', pathname: '/apps/timeguardian/go' }, addEventListener() {}, localStorage: {} };
  sandbox.self = sandbox; sandbox.window = sandbox;
  new Function('self', 'module', src).call(sandbox, sandbox, undefined);
  const links = head.children.filter((n) => n.rel === 'manifest');
  assert.strictEqual(links.length, 1); assert.strictEqual(links[0].href, '/apps/timeguardian/manifest'); assert.strictEqual(body.children.length, 0);
  assert.strictEqual(sandbox.TG.pwa.fixManifest(), 0, 'zweiter Lauf ändert nichts');
});
test('Service-Worker: keine Navigation, keine Offline-Seite, kein API-Zugriff', () => {
  const sw = fs.readFileSync(path.join(JS, 'tg-sw.js'), 'utf8');
  assert.ok(!/offlinePage|<!doctype|Offline/i.test(sw.replace(/\/\*[\s\S]*?\*\//g, '').replace(/\/\/.*$/gm, '')), 'keine Offline-Seite');
  assert.ok(/req\.mode === 'navigate'\) \{ return; \}/.test(sw)); assert.ok(sw.includes("'/api/'")); assert.ok(/tg-shell-v[2-9]/.test(sw));
});
test('Seiten: keine Inline-Skripte, Daten nie per innerHTML', () => {
  fs.readdirSync(path.join(__dirname, '../../templates')).forEach((f) => assert.ok(!/<script/i.test(fs.readFileSync(path.join(__dirname, '../../templates', f), 'utf8')), f));
  sources.forEach((s, i) => assert.ok(!/\.innerHTML\s*=/.test(s), 'innerHTML in Quelle ' + i));
});
test('i18n: Benennung je Sprache', () => {
  assert.strictEqual(i18n.dict.de.app_name, 'Zeit-Wächter'); assert.strictEqual(i18n.dict.en.app_name, 'TimeGuardian'); assert.strictEqual(i18n.dict.it.app_name, 'Guardiano ore');
  ['de', 'en', 'it'].forEach((l) => assert.ok(!/Timesheet Guardian|Il Guardiano delle ore/.test(JSON.stringify(i18n.dict[l]))));
});
test('Verbotene Verweise fehlen im Frontend', () => {
  const bad = new RegExp(['dan', 'iele|mich', 'elin|mik', 'web'].join(''), 'i');
  sources.forEach((s, i) => assert.ok(!bad.test(s), 'Verweis in Quelle ' + i));
});

/* ---- Kurz-Code, Scanner (iOS), Standalone-Seiten ---- */
test('Kurz-Code: normalisieren, prüfen, formatieren (Alphabet ohne 0/O/1/I)', () => {
  assert.strictEqual(core.normalizePairCode(' abcd-efgh '), 'ABCDEFGH'); assert.strictEqual(core.formatPairCode('abcdefgh'), 'ABCD-EFGH'); assert.strictEqual(core.formatPairCode('abcdefghjk'), 'ABCD-EFGH');
  assert.ok(core.validPairCode('abcd efgh')); assert.ok(core.validPairCode('ABCD-EFGH'));
  ['ABCD-EFG0', 'ABCD-EFGO', 'ABCD-EFG1', 'ABCD-EFGI', 'ABC', '', 'a'.repeat(64), null].forEach((x) => assert.ok(!core.validPairCode(x), String(x)));
  assert.strictEqual(core.parseScanText('ABCD-EFGH'), null, 'ein Kurz-Code ist kein Stempel-Code');
});
test('Scanner: iOS-tauglich (playsinline, muted, autoplay, Rückseitenkamera, Fehlerhinweise, jsQR-Fallback)', () => {
  const sc = fs.readFileSync(path.join(JS, 'tg-scanner.js'), 'utf8');
  ['playsinline', 'muted', 'autoplay', 'playsInline = true', "facingMode: { ideal: 'environment' }", 'NotAllowedError', 'OverconstrainedError', 'scan_denied_ios', 'jsQR'].forEach((k) => assert.ok(sc.includes(k), k));
  assert.ok(/BarcodeDetector' in root/.test(sc) && /loadJsQR\(\)/.test(sc), 'BarcodeDetector fehlt auf iOS: jsQR');
  ['de', 'en', 'it'].forEach((l) => assert.ok(/Safari/.test(i18n.dict[l].scan_denied_ios), l));
});
test('Standalone: /go koppelt per Koppel-QR und Code direkt in der App', () => {
  const pj = fs.readFileSync(path.join(JS, 'tg-punch.js'), 'utf8');
  assert.ok(/kind === 'pair'\) \{ pairWith\('token'/.test(pj), 'Koppel-QR im Scanner wird direkt eingelöst');
  assert.ok(pj.includes("pairWith('code'") && pj.includes('pairRedeemCode'), 'Code-Eingabe');
  assert.ok(pj.includes('autocapitalize') && pj.includes('ABCD-EFGH'));
  assert.ok(/pair_app_t/.test(pj), 'Hinweis auf der Kopplungsseite im Browser');
});
test('Standalone: Request-Token kommt aus #tg-root, kein Nextcloud-Core nötig', () => {
  const api = fs.readFileSync(path.join(JS, 'tg-api.js'), 'utf8');
  assert.ok(api.includes("getAttribute('data-requesttoken')") && api.includes('tg-root'));
  const app = fs.readFileSync(path.join(JS, 'tg-app.js'), 'utf8');
  assert.ok(app.includes(": base + '/sw.js'") && app.includes(": base + '/'"), 'Service-Worker-URL ohne OC.generateUrl');
});
test('Service-Worker: Cache-Version erhöht', () => { assert.ok(/tg-shell-v([4-9]|\d\d)/.test(fs.readFileSync(path.join(JS, 'tg-sw.js'), 'utf8'))); });

console.log((n - failed) + '/' + n + ' Tests bestanden');
process.exit(failed ? 1 : 0);
