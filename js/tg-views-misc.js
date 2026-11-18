/* TimeGuardian – Ansichten Geräte (Kopplung per QR), Einstellungen, Konfiguration (Chef) und Hilfe. */
(function (root) {
  'use strict';
  var TG = root.TG = root.TG || {};
  var core = TG.core, ui = TG.ui, api = TG.api, h = ui.h, M = TG.main, F = M.F, VT = function () { return TG.viewsTime; };
  var t = function (k, p) { return TG.i18n.t(k, p); };
  var views = TG.views = TG.views || {};

  /* ================= GERÄTE ================= */
  views.devices = { title: 'nav_devices', render: function (ctx) {
    var me = ctx.me;
    return api.devices().then(function (res) {
      var devices = TG.model.list(res, 'devices').filter(function (d) { return !d.revoked; });
      var wrap = h('div', { 'class': 'tg-grid' });
      var pairBox = h('div', { 'class': 'tg-pair' });
      var devBox = h('div');
      var timer = null, poll = null, baseline = devices.length, baseIds = devices.map(function (d) { return d.id; });
      ctx.onLeave(function () { if (timer) { root.clearInterval(timer); } if (poll) { root.clearInterval(poll); } });

      function stopTimers() { if (timer) { root.clearInterval(timer); timer = null; } if (poll) { root.clearInterval(poll); poll = null; } }
      function idle() {
        stopTimers();
        ui.mount(pairBox, [
          h('ol', { 'class': 'tg-steps' }, [1, 2, 3].map(function (i) { return h('li', null, h('span', { 'class': 'tg-steps__n' }, String(i)), h('span', null, t('pair_step' + i))); })),
          h('div', null, M.btn(t('pair_make'), 'qr', start, 'tg-btn--primary tg-btn--lg'))]);
      }
      function start() {
        stopTimers();
        ui.mount(pairBox, M.loading());
        api.pairCreate().then(function (r) {
          var text = r.qrText || r.url;
          var total = r.expiresInSec || 300, endAt = Date.now() + total * 1000;
          var qrEl;
          try { qrEl = TG.qr.svg(text, { ecc: 'M', label: t('pair_qr_label') }); } catch (e) { qrEl = h('div', { 'class': 'tg-formerr' }, t('err_generic_p')); }
          var ringSvg = TG.charts.ring({ size: 56, rings: [{ r: 22, width: 5, frac: 1, cls: 'tg-lv-ok' }] });
          var cd = h('div', { 'class': 'tg-cd__t', 'aria-live': 'off' });
          var urlIn = h('input', { type: 'text', readonly: '', value: text, 'class': 'tg-input tg-input--mono', 'aria-label': t('pair_link') });
          var overlay = h('div', { 'class': 'tg-pair__exp', hidden: '' }, h('b', null, t('pair_expired')), M.btn(t('pair_again'), 'refresh', start, 'tg-btn--primary'));
          var qrWrap = h('div', { 'class': 'tg-pair__qr' }, qrEl, overlay);
          var codeBox = r.code ? h('div', { 'class': 'tg-pair__code' }, h('span', null, t('pair_code_lead')), h('b', { 'aria-label': t('pair_code_label') }, String(r.code)), h('span', null, t('pair_code_hint'))) : null;
          ui.mount(pairBox, [
            h('p', { 'class': 'tg-pair__lead' }, t('pair_scan_with')), h('div', { 'class': 'tg-pair__both' }, qrWrap, codeBox),
            h('div', { 'class': 'tg-cd' }, h('div', { 'class': 'tg-cd__ring' }, ringSvg), h('div', null, h('div', { 'class': 'tg-muted' }, t('pair_valid')), cd)),
            h('details', { 'class': 'tg-details' }, h('summary', null, t('pair_link_alt')), h('div', { 'class': 'tg-copy' }, urlIn, M.btn(t('copy'), null, function () { urlIn.select(); try { root.navigator.clipboard.writeText(text).then(function () { ui.toast(t('copied'), 'ok'); }); } catch (e) { doc.execCommand('copy'); } }, 'tg-btn--sm'))),
            h('div', null, M.btn(t('cancel'), 'close', idle, 'tg-btn--sm'))]);
          var upd = function () {
            var left = Math.max(0, Math.round((endAt - Date.now()) / 1000));
            cd.textContent = core.pad(Math.floor(left / 60)) + ':' + core.pad(left % 60);
            TG.charts.updateRing(ringSvg, [left / total], [left < 60 ? 'warn' : 'ok']);
            if (left <= 0) { qrWrap.classList.add('is-expired'); overlay.removeAttribute('hidden'); stopTimers(); }
          };
          upd(); timer = root.setInterval(upd, 1000);
          poll = root.setInterval(function () {
            api.devices().then(function (rr) {
              var list = TG.model.list(rr, 'devices').filter(function (d) { return !d.revoked; });
              var fresh = list.filter(function (d) { return baseIds.indexOf(d.id) < 0; });
              if (fresh.length) {
                stopTimers(); baseIds = list.map(function (d) { return d.id; });
                ui.vibrate(30);
                ui.mount(pairBox, h('div', { 'class': 'tg-pairok' }, h('div', { 'class': 'tg-success' }, ui.s('svg', { viewBox: '0 0 52 52', width: 84, height: 84, 'aria-hidden': 'true' }, ui.s('circle', { cx: 26, cy: 26, r: 24, fill: 'none', 'class': 'tg-success__c' }), ui.s('path', { d: 'M14 27l8 8 16-17', fill: 'none', 'class': 'tg-success__p' }))),
                  h('h3', null, t('pair_connected')), h('p', { 'class': 'tg-muted' }, fresh[0].label), M.btn(t('pair_another'), 'plus', start, 'tg-btn--sm'), ' ', M.btn(t('close'), null, idle, 'tg-btn--sm')));
                renderDevices(list);
              }
            }, function () { /* ignore */ });
          }, 3000);
        }, function (err) {
          ui.mount(pairBox, [M.errBox(err, start)]);
        });
      }
      var doc = root.document;

      function renderDevices(list) {
        if (!list.length) { ui.mount(devBox, M.empty(t('dev_none'), 'phone')); return; }
        var rows = list.map(function (d) {
          return { cells: [{ node: h('b', null, d.label || t('pair_device_generic')) }, d.createdAt ? core.fmtDateTime(d.createdAt, TG.i18n.lang(), me.timezone) : '–', d.lastUsed ? core.fmtDateTime(d.lastUsed, TG.i18n.lang(), me.timezone) : t('never'), d.expiresAt ? core.fmtDateTime(d.expiresAt, TG.i18n.lang(), me.timezone).slice(0, 10) : '–',
            { node: M.btn(t('revoke'), 'trash', function () {
              ui.confirm(t('revoke_t'), t('revoke_p', { name: d.label || '' }), t('revoke'), t('cancel'), true).then(function (ok) {
                if (!ok) { return; }
                api.revokeDevice(d.id).then(function () { ui.toast(t('revoked'), 'ok'); list = list.filter(function (x) { return x.id !== d.id; }); baseIds = list.map(function (x) { return x.id; }); renderDevices(list); ctx.reloadMe(); }, function (e) { ui.toast(M.valMsg(e), 'err'); });
              });
            }, 'tg-btn--sm tg-btn--danger-o') }] };
        });
        ui.mount(devBox, VT().table([[t('dev_name')], [t('dev_created')], [t('dev_last')], [t('dev_expires')], ['']], rows));
      }
      var inst = TG.install ? TG.install.card({ dismissable: false }) : null;
      if (inst) { wrap.appendChild(inst); }
      wrap.appendChild(M.card(t('pair_title_card'), [h('p', { 'class': 'tg-hint' }, t('pair_intro')), pairBox]));
      wrap.appendChild(M.card(t('dev_mine'), devBox, null, 'tg-section--table'));
      if (me.isChef || me.isKiosk) {
        wrap.appendChild(M.card(t('kiosk_card'), [h('p', { 'class': 'tg-hint' }, t('kiosk_intro')), h('a', { 'class': 'tg-btn', href: api.page('/kiosk') }, ui.icon('fullscreen', 18), t('open_kiosk'))]));
      }
      wrap.appendChild(M.card(t('go_card'), [h('p', { 'class': 'tg-hint' }, t('go_card_p')), h('a', { 'class': 'tg-btn', href: api.page('/go') }, ui.icon('phone', 18), t('go_open'))]));
      renderDevices(devices); idle();
      return wrap;
    });
  } };

  /* ================= EINSTELLUNGEN ================= */
  views.settings = { title: 'nav_settings', render: function (ctx) {
    return api.settings().then(function (raw) {
      var conf = TG.model.conf(raw.conf || raw), me = ctx.me;
      var wrap = h('div', { 'class': 'tg-grid' });
      var mode = conf.mode, lang = conf.lang || TG.i18n.lang();
      var modeBox = h('div', { 'class': 'tg-choices', role: 'radiogroup', 'aria-label': t('set_mode') });
      function renderModes() {
        ui.mount(modeBox, ['simple', 'pause'].map(function (m) {
          return h('button', { type: 'button', role: 'radio', 'aria-checked': String(mode === m), 'class': 'tg-choice' + (mode === m ? ' is-active' : ''), onclick: function () { mode = m; renderModes(); } },
            h('span', { 'class': 'tg-choice__r' }), h('span', null, h('b', null, t('mode_' + m)), h('span', { 'class': 'tg-muted' }, t('mode_' + m + '_d'))));
        }));
      }
      renderModes();
      var langSel = ui.select([['de', 'Deutsch'], ['en', 'English'], ['it', 'Italiano']], lang, { 'class': 'tg-input' });
      var soundCb = h('input', { type: 'checkbox', checked: api.store('get', 'tg.sound') === '1' });
      soundCb.addEventListener('change', function () { api.store('set', 'tg.sound', soundCb.checked ? '1' : '0'); });
      var err = h('div', { 'class': 'tg-formerr', role: 'alert', hidden: '' });
      var saveBtn = M.btn(t('save'), 'check', function () {
        saveBtn.disabled = true; err.setAttribute('hidden', '');
        api.saveSettings({ mode: mode, lang: langSel.value }).then(function () {
          TG.i18n.setLang(langSel.value, true);
          return ctx.reloadMe().then(function () { ui.toast(t('saved'), 'ok'); ctx.reload(); M.renderNav(); });
        }, function (e) { saveBtn.disabled = false; err.textContent = M.valMsg(e); err.removeAttribute('hidden'); });
      }, 'tg-btn--primary');
      wrap.appendChild(M.card(t('set_stamp'), [h('p', { 'class': 'tg-hint' }, t('set_mode_hint')), modeBox]));
      wrap.appendChild(M.card(t('set_general'), h('div', { 'class': 'tg-form' }, ui.field(t('set_lang'), langSel), h('label', { 'class': 'tg-switch' }, soundCb, h('span', null, t('set_sound'))), h('div', { 'class': 'tg-hint' }, t('set_sound_hint')),
        h('dl', { 'class': 'tg-dl' }, h('dt', null, t('set_weekly')), h('dd', null, F.hm(conf.weeklyMin)), h('dt', null, t('set_days')), h('dd', null, F.dayNames().filter(function (d, i) { return conf.daysMask & (1 << i); }).join(', '))),
        h('div', { 'class': 'tg-hint' }, conf.canSetTarget ? t('set_weekly_chef') : t('set_weekly_hint')), err, h('div', null, saveBtn))));
      var ym = core.ymOf(F.today());
      wrap.appendChild(M.card(t('set_data'), [h('p', { 'class': 'tg-hint' }, t('set_data_hint')), h('div', { 'class': 'tg-btnrow' },
        h('a', { 'class': 'tg-btn', href: api.exportJsonUrl(), download: '' }, ui.icon('download', 18), t('set_json')),
        h('a', { 'class': 'tg-btn', href: api.exportCsvUrl(ym, 'detail'), download: '' }, ui.icon('download', 18), t('set_csv_month')))]));
      return wrap;
    });
  } };

  /* ================= KONFIGURATION (nur Chef) ================= */
  var STATE_NAMES = {
    de: { BW: 'Baden-Württemberg', BY: 'Bayern', BE: 'Berlin', BB: 'Brandenburg', HB: 'Bremen', HH: 'Hamburg', HE: 'Hessen', MV: 'Mecklenburg-Vorpommern', NI: 'Niedersachsen', NW: 'Nordrhein-Westfalen', RP: 'Rheinland-Pfalz', SL: 'Saarland', SN: 'Sachsen', ST: 'Sachsen-Anhalt', SH: 'Schleswig-Holstein', TH: 'Thüringen' },
    en: { BW: 'Baden-Württemberg', BY: 'Bavaria', BE: 'Berlin', BB: 'Brandenburg', HB: 'Bremen', HH: 'Hamburg', HE: 'Hesse', MV: 'Mecklenburg-Vorpommern', NI: 'Lower Saxony', NW: 'North Rhine-Westphalia', RP: 'Rhineland-Palatinate', SL: 'Saarland', SN: 'Saxony', ST: 'Saxony-Anhalt', SH: 'Schleswig-Holstein', TH: 'Thuringia' },
    it: { BW: 'Baden-Württemberg', BY: 'Baviera', BE: 'Berlino', BB: 'Brandeburgo', HB: 'Brema', HH: 'Amburgo', HE: 'Assia', MV: 'Meclemburgo-Pomerania Anteriore', NI: 'Bassa Sassonia', NW: 'Renania Settentrionale-Vestfalia', RP: 'Renania-Palatinato', SL: 'Saarland', SN: 'Sassonia', ST: 'Sassonia-Anhalt', SH: 'Schleswig-Holstein', TH: 'Turingia' }
  };
  var ZONES = ['Europe/Berlin', 'Europe/Vienna', 'Europe/Zurich', 'Europe/Rome', 'Europe/Paris', 'Europe/Amsterdam', 'Europe/Brussels', 'Europe/Luxembourg', 'Europe/Copenhagen', 'Europe/Prague', 'Europe/Warsaw', 'Europe/London', 'UTC'];
  var FIELD_LABEL = { state: 'cfg_state', timezone: 'cfg_tz', defaultWeeklyMin: 'cfg_weekly', defaultDaysMask: 'cfg_days', retentionDays: 'cfg_retention', projects: 'cfg_proj', deckStackId: 'cfg_deck_stack', talkRoomToken: 'cfg_talk_room', webhookUrl: 'cfg_hook_url', webhookSecret: 'cfg_hook_secret',
    'integrations.calendar': 'cfg_cal', 'integrations.absenceCalendar': 'cfg_abscal', 'integrations.deck': 'cfg_deck', 'integrations.talk': 'cfg_talk', 'integrations.notifications': 'cfg_int_notif', selfApproval: 'cfg_self', liveWarnings: 'cfg_live' };

  function switchRow(label, desc, checked) {
    var cb = h('input', { type: 'checkbox', checked: !!checked });
    return { input: cb, el: h('div', { 'class': 'tg-swrow' }, h('label', { 'class': 'tg-switch' }, cb, h('span', null, label)), desc ? h('div', { 'class': 'tg-hint' }, desc) : null) };
  }
  function cfgSection(icon, title, intro, kids) {
    return h('section', { 'class': 'tg-card tg-cfg' },
      h('header', { 'class': 'tg-cfg__head' }, h('span', { 'class': 'tg-cfg__ic' }, ui.icon(icon, 22)), h('div', null, h('h2', null, title), intro ? h('p', { 'class': 'tg-hint' }, intro) : null)),
      h('div', { 'class': 'tg-cfg__body' }, kids));
  }
  function usersUrl() { return api.base().replace(/\/apps\/timeguardian$/, '') + '/settings/users'; }
  function adminUrl() { return api.base().replace(/\/apps\/timeguardian$/, '') + '/settings/admin/timeguardian'; }

  views.config = { title: 'nav_config', chef: true, render: function (ctx) {
    return api.config().then(function (res) {
      var c = res.config, roles = res.roles || {}, th = res.thresholds || {}, lang = TG.i18n.lang();
      var wrap = h('div', { 'class': 'tg-grid tg-config' });
      var errBoxEl = h('div', { 'class': 'tg-formerr', role: 'alert', hidden: '' });
      wrap.appendChild(h('p', { 'class': 'tg-lead' }, t('cfg_intro')));

      // 1 Firma / Standort
      var names = STATE_NAMES[lang] || STATE_NAMES.de;
      var stateSel = ui.select(Object.keys(STATE_NAMES.de).map(function (k) { return [k, names[k] + ' (' + k + ')']; }), c.state, { 'class': 'tg-input' });
      var tzIn = h('input', { type: 'text', value: c.timezone, list: 'tg-zones', 'class': 'tg-input', autocomplete: 'off', spellcheck: 'false' });
      wrap.appendChild(cfgSection('home', t('cfg_loc_t'), t('cfg_loc_p'), [
        ui.field(t('cfg_state'), stateSel, t('cfg_state_h')), ui.field(t('cfg_tz'), tzIn, t('cfg_tz_h')), h('datalist', { id: 'tg-zones' }, ZONES.map(function (z) { return h('option', { value: z }); }))]));

      // 2 Arbeitszeit
      var weeklyIn = h('input', { type: 'number', min: '1', max: '60', step: '0.5', inputmode: 'decimal', value: String(Math.round(c.defaultWeeklyMin / 6) / 10), 'class': 'tg-input' });
      var dayBoxes = F.dayNames().map(function (n, i) { var cb = h('input', { type: 'checkbox', checked: !!(c.defaultDaysMask & (1 << i)) }); return { cb: cb, el: h('label', { 'class': 'tg-chip' }, cb, h('span', null, n)) }; });
      wrap.appendChild(cfgSection('clock', t('cfg_work_t'), t('cfg_work_p'), [
        ui.field(t('cfg_weekly'), weeklyIn, t('cfg_weekly_h')),
        h('div', { 'class': 'tg-field' }, h('label', null, t('cfg_days')), h('div', { 'class': 'tg-chips' }, dayBoxes.map(function (d) { return d.el; })), h('div', { 'class': 'tg-hint' }, t('cfg_days_h')))]));

      // 3 Aufbewahrung
      var keepIn = h('input', { type: 'number', min: '30', max: '3650', step: '1', inputmode: 'numeric', value: String(c.retentionDays), 'class': 'tg-input' });
      wrap.appendChild(cfgSection('shield', t('cfg_keep_t'), t('cfg_keep_p'), [ui.field(t('cfg_retention'), keepIn, t('cfg_retention_h'))]));

      // 4 Genehmigung
      var self = switchRow(t('cfg_self'), t('cfg_self_h'), c.selfApproval);
      wrap.appendChild(cfgSection('check', t('cfg_appr_t'), t('cfg_appr_p'), [self.el]));

      // 5 Benachrichtigungen
      var live = switchRow(t('cfg_live'), t('cfg_live_h'), c.liveWarnings);
      var notif = switchRow(t('cfg_int_notif'), t('cfg_int_notif_h'), c.integrations.notifications);
      var thList = h('ul', { 'class': 'tg-thr' },
        h('li', null, t('cfg_th_day', { time: core.fmtHM(th.warnDayMin || 570) })), h('li', null, t('cfg_th_break', { time: core.fmtHM(th.warnBreakMin || 345) })), h('li', null, t('cfg_th_forgot', { time: core.fmtHM(th.forgottenMin || 720) })));
      wrap.appendChild(cfgSection('bell', t('cfg_notif_t'), t('cfg_notif_p'), [notif.el, live.el, thList, h('p', { 'class': 'tg-hint' }, t('cfg_th_fixed'))]));

      // 6 Projekte
      var projIn = h('textarea', { rows: '4', 'class': 'tg-input', placeholder: t('cfg_proj_ph') }); projIn.value = (c.projects || []).join('\n');
      wrap.appendChild(cfgSection('edit', t('cfg_proj_t'), t('cfg_proj_p'), [ui.field(t('cfg_proj'), projIn, t('cfg_proj_h'))]));

      // 7 Integrationen
      var cal = switchRow(t('cfg_cal'), t('cfg_cal_h'), c.integrations.calendar);
      var abscal = switchRow(t('cfg_abscal'), t('cfg_abscal_h'), c.integrations.absenceCalendar);
      var deck = switchRow(t('cfg_deck'), t('cfg_deck_h'), c.integrations.deck);
      var stackIn = h('input', { type: 'number', min: '1', step: '1', inputmode: 'numeric', value: c.deckStackId ? String(c.deckStackId) : '', 'class': 'tg-input' });
      var talk = switchRow(t('cfg_talk'), t('cfg_talk_h'), c.integrations.talk);
      var roomIn = h('input', { type: 'text', maxlength: '32', value: c.talkRoomToken || '', 'class': 'tg-input tg-input--mono', autocomplete: 'off', spellcheck: 'false' });
      wrap.appendChild(cfgSection('calendar', t('cfg_int_t'), t('cfg_int_p'), [cal.el, abscal.el, deck.el, ui.field(t('cfg_deck_stack'), stackIn, t('cfg_deck_stack_h')), talk.el, ui.field(t('cfg_talk_room'), roomIn, t('cfg_talk_room_h'))]));

      // 8 Webhook
      var hookIn = h('input', { type: 'url', value: c.webhookUrl || '', placeholder: 'https://', 'class': 'tg-input', autocomplete: 'off', spellcheck: 'false' });
      var secretIn = h('input', { type: 'password', value: '', 'class': 'tg-input', autocomplete: 'new-password' });
      wrap.appendChild(cfgSection('link', t('cfg_hook_t'), t('cfg_hook_p'), [ui.field(t('cfg_hook_url'), hookIn, t('cfg_hook_url_h')), ui.field(t('cfg_hook_secret'), secretIn, c.hasWebhookSecret ? t('cfg_hook_secret_set') : t('cfg_hook_secret_h'))]));

      // 9 Rollen (nur Anzeige)
      var grp = function (label, name, exists, emptyText) {
        return [h('dt', null, label), h('dd', null, name || emptyText, name && exists === false ? h('span', { 'class': 'tg-pill tg-pill--warn' }, t('cfg_group_missing')) : null)];
      };
      var rolesKids = [
        h('dl', { 'class': 'tg-dl' }, grp(t('cfg_role_chef_g'), roles.chefGroup, roles.chefGroupExists, t('cfg_none')), grp(t('cfg_role_team_g'), roles.teamGroup, roles.teamGroupExists, t('cfg_all_users')), grp(t('cfg_role_kiosk_g'), roles.kioskGroup, null, t('cfg_none'))),
        h('div', { 'class': 'tg-cols2' },
          h('div', null, h('b', null, t('cfg_roles_chefs', { n: String((roles.chefs || []).length) })), h('div', { 'class': 'tg-muted' }, (roles.chefs || []).join(', ') || t('cfg_none'))),
          h('div', null, h('b', null, t('cfg_roles_members', { n: String(roles.memberCount || 0) })), h('div', { 'class': 'tg-muted' }, (roles.members || []).join(', ') || t('cfg_none')))),
        h('p', { 'class': 'tg-banner tg-banner--info' }, ui.icon('info', 18), h('span', null, t('cfg_roles_how', { chef: roles.chefGroup || 'tg-chef', team: roles.teamGroup || 'tg-team' }))),
        h('div', { 'class': 'tg-btnrow' }, h('a', { 'class': 'tg-btn tg-btn--sm', href: usersUrl() }, ui.icon('external', 16), t('cfg_roles_link')),
          res.canEditGroups ? h('a', { 'class': 'tg-btn tg-btn--sm', href: adminUrl() }, ui.icon('gear', 16), t('cfg_roles_admin_link')) : null)
      ];
      wrap.appendChild(cfgSection('users', t('cfg_roles_t'), t('cfg_roles_p'), rolesKids));

      // Speichern
      var saveBtn = M.btn(t('cfg_save'), 'check', save, 'tg-btn--primary tg-btn--lg');
      wrap.appendChild(h('div', { 'class': 'tg-savebar' }, errBoxEl, saveBtn));
      function save() {
        errBoxEl.setAttribute('hidden', '');
        var hours = parseFloat(String(weeklyIn.value).replace(',', '.'));
        if (!isFinite(hours) || hours <= 0) { errBoxEl.textContent = t('cfg_err', { fields: t('cfg_weekly') }); errBoxEl.removeAttribute('hidden'); return; }
        var mask = 0; dayBoxes.forEach(function (d, i) { if (d.cb.checked) { mask |= (1 << i); } });
        var body = {
          state: stateSel.value, timezone: tzIn.value.trim(), defaultWeeklyMin: Math.round(hours * 60), defaultDaysMask: mask, retentionDays: parseInt(keepIn.value, 10) || 0,
          projects: projIn.value, selfApproval: self.input.checked, liveWarnings: live.input.checked,
          integrations: { notifications: notif.input.checked, calendar: cal.input.checked, absenceCalendar: abscal.input.checked, deck: deck.input.checked, talk: talk.input.checked },
          deckStackId: stackIn.value.trim() === '' ? null : parseInt(stackIn.value, 10), talkRoomToken: roomIn.value.trim(), webhookUrl: hookIn.value.trim()
        };
        if (secretIn.value !== '') { body.webhookSecret = secretIn.value; }
        saveBtn.disabled = true;
        api.saveConfig(body).then(function () { ui.toast(t('cfg_saved'), 'ok'); ctx.reload(); }, function (err) {
          saveBtn.disabled = false;
          var f = err && err.data && Array.isArray(err.data.fields) ? err.data.fields : [];
          errBoxEl.textContent = (err && err.code === 'validation' && f.length) ? t('cfg_err', { fields: f.map(function (x) { return FIELD_LABEL[x] ? t(FIELD_LABEL[x]) : x; }).join(', ') }) : M.valMsg(err);
          errBoxEl.removeAttribute('hidden');
          if (errBoxEl.scrollIntoView) { errBoxEl.scrollIntoView({ block: 'nearest' }); }
        });
      }
      return wrap;
    });
  } };

  /* ================= HILFE ================= */
  views.help = { title: 'nav_help', render: function (ctx) {
    var me = ctx.me, list = h('div', { 'class': 'tg-faq' });
    var items = [1, 2, 3, 4, 5, 6, 7, 8, 9];
    items.forEach(function (n) {
      if (n === 9 && me.isChef) { return; } // Frage „Warum sehe ich die Konfiguration nicht?“ nur für Mitarbeiter
      list.appendChild(h('details', { 'class': 'tg-faq__i' }, h('summary', null, t('faq_' + n + '_q')), h('p', null, t('faq_' + n + '_a'))));
    });
    return h('div', { 'class': 'tg-grid' },
      M.card(t('help_title'), [h('p', { 'class': 'tg-hint' }, t('help_intro')), list]),
      M.card(t('help_priv_t'), [h('p', null, t('help_priv_p')), h('ul', { 'class': 'tg-bullets' }, [1, 2, 3].map(function (i) { return h('li', null, t('help_priv_' + i)); }))]),
      M.card(t('help_more_t'), h('div', { 'class': 'tg-btnrow' }, h('a', { 'class': 'tg-btn', href: api.page('/go') }, ui.icon('phone', 18), t('go_open')), h('a', { 'class': 'tg-btn', href: '#/devices' }, ui.icon('qr', 18), t('pair_title')))));
  } };
})(typeof self !== 'undefined' ? self : this);
