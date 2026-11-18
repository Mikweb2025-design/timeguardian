/* TimeGuardian – Orientierung auf der Startseite: Rollen-Kachel, Einrichtungs-Checkliste (Chefs), „So funktioniert’s“,
 * Aufforderung zum Koppeln und zur Installation. Alle Texte laufen als Textknoten ins DOM. */
(function (root) {
  'use strict';
  var TG = root.TG = root.TG || {};
  var core = TG.core, ui = TG.ui, api = TG.api, h = ui.h, M = TG.main;
  var t = function (k, p) { return TG.i18n.t(k, p); };

  function roleCard(me, setup) {
    var roles = (setup && setup.roles) || {};
    var chefs = roles.chefs || [];
    var title = me.isChef ? t('role_chef_member') : t('role_member');
    var rows = [
      h('dt', null, t('role_team_l')), h('dd', null, roles.teamGroup ? roles.teamGroup : t('role_everybody')),
      h('dt', null, t('role_chefs_l')), h('dd', null, chefs.length ? chefs.join(', ') : t('role_no_chef'))
    ];
    return h('section', { 'class': 'tg-card tg-role', 'aria-label': t('role_label') },
      h('span', { 'class': 'tg-role__ic' }, ui.icon(me.isChef ? 'shield' : 'user', 28)),
      h('div', { 'class': 'tg-role__body' },
        h('div', { 'class': 'tg-role__t' }, h('span', { 'class': 'tg-muted' }, t('role_you')), ' ', h('b', null, title)),
        h('p', { 'class': 'tg-role__p' }, me.isChef ? t('role_chef_p') : t('role_member_p')),
        h('dl', { 'class': 'tg-dl tg-role__dl' }, rows)));
  }

  function stepLink(id, setup) {
    if (id === 'roles' || id === 'region') { return h('a', { 'class': 'tg-btn tg-btn--sm', href: '#/config' }, ui.icon('tune', 16), t('chk_open_config')); }
    if (id === 'kiosk') { return h('a', { 'class': 'tg-btn tg-btn--sm', href: api.page('/kiosk') }, ui.icon('qr', 16), t('open_kiosk')); }
    if (id === 'phone') { return h('a', { 'class': 'tg-btn tg-btn--sm', href: '#/devices' }, ui.icon('phone', 16), t('chk_pair')); }
    if (id === 'install' && TG.pwa && TG.pwa.prompt) { return h('button', { type: 'button', 'class': 'tg-btn tg-btn--sm', onclick: function () { TG.pwa.install(); } }, ui.icon('download', 16), t('inst_btn')); }
    if (id === 'install') { return h('a', { 'class': 'tg-btn tg-btn--sm', href: '#/devices' }, ui.icon('download', 16), t('chk_install_how')); }
    return null;
  }

  function stepText(s, done) {
    var p = s.params || {};
    if (s.id === 'kiosk' && done && p.seenAt) { return t('chk_kiosk_d', { when: core.fmtDateTime(new Date(p.seenAt * 1000).toISOString(), TG.i18n.lang(), TG.main.S.me && TG.main.S.me.timezone) }); }
    return t('chk_' + s.id + (done ? '_d' : '_t'), { chefs: String(p.chefs || 0), members: String(p.members || 0), devices: String(p.devices || 0) });
  }

  function checklist(setup) {
    var c = setup && setup.checklist;
    if (!c) { return null; }
    var installed = TG.pwa ? TG.pwa.isInstalled() : false;
    var ul = h('ul', { 'class': 'tg-chk' });
    c.steps.forEach(function (s, i) {
      var done = s.kind === 'hint' ? installed : s.done === true;
      ul.appendChild(h('li', { 'class': 'tg-chk__i' + (done ? ' is-done' : '') },
        h('span', { 'class': 'tg-chk__box', 'aria-hidden': 'true' }, done ? ui.icon('checkc', 26) : ui.icon('circle', 26)),
        h('div', { 'class': 'tg-chk__body' },
          h('div', { 'class': 'tg-chk__t' }, (i + 1) + '. ' + t('chk_' + s.id), s.kind === 'hint' ? h('span', { 'class': 'tg-pill' }, t('chk_optional')) : null, h('span', { 'class': 'sr-only' }, done ? ' ' + t('chk_done_sr') : ' ' + t('chk_todo_sr'))),
          h('div', { 'class': 'tg-hint' }, stepText(s, done)),
          done ? null : stepLink(s.id, setup))));
    });
    var pct = c.required ? Math.round(100 * c.doneCount / c.required) : 100;
    var bar = h('div', { 'class': 'tg-prog', role: 'progressbar', 'aria-valuemin': '0', 'aria-valuemax': String(c.required), 'aria-valuenow': String(c.doneCount), 'aria-label': t('chk_title') }, h('i', { style: 'width:' + pct + '%' }));
    if (c.complete) {
      return h('details', { 'class': 'tg-card tg-check tg-check--done' },
        h('summary', null, ui.icon('checkc', 22), h('b', null, t('chk_complete')), h('span', { 'class': 'tg-muted' }, ' ' + t('chk_show'))), ul);
    }
    return h('section', { 'class': 'tg-card tg-check' },
      h('header', { 'class': 'tg-section__head' }, h('h2', null, t('chk_title')), h('span', { 'class': 'tg-pill tg-pill--info' }, t('chk_progress', { done: String(c.doneCount), total: String(c.required) }))),
      h('p', { 'class': 'tg-hint' }, t('chk_intro')), bar, ul);
  }

  function how(me, open) {
    var step = function (ic, n) {
      return h('li', { 'class': 'tg-how__i' }, h('span', { 'class': 'tg-how__ic' }, ui.icon(ic, 30)), h('b', null, t('how_' + n + '_t')), h('span', { 'class': 'tg-muted' }, t('how_' + n + '_p')));
    };
    return h('details', { 'class': 'tg-card tg-how', open: open ? '' : null },
      h('summary', { 'class': 'tg-how__sum' }, ui.icon('help', 22), h('h2', null, t('how_title'))),
      h('ol', { 'class': 'tg-how__list' }, step('qr', 1), step('camera', 2), step('clock', 3)),
      h('p', { 'class': 'tg-hint' }, me.isChef ? [t('how_chef') + ' ', h('a', { href: '#/team' }, t('nav_team'))] : t('how_member')));
  }

  function pairCta(me) {
    if (me.deviceCount > 0) { return null; }
    return h('section', { 'class': 'tg-card tg-cta' },
      h('span', { 'class': 'tg-role__ic' }, ui.icon('phone', 28)),
      h('div', { 'class': 'tg-cta__body' }, h('h2', null, t('cta_pair_t')), h('p', { 'class': 'tg-hint' }, t('cta_pair_p')),
        h('a', { 'class': 'tg-btn tg-btn--primary', href: '#/devices' }, ui.icon('qr', 18), t('pair_title'))));
  }

  /* Reihenfolge: Rolle, (Chef: Checkliste), Telefon koppeln, installieren, Erklärung */
  function intro(ctx, me, setup) {
    var out = [roleCard(me, setup)];
    out.push(checklist(setup));
    out.push(pairCta(me));
    out.push(TG.install ? TG.install.card({ dismissable: true }) : null);
    var c = setup && setup.checklist;
    out.push(how(me, me.deviceCount === 0 || !!(c && !c.complete)));
    return out.filter(function (n) { return !!n; });
  }

  TG.home = { intro: intro, roleCard: roleCard, checklist: checklist, how: how };
})(typeof self !== 'undefined' ? self : this);
