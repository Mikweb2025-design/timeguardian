/* TimeGuardian – Karte „Als App installieren“: Chrome/Edge/Android per Install-Button, iPhone/iPad per Kurz-Anleitung. */
(function (root) {
  'use strict';
  var TG = root.TG = root.TG || {};
  var ui = TG.ui, h = ui.h, s = ui.s;
  var t = function (k, p) { return TG.i18n.t(k, p); };
  var HIDE_KEY = 'tg.install.hide';

  function hiddenByUser() { return TG.api.store('get', HIDE_KEY) === '1'; }

  /* Nachgebaute Safari-Leiste (iPhone): die Schaltfläche „Teilen“ ist hervorgehoben */
  function iosBar() {
    var ic = function (x, d) { return s('path', { d: d, transform: 'translate(' + x + ' 16) scale(1.1)', fill: 'currentColor', opacity: '.55' }); };
    return s('svg', { viewBox: '0 0 300 60', width: '100%', height: 60, role: 'img', 'aria-label': t('inst_ios_bar'), 'class': 'tg-inst-bar' },
      s('rect', { x: 2, y: 4, width: 296, height: 52, rx: 16, 'class': 'tg-inst-bar__bg' }),
      ic(24, 'M15.4 7.4 14 6l-6 6 6 6 1.4-1.4L10.8 12z'),
      ic(78, 'M8.6 16.6 10 18l6-6-6-6-1.4 1.4 4.6 4.6z'),
      s('circle', { cx: 150, cy: 30, r: 21, 'class': 'tg-inst-bar__hl' }),
      s('path', { d: 'M12 2 8 6l1.4 1.4L11 5.8V15h2V5.8l1.6 1.6L16 6zM6 10a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2h-2v2h2v8H6v-8h2v-2z', transform: 'translate(137.5 17.5) scale(1.08)', fill: 'currentColor', 'class': 'tg-inst-bar__share' }),
      ic(204, 'M4 5a2 2 0 0 1 2-2h12v16H6a2 2 0 0 0-2 2zm4 2v2h8V7z'),
      ic(256, 'M4 6h10v10H4zm4-4h10v10h-2V4H8z'));
  }

  function iosSteps(safari) {
    var step = function (n, ic, txt, extra) {
      return h('li', { 'class': 'tg-inst-step' }, h('span', { 'class': 'tg-inst-pic' }, ui.icon(ic, 26)), h('span', { 'class': 'tg-inst-txt' }, h('b', null, n + '. '), txt, extra || null));
    };
    return [
      safari ? null : h('p', { 'class': 'tg-banner tg-banner--warn' }, ui.icon('warn', 18), h('span', null, t('inst_ios_safari'))),
      iosBar(),
      h('ol', { 'class': 'tg-inst-steps' },
        step(1, 'share', t('inst_ios_1')),
        step(2, 'addhome', t('inst_ios_2')),
        step(3, 'check', t('inst_ios_3'), h('span', { 'class': 'tg-inst-pill' }, t('inst_ios_add')))),
      h('p', { 'class': 'tg-note' }, t('inst_ios_note'))
    ];
  }

  /* opts: {dismissable: bool}. Gibt null zurück, wenn nichts anzuzeigen ist (läuft schon als App, ausgeblendet). */
  function card(opts) {
    opts = opts || {};
    var pwa = TG.pwa;
    if (!pwa || pwa.standalone()) { return null; }
    if (opts.dismissable && hiddenByUser()) { return null; }
    var box = h('section', { 'class': 'tg-card tg-install', id: 'tg-install' });
    function head(title, text) {
      return h('div', { 'class': 'tg-install__head' }, h('span', { 'class': 'tg-install__ic' }, ui.icon('phone', 26)), h('div', null, h('h2', null, title), h('p', { 'class': 'tg-hint' }, text)));
    }
    function render() {
      if (pwa.standalone()) { if (box.parentNode) { box.parentNode.removeChild(box); } return; }
      var p = pwa.platform(), kids = [];
      if (pwa.installedFlag()) {
        kids.push(head(t('inst_done_t'), t('inst_done_p')));
      } else if (pwa.prompt) {
        kids.push(head(t('inst_title'), t('inst_text')));
        kids.push(h('div', { 'class': 'tg-btnrow' }, h('button', { type: 'button', 'class': 'tg-btn tg-btn--primary tg-btn--lg tg-install__btn', onclick: function () { pwa.install(); } }, ui.icon('download', 20), t('inst_btn'))));
      } else if (p.ios) {
        kids.push(head(t('inst_title'), t('inst_text')));
        iosSteps(p.safari).forEach(function (n) { kids.push(n); });
      } else {
        kids.push(head(t('inst_title'), t('inst_text')));
        kids.push(h('p', { 'class': 'tg-hint' }, p.chromium ? t('inst_manual_chrome') : t('inst_manual_other')));
      }
      if (opts.dismissable && !pwa.installedFlag()) {
        kids.push(h('div', { 'class': 'tg-install__foot' }, h('button', { type: 'button', 'class': 'tg-link', onclick: function () { TG.api.store('set', HIDE_KEY, '1'); if (box.parentNode) { box.parentNode.removeChild(box); } } }, t('inst_hide'))));
      }
      ui.mount(box, kids);
    }
    pwa.onChange(render);
    render();
    return box;
  }

  TG.install = { card: card, iosSteps: iosSteps };
})(typeof self !== 'undefined' ? self : this);
