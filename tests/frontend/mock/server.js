/* Mock-Server für die Sichtprüfung: simuliert docs/API.md und rendert die echten PHP-Templates.
 * Start: node tests/frontend/mock/server.js [port]   Seiten: /apps/timeguardian/?as=boss | /kiosk | /s/<code> | /pair/<token>
 * Query: ?as=anna|boss  ?nc=0 (ohne Nextcloud-CSS-Variablen)  ?anon=1 (keine Session) */
'use strict';
const http = require('http'), fs = require('fs'), path = require('path'), cp = require('child_process');
const core = require('../../../js/tg-core.js');
const ROOT = path.resolve(__dirname, '../../..');
const PORT = +(process.argv[2] || process.env.PORT || 5191);
const BASE = '/apps/timeguardian';
const MIME = { '.js': 'text/javascript', '.css': 'text/css', '.png': 'image/png', '.svg': 'image/svg+xml' };

/* ---------- Daten ---------- */
let seed = 7; const rnd = () => (seed = (seed * 16807) % 2147483647) / 2147483647;
const OFF = 60; // Berlin = UTC+1 (vereinfacht)
const pad = core.pad;
const hm = (iso) => { const d = new Date(Date.parse(iso) + OFF * 60000); return pad(d.getUTCHours()) + ':' + pad(d.getUTCMinutes()); };
const toIso = (day, min) => new Date(core.parseDay(day) + (min - OFF) * 60000).toISOString().replace('.000Z', 'Z');
const today = () => core.fmtDay(Date.now() + OFF * 60000);
const nowMin = () => { const d = new Date(Date.now() + OFF * 60000); return d.getUTCHours() * 60 + d.getUTCMinutes(); };
const USERS = { anna: 'Anna Weber', boss: 'Bernd Chef', ben: 'Ben Keller', chiara: 'Chiara Rossi', dario: 'Dario Fischer' };
const S = { entries: [], nextId: 1, state: {}, conf: {}, devices: [], pair: {}, absences: [], corrections: [], audit: [], mode: {}, lang: {} };
function addEntry(uid, day, s, e, pause, project, source) {
  const en = { id: S.nextId++, uid, day, startMin: s, endMin: e, pauseMin: pause, project: project || null, note: null, source: source || 'scan' };
  S.entries.push(en); return en;
}
(function seedData() {
  const t0 = today();
  Object.keys(USERS).forEach((uid) => {
    S.conf[uid] = { weeklyMin: 2400, daysMask: 31 }; S.mode[uid] = uid === 'ben' ? 'pause' : 'simple'; S.lang[uid] = 'de'; S.state[uid] = { state: 'none', since: null, openEntryId: null, pauseSince: null };
    for (let i = 75; i >= 1; i--) {
      const day = core.addDays(t0, -i), dw = core.dow(day);
      if (dw > 4 && !(uid === 'dario' && dw === 6 && i < 40 && i > 36)) continue;
      if (rnd() < 0.05) continue;
      let s = 450 + Math.round(rnd() * 90), len = 480 + Math.round(rnd() * 60), pause = 30;
      if (rnd() < 0.08) len += 150; if (rnd() < 0.06) pause = 10; if (rnd() < 0.05) pause = 0;
      addEntry(uid, day, s, s + len + pause, pause, ['Support', 'Interna', 'Angebote'][Math.floor(rnd() * 3)], rnd() < .1 ? 'manual' : 'scan');
    }
  });
  const pushOpen = (uid, startAgo, st) => { const day = t0, s = Math.max(0, nowMin() - startAgo), en = addEntry(uid, day, s, null, 0, 'Support'); S.state[uid] = { state: st, since: toIso(day, s), openEntryId: en.id, pauseSince: st === 'pause' ? toIso(day, nowMin() - 8) : null }; if (st === 'pause') { en.endMin = nowMin() - 8; S.state[uid].openEntryId = null; } };
  pushOpen('anna', 125, 'working'); pushOpen('ben', 200, 'working'); pushOpen('chiara', 250, 'pause');
  S.entries = S.entries.filter((e) => !(e.uid === 'anna' && e.day === t0 && e.endMin !== null)); // Anna: nur offener Block
  S.absences.push({ id: 1, uid: 'anna', kind: 'vacation', fromDay: core.addDays(t0, 20), toDay: core.addDays(t0, 31), status: 'approved' }, { id: 2, uid: 'ben', kind: 'vacation', fromDay: core.addDays(t0, 5), toDay: core.addDays(t0, 9), status: 'pending' }, { id: 3, uid: 'anna', kind: 'sick', fromDay: core.addDays(t0, -30), toDay: core.addDays(t0, -29), status: 'approved' });
  S.corrections.push({ id: 1, uid: 'ben', entryId: S.entries.find((e) => e.uid === 'ben' && e.endMin).id, reason: 'Ausstempeln vergessen', status: 'pending', proposed: { startMin: 480, endMin: 1000, pauseMin: 45 } });
  S.audit.push({ id: 1, at: new Date().toISOString(), actor: 'anna', uid: 'anna', kind: 'entry.update', entity: 'entry', entityId: 5, before: { pauseMin: 30 }, after: { pauseMin: 45 } });
})();

