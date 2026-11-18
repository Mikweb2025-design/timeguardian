/* TimeGuardian – Kiosk (Vollbild am Eingang): rotierender QR, Countdown, Uhr, Anwesenheit. */
(function (root) {
  'use strict';
  var TG = root.TG = root.TG || {};
  var core = TG.core, ui = TG.ui, api = TG.api, h = ui.h;
  var t = function (k, p) { return TG.i18n.t(k, p); };
  var doc = root.document;

  function boot() {
    var el = doc.getElementById('tg-root');
    if (!el || el.getAttribute('data-page') !== 'kiosk') { return; }
    TG.i18n.setLang(TG.i18n.detect(el.getAttribute('data-lang') || null));
    doc.documentElement.lang = TG.i18n.lang();
    doc.title = t('kiosk_title') + ' · ' + t('app_name');
    TG.app.registerSW();

    var S = { code: null, fetchedAt: 0, windowSec: 60, offline: false, err: null, people: null, wake: null, fs: false };
    var qrHost = h('div', { 'class': 'tg-k__qr' });
    var ringSvg = TG.charts.ring({ size: 120, rings: [{ r: 50, width: 9, frac: 1, cls: 'tg-lv-ok' }] });
    var cdText = h('div', { 'class': 'tg-k__cdt' });
    var timeEl = h('div', { 'class': 'tg-k__time', 'aria-live': 'off' }), dateEl = h('div', { 'class': 'tg-k__date' });
    var presEl = h('div', { 'class': 'tg-k__pres' });
    var offEl = h('div', { 'class': 'tg-k__off', role: 'status', hidden: '' }, ui.icon('offline', 22), h('span', null, t('kiosk_offline')));
    var fsBtn = h('button', { type: 'button', 'class': 'tg-btn tg-k__fs', onclick: toggleFs }, ui.icon('fullscreen', 20), h('span', null, t('kiosk_fullscreen')));
    var wakeEl = h('span', { 'class': 'tg-k__wake', hidden: '' }, ui.icon('sun', 14), t('kiosk_awake'));
    var backEl = h('a', { 'class': 'tg-btn tg-k__back', href: api.page('/') }, ui.icon('arrowL', 20), h('span', null, t('kiosk_back')));
    var head = h('div', { 'class': 'tg-k__brand' }, el.getAttribute('data-icon') ? h('img', { src: el.getAttribute('data-icon'), alt: '', width: 40, height: 40 }) : null, h('span', null, t('app_name')));

    var cdBox = h('div', { 'class': 'tg-k__cd' }, h('div', { 'class': 'tg-k__ring' }, ringSvg, cdText), h('div', { 'class': 'tg-k__cdl' }, t('kiosk_next')));
    var left = h('section', { 'class': 'tg-k__left' }, h('h1', { 'class': 'tg-k__title' }, t('kiosk_title')), h('div', { 'class': 'tg-k__qrbox' }, qrHost, offEl), cdBox);
    var right = h('aside', { 'class': 'tg-k__right' }, head, timeEl, dateEl, h('h2', { 'class': 'tg-k__h2' }, t('kiosk_present')), presEl,
      h('div', { 'class': 'tg-k__tools' }, fsBtn, backEl, wakeEl));
    ui.mount(el, h('div', { 'class': 'tg-kiosk' }, left, right));

    function toggleFs() {
      try {
        if (!doc.fullscreenElement) { (doc.documentElement.requestFullscreen || doc.documentElement.webkitRequestFullscreen).call(doc.documentElement); }
        else { (doc.exitFullscreen || doc.webkitExitFullscreen).call(doc); }
      } catch (e) { ui.toast(t('kiosk_fs_fail'), 'warn'); }
    }
    doc.addEventListener('fullscreenchange', function () { S.fs = !!doc.fullscreenElement; fsBtn.querySelector('span').textContent = S.fs ? t('kiosk_fs_exit') : t('kiosk_fullscreen'); doc.documentElement.classList.toggle('tg-fs', S.fs); });
    if (!(doc.documentElement.requestFullscreen || doc.documentElement.webkitRequestFullscreen)) { fsBtn.hidden = true; }

    /* ---------- QR laden ---------- */
    var fetchTimer = null;
    function schedule(sec) { if (fetchTimer) { root.clearTimeout(fetchTimer); } fetchTimer = root.setTimeout(loadCode, Math.max(1, sec) * 1000); }
    function loadCode() {
      api.kioskCode().then(function (r) {
        var changed = !S.code || S.code.window !== r.window;
        S.code = r; S.fetchedAt = Date.now(); S.windowSec = r.windowSec || 60; S.offline = false; S.err = null;
        offEl.setAttribute('hidden', '');
        if (changed) { drawQr(r.qrText || r.url); }
        schedule(Math.min(20, Math.max(2, r.expiresInSec + 1)));
      }, function (err) {
        S.err = err;
        if (err && (err.status === 401 || err.status === 403)) {
          ui.mount(qrHost, h('div', { 'class': 'tg-k__msg' }, ui.icon('warn', 40), h('p', null, t('kiosk_denied')), h('a', { 'class': 'tg-btn tg-btn--primary', href: TG.app.loginUrl() }, t('err_login'))));
          return;
        }
        S.offline = true; offEl.removeAttribute('hidden'); schedule(5);
      });
    }
    function drawQr(text) {
      try { ui.mount(qrHost, TG.qr.svg(text, { ecc: 'M', quiet: 2, label: t('kiosk_qr_label') })); }
      catch (e) { ui.mount(qrHost, h('div', { 'class': 'tg-k__msg' }, t('err_generic_p'))); }
    }
    function tickCd() {
      if (!S.code) { return; }
      var left = Math.max(0, S.code.expiresInSec - (Date.now() - S.fetchedAt) / 1000);
      TG.charts.updateRing(ringSvg, [left / S.windowSec], [left < 8 ? 'warn' : 'ok']);
      cdText.textContent = String(Math.ceil(left));
      // Kein Refresh möglich und Code längst abgelaufen (>1 Fenster): QR ausblenden, nicht veraltete Codes zeigen
      qrHost.classList.toggle('is-stale', left <= 0 && S.offline && (Date.now() - S.fetchedAt) / 1000 > S.code.expiresInSec + S.windowSec);
      if (left <= 0 && !fetchTimer) { loadCode(); }
    }
    function tickClock() {
      var d = new Date();
      timeEl.textContent = core.pad(d.getHours()) + ':' + core.pad(d.getMinutes());
      dateEl.textContent = d.toLocaleDateString({ de: 'de-DE', en: 'en-GB', it: 'it-IT' }[TG.i18n.lang()] || 'de-DE', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    }

    /* ---------- Anwesenheit ---------- */
    function loadPresence() {
      api.teamOverview().then(function (ov) {
        var people = (ov.people || []).filter(function (p) { return p.state === 'working' || p.state === 'pause'; });
        var shown = people.slice(0, 14), more = people.length - shown.length;
        ui.mount(presEl, [h('div', { 'class': 'tg-k__count' }, h('b', null, String(people.length)), h('span', null, t('kiosk_of', { n: (ov.people || []).length }))),
          people.length ? h('ul', { 'class': 'tg-k__names' }, shown.map(function (p) { return h('li', { 'class': p.state === 'pause' ? 'is-pause' : '' }, h('i', { 'aria-hidden': 'true' }), p.name); }).concat(more > 0 ? [h('li', { 'class': 'tg-k__more' }, t('kiosk_more', { n: more }))] : [])) : h('div', { 'class': 'tg-muted' }, t('kiosk_nobody'))]);
      }, function () { /* kein Chef: Anwesenheit nicht verfügbar */ if (!S.people) { ui.mount(presEl, h('div', { 'class': 'tg-muted' }, t('kiosk_presence_na'))); } S.people = S.people || 'na'; });
    }

    /* ---------- Wake-Lock ---------- */
    function wake() {
      try {
        if (!('wakeLock' in root.navigator) || doc.hidden) { return; }
        root.navigator.wakeLock.request('screen').then(function (l) { S.wake = l; wakeEl.removeAttribute('hidden'); l.addEventListener('release', function () { wakeEl.setAttribute('hidden', ''); }); }, function () { /* ignore */ });
      } catch (e) { /* ignore */ }
    }
    doc.addEventListener('visibilitychange', function () { if (!doc.hidden) { wake(); loadCode(); loadPresence(); } });
    TG.app.watchOnline(function (on) { if (on) { loadCode(); } else { S.offline = true; offEl.removeAttribute('hidden'); } });

    tickClock(); root.setInterval(tickClock, 1000); root.setInterval(tickCd, 250);
    loadCode(); loadPresence(); root.setInterval(loadPresence, 30000); wake();
  }
  TG.app.ready(boot);
})(typeof self !== 'undefined' ? self : this);
