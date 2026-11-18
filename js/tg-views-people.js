/* TimeGuardian – Ansichten Abwesenheiten, Korrekturen, Team. */
(function (root) {
  'use strict';
  var TG = root.TG = root.TG || {};
  var core = TG.core, ui = TG.ui, api = TG.api, h = ui.h, M = TG.main, F = M.F, VT = function () { return TG.viewsTime; };
  var t = function (k, p) { return TG.i18n.t(k, p); };
  var views = TG.views = TG.views || {};
  var KINDS = ['vacation', 'sick', 'flex', 'trip'];
  var STATUS_TONE = { pending: 'warn', approved: 'ok', rejected: 'bad' };

  function listOf(raw, key) { return TG.model.list(raw, key); }
  function rangeText(a) { return a.fromDay === a.toDay ? F.day(a.fromDay) : F.day(a.fromDay) + ' – ' + F.day(a.toDay); }
  function noteDialog(title, label, okLabel, danger) {
    var inp = h('textarea', { rows: '2', maxlength: '300', 'class': 'tg-input' });
    return ui.modal(title, ui.field(label, inp), [{ label: t('cancel'), value: null }, { label: okLabel, primary: !danger, danger: !!danger, value: function () { return inp.value.trim(); } }]);
  }

  /* ================= ABWESENHEITEN ================= */
  views.absences = { title: 'nav_absences', render: function (ctx) {
    var me = ctx.me, today = F.today();
    var reqs = [api.absences('mine', core.addDays(today, -90), core.addDays(today, 400))];
    if (me.isChef) { reqs.push(api.absences('pending')); reqs.push(api.absences('team', today, core.addDays(today, 60))); }
    return Promise.all(reqs).then(function (res) {
      var mine = listOf(res[0], 'absences'), pending = me.isChef ? listOf(res[1], 'absences') : [], team = me.isChef ? listOf(res[2], 'absences') : [];
      var wrap = h('div', { 'class': 'tg-grid' });

      if (me.isChef) {
        var pl = h('ul', { 'class': 'tg-reqs' });
        pending.forEach(function (a) {
          pl.appendChild(h('li', { 'class': 'tg-req' },
            h('div', { 'class': 'tg-req__main' }, h('b', null, a.name || a.uid), M.pill(VT().kindLabel(a.kind), 'info'), h('span', null, rangeText(a) + ' · ' + t('days_n', { n: a.days })), a.note ? h('div', { 'class': 'tg-muted' }, a.note) : null),
            h('div', { 'class': 'tg-req__act' }, M.btn(t('approve'), 'check', function () { decide(a, 'approve'); }, 'tg-btn--sm tg-btn--primary'), M.btn(t('reject'), 'close', function () { noteDialog(t('reject_t'), t('reject_note'), t('reject'), true).then(function (n) { if (n !== null) { decide(a, 'reject', n); } }); }, 'tg-btn--sm'))));
        });
        wrap.appendChild(M.card(t('abs_pending'), pending.length ? pl : M.empty(t('abs_none_pending'), 'check')));
      }
      function decide(a, d, note) { api.decideAbsence(a.id, d, note).then(function () { ui.toast(d === 'approve' ? t('approved') : t('rejected'), 'ok'); ctx.reloadMe().then(function () { ctx.reload(); }); }, function (e) { ui.toast(M.valMsg(e), 'err'); }); }

      // Antrag
      var kind = ui.select(KINDS.map(function (k) { return [k, t('kind_' + k)]; }), 'vacation', { 'class': 'tg-input' });
      var from = h('input', { type: 'date', value: today, min: core.addDays(today, -90), max: core.addDays(today, 365), 'class': 'tg-input' });
      var to = h('input', { type: 'date', value: today, min: core.addDays(today, -90), max: core.addDays(today, 365), 'class': 'tg-input' });
      var note = h('input', { type: 'text', maxlength: '255', 'class': 'tg-input', autocomplete: 'off' });
      var err = h('div', { 'class': 'tg-formerr', role: 'alert', hidden: '' });
      from.addEventListener('change', function () { if (to.value < from.value) { to.value = from.value; } });
      var submit = h('button', { type: 'submit', 'class': 'tg-btn tg-btn--primary' }, ui.icon('plus', 18), t('abs_send'));
      var form = h('form', { 'class': 'tg-form tg-form--wide', onsubmit: function (e) {
        e.preventDefault(); err.setAttribute('hidden', ''); submit.disabled = true;
        api.addAbsence({ kind: kind.value, fromDay: from.value, toDay: to.value, note: note.value.trim() || undefined }).then(function () { ui.toast(t('abs_sent'), 'ok'); ctx.reload(); }, function (er) {
          submit.disabled = false;
          var m = er && er.data && er.data.fields ? er.data.fields.map(function (f) { return t('f_abs_' + f); }).join(' ') : M.valMsg(er);
          err.textContent = m; err.removeAttribute('hidden');
        });
      } }, h('div', { 'class': 'tg-row3' }, ui.field(t('abs_kind'), kind), ui.field(t('abs_from'), from), ui.field(t('abs_to'), to)), ui.field(t('f_note_l'), note), err, h('div', null, submit));
      wrap.appendChild(M.card(t('abs_request'), form));

      // Liste
      var rows = mine.map(function (a) {
        var del = (a.status !== 'rejected') ? h('button', { type: 'button', 'class': 'tg-iconbtn tg-iconbtn--danger', title: t('abs_cancel'), 'aria-label': t('abs_cancel'), onclick: function () {
          ui.confirm(t('abs_cancel_t'), t('abs_cancel_p', { range: rangeText(a) }), t('abs_cancel'), t('cancel'), true).then(function (ok) { if (ok) { api.deleteAbsence(a.id).then(function () { ui.toast(t('deleted'), 'ok'); ctx.reload(); }, function (e) { ui.toast(M.valMsg(e), 'err'); }); } });
        } }, ui.icon('trash', 18)) : null;
        return { cells: [{ node: M.pill(VT().kindLabel(a.kind), 'info') }, rangeText(a), String(a.days), { node: M.pill(t('status_' + a.status), STATUS_TONE[a.status]) }, a.note || '–', { node: del }] };
      });
      wrap.appendChild(M.card(t('abs_mine'), mine.length ? VT().table([[t('abs_kind')], [t('abs_range')], [t('col_days')], [t('col_status')], [t('f_note_l')], ['']], rows) : M.empty(t('abs_none'), 'sun'), null, 'tg-section--table'));

      if (me.isChef) {
        var trows = team.map(function (a) { return { cells: [{ node: h('b', null, a.name || a.uid) }, { node: M.pill(VT().kindLabel(a.kind), 'info') }, rangeText(a), String(a.days), { node: M.pill(t('status_' + a.status), STATUS_TONE[a.status]) }] }; });
        wrap.appendChild(M.card(t('abs_team'), team.length ? VT().table([[t('col_person')], [t('abs_kind')], [t('abs_range')], [t('col_days')], [t('col_status')]], trows) : M.empty(t('abs_none_team'), 'users'), null, 'tg-section--table'));
      }
      return wrap;
    });
  } };

  /* ================= KORREKTUREN ================= */
  function diffRow(cur, prop) {
    function seg(o) { return (o.startHM || '–') + '–' + (o.endHM || '–') + ' · ' + t('pause_short') + ' ' + (o.pauseMin != null ? o.pauseMin : 0) + ' min'; }
    return h('span', { 'class': 'tg-diff' }, h('span', { 'class': 'tg-diff__old' }, seg(cur)), h('span', { 'class': 'tg-diff__arrow', 'aria-label': t('becomes') }, '→'), h('span', { 'class': 'tg-diff__new' }, seg(prop)));
  }
  function auditSummary(a) {
    var keys = {}, out = [];
    [a.before, a.after].forEach(function (o) { if (o && typeof o === 'object') { Object.keys(o).forEach(function (k) { keys[k] = 1; }); } });
    Object.keys(keys).forEach(function (k) {
      var b = a.before ? a.before[k] : undefined, n = a.after ? a.after[k] : undefined;
      if (JSON.stringify(b) === JSON.stringify(n)) { return; }
      if (/^(id|uid|createdAt|updatedAt|start|end)$/.test(k) && k !== 'start' && k !== 'end') { return; }
      out.push(k + ': ' + fmtVal(b) + ' → ' + fmtVal(n));
    });
    return out;
  }
  function fmtVal(v) { return v === undefined || v === null ? '∅' : (typeof v === 'object' ? JSON.stringify(v) : String(v)); }

  views.corrections = { title: 'nav_corrections', render: function (ctx) {
    var me = ctx.me, today = F.today(), from = core.addDays(today, -60);
    var reqs = [api.corrections('mine'), api.entries(from, today), api.audit(me.uid, from, today)];
    if (me.isChef) { reqs.push(api.corrections('pending')); }
    return Promise.all(reqs.map(function (p, i) { return i === 2 ? p.catch(function () { return { audit: [] }; }) : p; })).then(function (res) {
      var mine = listOf(res[0], 'corrections'), entries = listOf(res[1], 'entries'), audit = listOf(res[2], 'audit'), pending = me.isChef ? listOf(res[3], 'corrections') : [];
      var wrap = h('div', { 'class': 'tg-grid' });
      function decide(c, d, note) { api.decideCorrection(c.id, d, note).then(function () { ui.toast(d === 'approve' ? t('approved') : t('rejected'), 'ok'); ctx.reloadMe().then(function () { ctx.reload(); }); }, function (e) { ui.toast(M.valMsg(e), 'err'); }); }

      if (me.isChef) {
        var pl = h('ul', { 'class': 'tg-reqs' });
        pending.forEach(function (c) {
          pl.appendChild(h('li', { 'class': 'tg-req' }, h('div', { 'class': 'tg-req__main' }, h('b', null, c.name || c.uid), h('span', { 'class': 'tg-muted' }, F.day(c.day)), h('div', null, diffRow(c.current, c.proposed)), h('div', { 'class': 'tg-reason' }, c.reason)),
            h('div', { 'class': 'tg-req__act' }, M.btn(t('approve'), 'check', function () { decide(c, 'approve'); }, 'tg-btn--sm tg-btn--primary'), M.btn(t('reject'), 'close', function () { noteDialog(t('reject_t'), t('reject_note'), t('reject'), true).then(function (n) { if (n !== null) { decide(c, 'reject', n); } }); }, 'tg-btn--sm'))));
        });
        wrap.appendChild(M.card(t('corr_pending'), pending.length ? pl : M.empty(t('corr_none_pending'), 'check')));
      }

      var done = entries.filter(function (e) { return !e.running; }).slice().reverse();
      var erows = done.slice(0, 30).map(function (e) {
        return { cells: [{ node: h('b', null, F.day(e.day)) }, e.startHM + '–' + e.endHM, F.hm(e.netMin), { node: e.project ? M.pill(e.project) : '–' }, { node: M.btn(t('corr_ask'), 'edit', function () { M.correctionDialog(e).then(function (r) { if (r) { ui.toast(t('corr_sent'), 'ok'); ctx.reload(); } }); }, 'tg-btn--sm') }] };
      });
      wrap.appendChild(M.card(t('corr_pick'), done.length ? [h('p', { 'class': 'tg-hint' }, t('corr_hint')), VT().table([[t('col_day')], [t('col_time')], [t('col_net')], [t('f_project_l')], ['']], erows)] : M.empty(t('no_entries'), 'clock'), null, 'tg-section--table'));

      var mrows = mine.map(function (c) { return { cells: [{ node: h('b', null, F.day(c.day)) }, { node: diffRow(c.current, c.proposed) }, c.reason, { node: M.pill(t('status_' + c.status), STATUS_TONE[c.status]) }] }; });
      wrap.appendChild(M.card(t('corr_mine'), mine.length ? VT().table([[t('col_day')], [t('corr_change')], [t('corr_reason')], [t('col_status')]], mrows) : M.empty(t('corr_none'), 'edit'), null, 'tg-section--table'));

      var al = h('ul', { 'class': 'tg-audit' });
      audit.slice(0, 40).forEach(function (a) {
        var sum = auditSummary(a);
        al.appendChild(h('li', { 'class': 'tg-audit__i' }, h('div', { 'class': 'tg-audit__h' }, h('b', null, t('audit_' + String(a.kind).replace(/\./g, '_')) === ('audit_' + String(a.kind).replace(/\./g, '_')) ? a.kind : t('audit_' + String(a.kind).replace(/\./g, '_'))), h('span', { 'class': 'tg-muted' }, core.fmtDateTime(a.at, TG.i18n.lang(), me.timezone) + ' · ' + a.actor)),
          sum.length ? h('div', { 'class': 'tg-audit__d' }, sum.slice(0, 5).map(function (x) { return h('div', null, x); })) : null));
      });
      wrap.appendChild(M.card(t('audit_t'), audit.length ? [h('p', { 'class': 'tg-hint' }, t('audit_hint')), al] : M.empty(t('audit_none'), 'info')));
      return wrap;
    });
  } };

  /* ================= TEAM ================= */
  function personDialog(p, week, ctx) {
    var body = h('div', { 'class': 'tg-persondlg' }, M.loading());
    var from = week.from, to = week.to;
    var m = ui.modal(p.name, body, [{ label: t('close'), value: null }]);
    api.teamPerson(p.uid, from, to).then(function (r) {
      var names = F.dayNames(), today = F.today();
      var days = (r.days || []).map(function (d, i) { return { date: d.day, label: names[i] || '', net: d.netMin, flag: VT().dayFlag(d), absent: !!(d.absence || d.holiday), today: d.day === today, weekend: i >= 5 }; });
      var blocks = (r.days || []).map(function (d, i) { return { label: names[i] || '', startMin: d.firstHM ? core.parseClock(d.firstHM) : null, endMin: d.lastHM ? core.parseClock(d.lastHM) : null, flag: VT().dayFlag(d), today: d.day === today, weekend: i >= 5 }; });
      var kids = [h('div', { 'class': 'tg-kpis tg-kpis--sm' }, M.kpi(t('col_net'), F.hm(p.netMin)), M.kpi(t('balance'), F.sgn((r.account && r.account.balanceMin) || 0)), M.kpi(t('state'), t('state_' + ((r.user && r.user.state && r.user.state.state) || 'none'))))];
      if (days.length) { kids.push(h('div', { 'class': 'tg-chartbox' }, TG.charts.bars(days, { W: 560, H: 220, dayTarget: 480, dayLimit: 600, showValues: true, label: p.name }))); }
      if (blocks.some(function (b) { return b.startMin !== null; })) { kids.push(h('div', { 'class': 'tg-chartbox' }, TG.charts.dayBlocks(blocks, { W: 560, H: 240, label: p.name }))); }
      // Soll setzen
      var wh = h('input', { type: 'number', min: '0', max: '60', step: '0.5', 'class': 'tg-input', placeholder: '40', inputmode: 'decimal' });
      var mask = 31;
      var boxes = F.dayNames().map(function (d, i) { return h('label', { 'class': 'tg-chk' }, h('input', { type: 'checkbox', checked: !!(mask & (1 << i)), 'data-bit': String(i) }), h('span', null, d)); });
      var saveErr = h('div', { 'class': 'tg-formerr', role: 'alert', hidden: '' });
      kids.push(h('div', { 'class': 'tg-targetform' }, h('h3', null, t('target_t')), h('div', { 'class': 'tg-row2' }, ui.field(t('target_week'), wh), h('div', { 'class': 'tg-field' }, h('label', null, t('target_days')), h('div', { 'class': 'tg-chks' }, boxes))), saveErr,
        M.btn(t('save'), 'check', function () {
          if (!wh.value) { saveErr.textContent = t('target_need'); saveErr.removeAttribute('hidden'); return; }
          saveErr.setAttribute('hidden', '');
          var mk = 0; boxes.forEach(function (b, i) { if (b.querySelector('input').checked) { mk |= (1 << i); } });
          api.setTarget(p.uid, { weeklyMin: Math.round(parseFloat(wh.value) * 60) || 0, daysMask: mk }).then(function () { ui.toast(t('saved'), 'ok'); }, function (e) { saveErr.textContent = M.valMsg(e); saveErr.removeAttribute('hidden'); });
        }, 'tg-btn--sm')));
      kids.push(h('h3', null, t('violations')), M.violationList(r.violations || []));
      ui.mount(body, kids);
    }, function (err) { ui.mount(body, M.errBox(err)); });
  }

  views.team = { title: 'nav_team', chef: true, render: function (ctx) {
    var date = ctx.params.d && core.parseDay(ctx.params.d) !== null ? ctx.params.d : F.today();
    return api.teamOverview(date).then(function (ov) {
      var wrap = h('div', { 'class': 'tg-grid' });
      var ym = core.ymOf(ov.week.from);
      var label = t('week_label', { kw: ov.week.kw, year: ov.week.year }), sub = F.day(ov.week.from) + ' – ' + F.day(ov.week.to);
      ctx.setActions([M.btn(t('print'), 'print', function () { root.print(); }, 'tg-btn--sm'),
        h('a', { 'class': 'tg-btn tg-btn--sm', href: api.teamExportUrl(ym, 'detail'), download: '' }, ui.icon('download', 16), t('csv_team')),
        h('a', { 'class': 'tg-btn tg-btn--sm', href: api.teamExportUrl(ym, 'payroll'), download: '' }, ui.icon('download', 16), t('csv_payroll'))]);
      ctx.setPrint(h('div', { 'class': 'tg-printhead__in' }, h('b', null, t('nav_team') + ' · ' + label), h('span', null, ' · ' + sub)));
      wrap.appendChild(M.periodNav(label, function () { ctx.go('team', { d: core.addDays(ov.week.from, -7) }); }, function () { ctx.go('team', { d: core.addDays(ov.week.from, 7) }); }, function () { ctx.go('team'); }, sub));
      var people = ov.people || [], bad = (ov.violations || []).filter(function (v) { return v.level === 'verstoss'; }).length;
      var pend = ov.pending || { absences: 0, corrections: 0 };
      wrap.appendChild(h('div', { 'class': 'tg-kpis' }, M.kpi(t('present_now'), String((ov.presentNow || []).length), t('of_n', { n: people.length })),
        M.kpi(t('violations'), String(bad), t('hints_n', { n: (ov.violations || []).length - bad }), bad ? 'bad' : ''),
        M.kpi(t('nav_absences'), String(pend.absences), t('pending_open'), pend.absences ? 'warn' : ''), M.kpi(t('nav_corrections'), String(pend.corrections), t('pending_open'), pend.corrections ? 'warn' : '')));

      // Anwesenheit jetzt
      var pres = h('ul', { 'class': 'tg-people' });
      people.forEach(function (p) {
        pres.appendChild(h('li', null, h('button', { type: 'button', 'class': 'tg-person tg-person--' + p.state, onclick: function () { personDialog(p, ov.week, ctx); } },
          h('span', { 'class': 'tg-avatar' }, core.initials(p.name)), h('span', { 'class': 'tg-person__n' }, p.name),
          h('span', { 'class': 'tg-person__s' }, p.state === 'working' ? t('present_since', { time: F.clock(p.since) }) : t('state_' + p.state)))));
      });
      wrap.appendChild(M.card(t('present_t'), people.length ? pres : M.empty(t('team_none'), 'users')));

      // Heatmap
      var vmap = {};
      (ov.violations || []).forEach(function (v) { var k = v.uid + '|' + v.day; if (v.level === 'verstoss' || !vmap[k]) { vmap[k] = v.level === 'verstoss' ? 'bad' : (vmap[k] || 'hint'); } });
      var hrows = people.map(function (p) { return { name: p.name, days: (p.days || []).map(function (d) { return d.netMin; }), flags: (p.days || []).map(function (d) { return vmap[p.uid + '|' + d.day] || ''; }) }; });
      if (people.length) {
        wrap.appendChild(M.card(t('team_heat'), [h('div', { 'class': 'tg-chartbox tg-chartbox--scroll' }, TG.charts.heatmap(hrows, F.dayNames(), { dayTarget: 480 })),
          h('ul', { 'class': 'tg-legend' }, [1, 2, 3, 4, 5].map(function (l) { return h('li', null, h('i', { 'class': 'tg-swatch tg-heat--' + l }), t('heat_' + l)); }).concat([h('li', null, h('i', { 'class': 'tg-swatch tg-swatch--bad' }), t('legend_bad'))]))]));
        wrap.appendChild(M.card(t('team_net'), h('div', { 'class': 'tg-chartbox' }, TG.charts.hBars(people.map(function (p) { return { name: p.name, value: p.netMin, flag: p.violations && p.violations.verstoss ? 'bad' : p.netMin > 2880 ? 'hint' : '' }; }), { W: 560, target: 2400, limit: 2880, targetLabel: '40 h', limitLabel: '48 h' }))));
      }
      wrap.appendChild(M.card(t('violations_week'), M.violationList(ov.violations, true)));
      var top = ov.topOvertime || [];
      wrap.appendChild(M.card(t('top_overtime'), top.length ? h('ol', { 'class': 'tg-rank' }, top.map(function (o) { return h('li', null, h('span', null, o.name), h('b', { 'class': o.balanceMin < 0 ? 'tg-neg' : 'tg-pos' }, F.sgn(o.balanceMin))); })) : M.empty(t('none'), 'info')));
      return wrap;
    });
  } };
})(typeof self !== 'undefined' ? self : this);