const HOLIDAYS = { '11-01': 'Allerheiligen' };
function entryJson(e, viewer) {
  const running = e.endMin === null, d = e.day, end = running ? nowMin() : e.endMin;
  return { id: e.id, uid: e.uid, day: d, start: toIso(d, e.startMin), end: running ? null : toIso(d, e.endMin), startHM: core.clockOf(e.startMin), endHM: running ? null : core.clockOf(e.endMin), pauseMin: e.pauseMin, netMin: Math.max(0, end - e.startMin - e.pauseMin), running, project: e.project, note: e.note, source: e.source, createdAt: toIso(d, e.startMin), updatedAt: toIso(d, e.startMin), editable: e.uid === viewer && core.parseDay(today()) - core.parseDay(d) <= 7 * 86400000 };
}
const TXT = { DAY10: (v) => `Mehr als 10 Stunden gearbeitet (${core.fmtH(v, 'de')}) · § 3 ArbZG`, DAY8: (v) => `Über 8 Stunden (${core.fmtH(v, 'de')}): braucht Ausgleich`, BREAK30: (v) => `Pause zu kurz: ${v} min, nötig sind 30 min bei mehr als 6 h · § 4 ArbZG`, BREAK45: (v) => `Pause zu kurz: ${v} min, nötig sind 45 min bei mehr als 9 h · § 4 ArbZG`, REST11: (v) => `Ruhezeit nur ${core.fmtHM(v)} (nötig: 11 h) · § 5 ArbZG`, SUN: (v) => `Sonntagsarbeit (${core.fmtH(v, 'de')}) · § 9 ArbZG` };
function dayObj(uid, day, viewer) {
  const list = S.entries.filter((e) => e.uid === uid && e.day === day).sort((a, b) => a.startMin - b.startMin);
  const dw = core.dow(day), conf = S.conf[uid], hol = HOLIDAYS[day.slice(5)];
  const abs = S.absences.find((a) => a.uid === uid && a.status === 'approved' && a.fromDay <= day && a.toDay >= day);
  const work = !!(conf.daysMask & (1 << dw)) && !hol;
  const soll = work ? Math.round(conf.weeklyMin / core.sum([0, 1, 2, 3, 4, 5, 6], (b) => (conf.daysMask & (1 << b) ? 1 : 0))) : 0;
  const ents = list.map((e) => entryJson(e, viewer));
  const net = core.sum(ents, (e) => e.netMin), pauseMin = core.sum(ents, (e) => e.pauseMin);
  const credit = abs && abs.kind !== 'flex' ? soll : 0;
  const viol = [];
  if (list.length) {
    const blocks = list.map((e) => ({ startMin: e.startMin, endMin: e.endMin === null ? nowMin() : e.endMin, pauseMin: e.pauseMin }));
    const prev = S.entries.filter((e) => e.uid === uid && e.day === core.addDays(day, -1) && e.endMin !== null);
    const prevEnd = prev.length ? Math.max.apply(null, prev.map((e) => e.endMin)) - 1440 : null;
    core.previewRules(blocks, prevEnd, dw).forEach((r) => viol.push({ uid, day, rule: r.rule, level: r.level, value: r.value, text: TXT[r.rule](r.value) }));
  }
  return { day, dow: dw, isToday: day === today(), holiday: hol ? { key: 'h', name: hol } : null, absence: abs ? { id: abs.id, kind: abs.kind } : null, sollMin: soll, netMin: net, pauseMin, creditMin: credit, deltaMin: net + credit - soll, firstHM: ents.length ? ents[0].startHM : null, lastHM: ents.length ? (ents[ents.length - 1].endHM || core.clockOf(nowMin())) : null, entries: ents, violations: viol };
}
function period(uid, from, to, viewer) {
  const days = core.rangeDays(from, to).map((d) => dayObj(uid, d, viewer));
  return { days, violations: [].concat.apply([], days.map((d) => d.violations)).reverse(), sollMin: core.sum(days, (d) => d.sollMin), netMin: core.sum(days, (d) => d.netMin), creditMin: core.sum(days, (d) => d.creditMin), deltaMin: core.sum(days, (d) => d.deltaMin) };
}
function weekObj(uid, date, viewer) {
  const from = core.monday(date), to = core.addDays(from, 6), iw = core.isoWeek(from);
  return Object.assign({ from, to, kw: iw.week, year: iw.year, targetMin: S.conf[uid].weeklyMin, limitMin: 2880 }, period(uid, from, to, viewer));
}
function netNow(uid) {
  const e = S.entries.filter((x) => x.uid === uid && x.day === today());
  return core.sum(e, (x) => Math.max(0, (x.endMin === null ? nowMin() : x.endMin) - x.startMin - x.pauseMin));
}
function status(uid) {
  const st = S.state[uid];
  return { name: USERS[uid], mode: S.mode[uid], state: st.state, since: st.since, pauseSince: st.pauseSince, netMinToday: netNow(uid), weekNetMin: weekObj(uid, today(), uid).netMin, targetMin: S.conf[uid].weeklyMin, limitMin: 2880 };
}
function account(uid) {
  const hist = [], t0 = today(); let bal = 95;
  for (let i = 11; i >= 0; i--) { const ym = core.addMonths(core.ymOf(t0), -i); const delta = Math.round((rnd() - .35) * 900); bal += delta; hist.push({ ym, sollMin: 10080, istMin: 10080 + delta - 0, creditMin: i % 4 === 0 ? 480 : 0, deltaMin: delta, balanceMin: bal }); }
  return { balanceMin: bal, asOf: core.addDays(t0, -1), since: core.addDays(t0, -330), history: hist };
}
const ACC = {}; const accOf = (uid) => ACC[uid] || (ACC[uid] = account(uid));
const me = (uid) => {
  const wk = weekObj(uid, today(), uid), td = dayObj(uid, today(), uid), a = accOf(uid), chef = uid === 'boss';
  return { uid, name: USERS[uid], isChef: chef, isKiosk: chef, isAdmin: false, lang: S.lang[uid], timezone: 'Europe/Berlin', today: today(), conf: { weeklyMin: S.conf[uid].weeklyMin, daysMask: S.conf[uid].daysMask, mode: S.mode[uid], lang: S.lang[uid], canSetTarget: chef }, state: S.state[uid],
    todaySummary: { day: td.day, netMin: netNow(uid), pauseMin: td.pauseMin, sollMin: td.sollMin, entries: td.entries }, week: wk, account: { balanceMin: a.balanceMin, asOf: a.asOf, todaySollMin: td.sollMin, todayNetMin: netNow(uid) }, deviceCount: S.devices.filter((d) => d.uid === uid && !d.revoked).length, projects: ['Support', 'Interna', 'Angebote'], features: {},
    pendingDecisions: chef ? { absences: S.absences.filter((x) => x.status === 'pending').length, corrections: S.corrections.filter((x) => x.status === 'pending').length } : { absences: 0, corrections: 0 } };
};
const absJson = (a) => Object.assign({ name: USERS[a.uid], days: Math.max(1, core.rangeDays(a.fromDay, a.toDay).filter((d) => core.dow(d) < 5).length), decidedBy: null, decidedAt: null, note: null, createdAt: new Date().toISOString() }, a);
const corrJson = (c) => { const e = S.entries.find((x) => x.id === c.entryId); const j = entryJson(e, e.uid); return { id: c.id, uid: c.uid, name: USERS[c.uid], entryId: c.entryId, day: e.day, current: { startHM: j.startHM, endHM: j.endHM, pauseMin: j.pauseMin }, proposed: { startHM: core.clockOf(c.proposed.startMin), endHM: core.clockOf(c.proposed.endMin), pauseMin: c.proposed.pauseMin }, reason: c.reason, status: c.status, decidedBy: null, decidedAt: null, createdAt: new Date().toISOString() }; };

