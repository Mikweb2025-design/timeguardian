/* TimeGuardian – gemeinsame Bootstrap-Hilfen: Antwort-Normalisierung (TG.model), Service-Worker, Online-Status. */
(function (root) {
  'use strict';
  var TG = root.TG = root.TG || {};
  var core = TG.core;

  function pick(o, keys, dflt) {
    if (!o || typeof o !== 'object') { return dflt; }
    for (var i = 0; i < keys.length; i++) { if (o[keys[i]] !== undefined && o[keys[i]] !== null) { return o[keys[i]]; } }
    return dflt;
  }
  function num(v, d) { v = Number(v); return isFinite(v) ? v : (d || 0); }

  /* ---------- Normalisierer (einzige Stelle, die Rohfelder kennt) ---------- */
  var model = {
    pick: pick,
    // GET /api/status | POST /api/punch  ->  einheitliche Statusform
    status: function (raw) {
      raw = raw || {};
      var st = raw.state, obj = (st && typeof st === 'object') ? st : raw;
      return {
        state: String((st && typeof st === 'object') ? (st.state || 'none') : (st || 'none')),
        since: obj.since || null,
        pauseSince: obj.pauseSince || null,
        netToday: num(pick(raw, ['netMinToday'], 0)),
        netWeek: num(pick(raw, ['weekNetMin'], 0)),
        weekTarget: num(raw.targetMin) || null,
        limitMin: num(raw.limitMin) || 2880,
        mode: raw.mode || null,
        name: raw.name || null
      };
    },
    conf: function (c) {
      c = c || {};
      return { weeklyMin: num(c.weeklyMin, 2400), daysMask: num(c.daysMask, 31), mode: c.mode === 'pause' ? 'pause' : 'simple', lang: c.lang || '', canSetTarget: !!c.canSetTarget };
    },
    // GET /api/me
    me: function (raw) {
      raw = raw || {};
      var conf = model.conf(raw.conf);
      var ts = raw.todaySummary || {};
      var wk = raw.week || {};
      var acc = raw.account || {};
      var status = model.status({ state: raw.state, name: raw.name, mode: conf.mode, netMinToday: ts.netMin, weekNetMin: wk.netMin, targetMin: wk.targetMin || conf.weeklyMin, limitMin: wk.limitMin });
      return {
        uid: String(raw.uid || ''), name: String(raw.name || raw.uid || ''), isChef: !!raw.isChef, isKiosk: !!raw.isKiosk, isAdmin: !!raw.isAdmin,
        lang: raw.lang || conf.lang || '', timezone: raw.timezone || '', today: raw.today || '',
        conf: conf, status: status, todaySummary: ts, week: wk, account: acc,
        deviceCount: num(raw.deviceCount), projects: Array.isArray(raw.projects) ? raw.projects : [],
        features: raw.features || {}, pending: raw.pendingDecisions || { absences: 0, corrections: 0 }
      };
    },
    list: function (raw, key) { return Array.isArray(raw) ? raw : (raw && Array.isArray(raw[key]) ? raw[key] : []); }
  };

  /* ---------- Service-Worker ---------- */
  function registerSW() {
    try {
      if (!('serviceWorker' in root.navigator)) { return Promise.resolve(false); }
      var base = TG.api.base();
      var url = (root.OC && root.OC.generateUrl) ? root.OC.generateUrl('/apps/timeguardian/sw.js') : base + '/sw.js';
      var scope = (root.OC && root.OC.generateUrl) ? root.OC.generateUrl('/apps/timeguardian/') : base + '/';
      return root.navigator.serviceWorker.register(url, { scope: scope }).then(function () { return true; }, function () { return false; });
    } catch (e) { return Promise.resolve(false); }
  }

  /* ---------- Online-Status ---------- */
  function watchOnline(cb) {
    var on = function () { cb(true); }, off = function () { cb(false); };
    root.addEventListener('online', on); root.addEventListener('offline', off);
    cb(root.navigator.onLine !== false);
  }

  /* ---------- Login-URL mit Rücksprung ---------- */
  function loginUrl() {
    var base = TG.api.base().replace(/\/apps\/timeguardian$/, '');
    return base + '/login?redirect_url=' + encodeURIComponent(root.location.pathname + root.location.search + root.location.hash);
  }

  // Manifest (tg-boot.js hält genau unseres im <head>) und Touch-Icon
  function injectHead() {
    try {
      if (root.TG.pwa && root.TG.pwa.fixManifest) { root.TG.pwa.fixManifest(); }
    } catch (e) { /* ignore */ }
  }

  function ready(fn) {
    var done = false, run = function () { if (!done) { done = true; injectHead(); fn(); } };
    if (root.document.readyState === 'complete') { run(); return; }
    root.document.addEventListener('DOMContentLoaded', run);
    root.addEventListener('load', run);
  }

  TG.model = model;
  TG.app = { registerSW: registerSW, watchOnline: watchOnline, loginUrl: loginUrl, ready: ready, core: core };
})(typeof self !== 'undefined' ? self : this);
