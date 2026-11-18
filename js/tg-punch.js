/* TimeGuardian – Stempel-Seite (scan) und Kopplungs-Seite (pair). */
(function (root) {
  'use strict';
  var TG = root.TG = root.TG || {};
  var core = TG.core, ui = TG.ui, api = TG.api, h = ui.h;
  var t = function (k, p) { return TG.i18n.t(k, p); };
  var SOUND_KEY = 'tg.sound';

  function soundOn() { return api.store('get', SOUND_KEY) === '1'; }

  function errKind(err) {
    if (!err) { return 'generic'; }
    var c = String(err.code || '');
    if (err.status === 0 || c === 'offline') { return 'offline'; }
    if (c === 'invalid_code') { return 'expired'; }
    if (c === 'code_required') { return 'needcode'; }
    if (c === 'too_fast') { return 'toosoon'; }
    if (c === 'rate_limited' || err.status === 429) { return 'rate'; }
    if (c === 'invalid_transition') { return 'transition'; }
    if (c === 'forbidden' || c === 'not_in_team') { return 'forbidden'; }
    if (c === 'unauthorized' || c === 'not_paired' || c === 'csrf' || err.status === 401) { return 'unpaired'; }
    if (c === 'future') { return 'future'; }
    return 'generic';
  }

  function header(el) {
    var icon = el.getAttribute('data-icon');
    return h('header', { 'class': 'tg-pg__head' },
      icon ? h('img', { src: icon, alt: '', width: 36, height: 36, 'class': 'tg-pg__logo' }) : null,
      h('div', { 'class': 'tg-pg__brand' }, t('app_name')));
  }

  /* ================= STEMPEL-SEITE ================= */
  function bootScan(el) {
    var code = el.getAttribute('data-code') || '';
    var isGo = !code; // /go: Startseite der installierten App (kein Code)
    root.document.title = (isGo ? t('go_title') + ' · ' : '') + t('app_name');
    var S = { status: null, fetchedAt: 0, busy: false, error: null, result: null, online: true, loading: true, scanning: false, pending: null };
    var host = h('main', { 'class': 'tg-pg__main' });
    var head = header(el);
    var offBanner = h('div', { 'class': 'tg-banner tg-banner--warn', role: 'status', hidden: '' }, ui.icon('offline', 18), h('span', null, t('offline_banner')));
    var foot = h('footer', { 'class': 'tg-pg__foot' });
    el.appendChild(h('div', { 'class': 'tg-pg' }, head, offBanner, host, foot));
    var ringSvg = null, centerEl = null, subEls = {};

    TG.app.watchOnline(function (on) {
      S.online = on;
      if (on) { offBanner.setAttribute('hidden', ''); if (!S.loading && !S.busy) { refresh(true); } } else { offBanner.removeAttribute('hidden'); }
      renderActions();
    });

    function setStatus(raw) {
      var norm = TG.model.status(raw);
      if (S.status) {
        if (norm.mode == null) { norm.mode = S.status.mode; }
        if (!norm.weekTarget) { norm.weekTarget = S.status.weekTarget; }
        if (!norm.name) { norm.name = S.status.name; }
      }
      S.status = norm; S.fetchedAt = Date.now();
    }

    function refresh(silent) {
      return api.status().then(function (raw) {
        setStatus(raw); S.loading = false; if (S.error && S.error.soft) { S.error = null; }
        render();
      }, function (err) {
        S.loading = false;
        var k = errKind(err);
        if (k === 'offline') { S.online = false; offBanner.removeAttribute('hidden'); if (!silent && !S.status) { S.error = { kind: 'offline', soft: true }; } }
        else if (k === 'unpaired') { S.error = { kind: 'unpaired', err: err }; S.status = null; }
        else if (!silent) { S.error = { kind: 'generic', err: err, soft: true }; }
        render();
      });
    }

    function doPunch(body, label) {
      if (S.busy) { return; }
      S.busy = true; S.error = null; render();
      return api.punch(body).then(function (raw) {
        var act = (raw && raw.action) || label || 'in';
        setStatus(raw);
        S.busy = false;
        var at = new Date();
        S.result = { kind: act, at: core.pad(at.getHours()) + ':' + core.pad(at.getMinutes()), ts: Date.now() };
        ui.vibrate(act === 'out' ? [40, 60, 40] : [30, 40, 30]);
        if (soundOn()) { ui.beep(act === 'out' ? 'out' : 'in'); }
        if (body.code) { api.store('set', 'tg.done.' + body.code, JSON.stringify(S.result)); }
        render();
      }, function (err) {
        S.busy = false;
        var k = errKind(err);
        S.error = { kind: k, err: err };
        if (k === 'unpaired') { S.status = null; }
        if (k === 'transition' && err.data && err.data.state) { var cur = S.status || {}; setStatus(Object.assign({}, { state: err.data.state, name: cur.name, mode: cur.mode, netMinToday: cur.netToday, weekNetMin: cur.netWeek, targetMin: cur.weekTarget }, {})); }
        ui.vibrate([140, 60, 140]);
        if (soundOn()) { ui.beep('error'); }
        if (k === 'offline') { S.online = false; offBanner.removeAttribute('hidden'); }
        render();
      });
    }

    function openScanner() { S.scanning = true; S.error = null; render(); }

    /* ---------- Ansichten ---------- */
    function errorCard() {
      var e = S.error, k = e.kind, title, text, actions = [];
      if (k === 'expired') { title = t('err_expired_t'); text = t('err_expired_p'); actions.push(btn(t('scan_again'), 'camera', openScanner, true)); }
      else if (k === 'unpaired') { return unpairedCard(); }
      else if (k === 'rate') { title = t('err_rate_t'); text = t('err_rate_p'); }
      else if (k === 'toosoon') { title = t('err_soon_t'); text = t('err_soon_p'); }
      else if (k === 'future') { title = t('err_future_t'); text = t('err_future_p'); }
      else if (k === 'forbidden') { title = t('err_forbidden_t'); text = t('err_forbidden_p'); }
      else if (k === 'transition') { title = t('err_transition_t'); text = (e.err && e.err.message) || t('err_transition_p'); }
      else if (k === 'needcode') { title = t('err_needcode_t'); text = t('err_needcode_p'); actions.push(btn(t('scan_qr'), 'camera', openScanner, true)); }
      else if (k === 'offline') { title = t('err_offline_t'); text = t('err_offline_p'); actions.push(btn(t('retry'), 'refresh', function () { S.error = null; S.loading = true; render(); refresh(); }, true)); }
      else { title = t('err_generic_t'); text = (e.err && e.err.message && e.err.message !== e.err.code) ? e.err.message : t('err_generic_p'); actions.push(btn(t('retry'), 'refresh', function () { S.error = null; S.loading = true; render(); refresh(); }, true)); }
      var tone = (k === 'unpaired' || k === 'offline') ? 'info' : 'err';
      return h('section', { 'class': 'tg-card tg-state tg-state--' + tone, role: 'alert' },
        h('div', { 'class': 'tg-state__icon' }, ui.icon(k === 'offline' ? 'offline' : k === 'unpaired' ? 'phone' : 'warn', 34)),
        h('h1', { 'class': 'tg-state__title' }, title), h('p', { 'class': 'tg-state__text' }, text),
        h('div', { 'class': 'tg-state__actions' }, actions));
    }
    /* Telefon nicht gekoppelt (und keine Nextcloud-Sitzung): klare Anleitung, Koppel-QR lässt sich hier scannen */
    function unpairedCard() {
      var steps = h('ol', { 'class': 'tg-gosteps' }, [1, 2, 3].map(function (i) { return h('li', null, h('span', { 'class': 'tg-gosteps__n' }, String(i)), h('span', null, t('go_pair_' + i))); }));
      return h('section', { 'class': 'tg-card tg-state tg-state--info', role: 'status' },
        h('div', { 'class': 'tg-state__icon' }, ui.icon('phone', 34)),
        h('h1', { 'class': 'tg-state__title' }, t('go_unpaired_t')), h('p', { 'class': 'tg-state__text' }, t('go_unpaired_p')), steps,
        h('div', { 'class': 'tg-state__actions' },
          btn(t('go_scan_pair'), 'camera', openScanner, true),
          h('a', { 'class': 'tg-btn', href: TG.app.loginUrl() }, ui.icon('user', 18), t('err_login'))),
        h('p', { 'class': 'tg-note' }, t('go_unpaired_note')));
    }
    function btn(label, icon, fn, primary, extra) {
      return h('button', { type: 'button', 'class': 'tg-btn' + (primary ? ' tg-btn--primary tg-btn--lg' : '') + (extra ? ' ' + extra : ''), onclick: fn }, icon ? ui.icon(icon, 20) : null, label);
    }

    function scannerCard() {
      var sc = TG.scanner.create({ onText: function (text) {
        var r = core.parseScanText(text);
        if (!r) { return t('scan_unknown'); }
        ui.vibrate(20);
        root.location.assign(r.kind === 'pair' ? api.page('/pair/' + encodeURIComponent(r.token)) : api.page('/s/' + encodeURIComponent(r.code)));
        return true;
      } });
      root.setTimeout(function () { sc.start(); }, 30);
      return h('section', { 'class': 'tg-card tg-scancard' },
        h('h1', { 'class': 'tg-state__title' }, t('scan_title')), sc.el,
        h('div', { 'class': 'tg-state__actions' }, btn(t('cancel'), 'close', function () { sc.stop(); S.scanning = false; render(); })));
    }

    function statusCard() {
      var st = S.status;
      ringSvg = TG.charts.ring({ size: 248, rings: [
        { r: 108, width: 16, frac: 0, cls: 'tg-lv-ok', marks: [0.8] },
        { r: 84, width: 10, frac: 0, cls: 'tg-lv-ok' }
      ] });
      var stateEl = h('div', { 'class': 'tg-ringc__state' });
      var bigEl = h('div', { 'class': 'tg-ringc__big', 'aria-live': 'off' });
      var smallEl = h('div', { 'class': 'tg-ringc__small' });
      centerEl = { state: stateEl, big: bigEl, small: smallEl };
      var ringBox = h('div', { 'class': 'tg-ringc' }, ringSvg, h('div', { 'class': 'tg-ringc__center' }, stateEl, bigEl, smallEl));
      subEls = { today: h('b'), week: h('b'), limit: h('div', { 'class': 'tg-limit' }), go: isGo ? h('div', { 'class': 'tg-goline', 'aria-live': 'polite' }) : null };
      var stats = h('div', { 'class': 'tg-stats' },
        h('div', { 'class': 'tg-stat' }, h('span', null, t('net_today')), subEls.today),
        h('div', { 'class': 'tg-stat' }, h('span', null, t('week')), subEls.week));
      var resultEl = S.result ? h('div', { 'class': 'tg-flash tg-flash--' + S.result.kind, role: 'status' }, h('span', { 'class': 'tg-flash__tick' }, ui.icon('check', 22)), h('span', null, t('res_' + S.result.kind, { time: S.result.at }))) : null;
      var actions = h('div', { 'class': 'tg-actions', id: 'tg-actions' });
      return h('section', { 'class': 'tg-card tg-status tg-status--' + st.state },
        st.name ? h('div', { 'class': 'tg-hello' }, t('hello', { name: st.name })) : null,
        subEls.go, resultEl, ringBox, stats, subEls.limit, actions);
    }

    function renderActions() {
      var box = root.document.getElementById('tg-actions');
      if (!box || !S.status) { return; }
      var st = S.status, mode = st.mode === 'pause' ? 'pause' : 'simple', dis = S.busy || !S.online;
      var items = [];
      function big(label, icon, fn, cls) { var b = btn(label, icon, fn, true, cls); if (dis) { b.disabled = true; } return b; }
      function small(label, icon, fn, cls) { var b = btn(label, icon, fn, false, cls); if (dis) { b.disabled = true; } return b; }
      var codeUsable = !!code && !api.store('get', 'tg.done.' + code) && !(S.error && S.error.kind === 'expired');
      if (st.state === 'none' || st.state === 'done') {
        items.push(codeUsable ? big(t('act_in'), 'play', function () { doPunch({ code: code }, 'in'); }, 'tg-btn--go') : big(t('scan_qr'), 'camera', openScanner, 'tg-btn--go'));
      } else if (st.state === 'working') {
        if (mode === 'pause') {
          items.push(big(t('act_pause'), 'pause', function () { doPunch({ action: 'pause' }, 'pause'); }, 'tg-btn--warn'));
          items.push(small(t('act_end'), 'stop', function () { confirmEnd(); }));
        } else {
          items.push(big(t('act_out'), 'stop', function () { confirmEnd(); }, 'tg-btn--stop'));
        }
      } else if (st.state === 'pause') {
        items.push(big(t('act_resume'), 'play', function () { doPunch({ action: 'resume' }, 'resume'); }, 'tg-btn--go'));
        items.push(small(t('act_end'), 'stop', function () { confirmEnd(); }));
      }
      if (st.state !== 'none' && st.state !== 'done') { items.push(h('button', { type: 'button', 'class': 'tg-link', onclick: openScanner }, ui.icon('qr', 18), t('scan_qr'))); }
      ui.mount(box, items);
      if (S.busy) { box.classList.add('is-busy'); } else { box.classList.remove('is-busy'); }
    }

    function confirmEnd() {
      ui.confirm(t('confirm_end_t'), t('confirm_end_p'), t('act_end_do'), t('cancel'), false).then(function (ok) { if (ok) { doPunch({ action: 'out' }, 'out'); } });
    }

    function tick() {
      if (!S.status || !centerEl) { return; }
      var st = S.status, now = Date.now();
      var net = core.liveNet(st.netToday, st.state, S.fetchedAt, now);
      var weekNet = core.liveNet(st.netWeek, st.state, S.fetchedAt, now);
      var wt = st.weekTarget || 2400;
      var todayLvl = core.limitLevel(net, 600, 570), weekLvl = weekNet > 2880 ? 'bad' : (weekNet >= wt ? 'ok' : 'ok');
      TG.charts.updateRing(ringSvg, [core.ratio(net, 600), core.ratio(weekNet, wt)], [todayLvl, weekLvl === 'bad' ? 'bad' : 'ok']);
      centerEl.state.textContent = t('state_' + st.state);
      centerEl.state.className = 'tg-ringc__state tg-st-' + st.state;
      if (st.state === 'working' || st.state === 'pause') {
        var sec = st.since ? Math.max(0, (now - Date.parse(st.since)) / 1000) : 0;
        centerEl.big.textContent = st.since ? core.fmtStopwatch(sec) : core.fmtClockDur(net);
        centerEl.small.textContent = st.state === 'working' ? t('since_block', { time: core.isoToLocalClock(st.since) }) : t('pause_since', { time: core.isoToLocalClock(st.since) });
      } else {
        centerEl.big.textContent = core.fmtClockDur(net) + ' h';
        centerEl.small.textContent = st.state === 'done' ? t('done_today') : t('not_started');
      }
      if (subEls.go) {
        subEls.go.textContent = st.state === 'working' ? t('go_in_since', { time: core.isoToLocalClock(st.since) }) : st.state === 'pause' ? t('go_pause_since', { time: core.isoToLocalClock(st.since) }) : st.state === 'done' ? t('go_done') : t('go_none');
        subEls.go.className = 'tg-goline tg-goline--' + st.state;
      }
      subEls.today.textContent = core.fmtHM(net);
      subEls.week.textContent = core.fmtClockDur(weekNet) + ' / ' + core.fmtClockDur(wt) + ' h';
      var left = 600 - net;
      subEls.limit.className = 'tg-limit tg-limit--' + todayLvl;
      subEls.limit.textContent = (st.state === 'working' || net > 0) ? (left >= 0 ? t('limit_left', { time: core.fmtHM(left) }) : t('limit_over', { time: core.fmtHM(-left) })) : '';
    }

    function render() {
      ui.clear(host);
      if (S.scanning) { host.appendChild(scannerCard()); return; }
      if (S.error && !S.error.soft && !S.status) { host.appendChild(errorCard()); addInstall(); renderFoot(); return; }
      if (S.loading && !S.status) { host.appendChild(h('section', { 'class': 'tg-card tg-skeleton', 'aria-busy': 'true', 'aria-label': t('loading') }, h('div', { 'class': 'tg-skeleton__ring' }), h('div', { 'class': 'tg-skeleton__line' }), h('div', { 'class': 'tg-skeleton__btn' }))); return; }
      if (!S.status) { host.appendChild(S.error ? errorCard() : h('p', null, t('loading'))); return; }
      if (S.error) { host.appendChild(errorBanner()); }
      host.appendChild(statusCard());
      addInstall();
      renderActions(); tick(); renderFoot();
    }
    function addInstall() {
      var c = (isGo && TG.install) ? TG.install.card({ dismissable: true }) : null;
      if (c) { host.appendChild(c); }
    }
    function errorBanner() {
      var e = S.error, map = { expired: ['err_expired_t', 'err_expired_p'], rate: ['err_rate_t', 'err_rate_p'], toosoon: ['err_soon_t', 'err_soon_p'], future: ['err_future_t', 'err_future_p'], needcode: ['err_needcode_t', 'err_needcode_p'], forbidden: ['err_forbidden_t', 'err_forbidden_p'], transition: ['err_transition_t', 'err_transition_p'], offline: ['err_offline_t', 'err_offline_p'], generic: ['err_generic_t', 'err_generic_p'] };
      var m = map[e.kind] || map.generic;
      var detail = (e.err && e.err.message && e.err.message !== e.err.code && (e.kind === 'generic' || e.kind === 'transition')) ? e.err.message : t(m[1]);
      return h('div', { 'class': 'tg-banner tg-banner--err', role: 'alert' }, ui.icon('warn', 20), h('div', null, h('b', null, t(m[0])), h('div', null, detail),
        e.kind === 'expired' || e.kind === 'needcode' ? h('button', { type: 'button', 'class': 'tg-btn tg-btn--sm', onclick: openScanner }, ui.icon('camera', 16), t('scan_again')) : null));
    }
    function renderFoot() {
      var sound = h('button', { type: 'button', 'class': 'tg-link', 'aria-pressed': String(soundOn()), onclick: function () { api.store('set', SOUND_KEY, soundOn() ? '0' : '1'); if (soundOn()) { ui.beep('in'); } renderFoot(); } }, ui.icon('bell', 16), soundOn() ? t('sound_on') : t('sound_off'));
      ui.mount(foot, [sound, h('a', { 'class': 'tg-link', href: api.page('/') }, ui.icon('home', 16), isGo ? t('go_all_reports') : t('to_app'))]);
    }

    /* ---------- Start ---------- */
    render();
    TG.app.registerSW();
    var prior = code ? api.store('get', 'tg.done.' + code) : null;
    refresh(false).then(function () {
      if (code && !prior && S.status && !S.error) {
        // Scan = Intention: automatisch stempeln (einmal je Code)
        return doPunch({ code: code }, 'in');
      }
      if (prior && S.status) { try { var r = JSON.parse(prior); if (Date.now() - r.ts < 120000) { S.result = r; render(); } } catch (e) { /* ignore */ } }
      if (!code && S.status && !S.error) { /* ohne Code: Kamera-Hinweis */ }
    });
    root.setInterval(function () { if (!S.scanning) { tick(); } }, 1000);
    root.setInterval(function () { if (!S.scanning && !S.busy && S.online && !root.document.hidden) { refresh(true); } }, 60000);
    root.document.addEventListener('visibilitychange', function () { if (!root.document.hidden && !S.scanning && !S.busy) { refresh(true); } });
  }

  /* ================= KOPPLUNGS-SEITE ================= */
  function bootPair(el) {
    var token = el.getAttribute('data-token') || '';
    var host = h('main', { 'class': 'tg-pg__main' });
    var foot = h('footer', { 'class': 'tg-pg__foot' });
    el.appendChild(h('div', { 'class': 'tg-pg' }, header(el), host, foot));
    TG.app.registerSW();
    root.document.title = t('pair_title') + ' · ' + t('app_name');
    var S = { step: 'label', busy: false, err: null, name: '', scanning: false };
    var deviceGuess = core.deviceLabelDefault(root.navigator.userAgent) || t('pair_device_generic');
    var existing = api.getDevice();

    function render() {
      ui.clear(host); ui.clear(foot);
      if (S.step === 'label') { host.appendChild(labelCard()); }
      else if (S.step === 'done') { host.appendChild(S.scanning ? scanCard() : doneCard()); }
      else { host.appendChild(failCard()); }
    }
    function labelCard() {
      var input = h('input', { type: 'text', maxlength: '60', autocomplete: 'off', value: api.getDeviceName() || deviceGuess, 'class': 'tg-input tg-input--lg' });
      var errEl = h('div', { 'class': 'tg-formerr', role: 'alert', hidden: '' });
      var go = h('button', { type: 'button', 'class': 'tg-btn tg-btn--primary tg-btn--lg' }, ui.icon('phone', 20), t('pair_go'));
      var run = function () {
        var label = core.clampStr(input.value.trim(), 60) || deviceGuess;
        go.disabled = true; errEl.setAttribute('hidden', '');
        api.pairRedeem(token, label).then(function (res) {
          var dt = res && (res.deviceToken || res.device_token);
          if (!dt) { throw new api.ApiError(500, 'bad_response', t('err_generic_p')); }
          api.setDevice(dt); api.setDeviceName(label);
          api.store('set', 'tg.pairedName', String((res && res.name) || ''));
          api.store('set', 'tg.paired.' + token.slice(0, 12), '1');
          S.name = String((res && res.name) || ''); S.step = 'done'; ui.vibrate([30, 40, 30]); render();
        }, function (err) {
          go.disabled = false;
          if (api.store('get', 'tg.paired.' + token.slice(0, 12)) && api.getDevice()) { S.step = 'done'; S.name = api.store('get', 'tg.pairedName') || ''; render(); return; }
          var kind = errKind(err);
          if (err && err.code === 'rate_limited') { kind = 'rate'; }
          if (kind === 'offline') { errEl.textContent = t('err_offline_p'); errEl.removeAttribute('hidden'); return; }
          if (kind === 'rate') { errEl.textContent = t('err_rate_p'); errEl.removeAttribute('hidden'); return; }
          S.step = 'fail'; S.err = err; render();
        });
      };
      go.addEventListener('click', run);
      input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { run(); } });
      if (!token) { S.step = 'fail'; return failCard(); }
      return h('section', { 'class': 'tg-card tg-state' },
        h('div', { 'class': 'tg-state__icon tg-state__icon--brand' }, ui.icon('qr', 34)),
        h('h1', { 'class': 'tg-state__title' }, t('pair_title')), h('p', { 'class': 'tg-state__text' }, t('pair_text')),
        existing ? h('p', { 'class': 'tg-note' }, t('pair_replace')) : null,
        h('div', { 'class': 'tg-field tg-field--center' }, h('label', { 'for': 'tg-label' }, t('pair_label')), (input.id = 'tg-label', input)),
        errEl, h('div', { 'class': 'tg-state__actions' }, go),
        h('p', { 'class': 'tg-note' }, t('pair_privacy')));
    }
    function doneCard() {
      return h('section', { 'class': 'tg-card tg-state tg-state--ok' },
        h('div', { 'class': 'tg-success' }, ui.s('svg', { viewBox: '0 0 52 52', width: 84, height: 84, 'aria-hidden': 'true' }, ui.s('circle', { cx: 26, cy: 26, r: 24, fill: 'none', 'class': 'tg-success__c' }), ui.s('path', { d: 'M14 27l8 8 16-17', fill: 'none', 'class': 'tg-success__p' }))),
        h('h1', { 'class': 'tg-state__title' }, S.name ? t('pair_done_hi', { name: S.name }) : t('pair_done')),
        h('p', { 'class': 'tg-state__text' }, t('pair_done_p')),
        h('div', { 'class': 'tg-state__actions' }, h('button', { type: 'button', 'class': 'tg-btn tg-btn--primary tg-btn--lg', onclick: function () { S.scanning = true; render(); } }, ui.icon('camera', 20), t('pair_scan_now')),
          h('a', { 'class': 'tg-btn', href: api.page('/go') }, ui.icon('home', 18), t('go_open'))),
        TG.install ? TG.install.card({ dismissable: false }) : null);
    }
    function scanCard() {
      var sc = TG.scanner.create({ onText: function (text) {
        var r = core.parseScanText(text);
        if (!r || r.kind !== 'punch') { return t('scan_unknown'); }
        ui.vibrate(20); root.location.assign(api.page('/s/' + encodeURIComponent(r.code))); return true;
      } });
      root.setTimeout(function () { sc.start(); }, 30);
      return h('section', { 'class': 'tg-card tg-scancard' }, h('h1', { 'class': 'tg-state__title' }, t('pair_scan_now')), h('p', { 'class': 'tg-state__text' }, t('pair_scan_hint')), sc.el,
        h('div', { 'class': 'tg-state__actions' }, h('button', { type: 'button', 'class': 'tg-btn', onclick: function () { sc.stop(); S.scanning = false; render(); } }, ui.icon('close', 18), t('later'))));
    }
    function failCard() {
      var already = !!api.getDevice();
      return h('section', { 'class': 'tg-card tg-state tg-state--err', role: 'alert' },
        h('div', { 'class': 'tg-state__icon' }, ui.icon('warn', 34)),
        h('h1', { 'class': 'tg-state__title' }, t('pair_fail_t')), h('p', { 'class': 'tg-state__text' }, t('pair_fail_p')),
        h('div', { 'class': 'tg-state__actions' },
          already ? h('button', { type: 'button', 'class': 'tg-btn tg-btn--primary tg-btn--lg', onclick: function () { S.step = 'done'; S.name = api.store('get', 'tg.pairedName') || ''; render(); } }, ui.icon('check', 20), t('pair_already')) : null,
          h('a', { 'class': 'tg-btn' + (already ? '' : ' tg-btn--primary tg-btn--lg'), href: api.page('/#/devices') }, t('pair_new_code')),
          already ? h('a', { 'class': 'tg-btn', href: api.page('/go') }, ui.icon('home', 18), t('go_open')) : null));
    }
    render();
  }

  function boot() {
    var el = root.document.getElementById('tg-root');
    if (!el) { return; }
    TG.i18n.setLang(TG.i18n.detect(null));
    root.document.documentElement.lang = TG.i18n.lang();
    var page = el.getAttribute('data-page');
    if (page === 'scan') { bootScan(el); } else if (page === 'pair') { bootPair(el); }
  }
  TG.app.ready(boot);
})(typeof self !== 'undefined' ? self : this);