/* ---------- Routing ---------- */
function cookies(req) { const o = {}; String(req.headers.cookie || '').split(/;\s*/).forEach((c) => { const i = c.indexOf('='); if (i > 0) o[c.slice(0, i)] = decodeURIComponent(c.slice(i + 1)); }); return o; }
function send(res, code, body, type, extra) { res.writeHead(code, Object.assign({ 'Content-Type': type || 'application/json; charset=utf-8', 'Cache-Control': 'no-store' }, extra || {})); res.end(typeof body === 'string' || Buffer.isBuffer(body) ? body : JSON.stringify(body)); }
const err = (res, code, error, message, extra) => send(res, code, Object.assign({ error, message }, extra || {}));
function readBody(req) { return new Promise((r) => { let b = ''; req.on('data', (c) => (b += c)); req.on('end', () => { try { r(b ? JSON.parse(b) : {}); } catch (e) { r({}); } }); }); }

const NCCSS = `:root{--color-main-background:#fff;--color-main-text:#222;--color-text-maxcontrast:#6b6b6b;--color-border:#ededed;--color-background-hover:#f5f5f5;--color-background-dark:#ededed;--color-primary-element:#00679e;--color-primary-element-text:#fff;--color-success:#2d7b41;--color-warning:#a37200;--color-error:#c9302c}
@media (prefers-color-scheme:dark){:root{--color-main-background:#171717;--color-main-text:#fff;--color-text-maxcontrast:#a5a5a5;--color-border:#3b3b3b;--color-background-hover:#262626;--color-background-dark:#2e2e2e;--color-primary-element:#4cb3ff;--color-primary-element-text:#00212f;--color-success:#46ba61;--color-warning:#eca700;--color-error:#e9322d}}
body{margin:0;font-family:system-ui,sans-serif;background:#f0f0f0}@media (prefers-color-scheme:dark){body{background:#000}}
#mock-header{height:50px;background:var(--color-primary-element);color:#fff;display:flex;align-items:center;padding:0 16px;font-weight:700}
#content{display:flex;height:calc(100vh - 50px);margin:8px;border-radius:20px;overflow:hidden;background:var(--color-main-background);color:var(--color-main-text)}#content>#tg-root{flex:1;min-width:0}`;

