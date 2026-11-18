/* TimeGuardian – Ansichten Heute, Woche, Monat, Zeitkonto. */
(function (root) {
  'use strict';
  var TG = root.TG = root.TG || {};
  var core = TG.core, ui = TG.ui, api = TG.api, h = ui.h, M = TG.main, F = M.F;
  var t = function (k, p) { return TG.i18n.t(k, p); };
  var views = TG.views = TG.views || {};

  function sessionPunch(body) { return api.req('POST', '/api/punch', { body: body, device: 'never' }); }
  function table(headers, rows, cls) {
    var thead = h('thead', null, h('tr', null, headers.map(function (x) { return h('th', { scope: 'col', 'class': x[1] || '' }, x[0]); })));
    var tbody = h('tbody', null, rows.map(function (r) {
      return h('tr', { 'class': r.cls || '' }, r.cells.map(function (c, i) { return h('td', { 'data-label': headers[i][0], 'class': (headers[i][1] || '') + (c && c.cls ? ' ' + c.cls : '') }, c && c.node !== undefined ? c.node : c); }));
    }));
    return h('div', { 'class': 'tg-tablewrap' }, h('table', { 'class': 'tg-table ' + (cls || '') }, thead, tbody));
  }
  function dayFlag(d) {
    var v = d.violations || [];
    if (v.some(function (x) { return x.level === 'verstoss'; })) { return 'bad'; }
    return v.length ? 'hint' : '';
  }
  function kindLabel(k) { return t('kind_' + k); }

  /* Eintragszeile mit Aktionen */
  function entryLine(e, onChange) {
    var act = [];
    if (!e.running) {
      if (e.editable) {
        act.push(h('button', { type: 'button', 'class': 'tg-iconbtn', title: t('edit'), 'aria-label': t('edit'), onclick: function () { M.entryDialog(e).then(function (r) { if (r) { ui.toast(t('saved'), 'ok'); onChange(); } }); } }, ui.icon('edit', 18)));
        act.push(h('button', { type: 'button', 'class': 'tg-iconbtn tg-iconbtn--danger', title: t('delete'), 'aria-label': t('delete'), onclick: function () {
          ui.confirm(t('del_entry_t'), t('del_entry_p', { time: e.startHM + '–' + (e.endHM || '…') }), t('delete'), t('cancel'), true).then(function (ok) {
            if (ok) { api.deleteEntry(e.id).then(function () { ui.toast(t('deleted'), 'ok'); onChange(); }, function (err) { ui.toast(M.valMsg(err), 'err'); }); }
          });
        } }, ui.icon('trash', 18)));
      } else {
        act.push(h('button', { type: 'button', 'class': 'tg-btn tg-btn--sm', onclick: function () { M.correctionDialog(e).then(function (r) { if (r) { ui.toast(t('corr_sent'), 'ok'); onChange(); } }); } }, ui.icon('edit', 16), t('corr_ask')));
      }
    }
    return h('li', { 'class': 'tg-eline' + (e.running ? ' is-running' : '') },
      h('span', { 'class': 'tg-eline__t' }, e.startHM + '–' + (e.endHM || t('running'))),
      h('span', { 'class': 'tg-eline__m' }, F.hm(e.netMin) + (e.pauseMin ? ' · ' + t('pause_short') + ' ' + e.pauseMin + ' min' : '')),
      e.project ? M.pill(e.project) : null,
      e.source && e.source !== 'scan' ? h('span', { 'class': 'tg-eline__src' }, t('src_' + e.source)) : null,
      h('span', { 'class': 'tg-eline__a' }, act));
  }

  /* ================= HEUTE ================= */
  views.today = { title: 'nav_today', render: function (ctx) {
    return Promise.all([ctx.reloadMe(), api.setup().then(function (r) { return r; }, function () { return null; })]).then(function (r) { return buildToday(ctx, r[0], r[1]); });
  } };
  function buildToday(ctx, me, setup) {
    var st = function () { return TG.main.S.me.status; };
    var ts = me.todaySummary || {};
    var entries = ts.entries || [];
    var wrap = h('div', { 'class': 'tg-grid' });
    var ringSvg = TG.charts.ring({ size: 232, rings: [{ r: 100, width: 15, frac: 0, cls: 'tg-lv-ok', marks: [0.8] }, { r: 78, width: 9, frac: 0, cls: 'tg-lv-ok' }] });
    var stateEl = h('div', { 'class': 'tg-ringc__state' }), bigEl = h('div', { 'class': 'tg-ringc__big' }), smallEl = h('div', { 'class': 'tg-ringc__small' });
    var ringBox = h('div', { 'class': 'tg-ringc' }, ringSvg, h('div', { 'class': 'tg-ringc__center' }, stateEl, bigEl, smallEl));
    var legend = h('ul', { 'class': 'tg-legend' },
      h('li', null, h('i', { 'class': 'tg-dot tg-dot--a' }), t('legend_day')), h('li', null, h('i', { 'class': 'tg-dot tg-dot--b' }), t('legend_week')));

    function actionButtons() {
      var s = st(), mode = me.conf.mode, out = [];
      var run = function (body, msgKey) {
        return function () {
          sessionPunch(body).then(function () { ui.vibrate(30); ui.toast(t(msgKey), 'ok'); ctx.reload(); }, function (err) { ui.toast(err && err.message && err.code !== 'offline' ? err.message : t('err_offline_p'), 'err'); });
        };
      };
      if (s.state === 'none' || s.state === 'done') {
        out.push(M.btn(t('act_in_scan'), 'camera', function () {
          M.scanDialog().then(function (r) {
            if (!r) { return; }
            if (r.ok) { ui.vibrate(30); ui.toast(t('res_' + (r.res.action || 'in'), { time: F.clock(new Date().toISOString()) }), 'ok'); ctx.reload(); }
            else { ui.toast(r.err && r.err.code === 'invalid_code' ? t('err_expired_p') : (r.err && r.err.message) || t('err_generic_p'), 'err'); }
          });
        }, 'tg-btn--primary tg-btn--lg tg-btn--go'));
      } else if (s.state === 'working') {
        if (mode === 'pause') { out.push(M.btn(t('act_pause'), 'pause', run({ action: 'pause' }, 'res_pause'), 'tg-btn--primary tg-btn--lg tg-btn--warn')); out.push(M.btn(t('act_end'), 'stop', function () { ui.confirm(t('confirm_end_t'), t('confirm_end_p'), t('act_end_do'), t('cancel')).then(function (ok) { if (ok) { run({ action: 'out' }, 'res_out')(); } }); }, 'tg-btn--lg')); }
        else { out.push(M.btn(t('act_out'), 'stop', function () { ui.confirm(t('confirm_end_t'), t('confirm_end_p'), t('act_end_do'), t('cancel')).then(function (ok) { if (ok) { run({ action: 'out' }, 'res_out')(); } }); }, 'tg-btn--primary tg-btn--lg tg-btn--stop')); }
      } else if (s.state === 'pause') {
        out.push(M.btn(t('act_resume'), 'play', run({ action: 'resume' }, 'res_resume'), 'tg-btn--primary tg-btn--lg tg-btn--go'));
        out.push(M.btn(t('act_end'), 'stop', run({ action: 'out' }, 'res_out'), 'tg-btn--lg'));
      }
      return out;
    }
    var kNet = h('div'), kSoll = h('div'), kWeek = h('div'), kBal = h('div');
    function tick() {
      var cur = TG.main.S.me, s = cur.status, now = Date.now(), ft = TG.main.S.fetchedAt;
      var net = core.liveNet((cur.todaySummary || {}).netMin || 0, s.state, ft, now);
      var wk = core.liveNet(cur.week.netMin || 0, s.state, ft, now), target = (cur.week.targetMin || cur.conf.weeklyMin || 2400);
      TG.charts.updateRing(ringSvg, [core.ratio(net, 600), core.ratio(wk, target)], [core.limitLevel(net, 600, 570), wk > (cur.week.limitMin || 2880) ? 'bad' : 'ok']);
      stateEl.textContent = t('state_' + s.state); stateEl.className = 'tg-ringc__state tg-st-' + s.state;
      if (s.state === 'working' || s.state === 'pause') {
        var ref = s.state === 'pause' ? s.pauseSince : s.since;
        bigEl.textContent = ref ? core.fmtStopwatch((now - Date.parse(ref)) / 1000) : core.fmtClockDur(net);
        smallEl.textContent = (s.state === 'pause' ? t('pause_since', { time: F.clock(s.pauseSince) }) : t('since_block', { time: F.clock(s.since) }));
      } else { bigEl.textContent = core.fmtClockDur(net) + ' h'; smallEl.textContent = s.state === 'done' ? t('done_today') : t('not_started'); }
      var soll = (cur.todaySummary || {}).sollMin || 0;
      ui.mount(kNet, kpiNode(t('net_today'), F.hm(net), net > 600 ? t('limit_over', { time: F.hm(net - 600) }) : t('limit_left', { time: F.hm(600 - net) }), net > 600 ? 'bad' : net >= 570 ? 'warn' : ''));
      ui.mount(kSoll, kpiNode(t('soll_today'), soll ? F.hm(soll) : '–', soll ? (net >= soll ? t('soll_done', { time: F.hm(net - soll) }) : t('soll_left', { time: F.hm(soll - net) })) : t('no_target_today'), ''));
      ui.mount(kWeek, kpiNode(t('week'), core.fmtClockDur(wk) + ' / ' + core.fmtClockDur(target) + ' h', t('week_limit_hint'), wk > (cur.week.limitMin || 2880) ? 'bad' : ''));
      var bal = (cur.account.balanceMin || 0);
      ui.mount(kBal, kpiNode(t('balance'), F.sgn(bal), t('balance_asof', { date: cur.account.asOf ? F.day(cur.account.asOf) : '–' }), bal < 0 ? 'warn' : ''));
    }
    function kpiNode(l, v, sub, tone) { return M.kpi(l, v, sub, tone); }

    var greet = h('div', { 'class': 'tg-hero__text' },
      h('div', { 'class': 'tg-hello' }, t('hello', { name: me.name })), h('div', { 'class': 'tg-muted' }, F.dayLong(F.today())),
      h('div', { 'class': 'tg-hero__actions' }, actionButtons()),
      h('div', { 'class': 'tg-hero__sub' }, M.btn(t('entry_new'), 'plus', function () { M.entryDialog(null).then(function (r) { if (r) { ui.toast(t('saved'), 'ok'); if (r.newViolations && r.newViolations.length) { ui.toast(r.newViolations.map(function (v) { return v.text; }).join(' '), 'warn', 7000); } ctx.reload(); } }); }, 'tg-btn--sm')),
      legend);
    if (TG.home) { TG.home.intro(ctx, me, setup).forEach(function (n) { wrap.appendChild(n); }); }
    wrap.appendChild(h('section', { 'class': 'tg-card tg-hero tg-hero--' + st().state }, ringBox, greet));
    wrap.appendChild(h('div', { 'class': 'tg-kpis' }, kNet, kSoll, kWeek, kBal));

    // Timeline
    var nowMin = (function () { var c = F.clock(new Date().toISOString()); return core.parseClock(c) || 0; })();
    var items = entries.filter(function (e) { return e.startHM; }).map(function (e) { return { startMin: core.parseClock(e.startHM), endMin: e.endHM ? core.parseClock(e.endHM) : nowMin, open: !e.endHM, entry: e }; });
    var tl = core.timelineSegments(items);
    var bar = h('div', { 'class': 'tg-tl', role: 'img', 'aria-label': t('timeline') });
    tl.segs.forEach(function (sg) { bar.appendChild(h('span', { 'class': 'tg-tl__seg' + (sg.open ? ' is-open' : ''), style: 'left:' + sg.left + '%;width:' + sg.width + '%', title: core.clockOf(sg.startMin) + '–' + (sg.open ? t('running') : core.clockOf(sg.endMin)) })); });
    var ticks = h('div', { 'class': 'tg-tl__ticks', 'aria-hidden': 'true' });
    for (var hr = Math.ceil(tl.lo / 60); hr * 60 <= tl.hi; hr += (tl.hi - tl.lo > 600 ? 2 : 1)) { ticks.appendChild(h('span', { style: 'left:' + ((hr * 60 - tl.lo) / (tl.hi - tl.lo) * 100) + '%' }, String(hr))); }
    var list = h('ul', { 'class': 'tg-elist' }, entries.map(function (e) { return entryLine(e, function () { ctx.reload(); }); }));
    wrap.appendChild(M.card(t('timeline'), entries.length ? [h('div', { 'class': 'tg-tlwrap' }, bar, ticks), list] : M.empty(t('no_entries_today'), 'clock')));

    // Hinweise der Woche
    var viol = (me.week.violations || []);
    wrap.appendChild(M.card(t('violations_week'), M.violationList(viol), h('a', { 'class': 'tg-link', href: '#/week' }, t('nav_week'))));

    ctx.every(1000, tick); tick();
    ctx.every(60000, function () { ctx.reloadMe().then(function () { tick(); M.renderNav(); }, function () { /* offline */ }); });
    return wrap;
  }

  /* ================= WOCHE ================= */
  function weekDays(wk, ctx) {
    var names = F.dayNames(), today = F.today();
    return wk.days.map(function (d, i) {
      return { date: d.day, label: names[i], net: d.netMin, flag: dayFlag(d), absent: !!(d.absence || d.holiday), today: d.day === today, weekend: i >= 5,
        tip: names[i] + ' ' + F.day(d.day) + ': ' + F.hm(d.netMin) + (d.holiday ? ' · ' + d.holiday.name : '') + (d.absence ? ' · ' + kindLabel(d.absence.kind) : '') };
    });
  }
  function dayTableRows(days, ctx) {
    return days.filter(function (d) { return d.entries.length || d.holiday || d.absence || d.violations.length || d.sollMin; }).map(function (d) {
      var badges = [];
      if (d.holiday) { badges.push(M.pill(d.holiday.name, 'info')); }
      if (d.absence) { badges.push(M.pill(kindLabel(d.absence.kind), 'info')); }
      (d.violations || []).forEach(function (v) { badges.push(M.pill(t('rule_short_' + v.rule), v.level === 'verstoss' ? 'bad' : 'warn')); });
      var delta = d.deltaMin;
      return { cls: (d.isToday ? 'is-today ' : '') + (dayFlag(d) === 'bad' ? 'is-bad' : ''), cells: [
        { node: h('b', null, F.day(d.day)) },
        { node: d.entries.length ? h('ul', { 'class': 'tg-elist tg-elist--in' }, d.entries.map(function (e) { return entryLine(e, function () { ctx.reload(); }); })) : h('span', { 'class': 'tg-muted' }, '–') },
        d.pauseMin ? d.pauseMin + ' min' : '–',
        d.netMin ? F.hm(d.netMin) : '–',
        d.sollMin ? F.hm(d.sollMin) : '–',
        { node: d.sollMin || d.netMin ? h('span', { 'class': delta < 0 ? 'tg-neg' : delta > 0 ? 'tg-pos' : '' }, F.sgn(delta)) : '–' },
        { node: h('span', { 'class': 'tg-badges' }, badges) }
      ] };
    });
  }
  var DAY_HEADERS = function () { return [[t('col_day')], [t('col_time'), 'tg-col-time'], [t('col_pause')], [t('col_net')], [t('col_soll')], [t('col_delta')], [t('col_notes')]]; };

  views.week = { title: 'nav_week', render: function (ctx) {
    var date = ctx.params.d || F.today();
    if (core.parseDay(date) === null) { date = F.today(); }
    return api.week(date).then(function (wk) {
      var wrap = h('div', { 'class': 'tg-grid' });
      var label = t('week_label', { kw: wk.kw, year: wk.year });
      var sub = F.day(wk.from) + ' – ' + F.day(wk.to);
      ctx.setActions([M.btn(t('print'), 'print', function () { root.print(); }, 'tg-btn--sm'), h('a', { 'class': 'tg-btn tg-btn--sm', href: api.exportCsvUrl(core.ymOf(wk.from), 'detail'), download: '' }, ui.icon('download', 16), t('csv'))]);
      ctx.setPrint(h('div', { 'class': 'tg-printhead__in' }, h('b', null, t('week_label', { kw: wk.kw, year: wk.year })), h('span', null, ' · ' + sub)));
      wrap.appendChild(M.periodNav(label, function () { ctx.go('week', { d: core.addDays(wk.from, -7) }); }, function () { ctx.go('week', { d: core.addDays(wk.from, 7) }); }, function () { ctx.go('week'); }, sub));
      var bad = wk.violations.filter(function (v) { return v.level === 'verstoss'; }).length, hints = wk.violations.length - bad;
      wrap.appendChild(h('div', { 'class': 'tg-kpis' },
        M.kpi(t('col_net'), F.hm(wk.netMin), t('of_target', { target: F.hm(wk.targetMin) }), wk.netMin > wk.limitMin ? 'bad' : ''),
        M.kpi(t('col_soll'), F.hm(wk.sollMin), wk.creditMin ? t('credit_n', { time: F.hm(wk.creditMin) }) : null),
        M.kpi(t('col_delta'), F.sgn(wk.deltaMin), null, wk.deltaMin < 0 ? 'warn' : ''),
        M.kpi(t('violations'), String(bad), t('hints_n', { n: hints }), bad ? 'bad' : '')));
      var days = weekDays(wk, ctx);
      wrap.appendChild(M.card(t('chart_hours'), [h('div', { 'class': 'tg-chartbox' }, TG.charts.bars(days, { W: 600, H: 250, dayTarget: 480, dayLimit: 600, showValues: true, label: t('chart_hours') + ' ' + label })), chartLegend()]));
      var blocks = wk.days.map(function (d, i) { return { label: F.dayNames()[i], startMin: d.firstHM ? core.parseClock(d.firstHM) : null, endMin: d.lastHM ? core.parseClock(d.lastHM) : null, flag: dayFlag(d), today: d.day === F.today(), weekend: i >= 5 }; });
      if (blocks.some(function (b) { return b.startMin !== null; })) { wrap.appendChild(M.card(t('chart_blocks'), h('div', { 'class': 'tg-chartbox' }, TG.charts.dayBlocks(blocks, { W: 600, H: 280 })), null, 'tg-noprint-sm')); }
      wrap.appendChild(M.card(t('days_title'), table(DAY_HEADERS(), dayTableRows(wk.days, ctx), 'tg-table--days'), null, 'tg-section--table'));
      wrap.appendChild(M.card(t('violations_week'), M.violationList(wk.violations)));
      return wrap;
    });
  } };
  function chartLegend() {
    return h('ul', { 'class': 'tg-legend' }, h('li', null, h('i', { 'class': 'tg-dot tg-dot--ok' }), t('legend_ok')), h('li', null, h('i', { 'class': 'tg-dot tg-dot--hint' }), t('legend_hint')), h('li', null, h('i', { 'class': 'tg-dot tg-dot--bad' }), t('legend_bad')),
      h('li', null, h('i', { 'class': 'tg-line tg-line--target' }), t('legend_8h')), h('li', null, h('i', { 'class': 'tg-line tg-line--limit' }), t('legend_10h')));
  }

  /* ================= MONAT ================= */
  views.month = { title: 'nav_month', render: function (ctx) {
    var ym = ctx.params.m && /^\d{4}-\d{2}$/.test(ctx.params.m) ? ctx.params.m : core.ymOf(F.today());
    return api.month(ym).then(function (mo) {
      var wrap = h('div', { 'class': 'tg-grid' });
      var label = core.fmtMonth(ym, TG.i18n.lang());
      ctx.setActions([M.btn(t('print'), 'print', function () { root.print(); }, 'tg-btn--sm'),
        h('a', { 'class': 'tg-btn tg-btn--sm', href: api.exportCsvUrl(ym, 'detail'), download: '' }, ui.icon('download', 16), t('csv')),
        h('a', { 'class': 'tg-btn tg-btn--sm', href: api.exportCsvUrl(ym, 'payroll'), download: '' }, ui.icon('download', 16), t('csv_payroll'))]);
      ctx.setPrint(h('div', { 'class': 'tg-printhead__in' }, h('b', null, label)));
      wrap.appendChild(M.periodNav(label, function () { ctx.go('month', { m: core.addMonths(ym, -1) }); }, function () { ctx.go('month', { m: core.addMonths(ym, 1) }); }, function () { ctx.go('month'); }));
      var bad = mo.violations.filter(function (v) { return v.level === 'verstoss'; }).length;
      wrap.appendChild(h('div', { 'class': 'tg-kpis' }, M.kpi(t('col_net'), F.hm(mo.netMin)), M.kpi(t('col_soll'), F.hm(mo.sollMin), mo.creditMin ? t('credit_n', { time: F.hm(mo.creditMin) }) : null),
        M.kpi(t('col_delta'), F.sgn(mo.deltaMin), null, mo.deltaMin < 0 ? 'warn' : ''), M.kpi(t('violations'), String(bad), t('hints_n', { n: mo.violations.length - bad }), bad ? 'bad' : '')));
      var today = F.today();
      var items = mo.days.map(function (d) {
        var dn = F.dayNames()[d.dow];
        return { date: d.day, label: String(+d.day.slice(8)), net: d.netMin, flag: dayFlag(d), absent: !!(d.absence || d.holiday), today: d.day === today, weekend: d.dow >= 5, tip: dn + ' ' + F.day(d.day) + ': ' + F.hm(d.netMin) + (d.holiday ? ' · ' + d.holiday.name : '') + (d.absence ? ' · ' + kindLabel(d.absence.kind) : '') };
      });
      wrap.appendChild(M.card(t('chart_hours'), [h('div', { 'class': 'tg-chartbox tg-chartbox--scroll' }, TG.charts.bars(items, { W: 760, H: 250, dayTarget: 480, dayLimit: 600, showValues: false, maxBar: 18, fill: 0.7, label: t('chart_hours') + ' ' + label })), chartLegend()]));
      if (mo.byProject && mo.byProject.length) {
        wrap.appendChild(M.card(t('by_project'), h('div', { 'class': 'tg-chartbox' }, TG.charts.hBars(mo.byProject.map(function (p) { return { name: p.project || t('no_project'), value: p.netMin }; }), { W: 560 }))));
      }
      wrap.appendChild(M.card(t('days_title'), table(DAY_HEADERS(), dayTableRows(mo.days, ctx), 'tg-table--days'), null, 'tg-section--table'));
      wrap.appendChild(M.card(t('violations_month'), M.violationList(mo.violations)));
      return wrap;
    });
  } };

  /* ================= ZEITKONTO ================= */
  views.account = { title: 'nav_account', render: function (ctx) {
    return api.account().then(function (a) {
      var wrap = h('div', { 'class': 'tg-grid' });
      var bal = a.balanceMin || 0;
      ctx.setActions([M.btn(t('print'), 'print', function () { root.print(); }, 'tg-btn--sm')]);
      ctx.setPrint(h('div', { 'class': 'tg-printhead__in' }, h('b', null, t('nav_account'))));
      wrap.appendChild(h('section', { 'class': 'tg-card tg-balance tg-balance--' + (bal < 0 ? 'neg' : 'pos') },
        h('div', { 'class': 'tg-balance__l' }, t('balance')), h('div', { 'class': 'tg-balance__v' }, F.sgn(bal)),
        h('div', { 'class': 'tg-muted' }, t('balance_asof', { date: a.asOf ? F.dayLong(a.asOf) : '–' }) + (a.since ? ' · ' + t('balance_since', { date: F.day(a.since) }) : '')),
        h('p', { 'class': 'tg-hint' }, t('balance_explain'))));
      var hist = a.history || [];
      var pts = hist.map(function (r) { return { label: core.fmtMonthShort(r.ym, TG.i18n.lang()), value: r.balanceMin }; });
      wrap.appendChild(M.card(t('balance_curve'), hist.length ? h('div', { 'class': 'tg-chartbox' }, TG.charts.balanceLine(pts, { W: 640, H: 260, label: t('balance_curve') })) : M.empty(t('no_history'), 'chart')));
      if (hist.length) {
        var rows = hist.slice().reverse().map(function (r) {
          return { cells: [{ node: h('b', null, core.fmtMonth(r.ym, TG.i18n.lang())) }, F.hm(r.sollMin), F.hm(r.istMin), r.creditMin ? F.hm(r.creditMin) : '–', { node: h('span', { 'class': r.deltaMin < 0 ? 'tg-neg' : r.deltaMin > 0 ? 'tg-pos' : '' }, F.sgn(r.deltaMin)) }, { node: h('b', null, F.sgn(r.balanceMin)) }] };
        });
        wrap.appendChild(M.card(t('months'), table([[t('col_month')], [t('col_soll')], [t('col_ist')], [t('col_credit')], [t('col_delta')], [t('balance')]], rows), null, 'tg-section--table'));
      }
      return wrap;
    });
  } };

  TG.viewsTime = { entryLine: entryLine, table: table, dayFlag: dayFlag, kindLabel: kindLabel, chartLegend: chartLegend, weekDays: weekDays };
})(typeof self !== 'undefined' ? self : this);
