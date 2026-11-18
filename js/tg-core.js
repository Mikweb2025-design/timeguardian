/* TimeGuardian – reine Logik (ohne DOM), im Browser als window.TG.core, in Node per require(). */
(function (root, factory) {
  var api = factory();
  if (typeof module === 'object' && module.exports) { module.exports = api; }
  root.TG = root.TG || {};
  root.TG.core = api;
})(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  var DAY_MS = 86400000;

  function pad(n) { return (n < 10 ? '0' : '') + n; }

  /* ---------- Datum (Kalendertage als 'YYYY-MM-DD', rein UTC-rechnerisch) ---------- */
  function parseDay(s) {
    var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(s || ''));
    if (!m) { return null; }
    var t = Date.UTC(+m[1], +m[2] - 1, +m[3]);
    var d = new Date(t);
    if (d.getUTCMonth() !== +m[2] - 1) { return null; }
    return t;
  }
  function fmtDay(t) { var d = new Date(t); return d.getUTCFullYear() + '-' + pad(d.getUTCMonth() + 1) + '-' + pad(d.getUTCDate()); }
  function addDays(s, n) { return fmtDay(parseDay(s) + n * DAY_MS); }
  function dow(s) { return (new Date(parseDay(s)).getUTCDay() + 6) % 7; } // Montag = 0
  function monday(s) { return addDays(s, -dow(s)); }
  function localDay(d) { d = d || new Date(); return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
  function isoWeek(s) {
    var t = parseDay(s), d = new Date(t);
    var wd = (d.getUTCDay() + 6) % 7;
    var thu = new Date(t + (3 - wd) * DAY_MS);
    var y = thu.getUTCFullYear();
    var jan1 = Date.UTC(y, 0, 1);
    return { year: y, week: Math.floor((thu.getTime() - jan1) / DAY_MS / 7) + 1 };
  }
  function ymOf(s) { return String(s).slice(0, 7); }
  function addMonths(ym, n) {
    var y = +ym.slice(0, 4), m = +ym.slice(5, 7) - 1 + n;
    y += Math.floor(m / 12); m = ((m % 12) + 12) % 12;
    return y + '-' + pad(m + 1);
  }
  function daysInMonth(ym) { return new Date(Date.UTC(+ym.slice(0, 4), +ym.slice(5, 7), 0)).getUTCDate(); }
  function monthDays(ym) {
    var n = daysInMonth(ym), out = [];
    for (var i = 1; i <= n; i++) { out.push(ym + '-' + pad(i)); }
    return out;
  }
  function rangeDays(from, to) {
    var out = [], a = parseDay(from), b = parseDay(to);
    if (a === null || b === null || b < a) { return out; }
    for (var t = a; t <= b && out.length < 800; t += DAY_MS) { out.push(fmtDay(t)); }
    return out;
  }

  /* ---------- Dauern ---------- */
  function toInt(v) { v = Math.round(Number(v)); return isFinite(v) ? v : 0; }
  // 450 -> "7 h 30 min"
  function fmtHM(min) {
    min = toInt(min);
    var sign = min < 0 ? '-' : '';
    min = Math.abs(min);
    return sign + Math.floor(min / 60) + ' h ' + pad(min % 60) + ' min';
  }
  // 450 -> "7:30"
  function fmtClockDur(min) {
    min = toInt(min);
    var sign = min < 0 ? '-' : '';
    min = Math.abs(min);
    return sign + Math.floor(min / 60) + ':' + pad(min % 60);
  }
  // 450 -> "7,5 h" (de/it) / "7.5 h" (en)
  function fmtH(min, lang) {
    var h = Math.round(Number(min) / 6) / 10;
    var t = h.toFixed(1);
    return (lang === 'en' ? t : t.replace('.', ',')) + ' h';
  }
  function fmtSigned(min, lang) {
    min = toInt(min);
    return (min > 0 ? '+' : min < 0 ? '−' : '') + fmtH(Math.abs(min), lang);
  }
  function fmtSignedHM(min) {
    min = toInt(min);
    return (min > 0 ? '+' : min < 0 ? '−' : '') + fmtClockDur(Math.abs(min)) + ' h';
  }
  // "HH:MM" -> Minuten (oder null)
  function parseClock(s) {
    var m = /^([01]?\d|2[0-3]):([0-5]\d)$/.exec(String(s == null ? '' : s).trim());
    return m ? +m[1] * 60 + +m[2] : null;
  }
  function clockOf(min) { min = toInt(min); return pad(Math.floor(min / 60)) + ':' + pad(min % 60); }
  function isoToLocalClock(iso) {
    var d = new Date(iso);
    if (isNaN(d.getTime())) { return ''; }
    return pad(d.getHours()) + ':' + pad(d.getMinutes());
  }
  function isoToLocalDay(iso) { var d = new Date(iso); return isNaN(d.getTime()) ? '' : localDay(d); }
  function localToIso(day, clock) {
    var m = parseClock(clock), p = /^(\d{4})-(\d{2})-(\d{2})$/.exec(day || '');
    if (m === null || !p) { return null; }
    return new Date(+p[1], +p[2] - 1, +p[3], Math.floor(m / 60), m % 60, 0).toISOString();
  }

  /* ---------- Buchungen ---------- */
  function entryMinutes(e, nowMs) {
    if (!e || !e.start) { return 0; }
    var s = Date.parse(e.start);
    var en = e.end ? Date.parse(e.end) : (nowMs || Date.now());
    if (isNaN(s) || isNaN(en) || en < s) { return 0; }
    return Math.max(0, Math.round((en - s) / 60000) - toInt(e.pauseMin != null ? e.pauseMin : e.pause_min));
  }
  // Live-Netto: Basis (Server) + Zeit seit Abfrage, nur im Zustand working
  function liveNet(base, state, fetchedAtMs, nowMs) {
    base = toInt(base);
    if (state !== 'working') { return base; }
    var add = Math.floor(Math.max(0, (nowMs - fetchedAtMs)) / 60000);
    return base + add;
  }
  function sinceMinutes(sinceIso, nowMs) {
    var t = Date.parse(sinceIso);
    if (isNaN(t)) { return 0; }
    return Math.max(0, Math.floor((nowMs - t) / 60000));
  }
  function fmtStopwatch(sec) {
    sec = Math.max(0, Math.floor(sec));
    return pad(Math.floor(sec / 3600)) + ':' + pad(Math.floor(sec / 60) % 60) + ':' + pad(sec % 60);
  }

  /* ---------- Kennzahlen / Ringe ---------- */
  function clamp(x, a, b) { return Math.min(b, Math.max(a, x)); }
  function ratio(v, max) { return max > 0 ? clamp(v / max, 0, 1) : 0; }
  function ringGeometry(r, frac) {
    var c = 2 * Math.PI * r;
    return { circumference: c, dash: c * clamp(frac, 0, 1), gap: c };
  }
  // Fortschrittsfarbe: ok / warn / bad nach Anteil an der Grenze
  function limitLevel(min, limitMin, warnAtMin) {
    if (min > limitMin) { return 'bad'; }
    if (min >= (warnAtMin != null ? warnAtMin : limitMin * 0.9)) { return 'warn'; }
    return 'ok';
  }

  /* ---------- Chart-Berechnungen ---------- */
  function niceMax(maxMin, stepMin) {
    stepMin = stepMin || 120;
    return Math.max(stepMin * 2, Math.ceil(maxMin / stepMin) * stepMin);
  }
  function yTicks(maxMin, stepMin) {
    var out = [];
    for (var v = 0; v <= maxMin + 0.1; v += stepMin) { out.push(v); }
    return out;
  }
  // Balkendiagramm: items [{label,value,flag}] -> Rechtecke im Koordinatensystem
  function barLayout(items, W, H, opts) {
    opts = opts || {};
    var padL = opts.padL != null ? opts.padL : 36, padR = opts.padR != null ? opts.padR : 8, padT = opts.padT != null ? opts.padT : 14, padB = opts.padB != null ? opts.padB : 26;
    var maxVal = Math.max.apply(null, items.map(function (i) { return i.value; }).concat([0]));
    var top = opts.max || niceMax(Math.max(maxVal, opts.minMax || 0), opts.step || 120);
    var iw = W - padL - padR, ih = H - padT - padB;
    var slot = items.length ? iw / items.length : iw;
    var bw = Math.min(opts.maxBar || 44, slot * (opts.fill || 0.62));
    var bars = items.map(function (it, i) {
      var h = it.value > 0 ? Math.max(2, (it.value / top) * ih) : 0;
      return { x: padL + slot * i + (slot - bw) / 2, y: padT + ih - h, w: bw, h: h, cx: padL + slot * i + slot / 2, item: it, index: i };
    });
    return { bars: bars, top: top, padL: padL, padT: padT, ih: ih, iw: iw, W: W, H: H, y: function (v) { return padT + ih - (v / top) * ih; } };
  }
  // Liniendiagramm (Saldo): points [{label,value}] -> Pfad
  function lineLayout(points, W, H, opts) {
    opts = opts || {};
    var padL = opts.padL != null ? opts.padL : 44, padR = opts.padR != null ? opts.padR : 12, padT = opts.padT != null ? opts.padT : 14, padB = opts.padB != null ? opts.padB : 28;
    var vals = points.map(function (p) { return p.value; });
    var lo = Math.min.apply(null, vals.concat([0])), hi = Math.max.apply(null, vals.concat([0]));
    var step = opts.step || 300;
    lo = Math.floor(lo / step) * step; hi = Math.ceil(hi / step) * step;
    if (hi === lo) { hi = lo + step; }
    var iw = W - padL - padR, ih = H - padT - padB;
    var n = points.length;
    function X(i) { return padL + (n > 1 ? iw * i / (n - 1) : iw / 2); }
    function Y(v) { return padT + ih - ((v - lo) / (hi - lo)) * ih; }
    var pts = points.map(function (p, i) { return { x: X(i), y: Y(p.value), p: p }; });
    var d = pts.map(function (q, i) { return (i ? 'L' : 'M') + r1(q.x) + ' ' + r1(q.y); }).join(' ');
    var zero = Y(0);
    var area = pts.length ? d + ' L' + r1(pts[pts.length - 1].x) + ' ' + r1(zero) + ' L' + r1(pts[0].x) + ' ' + r1(zero) + ' Z' : '';
    return { pts: pts, d: d, area: area, zeroY: zero, lo: lo, hi: hi, step: step, padL: padL, padT: padT, ih: ih, iw: iw, Y: Y };
  }
  function r1(x) { return Math.round(x * 10) / 10; }
  // Tagesbalken (Floating-Bar): erster Beginn bis letztes Ende in Tagesminuten
  function dayBlockLayout(blocks, H, t0, t1) {
    t0 = t0 == null ? 5 * 60 : t0; t1 = t1 == null ? 24 * 60 : t1;
    return blocks.map(function (b) {
      var a = clamp(b.startMin, t0, t1), z = clamp(b.endMin, t0, t1);
      return { y: (a - t0) / (t1 - t0) * H, h: Math.max(3, (z - a) / (t1 - t0) * H), b: b };
    });
  }
  // Timeline für „Heute“: items [{startMin,endMin,open,entry}] -> Prozentpositionen (Spanne mindestens 06:00-22:00)
  function timelineSegments(items) {
    var lo = 6 * 60, hi = 22 * 60;
    items.forEach(function (s) { lo = Math.min(lo, Math.floor(s.startMin / 60) * 60); hi = Math.max(hi, Math.ceil(Math.max(s.endMin, s.startMin + 1) / 60) * 60); });
    lo = Math.max(0, lo); hi = Math.min(24 * 60, hi);
    var span = hi - lo;
    return { lo: lo, hi: hi, segs: items.map(function (s) { return { left: (s.startMin - lo) / span * 100, width: Math.max(0.6, (Math.max(s.endMin, s.startMin + 1) - s.startMin) / span * 100), open: !!s.open, startMin: s.startMin, endMin: s.endMin, entry: s.entry }; }) };
  }
  // Heatmap-Stufe 0..4 aus Minuten
  function heatLevel(min, targetDayMin) {
    if (!min) { return 0; }
    var t = targetDayMin || 480;
    if (min > 600) { return 5; }
    if (min >= t) { return 4; }
    if (min >= t * 0.75) { return 3; }
    if (min >= t * 0.4) { return 2; }
    return 1;
  }
  // Kalenderraster für Monat: Wochen als Arrays (null = leer)
  function monthGrid(ym) {
    var first = ym + '-01', pad0 = dow(first), days = monthDays(ym), cells = [], i;
    for (i = 0; i < pad0; i++) { cells.push(null); }
    days.forEach(function (d) { cells.push(d); });
    while (cells.length % 7) { cells.push(null); }
    var weeks = [];
    for (i = 0; i < cells.length; i += 7) { weeks.push(cells.slice(i, i + 7)); }
    return weeks;
  }

  /* ---------- QR-Text ---------- */
  var CODE_RE = /^[A-Za-z0-9_-]{1,32}\.[A-Za-z0-9_-]{8,128}$/;
  var TOKEN_RE = /^[A-Za-z0-9_-]{20,128}$/;
  // Erkennt App-URLs (…/apps/timeguardian/s/<code> oder …/pair/<token>) bzw. nackten Code.
  // Gibt nur Teile zurück, nie eine fremde URL: Navigation baut die App selbst.
  function parseScanText(text) {
    if (typeof text !== 'string') { return null; }
    var s = text.trim();
    if (!s || s.length > 600) { return null; }
    var m = /\/apps\/timeguardian\/(?:index\.php\/)?(s|pair)\/([^/?#\s]+)/.exec(s);
    if (m) {
      var part = safeDecode(m[2]);
      if (m[1] === 's' && CODE_RE.test(part)) { return { kind: 'punch', code: part }; }
      if (m[1] === 'pair' && TOKEN_RE.test(part)) { return { kind: 'pair', token: part }; }
      return null;
    }
    if (/^[a-z][a-z0-9+.-]*:/i.test(s) || s.indexOf('/') >= 0) { return null; }
    if (CODE_RE.test(s)) { return { kind: 'punch', code: s }; }
    return null;
  }
  function safeDecode(x) { try { return decodeURIComponent(x); } catch (e) { return ''; } }
  function sameApp(text, origin) {
    try { return new URL(text).origin === origin; } catch (e) { return false; }
  }


  /* ---------- Zeitzone / Datumsformate (Intl) ---------- */
  var clockFmt = {};
  function clockTz(iso, tz) {
    var d = new Date(iso);
    if (isNaN(d.getTime())) { return ''; }
    try {
      var f = clockFmt[tz || ''] || (clockFmt[tz || ''] = new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit', hour12: false, timeZone: tz || undefined }));
      return f.format(d).replace(/^24/, '00');
    } catch (e) { return isoToLocalClock(iso); }
  }
  var LOCALES = { de: 'de-DE', en: 'en-GB', it: 'it-IT' };
  function fmtDayShort(day, lang) { // "Mo., 17.11."
    var t = parseDay(day); if (t === null) { return String(day || ''); }
    return new Date(t).toLocaleDateString(LOCALES[lang] || 'de-DE', { weekday: 'short', day: '2-digit', month: '2-digit', timeZone: 'UTC' });
  }
  function fmtDayLong(day, lang) { // "Dienstag, 17. November 2026"
    var t = parseDay(day); if (t === null) { return String(day || ''); }
    return new Date(t).toLocaleDateString(LOCALES[lang] || 'de-DE', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' });
  }
  function fmtDayMonth(day, lang) { // "17. Nov."
    var t = parseDay(day); if (t === null) { return String(day || ''); }
    return new Date(t).toLocaleDateString(LOCALES[lang] || 'de-DE', { day: 'numeric', month: 'short', timeZone: 'UTC' });
  }
  function fmtMonth(ym, lang) {
    var t = parseDay(ym + '-01'); if (t === null) { return String(ym || ''); }
    return new Date(t).toLocaleDateString(LOCALES[lang] || 'de-DE', { month: 'long', year: 'numeric', timeZone: 'UTC' });
  }
  function fmtMonthShort(ym, lang) {
    var t = parseDay(ym + '-01'); if (t === null) { return String(ym || ''); }
    return new Date(t).toLocaleDateString(LOCALES[lang] || 'de-DE', { month: 'short', timeZone: 'UTC' });
  }
  function fmtDateTime(iso, lang, tz) {
    var d = new Date(iso); if (isNaN(d.getTime())) { return ''; }
    try { return d.toLocaleString(LOCALES[lang] || 'de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', timeZone: tz || undefined }); } catch (e) { return d.toLocaleString(); }
  }

  /* ---------- Regelvorschau für manuelle Buchung (Spiegel der Server-Regeln, Server ist maßgeblich) ---------- */
  // blocks: [{startMin,endMin,pauseMin}] des Tages inkl. Kandidat; prevEndMin: Ende des Vortags relativ zu 00:00 dieses Tages (negativ) oder null
  function previewRules(blocks, prevEndMin, dowIdx) {
    var out = [];
    if (!blocks.length) { return out; }
    var b = blocks.slice().sort(function (x, y) { return x.startMin - y.startMin; });
    var net = 0, declared = 0, gaps = 0, i;
    for (i = 0; i < b.length; i++) {
      net += Math.max(0, b[i].endMin - b[i].startMin - (b[i].pauseMin || 0));
      declared += b[i].pauseMin || 0;
      if (i > 0) { var g = b[i].startMin - b[i - 1].endMin; if (g >= 15) { gaps += g; } }
    }
    var pauseTotal = declared + gaps;
    if (net > 600) { out.push({ rule: 'DAY10', level: 'verstoss', value: net }); }
    else if (net > 480) { out.push({ rule: 'DAY8', level: 'hinweis', value: net }); }
    if (net > 540 && pauseTotal < 45) { out.push({ rule: 'BREAK45', level: 'verstoss', value: pauseTotal }); }
    else if (net > 360 && pauseTotal < 30) { out.push({ rule: 'BREAK30', level: 'verstoss', value: pauseTotal }); }
    if (dowIdx === 6 && net > 0) { out.push({ rule: 'SUN', level: 'verstoss', value: net }); }
    if (prevEndMin != null) {
      var rest = b[0].startMin - prevEndMin;
      if (rest < 660) { out.push({ rule: 'REST11', level: 'verstoss', value: Math.max(0, rest) }); }
    }
    return out;
  }
  function blocksOverlap(a, others) {
    return others.some(function (o) { return a.startMin < o.endMin && a.endMin > o.startMin; });
  }

  /* ---------- Sonstiges ---------- */
  function initials(name) {
    var p = String(name || '?').trim().split(/\s+/).filter(Boolean);
    if (!p.length) { return '?'; }
    return (p[0][0] + (p.length > 1 ? p[p.length - 1][0] : '')).toUpperCase();
  }
  function deviceLabelDefault(ua, who) {
    ua = String(ua || '');
    var dev = /iPhone/.test(ua) ? 'iPhone' : /iPad/.test(ua) ? 'iPad' : /Android/.test(ua) ? 'Android' : /Macintosh/.test(ua) ? 'Mac' : /Windows/.test(ua) ? 'Windows' : '';
    return dev;
  }
  function clampStr(s, n) { s = String(s == null ? '' : s); return s.length > n ? s.slice(0, n) : s; }
  function csvDownloadName(prefix, ym) { return prefix + '-' + ym + '.csv'; }
  function sum(arr, f) { return arr.reduce(function (a, x) { return a + (f ? f(x) : x); }, 0); }

  return {
    DAY_MS: DAY_MS, pad: pad, parseDay: parseDay, fmtDay: fmtDay, addDays: addDays, dow: dow, monday: monday, localDay: localDay, isoWeek: isoWeek,
    ymOf: ymOf, addMonths: addMonths, daysInMonth: daysInMonth, monthDays: monthDays, rangeDays: rangeDays,
    fmtHM: fmtHM, fmtClockDur: fmtClockDur, fmtH: fmtH, fmtSigned: fmtSigned, fmtSignedHM: fmtSignedHM, parseClock: parseClock, clockOf: clockOf,
    isoToLocalClock: isoToLocalClock, isoToLocalDay: isoToLocalDay, localToIso: localToIso,
    entryMinutes: entryMinutes, liveNet: liveNet, sinceMinutes: sinceMinutes, fmtStopwatch: fmtStopwatch,
    clamp: clamp, ratio: ratio, ringGeometry: ringGeometry, limitLevel: limitLevel,
    niceMax: niceMax, yTicks: yTicks, barLayout: barLayout, lineLayout: lineLayout, dayBlockLayout: dayBlockLayout, timelineSegments: timelineSegments,
    heatLevel: heatLevel, monthGrid: monthGrid,
    parseScanText: parseScanText, sameApp: sameApp, CODE_RE: CODE_RE, TOKEN_RE: TOKEN_RE,
    clockTz: clockTz, fmtDayShort: fmtDayShort, fmtDayLong: fmtDayLong, fmtDayMonth: fmtDayMonth, fmtMonth: fmtMonth, fmtMonthShort: fmtMonthShort, fmtDateTime: fmtDateTime,
    previewRules: previewRules, blocksOverlap: blocksOverlap,
    initials: initials, deviceLabelDefault: deviceLabelDefault, clampStr: clampStr, csvDownloadName: csvDownloadName, sum: sum
  };
});