function page(tpl, param, val, layout, q, ck) {
  const out = JSON.parse(cp.execFileSync('php', [path.join(__dirname, 'render.php'), tpl, param || '', val || ''], { encoding: 'utf8' }));
  if (out.standalone) { return out.standalone; }
  const nc = q.get('nc') !== '0';
  const asParam = q.get('as') ? `<script nonce="x">document.cookie='mock_uid=${q.get('as').replace(/[^a-z]/g, '')};path=/'</script>` : '';
  const tags = out.styles.map((s) => `<link rel="stylesheet" href="${BASE}/css/${s}.css">`).join('') + (nc ? `<link rel="stylesheet" href="/mock/nc.css">` : '');
  const js = out.scripts.map((s) => `<script src="${BASE}/js/${s}.js" defer></script>`).join('');
  const body = layout === 'user' ? `<div id="mock-header">Nextcloud</div><main id="content">${out.html}</main>` : out.html;
  return `<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>TimeGuardian</title>${tags}</head><body>${body}${js}</body></html>`;
}
const csv = (rows) => '﻿' + rows.map((r) => r.join(',')).join('\r\n');

const server = http.createServer(async (req, res) => {
  const u = new URL(req.url, 'http://x'), p = u.pathname, q = u.searchParams, ck = cookies(req);
  if (p === '/mock/nc.css') { return send(res, 200, NCCSS, 'text/css'); }
  if (p === '/mock/login') { return send(res, 200, '<h1>Nextcloud Login (Mock)</h1><p><a href="' + BASE + '/?as=anna">als Anna anmelden</a></p>', 'text/html; charset=utf-8'); }
  if (p === '/mock/anon') { return send(res, 302, '', 'text/plain', { 'Set-Cookie': 'mock_uid=none; Path=/', Location: q.get('to') || BASE + '/' }); }
  if (p.indexOf(BASE + '/js/') === 0 || p.indexOf(BASE + '/css/') === 0 || p.indexOf(BASE + '/img/') === 0) {
    const f = path.join(ROOT, p.slice(BASE.length)); if (f.indexOf(ROOT) !== 0 || !fs.existsSync(f)) { return send(res, 404, 'nf', 'text/plain'); }
    return send(res, 200, fs.readFileSync(f), MIME[path.extname(f)] || 'application/octet-stream', { 'Cache-Control': 'max-age=0' });
  }
  if (p === BASE + '/sw.js') { return send(res, 200, fs.readFileSync(path.join(ROOT, 'js/tg-sw.js')), 'text/javascript', { 'Service-Worker-Allowed': BASE + '/' }); }
  if (p === BASE + '/manifest') { return send(res, 200, { name: 'TimeGuardian', start_url: BASE + '/', scope: BASE + '/', display: 'standalone', icons: [{ src: BASE + '/img/icon-192.png', sizes: '192x192', type: 'image/png' }] }, 'application/manifest+json'); }
  const uid = (() => { const dev = req.headers['x-tg-device']; if (dev) { const d = S.devices.find((x) => x.token === dev && !x.revoked); return d ? d.uid : null; } const c = q.get('as') || ck.mock_uid || 'anna'; return USERS[c] ? c : null; })();
  // Seiten
  let m;
  if (p === BASE + '/' || p === BASE) { if (q.get('as')) { res.setHeader('Set-Cookie', 'mock_uid=' + q.get('as') + '; Path=/'); } return send(res, 200, page('main', '', '', 'user', q, ck), 'text/html; charset=utf-8'); }
  if (p === BASE + '/kiosk') { if (q.get('as')) { res.setHeader('Set-Cookie', 'mock_uid=' + q.get('as') + '; Path=/'); } return send(res, 200, page('kiosk', '', '', 'user', q, ck), 'text/html; charset=utf-8'); }
  if (p === BASE + '/go') { return send(res, 200, page('scan', 'code', '', 'blank', q, ck), 'text/html; charset=utf-8'); }
  if ((m = /^\/apps\/timeguardian\/s\/(.+)$/.exec(p))) { if (q.get('as')) { res.setHeader('Set-Cookie', 'mock_uid=' + q.get('as') + '; Path=/'); } return send(res, 200, page('scan', 'code', decodeURIComponent(m[1]), 'blank', q, ck), 'text/html; charset=utf-8'); }
  if ((m = /^\/apps\/timeguardian\/pair\/(.+)$/.exec(p))) { return send(res, 200, page('pair', 'token', decodeURIComponent(m[1]), 'blank', q, ck), 'text/html; charset=utf-8'); }
  // API
  if (p.indexOf(BASE + '/api/') !== 0) { return send(res, 404, 'nf', 'text/plain'); }
  const api = p.slice(BASE.length + 4), verb = req.method;
  const body = verb === 'GET' || verb === 'DELETE' ? {} : await readBody(req);
  if (api === '/pair/redeem') {
    const pr = body.code ? S.pair['code:' + String(body.code).toUpperCase().replace(/[\s-]+/g, '')] : S.pair[body.token]; if (!pr) return err(res, 400, 'token_invalid', 'Token ungültig.'); if (pr.used) return err(res, 410, 'token_used', 'Token bereits benutzt.'); if (Date.now() > pr.exp) return err(res, 410, 'token_expired', 'Token abgelaufen.');
    pr.used = true; const dev = { id: S.nextId++, uid: pr.uid, token: 'dev-' + pr.uid + '-' + S.nextId, label: String(body.label || 'Gerät'), createdAt: new Date().toISOString(), lastUsed: null, expiresAt: new Date(Date.now() + 180 * 86400000).toISOString(), revoked: false };
    S.devices.push(dev); return send(res, 200, { deviceToken: dev.token, name: USERS[pr.uid], expiresAt: dev.expiresAt });
  }
  if (!uid) return err(res, 401, 'unauthorized', 'Nicht angemeldet.');
  const chef = uid === 'boss', t0 = today();
  const needChef = () => { if (!chef) { err(res, 403, 'forbidden', 'Nur für Vorgesetzte.'); return true; } return false; };
  const dayOK = (b) => /^\d{4}-\d{2}-\d{2}$/.test(b.day || '');
  if (api === '/status' && verb === 'GET') return send(res, 200, status(uid));
  if (api === '/punch' && verb === 'POST') {
    const st = S.state[uid], code = String(body.code || ''), now = nowMin(); let action = body.action;
    if (/^expired/.test(code)) return err(res, 400, 'invalid_code', 'Code abgelaufen.');
    if (/^toofast/.test(code)) return err(res, 429, 'too_fast', 'Mindestens 2 Minuten Abstand.');
    if (/^ratelimit/.test(code)) return err(res, 429, 'rate_limited', 'Zu viele Stempelungen.');
    if (!action) action = (st.state === 'none' || st.state === 'done') ? 'in' : st.state === 'working' ? (S.mode[uid] === 'pause' ? 'pause' : 'out') : 'resume';
    if (action === 'in' && !code) return err(res, 400, 'code_required', 'QR-Code nötig.');
    const open = S.entries.find((e) => e.id === st.openEntryId);
    const bad = () => err(res, 409, 'invalid_transition', 'Das passt nicht zum aktuellen Status.', { state: st.state });
    if (action === 'in') { if (st.state === 'working' || st.state === 'pause') return bad(); const en = addEntry(uid, t0, now, null, 0, null); S.state[uid] = { state: 'working', since: toIso(t0, now), openEntryId: en.id, pauseSince: null }; }
    else if (action === 'out') { if (st.state === 'none' || st.state === 'done') return bad(); if (open) open.endMin = Math.max(open.startMin + 1, now); S.state[uid] = { state: 'done', since: null, openEntryId: null, pauseSince: null }; }
    else if (action === 'pause') { if (st.state !== 'working') return bad(); if (open) open.endMin = Math.max(open.startMin + 1, now); S.state[uid] = { state: 'pause', since: st.since, openEntryId: null, pauseSince: toIso(t0, now) }; }
    else if (action === 'resume') { if (st.state !== 'pause') return bad(); const en = addEntry(uid, t0, now, 0, 0, null); S.state[uid] = { state: 'working', since: toIso(t0, now), openEntryId: en.id, pauseSince: null }; }
    const s2 = status(uid); return send(res, 200, { ok: true, action, name: USERS[uid], mode: S.mode[uid], state: S.state[uid], netMinToday: s2.netMinToday, weekNetMin: s2.weekNetMin, newViolations: [] });
  }
  if (api === '/me') return send(res, 200, me(uid));
  if (api === '/settings') { if (verb === 'PUT') { if (body.mode) S.mode[uid] = body.mode; if (body.lang) S.lang[uid] = body.lang; } return send(res, 200, { conf: me(uid).conf }); }
  if (api === '/entries' && verb === 'GET') { const from = q.get('from'), to = q.get('to'); return send(res, 200, { entries: S.entries.filter((e) => e.uid === uid && e.day >= from && e.day <= to).sort((a, b) => a.day.localeCompare(b.day) || a.startMin - b.startMin).map((e) => entryJson(e, uid)) }); }
  if (api === '/entries' && verb === 'POST') {
    const s = core.parseClock(body.start), e = core.parseClock(body.end), fields = [];
    if (!dayOK(body)) fields.push('day'); else if (body.day > t0) fields.push('day_future');
    if (s === null) fields.push('start'); if (e === null) fields.push('end'); if (s !== null && e !== null && e <= s) fields.push('order');
    if (fields.length) return err(res, 400, 'validation', 'Eingabe ungültig.', { fields });
    if (S.entries.some((x) => x.uid === uid && x.day === body.day && x.endMin !== null && s < x.endMin && e > x.startMin)) return err(res, 409, 'overlap', 'Überschneidung.');
    const en = addEntry(uid, body.day, s, e, +body.pauseMin || 0, body.project, 'manual'); en.note = body.note || null; const d = dayObj(uid, body.day, uid);
    return send(res, 201, { entry: entryJson(en, uid), newViolations: d.violations, violations: d.violations });
  }
  if ((m = /^\/entries\/(\d+)$/.exec(api))) {
    const en = S.entries.find((x) => x.id === +m[1] && x.uid === uid); if (!en) return err(res, 404, 'not_found', 'Nicht gefunden.');
    if (!entryJson(en, uid).editable) return err(res, 409, 'locked', 'Älter als 7 Tage.');
    if (verb === 'DELETE') { S.entries = S.entries.filter((x) => x !== en); return send(res, 200, { deleted: true }); }
    const s = core.parseClock(body.start), e = core.parseClock(body.end); if (s !== null) en.startMin = s; if (e !== null) en.endMin = e; if (body.pauseMin !== undefined) en.pauseMin = +body.pauseMin || 0; en.project = body.project || null; en.note = body.note || null;
    const d = dayObj(uid, en.day, uid); return send(res, 200, { entry: entryJson(en, uid), newViolations: [], violations: d.violations });
  }
  if (api === '/week') return send(res, 200, weekObj(uid, q.get('date') || t0, uid));
  if (api === '/month') { const ym = q.get('ym') || core.ymOf(t0), pr = period(uid, ym + '-01', ym + '-' + pad(core.daysInMonth(ym)), uid); const bp = {}; pr.days.forEach((d) => d.entries.forEach((e) => { bp[e.project || ''] = (bp[e.project || ''] || 0) + e.netMin; })); return send(res, 200, Object.assign({ ym, from: ym + '-01', to: ym + '-' + pad(core.daysInMonth(ym)) }, pr, { byProject: Object.keys(bp).map((k) => ({ project: k || null, netMin: bp[k] })) })); }
  if (api === '/account') return send(res, 200, accOf(uid));
  if (api === '/violations') return send(res, 200, { violations: period(uid, q.get('from'), q.get('to'), uid).violations });
  if (api === '/pair' && verb === 'POST') { const token = require('crypto').randomBytes(32).toString('hex'); const code = Array.from({ length: 8 }, () => 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'[Math.floor(Math.random() * 32)]).join(''); S.pair[token] = { uid, exp: Date.now() + 300000, used: false, code }; S.pair['code:' + code] = S.pair[token]; const url = 'http://' + req.headers.host + BASE + '/pair/' + token; return send(res, 201, { token, code: code.slice(0, 4) + '-' + code.slice(4), url, qrText: url, expires: new Date(Date.now() + 300000).toISOString(), expiresInSec: 300 }); }
  if (api === '/devices' && verb === 'GET') return send(res, 200, { devices: S.devices.filter((d) => d.uid === uid).map((d) => ({ id: d.id, label: d.label, createdAt: d.createdAt, lastUsed: d.lastUsed, expiresAt: d.expiresAt, revoked: d.revoked })) });
  if ((m = /^\/devices\/(\d+)$/.exec(api))) { const d = S.devices.find((x) => x.id === +m[1] && x.uid === uid); if (!d) return err(res, 404, 'not_found', 'Nicht gefunden.'); d.revoked = true; return send(res, 200, { revoked: true }); }
  if (api === '/absences' && verb === 'GET') { const sc = q.get('scope') || 'mine'; let l = S.absences; if (sc === 'mine') l = l.filter((a) => a.uid === uid); else { if (needChef()) return; if (sc === 'pending') l = l.filter((a) => a.status === 'pending'); } return send(res, 200, { absences: l.map(absJson) }); }
  if (api === '/absences' && verb === 'POST') { const f = []; if (!['vacation', 'sick', 'flex', 'trip'].includes(body.kind)) f.push('kind'); if (!dayOK({ day: body.fromDay }) || !dayOK({ day: body.toDay })) f.push('date'); else if (body.toDay < body.fromDay) f.push('range'); if (f.length) return err(res, 400, 'validation', 'Ungültig.', { fields: f }); const a = { id: S.nextId++, uid, kind: body.kind, fromDay: body.fromDay, toDay: body.toDay, status: 'pending', note: body.note || null }; S.absences.push(a); return send(res, 201, { absence: absJson(a) }); }
  if ((m = /^\/absences\/(\d+)(\/decide)?$/.exec(api))) { const a = S.absences.find((x) => x.id === +m[1]); if (!a) return err(res, 404, 'not_found', 'Nicht gefunden.'); if (m[2]) { if (needChef()) return; if (a.status !== 'pending') return err(res, 409, 'already_decided', 'Bereits entschieden.'); a.status = body.decision === 'approve' ? 'approved' : 'rejected'; return send(res, 200, { absence: absJson(a) }); } if (a.uid !== uid) return err(res, 404, 'not_found', 'Nicht gefunden.'); S.absences = S.absences.filter((x) => x !== a); return send(res, 200, { deleted: true }); }
  if (api === '/corrections' && verb === 'GET') { const sc = q.get('scope') || 'mine'; let l = S.corrections; if (sc === 'mine') l = l.filter((c) => c.uid === uid); else { if (needChef()) return; l = l.filter((c) => c.status === 'pending'); } return send(res, 200, { corrections: l.map(corrJson) }); }
  if (api === '/corrections' && verb === 'POST') { const en = S.entries.find((e) => e.id === +body.entryId && e.uid === uid); if (!en) return err(res, 400, 'validation', 'Ungültig.', { fields: ['entryId'] }); if (String(body.reason || '').length < 3) return err(res, 400, 'validation', 'Ungültig.', { fields: ['reason'] }); const c = { id: S.nextId++, uid, entryId: en.id, reason: body.reason, status: 'pending', proposed: { startMin: core.parseClock(body.start) ?? en.startMin, endMin: core.parseClock(body.end) ?? en.endMin, pauseMin: +body.pauseMin || 0 } }; S.corrections.push(c); return send(res, 201, { correction: corrJson(c) }); }
  if ((m = /^\/corrections\/(\d+)\/decide$/.exec(api))) { if (needChef()) return; const c = S.corrections.find((x) => x.id === +m[1]); if (!c) return err(res, 404, 'not_found', 'Nicht gefunden.'); c.status = body.decision === 'approve' ? 'approved' : 'rejected'; return send(res, 200, { correction: corrJson(c), entry: null }); }
  if (api === '/team/overview') {
    if (needChef()) return; const date = q.get('week') || t0, from = core.monday(date), iw = core.isoWeek(from); const ppl = ['anna', 'ben', 'chiara', 'dario'].map((u) => { const w = weekObj(u, date, uid); return { uid: u, name: USERS[u], state: S.state[u].state, since: S.state[u].since, netMin: w.netMin, sollMin: w.sollMin, balanceMin: accOf(u).balanceMin, days: w.days.map((d) => ({ day: d.day, netMin: d.netMin })), violations: { verstoss: w.violations.filter((v) => v.level === 'verstoss').length, hinweis: w.violations.filter((v) => v.level !== 'verstoss').length }, _v: w.violations }; });
    return send(res, 200, { week: { from, to: core.addDays(from, 6), kw: iw.week, year: iw.year }, people: ppl.map((x) => { const c = Object.assign({}, x); delete c._v; return c; }), presentNow: ppl.filter((x) => x.state === 'working').map((x) => x.uid), violations: [].concat.apply([], ppl.map((x) => x._v.map((v) => Object.assign({ name: x.name }, v)))), topOvertime: ppl.slice().sort((a, b) => b.balanceMin - a.balanceMin).slice(0, 3).map((x) => ({ uid: x.uid, name: x.name, balanceMin: x.balanceMin })), pending: me(uid).pendingDecisions });
  }
  if ((m = /^\/team\/person\/([^/]+)(\/target)?$/.exec(api))) { if (needChef()) return; const pu = m[1]; if (!USERS[pu]) return err(res, 404, 'not_found', 'Nicht gefunden.'); if (m[2]) { S.conf[pu].weeklyMin = +body.weeklyMin; S.conf[pu].daysMask = +body.daysMask; return send(res, 200, { conf: S.conf[pu] }); } const pr = period(pu, q.get('from') || core.monday(t0), q.get('to') || core.addDays(core.monday(t0), 6), uid); return send(res, 200, { user: { uid: pu, name: USERS[pu], state: S.state[pu] }, entries: [], days: pr.days, violations: pr.violations, account: accOf(pu) }); }
  if (api === '/audit') return send(res, 200, { audit: S.audit.filter((a) => chef || a.uid === uid) });
  if (api === '/kiosk/code') { if (needChef()) return; const win = Math.floor(Date.now() / 60000), left = 60 - (Date.now() % 60000) / 1000; const url = 'http://' + req.headers.host + BASE + '/s/' + win + '.' + require('crypto').createHash('sha256').update('k' + win).digest('hex').slice(0, 16); return send(res, 200, { url, qrText: url, window: win, expiresInSec: Math.round(left), windowSec: 60 }); }
  if (api === '/export.csv' || api === '/team/export.csv') return send(res, 200, csv([['day', 'net'], [t0, '480']]), 'text/csv; charset=utf-8', { 'Content-Disposition': 'attachment; filename="export.csv"' });
  if (api === '/export.json') return send(res, 200, { uid, entries: S.entries.filter((e) => e.uid === uid).length });
  return err(res, 404, 'not_found', 'Unbekannter Endpunkt: ' + api);
});
server.listen(PORT, () => console.log('TimeGuardian mock: http://localhost:' + PORT + BASE + '/'));
