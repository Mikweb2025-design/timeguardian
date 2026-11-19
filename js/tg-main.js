/* TimeGuardian – Hauptseite: Shell, Hash-Router, gemeinsame Bausteine. Ansichten registrieren sich in TG.views. */
(function (root) {
  'use strict';
  var TG = root.TG = root.TG || {};
  var core = TG.core, ui = TG.ui, api = TG.api, h = ui.h;
  var t = function (k, p) { return TG.i18n.t(k, p); };
  var doc = root.document;

  var S = { me: null, fetchedAt: 0, skew: 0, route: null, leave: [] };
  var views = TG.views = TG.views || {};

  var NAV = [
    ['today', 'nav_today', 'home'], ['week', 'nav_week', 'chart'], ['month', 'nav_month', 'calendar'], ['account', 'nav_account', 'clock'],
    ['absences', 'nav_absences', 'sun'], ['corrections', 'nav_corrections', 'edit'], ['team', 'nav_team', 'users', 'chef'],
    ['devices', 'nav_devices', 'phone'], ['config', 'nav_config', 'tune', 'chef'], ['settings', 'nav_settings', 'gear'], ['help', 'nav_help', 'help']
  ];

  /* ---------- Formatierung ---------- */
  var F = {
    lang: function () { return TG.i18n.lang(); },
    h: function (min) { return core.fmtH(min, TG.i18n.lang()); },
    hm: function (min) { return core.fmtHM(min); },
    sgn: function (min) { return core.fmtSignedHM(min); },
    day: function (d) { return core.fmtDayShort(d, TG.i18n.lang()); },
    dayLong: function (d) { return core.fmtDayLong(d, TG.i18n.lang()); },
    clock: function (iso) { return core.clockTz(iso, S.me && S.me.timezone); },
    today: function () { return (S.me && S.me.today) || core.localDay(); },
    dayNames: function () { return t('daynames').split(','); }
  };

  /* ---------- Bausteine ---------- */
  function kpi(label, value, sub, tone) {
    return h('div', { 'class': 'tg-kpi' + (tone ? ' tg-kpi--' + tone : '') }, h('div', { 'class': 'tg-kpi__v' }, value), h('div', { 'class': 'tg-kpi__l' }, label), sub ? h('div', { 'class': 'tg-kpi__s' }, sub) : null);
  }
  function card(title, body, actions, cls) {
    return h('section', { 'class': 'tg-card tg-section' + (cls ? ' ' + cls : '') },
      (title || actions) ? h('header', { 'class': 'tg-section__head' }, title ? h('h2', null, title) : h('span'), actions ? h('div', { 'class': 'tg-section__actions' }, actions) : null) : null, body);
  }
  function pill(text, tone) { return h('span', { 'class': 'tg-pill' + (tone ? ' tg-pill--' + tone : '') }, text); }
  function empty(text, icon) { return h('div', { 'class': 'tg-empty' }, ui.icon(icon || 'info', 28), h('p', null, text)); }
  function loading() { return h('div', { 'class': 'tg-loading', 'aria-busy': 'true' }, h('div', { 'class': 'tg-spin' }), h('span', null, t('loading'))); }
  function errBox(err, retry) {
    var msg = (err && err.code === 'offline') ? t('err_offline_p') : (err && err.message && err.message !== err.code ? err.message : t('err_generic_p'));
    return h('div', { 'class': 'tg-banner tg-banner--err', role: 'alert' }, ui.icon('warn', 20), h('div', null, h('b', null, t('err_generic_t')), h('div', null, msg),
      retry ? h('button', { type: 'button', 'class': 'tg-btn tg-btn--sm', onclick: retry }, ui.icon('refresh', 16), t('retry')) : null));
  }
  function btn(label, icon, fn, cls) {
    return h('button', { type: 'button', 'class': 'tg-btn' + (cls ? ' ' + cls : ''), onclick: fn }, icon ? ui.icon(icon, 18) : null, label);
  }
  function periodNav(label, onPrev, onNext, onToday, sub) {
    return h('div', { 'class': 'tg-pnav' },
      h('button', { type: 'button', 'class': 'tg-iconbtn', 'aria-label': t('prev'), onclick: onPrev }, ui.icon('arrowL', 22)),
      h('div', { 'class': 'tg-pnav__label' }, h('b', null, label), sub ? h('span', null, sub) : null),
      h('button', { type: 'button', 'class': 'tg-iconbtn', 'aria-label': t('next'), onclick: onNext }, ui.icon('arrowR', 22)),
      onToday ? h('button', { type: 'button', 'class': 'tg-btn tg-btn--sm', onclick: onToday }, t('today_btn')) : null);
  }
  function violationList(list, withName) {
    if (!list || !list.length) { return h('div', { 'class': 'tg-ok' }, ui.icon('check', 20), h('span', null, t('no_violations'))); }
    var ul = h('ul', { 'class': 'tg-viol' });
    list.forEach(function (v) {
      ul.appendChild(h('li', { 'class': 'tg-viol__i tg-viol__i--' + (v.level === 'verstoss' ? 'bad' : 'hint') },
        h('span', { 'class': 'tg-viol__dot', 'aria-hidden': 'true' }),
        h('span', { 'class': 'tg-viol__d' }, F.day(v.day)),
        h('span', { 'class': 'tg-viol__t' }, (withName && v.name ? v.name + ' · ' : '') + (v.text || t('rule_' + v.rule))),
        h('span', { 'class': 'tg-viol__lv sr-only' }, v.level === 'verstoss' ? t('violation') : t('hint'))));
    });
    return ul;
  }
  function ruleText(r) {
    var v = r.value;
    return t('rule_' + r.rule, { h: r.rule === 'REST11' ? core.fmtHM(v) : core.fmtH(v, TG.i18n.lang()), p: String(v) });
  }

  /* ---------- Manuelle Buchung / Bearbeiten (mit Regelvorschau) ---------- */
  var FIELD_MSG = { day: 'f_day', day_future: 'f_day_future', day_old: 'f_day_old', start: 'f_time', end: 'f_time', order: 'f_order', future: 'f_future', pause: 'f_pause', pause_long: 'f_pause', project: 'f_project', reason: 'f_reason', entryId: 'f_entry' };
  function valMsg(err) {
    if (err && err.code === 'validation' && err.data && Array.isArray(err.data.fields) && err.data.fields.length) {
      return err.data.fields.map(function (f) { return t(FIELD_MSG[f] || 'f_generic'); }).filter(function (x, i, a) { return a.indexOf(x) === i; }).join(' ');
    }
    if (err && err.code === 'overlap') { return t('err_overlap'); }
    if (err && err.code === 'locked') { return t('err_locked'); }
    return (err && err.message && err.message !== err.code) ? err.message : t('err_generic_p');
  }
  function entryDialog(entry, opts) {
    opts = opts || {};
    var me = S.me, today = F.today();
    var isEdit = !!entry;
    var dayIn = h('input', { type: 'date', value: entry ? entry.day : (opts.day || today), max: today, min: core.addDays(today, -31), 'class': 'tg-input' });
    var startIn = h('input', { type: 'time', value: entry ? entry.startHM : '', 'class': 'tg-input', step: '60' });
    var endIn = h('input', { type: 'time', value: entry && entry.endHM ? entry.endHM : '', 'class': 'tg-input', step: '60' });
    var pauseIn = h('input', { type: 'number', min: '0', max: '600', step: '5', value: entry ? String(entry.pauseMin) : '0', 'class': 'tg-input', inputmode: 'numeric' });
    var projList = h('datalist', { id: 'tg-proj-list' }, (me.projects || []).map(function (p) { return h('option', { value: p }); }));
    var projIn = h('input', { type: 'text', maxlength: '64', value: entry && entry.project ? entry.project : '', list: 'tg-proj-list', 'class': 'tg-input', autocomplete: 'off' });
    var noteIn = h('input', { type: 'text', maxlength: '255', value: entry && entry.note ? entry.note : '', 'class': 'tg-input', autocomplete: 'off' });
    var prev = h('div', { 'class': 'tg-preview', 'aria-live': 'polite' });
    var errEl = h('div', { 'class': 'tg-formerr', role: 'alert', hidden: '' });
    var cache = {};
    function load(day) {
      if (cache[day]) { return Promise.resolve(cache[day]); }
      return api.entries(core.addDays(day, -1), day).then(function (r) { cache[day] = TG.model.list(r, 'entries'); return cache[day]; }, function () { return []; });
    }
    function mins(hm) { return core.parseClock(hm); }
    var seq = 0;
    function update() {
      var day = dayIn.value, s = mins(startIn.value), e = mins(endIn.value), my = ++seq;
      ui.clear(prev);
      if (!day || s === null || e === null) { prev.appendChild(h('span', { 'class': 'tg-muted' }, t('preview_hint'))); return; }
      if (e <= s) { prev.appendChild(h('div', { 'class': 'tg-viol__i tg-viol__i--bad' }, h('span', { 'class': 'tg-viol__dot' }), h('span', null, t('f_order')))); return; }
      load(day).then(function (list) {
        if (my !== seq) { return; }
        ui.clear(prev);
        var same = list.filter(function (x) { return x.day === day && (!entry || x.id !== entry.id) && x.endHM; }).map(function (x) { return { startMin: mins(x.startHM), endMin: mins(x.endHM), pauseMin: x.pauseMin || 0 }; });
        var cand = { startMin: s, endMin: e, pauseMin: parseInt(pauseIn.value, 10) || 0 };
        var prevDay = core.addDays(day, -1);
        var pe = list.filter(function (x) { return x.day === prevDay && x.endHM; }).map(function (x) { return mins(x.endHM); });
        var prevEnd = pe.length ? Math.max.apply(null, pe) - 1440 : null;
        var res = core.previewRules(same.concat([cand]), prevEnd, core.dow(day));
        if (core.blocksOverlap(cand, same)) { prev.appendChild(h('div', { 'class': 'tg-viol__i tg-viol__i--bad' }, h('span', { 'class': 'tg-viol__dot' }), h('span', null, t('err_overlap')))); }
        if (!res.length) { prev.appendChild(h('div', { 'class': 'tg-ok' }, ui.icon('check', 18), h('span', null, t('preview_ok')))); return; }
        res.forEach(function (r) { prev.appendChild(h('div', { 'class': 'tg-viol__i tg-viol__i--' + (r.level === 'verstoss' ? 'bad' : 'hint') }, h('span', { 'class': 'tg-viol__dot' }), h('span', null, ruleText(r)))); });
      });
    }
    [dayIn, startIn, endIn, pauseIn].forEach(function (i) { i.addEventListener('input', ui.debounce(update, 120)); i.addEventListener('change', update); });
    var body = h('div', { 'class': 'tg-form' },
      ui.field(t('f_day_l'), dayIn), h('div', { 'class': 'tg-row2' }, ui.field(t('f_start'), startIn), ui.field(t('f_end'), endIn)),
      ui.field(t('f_pause_l'), pauseIn, t('f_pause_h')), ui.field(t('f_project_l'), projIn), projList, ui.field(t('f_note_l'), noteIn),
      h('div', { 'class': 'tg-preview-wrap' }, h('div', { 'class': 'tg-preview-t' }, t('preview_t')), prev), errEl);
    update();
    var save = function (boxEl, close) {
      var b = { day: dayIn.value, start: startIn.value, end: endIn.value, pauseMin: parseInt(pauseIn.value, 10) || 0, project: projIn.value.trim() || null, note: noteIn.value.trim() || null };
      errEl.setAttribute('hidden', '');
      var sb = boxEl.querySelector('.tg-btn--primary'); sb.disabled = true;
      (isEdit ? api.updateEntry(entry.id, b) : api.addEntry(b)).then(function (res) { close(res); }, function (err) { sb.disabled = false; errEl.textContent = valMsg(err); errEl.removeAttribute('hidden'); });
    };
    return ui.modal(isEdit ? t('entry_edit') : t('entry_new'), body, [{ label: t('cancel'), value: null }, { label: t('save'), primary: true, action: save }]);
  }

  function correctionDialog(entry) {
    var me = S.me;
    var startIn = h('input', { type: 'time', value: entry.startHM || '', 'class': 'tg-input' });
    var endIn = h('input', { type: 'time', value: entry.endHM || '', 'class': 'tg-input' });
    var pauseIn = h('input', { type: 'number', min: '0', max: '600', step: '5', value: String(entry.pauseMin || 0), 'class': 'tg-input' });
    var reason = h('textarea', { rows: '3', maxlength: '500', 'class': 'tg-input', placeholder: t('corr_reason_ph') });
    var errEl = h('div', { 'class': 'tg-formerr', role: 'alert', hidden: '' });
    var body = h('div', { 'class': 'tg-form' }, h('p', { 'class': 'tg-muted' }, F.dayLong(entry.day) + ' · ' + (entry.startHM || '') + '–' + (entry.endHM || '…') + ' · ' + t('pause_short') + ' ' + (entry.pauseMin || 0) + ' min'),
      h('div', { 'class': 'tg-row2' }, ui.field(t('f_start'), startIn), ui.field(t('f_end'), endIn)), ui.field(t('f_pause_l'), pauseIn), ui.field(t('corr_reason'), reason), errEl);
    return ui.modal(t('corr_new'), body, [{ label: t('cancel'), value: null }, { label: t('corr_send'), primary: true, action: function (boxEl, close) {
      if (reason.value.trim().length < 3) { errEl.textContent = t('f_reason'); errEl.removeAttribute('hidden'); return; }
      var sb = boxEl.querySelector('.tg-btn--primary'); sb.disabled = true;
      api.addCorrection({ entryId: entry.id, start: startIn.value || undefined, end: endIn.value || undefined, pauseMin: parseInt(pauseIn.value, 10) || 0, reason: reason.value.trim() }).then(function (res) { close(res); }, function (err) { sb.disabled = false; errEl.textContent = valMsg(err); errEl.removeAttribute('hidden'); });
    } }]);
  }

  /* ---------- Scan-Dialog (Einstempeln am Rechner/Telefon mit Session) ---------- */
  function scanDialog() {
    return new Promise(function (resolve) {
      var finished = false, sc, m;
      sc = TG.scanner.create({ onText: function (text) {
        var r = core.parseScanText(text);
        if (!r || r.kind !== 'punch') { return t('scan_unknown'); }
        finished = true;
        api.req('POST', '/api/punch', { body: { code: r.code }, device: 'never' }).then(function (res) { resolve({ ok: true, res: res }); }, function (err) { resolve({ ok: false, err: err }); });
        if (m) { m.close(null); }
        return true;
      } });
      var body = h('div', null, sc.el);
      m = ui.modal(t('scan_title'), body, [{ label: t('cancel'), value: null }]);
      m.then(function () { sc.stop(); if (!finished) { resolve(null); } });
      root.setTimeout(function () { sc.start(); }, 50);
    });
  }

  /* ---------- Shell ---------- */
  var shell = {};
  function buildShell(el) {
    shell.nav = h('nav', { 'class': 'tg-nav', 'aria-label': t('app_name') });
    shell.title = h('h1', { 'class': 'tg-view-title', tabindex: '-1' });
    shell.actions = h('div', { 'class': 'tg-view-actions' });
    shell.body = h('div', { 'class': 'tg-view-body', id: 'tg-view' });
    shell.banner = h('div', { 'class': 'tg-banner tg-banner--warn tg-offline', role: 'status', hidden: '' }, ui.icon('offline', 18), h('span', null, t('offline_banner')));
    shell.print = h('div', { 'class': 'tg-printhead' });
    var main = h('main', { 'class': 'tg-main', id: 'tg-main' }, shell.banner, h('header', { 'class': 'tg-view-head' }, shell.title, shell.actions), shell.print, shell.body);
    ui.mount(el, h('div', { 'class': 'tg-app' }, shell.nav, main));
    shell.main = main;
  }
  function renderNav() {
    var me = S.me, cur = S.route && S.route.name;
    var ul = h('ul', { 'class': 'tg-nav__list' });
    NAV.forEach(function (n) {
      if (n[3] === 'chef' && !me.isChef) { return; }
      var badge = 0;
      if (n[0] === 'absences') { badge = me.pending.absences || 0; }
      if (n[0] === 'corrections') { badge = me.pending.corrections || 0; }
      if (n[0] === 'team') { badge = (me.pending.absences || 0) + (me.pending.corrections || 0); }
      ul.appendChild(h('li', null, h('a', { href: '#/' + n[0], title: t('tip_' + n[0]), 'class': 'tg-nav__a' + (cur === n[0] ? ' is-active' : ''), 'aria-current': cur === n[0] ? 'page' : null },
        ui.icon(n[2], 20), h('span', { 'class': 'tg-nav__t' }, t(n[1])), badge ? h('span', { 'class': 'tg-badge', 'aria-label': t('pending_n', { n: badge }) }, String(badge)) : null)));
    });
    var foot = [];
    if (me.isChef || me.isKiosk) { foot.push(h('a', { href: api.page('/kiosk'), title: t('tip_kiosk'), 'class': 'tg-nav__a tg-nav__a--sub' }, ui.icon('qr', 20), h('span', { 'class': 'tg-nav__t' }, t('open_kiosk')))); }
    ui.mount(shell.nav, [ul, foot.length ? h('div', { 'class': 'tg-nav__foot' }, foot) : null, h('div', { 'class': 'tg-nav__user' }, h('span', { 'class': 'tg-avatar', 'aria-hidden': 'true' }, core.initials(me.name)), h('span', { 'class': 'tg-nav__name' }, me.name))]);
  }

  function parseHash() {
    var raw = (root.location.hash || '').replace(/^#\/?/, '');
    var q = raw.indexOf('?'), name = q >= 0 ? raw.slice(0, q) : raw, params = {};
    if (q >= 0) { raw.slice(q + 1).split('&').forEach(function (kv) { var p = kv.split('='); if (p[0]) { params[decodeURIComponent(p[0])] = decodeURIComponent(p[1] || ''); } }); }
    if (!name || !views[name]) { name = 'today'; }
    return { name: name, params: params };
  }
  function go(name, params) {
    var q = params ? '?' + Object.keys(params).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); }).join('&') : '';
    root.location.hash = '#/' + name + q;
  }
  function leave() { S.leave.forEach(function (f) { try { f(); } catch (e) { /* ignore */ } }); S.leave = []; }
  var renderToken = 0;
  function renderRoute() {
    leave();
    var r = parseHash(); S.route = r;
    var v = views[r.name], tok = ++renderToken;
    shell.title.textContent = t(v.title);
    root.document.title = t(v.title) + ' · ' + t('app_name');
    ui.clear(shell.actions); ui.clear(shell.print); ui.clear(shell.body);
    if (v.chef && !S.me.isChef) { shell.body.appendChild(h('div', { 'class': 'tg-card tg-state' }, ui.icon('warn', 30), h('h2', null, t('err_forbidden_t')), h('p', null, t('team_only')))); renderNav(); return; }
    renderNav();
    var ctx = {
      S: S, me: S.me, params: r.params, body: shell.body, go: go, F: F, isCurrent: function () { return tok === renderToken; },
      setActions: function (nodes) { ui.mount(shell.actions, nodes); }, every: function (ms, fn) { var id = root.setInterval(function () { if (tok === renderToken && !doc.hidden) { fn(); } }, ms); S.leave.push(function () { root.clearInterval(id); }); },
      onLeave: function (fn) { S.leave.push(fn); }, setPrint: function (nodes) { ui.mount(shell.print, nodes); }, reload: function () { renderRoute(); }, reloadMe: reloadMe
    };
    shell.body.appendChild(loading());
    var out;
    try { out = v.render(ctx); } catch (e) { out = Promise.reject(e); }
    Promise.resolve(out).then(function (node) {
      if (tok !== renderToken) { return; }
      if (node) { ui.mount(shell.body, node); }
      shell.title.focus({ preventScroll: true });
    }, function (err) {
      if (tok !== renderToken) { return; }
      ui.mount(shell.body, errBox(err, function () { renderRoute(); }));
    });
    var scroller = shell.main; if (scroller && scroller.scrollTo) { scroller.scrollTo(0, 0); }
  }

  function applyMe(raw) {
    S.me = TG.model.me(raw); S.fetchedAt = Date.now(); S.skew = core.skewOf(S.me.status && S.me.status.serverNow, S.fetchedAt);
    var lang = S.me.conf.lang || S.me.lang;
    if (lang && lang !== TG.i18n.lang() && TG.i18n.has(lang)) { TG.i18n.setLang(lang, true); }
    doc.documentElement.lang = TG.i18n.lang();
    return S.me;
  }
  function reloadMe() { return api.me().then(applyMe); }

  function boot() {
    var el = doc.getElementById('tg-root');
    if (!el) { return; }
    TG.i18n.setLang(TG.i18n.detect(null));
    doc.documentElement.lang = TG.i18n.lang();
    el.textContent = '';
    el.appendChild(loading());
    TG.app.registerSW();
    api.me().then(function (raw) {
      applyMe(raw);
      buildShell(el);
      TG.app.watchOnline(function (on) { if (on) { shell.banner.setAttribute('hidden', ''); } else { shell.banner.removeAttribute('hidden'); } });
      root.addEventListener('hashchange', renderRoute);
      root.addEventListener('beforeprint', function () { ui.clear(shell.print); shell.print.appendChild(h('div', { 'class': 'tg-printhead__in' }, h('b', null, S.me.name), h('span', null, ' · ' + t('app_name') + ' · ' + core.fmtDateTime(new Date().toISOString(), TG.i18n.lang(), S.me.timezone)))); });
      renderRoute();
      root.setInterval(function () { if (!doc.hidden && root.navigator.onLine !== false) { reloadMe().then(renderNav, function () { /* ignore */ }); } }, 120000);
    }, function (err) {
      var k = err && err.status;
      ui.mount(el, h('div', { 'class': 'tg-card tg-state tg-state--info' },
        h('div', { 'class': 'tg-state__icon' }, ui.icon(k === 401 ? 'phone' : 'warn', 34)),
        h('h1', { 'class': 'tg-state__title' }, k === 401 ? t('err_unpaired_t') : (k === 403 ? t('err_forbidden_t') : t('err_generic_t'))),
        h('p', { 'class': 'tg-state__text' }, k === 403 ? t('err_notteam_p') : (k === 401 ? t('err_session_p') : (err && err.message) || t('err_generic_p'))),
        h('div', { 'class': 'tg-state__actions' }, k === 401 ? h('a', { 'class': 'tg-btn tg-btn--primary tg-btn--lg', href: TG.app.loginUrl() }, t('err_login')) : btn(t('retry'), 'refresh', function () { root.location.reload(); }, 'tg-btn--primary tg-btn--lg'))));
    });
  }

  TG.main = { S: S, F: F, kpi: kpi, card: card, pill: pill, empty: empty, loading: loading, errBox: errBox, btn: btn, periodNav: periodNav, violationList: violationList,
    ruleText: ruleText, entryDialog: entryDialog, correctionDialog: correctionDialog, scanDialog: scanDialog, valMsg: valMsg, go: go, reloadMe: reloadMe, renderNav: renderNav, boot: boot };
  TG.app.ready(function () { if (doc.getElementById('tg-root') && doc.getElementById('tg-root').getAttribute('data-page') === 'main') { TG.main.boot(); } });
})(typeof self !== 'undefined' ? self : this);
