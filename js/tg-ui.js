/* TimeGuardian – DOM-Helfer. Nutzerdaten gelangen nur über Textknoten/Attribute ins DOM, nie über innerHTML. */
(function (root) {
  'use strict';
  var TG = root.TG = root.TG || {};
  var doc = root.document;
  var SVGNS = 'http://www.w3.org/2000/svg';

  function apply(el, attrs) {
    if (!attrs) { return el; }
    Object.keys(attrs).forEach(function (k) {
      var v = attrs[k];
      if (v === null || v === undefined || v === false) { return; }
      if (k === 'class') { el.setAttribute('class', v); }
      else if (k === 'text') { el.textContent = v; }
      else if (k === 'dataset') { Object.keys(v).forEach(function (d) { el.dataset[d] = v[d]; }); }
      else if (k.slice(0, 2) === 'on' && typeof v === 'function') { el.addEventListener(k.slice(2), v); }
      else if (k === 'value') { el.value = v; }
      else if (k === 'checked' || k === 'disabled' || k === 'selected') { el[k] = !!v; if (v) { el.setAttribute(k, ''); } }
      else { el.setAttribute(k, v === true ? '' : String(v)); }
    });
    return el;
  }
  function append(el, kids) {
    (kids || []).forEach(function (c) {
      if (c === null || c === undefined || c === false) { return; }
      if (Array.isArray(c)) { append(el, c); return; }
      el.appendChild(c && c.nodeType ? c : doc.createTextNode(String(c)));
    });
    return el;
  }
  function h(tag, attrs) {
    var kids = Array.prototype.slice.call(arguments, 2);
    if (attrs && (attrs.nodeType || Array.isArray(attrs) || typeof attrs === 'string' || typeof attrs === 'number')) { kids.unshift(attrs); attrs = null; }
    return append(apply(doc.createElement(tag), attrs), kids);
  }
  function s(tag, attrs) {
    var kids = Array.prototype.slice.call(arguments, 2);
    if (attrs && (attrs.nodeType || Array.isArray(attrs) || typeof attrs === 'string')) { kids.unshift(attrs); attrs = null; }
    var el = doc.createElementNS(SVGNS, tag);
    if (attrs) {
      Object.keys(attrs).forEach(function (k) {
        var v = attrs[k];
        if (v === null || v === undefined || v === false) { return; }
        if (k === 'text') { el.textContent = v; } else { el.setAttribute(k, String(v)); }
      });
    }
    return append(el, kids);
  }
  function clear(el) { while (el.firstChild) { el.removeChild(el.firstChild); } return el; }
  function mount(el, kids) { clear(el); return append(el, Array.isArray(kids) ? kids : [kids]); }

  /* ---------- Icons (statische Pfade, 24x24) ---------- */
  var ICONS = {
    play: 'M8 5v14l11-7z',
    stop: 'M6 6h12v12H6z',
    pause: 'M6 5h4v14H6zM14 5h4v14h-4z',
    check: 'M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4z',
    close: 'M19 6.4 17.6 5 12 10.6 6.4 5 5 6.4 10.6 12 5 17.6 6.4 19 12 13.4 17.6 19 19 17.6 13.4 12z',
    plus: 'M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6z',
    clock: 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm0 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16zm.5-13H11v6l5.2 3.1.8-1.3-4.5-2.7z',
    qr: 'M3 3h8v8H3zm2 2v4h4V5zm8-2h8v8h-8zm2 2v4h4V5zM3 13h8v8H3zm2 2v4h4v-4zm8-2h2v2h-2zm2 2h2v2h-2zm-2 2h2v2h-2zm4-4h2v2h-2zm0 4h2v2h-2zm-2 2h2v2h-2zm4 0h2v2h-2z',
    camera: 'M9 3 7.2 5H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-3.2L15 3zm3 15a5 5 0 1 1 0-10 5 5 0 0 1 0 10zm0-2a3 3 0 1 0 0-6 3 3 0 0 0 0 6z',
    phone: 'M17 1H7a2 2 0 0 0-2 2v18a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2zm0 18H7V5h10zm-5 3a1 1 0 1 1 0-2 1 1 0 0 1 0 2z',
    fullscreen: 'M7 14H5v5h5v-2H7zm-2-4h2V7h3V5H5zm12 7h-3v2h5v-5h-2zM14 5v2h3v3h2V5z',
    print: 'M19 8H5a3 3 0 0 0-3 3v6h4v4h12v-4h4v-6a3 3 0 0 0-3-3zm-3 11H8v-5h8zm3-7a1 1 0 1 1 0-2 1 1 0 0 1 0 2zM18 3H6v4h12z',
    download: 'M5 20h14v-2H5zM19 9h-4V3H9v6H5l7 7z',
    warn: 'M1 21h22L12 2zm12-3h-2v-2h2zm0-4h-2v-4h2z',
    info: 'M11 7h2v2h-2zm0 4h2v6h-2zm1-9a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm0 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16z',
    offline: 'M24 8.98C20.93 5.9 16.69 4 12 4S3.07 5.9 0 8.98L12 21 24 8.98zM2.92 9.07C5.51 7.08 8.67 6 12 6s6.49 1.08 9.08 3.07L12 18.17z',
    trash: 'M6 19a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V7H6zM19 4h-3.5l-1-1h-5l-1 1H5v2h14z',
    edit: 'M3 17.25V21h3.75L17.8 9.94l-3.75-3.75zM20.7 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75z',
    refresh: 'M17.65 6.35A8 8 0 1 0 19.74 14h-2.08A6 6 0 1 1 12 6c1.66 0 3.14.69 4.22 1.78L13 11h7V4z',
    users: 'M16 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm-8 0a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm0 2c-2.3 0-7 1.2-7 3.5V19h14v-2.5C15 14.2 10.3 13 8 13zm8 0c-.3 0-.6 0-1 .1 1.2.8 2 1.9 2 3.4V19h6v-2.5c0-2.3-4.7-3.5-7-3.5z',
    home: 'M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z',
    calendar: 'M19 4h-1V2h-2v2H8V2H6v2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zm0 16H5V9h14zM7 11h5v5H7z',
    chart: 'M5 9.2h3V19H5zM10.6 5h2.8v14h-2.8zm5.6 8H19v6h-2.8z',
    gear: 'M19.4 13a7.7 7.7 0 0 0 0-2l2.1-1.6a.5.5 0 0 0 .1-.6l-2-3.5a.5.5 0 0 0-.6-.2l-2.5 1a7.3 7.3 0 0 0-1.7-1l-.4-2.7a.5.5 0 0 0-.5-.4h-4a.5.5 0 0 0-.5.4L9 5.5a7.3 7.3 0 0 0-1.7 1l-2.5-1a.5.5 0 0 0-.6.2l-2 3.5a.5.5 0 0 0 .1.6L4.6 11a7.7 7.7 0 0 0 0 2l-2.1 1.6a.5.5 0 0 0-.1.6l2 3.5c.1.2.4.3.6.2l2.5-1c.5.4 1.1.7 1.7 1l.4 2.7c0 .2.2.4.5.4h4c.3 0 .5-.2.5-.4l.4-2.7c.6-.3 1.2-.6 1.7-1l2.5 1c.2.1.5 0 .6-.2l2-3.5a.5.5 0 0 0-.1-.6zM12 15.5a3.5 3.5 0 1 1 0-7 3.5 3.5 0 0 1 0 7z',
    sun: 'M6.8 4.4 5.4 3 4 4.4l1.4 1.4zM1 13h3v-2H1zm10-9h2V1h-2zm8.6 1.8L21 4.4 19.6 3l-1.4 1.4zM17.2 19.2l1.4 1.4 1.4-1.4-1.4-1.4zM20 11v2h3v-2zM12 6a6 6 0 1 0 0 12 6 6 0 0 0 0-12zM11 23h2v-3h-2zM3 19.6 4.4 21l1.4-1.4-1.4-1.4z',
    bell: 'M12 22a2 2 0 0 0 2-2h-4a2 2 0 0 0 2 2zm6-6v-5c0-3.1-1.6-5.6-4.5-6.3V4a1.5 1.5 0 0 0-3 0v.7C7.6 5.4 6 7.9 6 11v5l-2 2v1h16v-1z',
    arrowL: 'M15.4 7.4 14 6l-6 6 6 6 1.4-1.4L10.8 12z',
    arrowR: 'M8.6 16.6 10 18l6-6-6-6-1.4 1.4 4.6 4.6z',
    share: 'M12 2 8 6l1.4 1.4L11 5.8V15h2V5.8l1.6 1.6L16 6zM6 10a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2h-2v2h2v8H6v-8h2v-2z',
    addhome: 'M19 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2zm-2 10h-4v4h-2v-4H7v-2h4V7h2v4h4z',
    tune: 'M3 17v2h6v-2H3zM3 5v2h10V5H3zm10 16v-2h8v-2h-8v-2h-2v6h2zM7 9v2H3v2h4v2h2V9H7zm14 4v-2H11v2h10zm-6-4h2V7h4V5h-4V3h-2v6z',
    help: 'M11 18h2v-2h-2v2zm1-16C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm0-14c-2.21 0-4 1.79-4 4h2c0-1.1.9-2 2-2s2 .9 2 2c0 2-3 1.75-3 5h2c0-2.25 3-2.5 3-5 0-2.21-1.79-4-4-4z',
    checkc: 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm-2 15-5-5 1.4-1.4L10 14.2l7.6-7.6L19 8z',
    circle: 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm0 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16z',
    external: 'M19 19H5V5h7V3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14c1.1 0 2-.9 2-2v-7h-2zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3z',
    shield: 'M12 1 3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5zm-1 15-4-4 1.4-1.4L11 13.2l4.6-4.6L17 10z',
    user: 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z',
    link: 'M3.9 12a3.1 3.1 0 0 1 3.1-3.1h4V7H7a5 5 0 0 0 0 10h4v-1.9H7A3.1 3.1 0 0 1 3.9 12zM8 13h8v-2H8zm9-6h-4v1.9h4a3.1 3.1 0 0 1 0 6.2h-4V17h4a5 5 0 0 0 0-10z'
  };
  function icon(name, size, cls) {
    var sz = size || 20;
    return s('svg', { viewBox: '0 0 24 24', width: sz, height: sz, 'aria-hidden': 'true', focusable: 'false', 'class': 'tg-icon' + (cls ? ' ' + cls : '') }, s('path', { d: ICONS[name] || ICONS.info, fill: 'currentColor' }));
  }

  /* ---------- Toasts ---------- */
  var toastHost = null;
  function toast(msg, kind, ms) {
    if (!toastHost) {
      toastHost = h('div', { 'class': 'tg-toasts', role: 'status', 'aria-live': 'polite' });
      doc.body.appendChild(toastHost);
    }
    var el = h('div', { 'class': 'tg-toast' + (kind ? ' tg-toast--' + kind : '') }, String(msg));
    toastHost.appendChild(el);
    root.setTimeout(function () { el.classList.add('is-out'); root.setTimeout(function () { if (el.parentNode) { el.parentNode.removeChild(el); } }, 300); }, ms || 3800);
  }

  /* ---------- Dialog (Bestätigung/Formular) ---------- */
  function modal(title, bodyEl, buttons) {
    var handle = {};
    var p = new Promise(function (resolve) {
      var prev = doc.activeElement;
      var close = function (v) {
        if (back.parentNode) { back.parentNode.removeChild(back); }
        doc.removeEventListener('keydown', onKey, true);
        if (prev && prev.focus) { try { prev.focus(); } catch (e) { /* ignore */ } }
        resolve(v);
      };
      handle.close = close;
      var onKey = function (e) {
        if (e.key === 'Escape') { e.stopPropagation(); close(null); }
        else if (e.key === 'Tab') {
          var f = box.querySelectorAll('button, input, select, textarea, [href]');
          if (!f.length) { return; }
          var first = f[0], last = f[f.length - 1];
          if (e.shiftKey && doc.activeElement === first) { e.preventDefault(); last.focus(); }
          else if (!e.shiftKey && doc.activeElement === last) { e.preventDefault(); first.focus(); }
        }
      };
      var btns = (buttons || []).map(function (b) {
        return h('button', { type: 'button', 'class': 'tg-btn' + (b.primary ? ' tg-btn--primary' : '') + (b.danger ? ' tg-btn--danger' : ''), onclick: function () {
          if (b.action) { b.action(box, close); return; }
          if (b.validate && !b.validate(box)) { return; }
          close(b.value === undefined ? true : (typeof b.value === 'function' ? b.value(box) : b.value));
        } }, b.label);
      });
      var box = h('div', { 'class': 'tg-modal', role: 'dialog', 'aria-modal': 'true', 'aria-label': title },
        h('h2', { 'class': 'tg-modal__title' }, title), h('div', { 'class': 'tg-modal__body' }, bodyEl), h('div', { 'class': 'tg-modal__actions' }, btns));
      var back = h('div', { 'class': 'tg-modal-back', onclick: function (e) { if (e.target === back) { close(null); } } }, box);
      doc.body.appendChild(back);
      doc.addEventListener('keydown', onKey, true);
      var inp = box.querySelector('input, select, textarea');
      var focusEl = inp || box.querySelector('.tg-btn--primary') || box.querySelector('button');
      if (focusEl) { focusEl.focus(); }
    });
    p.close = function (v) { if (handle.close) { handle.close(v === undefined ? null : v); } };
    return p;
  }
  function confirmDialog(title, text, okLabel, cancelLabel, danger) {
    return modal(title, h('p', null, text), [
      { label: cancelLabel, value: false },
      { label: okLabel, primary: !danger, danger: !!danger, value: true }
    ]).then(function (v) { return v === true; });
  }

  /* ---------- Haptik / Ton ---------- */
  function vibrate(p) { try { if (root.navigator && root.navigator.vibrate) { root.navigator.vibrate(p); } } catch (e) { /* ignore */ } }
  var actx = null;
  function beep(kind) {
    try {
      var AC = root.AudioContext || root.webkitAudioContext;
      if (!AC) { return; }
      actx = actx || new AC();
      var seq = kind === 'error' ? [[220, 0, 0.18], [180, 0.2, 0.22]] : kind === 'out' ? [[660, 0, 0.12], [440, 0.14, 0.18]] : [[520, 0, 0.12], [780, 0.14, 0.2]];
      seq.forEach(function (n) {
        var o = actx.createOscillator(), g = actx.createGain(), t = actx.currentTime + n[1];
        o.type = 'sine'; o.frequency.value = n[0];
        g.gain.setValueAtTime(0.0001, t); g.gain.exponentialRampToValueAtTime(0.18, t + 0.02); g.gain.exponentialRampToValueAtTime(0.0001, t + n[2]);
        o.connect(g); g.connect(actx.destination); o.start(t); o.stop(t + n[2] + 0.02);
      });
    } catch (e) { /* ignore */ }
  }

  /* ---------- Sonstiges ---------- */
  function debounce(fn, ms) { var t; return function () { var a = arguments, c = this; root.clearTimeout(t); t = root.setTimeout(function () { fn.apply(c, a); }, ms); }; }
  function field(label, input, hint, id) {
    id = id || ('f' + Math.random().toString(36).slice(2, 8));
    input.setAttribute('id', id);
    return h('div', { 'class': 'tg-field' }, h('label', { 'for': id }, label), input, hint ? h('div', { 'class': 'tg-hint' }, hint) : null);
  }
  function selectEl(options, value, attrs) {
    var sel = h('select', attrs || {});
    options.forEach(function (o) { var op = h('option', { value: o[0] }, o[1]); if (String(o[0]) === String(value)) { op.selected = true; } sel.appendChild(op); });
    return sel;
  }
  function tabs(items, active, onSelect) {
    var wrap = h('div', { 'class': 'tg-seg', role: 'tablist' });
    items.forEach(function (it) {
      wrap.appendChild(h('button', { type: 'button', role: 'tab', 'aria-selected': String(it[0] === active), 'class': 'tg-seg__btn' + (it[0] === active ? ' is-active' : ''), onclick: function () { onSelect(it[0]); } }, it[1]));
    });
    return wrap;
  }

  /* Gelber Hinweis bei verdächtig langer Pause / vergessenem Ausstempeln.
   * o: { kind: 'pause'|'work', disabled, onResume, onOut, fixHref } */
  function stateAlert(o) {
    var tr = function (k) { return TG.i18n.t(k); };
    var acts = [];
    if (o.kind === 'pause') { acts.push(h('button', { type: 'button', 'class': 'tg-btn tg-btn--primary tg-btn--go', disabled: o.disabled ? true : null, onclick: o.onResume }, icon('play', 18), tr('act_resume_now'))); }
    acts.push(h('button', { type: 'button', 'class': 'tg-btn' + (o.kind === 'pause' ? '' : ' tg-btn--primary tg-btn--stop'), disabled: o.disabled ? true : null, onclick: o.onOut }, icon('stop', 18), tr('act_out_now')));
    return h('div', { 'class': 'tg-banner tg-banner--warn tg-alert', role: 'alert' }, icon('warn', 20),
      h('div', { 'class': 'tg-alert__body' },
        h('b', null, tr(o.kind === 'pause' ? 'warn_pause_long' : 'warn_work_long')),
        h('div', { 'class': 'tg-alert__act' }, acts),
        h('a', { 'class': 'tg-link', href: o.fixHref }, icon('edit', 16), tr('corr_fix')),
        h('div', { 'class': 'tg-hint' }, tr('corr_fix_hint'))));
  }

  TG.ui = { stateAlert: stateAlert, h: h, s: s, clear: clear, mount: mount, icon: icon, toast: toast, modal: modal, confirm: confirmDialog, vibrate: vibrate, beep: beep, debounce: debounce, field: field, select: selectEl, tabs: tabs, SVGNS: SVGNS };
})(typeof self !== 'undefined' ? self : this);
