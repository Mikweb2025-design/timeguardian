/* TimeGuardian – SVG-Diagramme (reines SVG via createElementNS, Farben über CSS-Klassen/Variablen). */
(function (root) {
  'use strict';
  var TG = root.TG = root.TG || {};
  var core = TG.core, ui = TG.ui, s = ui.s;
  var t = function (k, p) { return TG.i18n.t(k, p); };

  function svgRoot(w, h, label, cls) {
    return s('svg', { viewBox: '0 0 ' + w + ' ' + h, 'class': 'tg-chart ' + (cls || ''), role: 'img', 'aria-label': label || '', preserveAspectRatio: 'xMidYMid meet' });
  }
  function text(x, y, str, cls, anchor) {
    return s('text', { x: x, y: y, 'class': 'tg-c-text ' + (cls || ''), 'text-anchor': anchor || 'start' }, String(str));
  }
  function hLabel(min, lang) { return Math.round(min / 60) + ''; }

  /* ---------- Ringe ---------- */
  // opts: {size, rings:[{frac, cls, r, width}], marks:[{frac, r}]}; Kinder (Mitte) übernimmt der Aufrufer per HTML-Overlay
  function ring(opts) {
    var size = opts.size || 220, c = size / 2;
    var svg = s('svg', { viewBox: '0 0 ' + size + ' ' + size, width: size, height: size, 'class': 'tg-ring', 'aria-hidden': 'true' });
    var parts = [];
    (opts.rings || []).forEach(function (r) {
      var geo = core.ringGeometry(r.r, r.frac);
      parts.push(s('circle', { cx: c, cy: c, r: r.r, fill: 'none', 'stroke-width': r.width, 'class': 'tg-ring__track' }));
      var arc = s('circle', { cx: c, cy: c, r: r.r, fill: 'none', 'stroke-width': r.width, 'stroke-linecap': 'round', 'class': 'tg-ring__arc ' + (r.cls || ''), 'stroke-dasharray': geo.dash + ' ' + geo.gap, transform: 'rotate(-90 ' + c + ' ' + c + ')' });
      parts.push(arc);
      (r.marks || []).forEach(function (f) {
        var a = f * 2 * Math.PI - Math.PI / 2;
        var x1 = c + Math.cos(a) * (r.r - r.width / 2 - 1), y1 = c + Math.sin(a) * (r.r - r.width / 2 - 1);
        var x2 = c + Math.cos(a) * (r.r + r.width / 2 + 1), y2 = c + Math.sin(a) * (r.r + r.width / 2 + 1);
        parts.push(s('line', { x1: x1, y1: y1, x2: x2, y2: y2, 'class': 'tg-ring__mark', 'stroke-width': 2 }));
      });
    });
    parts.forEach(function (p) { svg.appendChild(p); });
    return svg;
  }
  function updateRing(svg, fracs, levels) {
    var arcs = svg.querySelectorAll('.tg-ring__arc');
    Array.prototype.forEach.call(arcs, function (a, i) {
      var r = +a.getAttribute('r'), geo = core.ringGeometry(r, fracs[i] || 0);
      a.setAttribute('stroke-dasharray', geo.dash + ' ' + geo.gap);
      if (levels && levels[i]) { a.setAttribute('class', 'tg-ring__arc tg-lv-' + levels[i]); }
    });
  }

  /* ---------- Balken (Woche/Monat) ---------- */
  // days: [{date,label,net,flag:'bad'|'hint'|'',absent,today,weekend}], opts: {W,H,dayTarget,dayLimit,showValues,labelEvery}
  function bars(days, opts) {
    opts = opts || {};
    var W = opts.W || 600, H = opts.H || 260;
    var items = days.map(function (d) { return { label: d.label, value: d.net || 0, flag: d.flag, day: d }; });
    var L = core.barLayout(items, W, H, { padL: 34, padR: 8, padT: 16, padB: 28, minMax: 600, step: 120, maxBar: opts.maxBar || 46, fill: opts.fill || 0.62 });
    var svg = svgRoot(W, H, opts.label || t('chart_hours'), 'tg-chart--bars');
    core.yTicks(L.top, 120).forEach(function (v) {
      var y = L.y(v);
      svg.appendChild(s('line', { x1: L.padL, x2: W - 8, y1: y, y2: y, 'class': 'tg-c-grid' }));
      svg.appendChild(text(L.padL - 6, y + 3.5, hLabel(v), 'tg-c-axis', 'end'));
    });
    if (opts.dayTarget) { var yt = L.y(opts.dayTarget); svg.appendChild(s('line', { x1: L.padL, x2: W - 8, y1: yt, y2: yt, 'class': 'tg-c-target' })); }
    if (opts.dayLimit && opts.dayLimit <= L.top) {
      var yl = L.y(opts.dayLimit);
      svg.appendChild(s('line', { x1: L.padL, x2: W - 8, y1: yl, y2: yl, 'class': 'tg-c-limit' }));
      svg.appendChild(text(W - 10, yl - 4, '10 h', 'tg-c-limit-label', 'end'));
    }
    var every = opts.labelEvery || 1;
    L.bars.forEach(function (b) {
      var d = b.item.day, g = s('g', { 'class': 'tg-c-col' + (d.today ? ' is-today' : '') + (d.weekend ? ' is-weekend' : '') });
      if (d.weekend) { g.appendChild(s('rect', { x: b.cx - (W - L.padL - 8) / items.length / 2, y: L.padT, width: (W - L.padL - 8) / items.length, height: L.ih, 'class': 'tg-c-weekend' })); }
      var cls = 'tg-c-bar' + (d.flag === 'bad' ? ' tg-c-bar--bad' : d.flag === 'hint' ? ' tg-c-bar--hint' : '') + (d.today ? ' tg-c-bar--today' : '');
      if (b.h > 0) {
        var r = s('rect', { x: b.x, y: b.y, width: b.w, height: b.h, rx: Math.min(5, b.w / 3), 'class': cls });
        r.appendChild(s('title', null, d.tip || (d.label + ': ' + core.fmtHM(d.net))));
        g.appendChild(r);
        if (opts.showValues !== false && (opts.showValues || b.w >= 20)) { g.appendChild(text(b.cx, b.y - 4, core.fmtH(d.net, TG.i18n.lang()).replace(' h', ''), 'tg-c-val' + (d.flag === 'bad' ? ' tg-c-val--bad' : ''), 'middle')); }
      } else if (d.absent) {
        g.appendChild(s('rect', { x: b.x, y: L.padT + L.ih - 6, width: b.w, height: 6, rx: 3, 'class': 'tg-c-absent' }));
      }
      if (d.absent && b.h > 0) { g.appendChild(s('circle', { cx: b.cx, cy: L.padT + L.ih + 3, r: 2.5, 'class': 'tg-c-absent-dot' })); }
      if (b.index % every === 0) { g.appendChild(text(b.cx, H - 9, d.label, 'tg-c-axis' + (d.weekend ? ' tg-c-axis--we' : '') + (d.today ? ' tg-c-axis--today' : ''), 'middle')); }
      svg.appendChild(g);
    });
    return svg;
  }

  /* ---------- Tagesblöcke (erster Beginn bis letztes Ende) ---------- */
  // days: [{label, startMin, endMin, net, flag, today, weekend}]
  function dayBlocks(days, opts) {
    opts = opts || {};
    var W = opts.W || 600, H = opts.H || 280, padL = 30, padT = 18, padB = 26, ih = H - padT - padB, iw = W - padL - 8;
    var svg = svgRoot(W, H, opts.label || t('chart_blocks'), 'tg-chart--blocks');
    var t0 = 5 * 60, t1 = 24 * 60;
    [6, 9, 12, 15, 18, 21, 24].forEach(function (hr) {
      var y = padT + (hr * 60 - t0) / (t1 - t0) * ih;
      svg.appendChild(s('line', { x1: padL, x2: W - 8, y1: y, y2: y, 'class': 'tg-c-grid' }));
      svg.appendChild(text(padL - 6, y + 3.5, String(hr), 'tg-c-axis', 'end'));
    });
    var cw = iw / days.length;
    days.forEach(function (d, i) {
      var cx = padL + cw * i + cw / 2;
      svg.appendChild(text(cx, H - 9, d.label, 'tg-c-axis' + (d.weekend ? ' tg-c-axis--we' : '') + (d.today ? ' tg-c-axis--today' : ''), 'middle'));
      if (d.startMin == null) { return; }
      var a = core.clamp(d.startMin, t0, t1), z = core.clamp(d.endMin, t0, t1);
      var y0 = padT + (a - t0) / (t1 - t0) * ih, y1 = padT + (z - t0) / (t1 - t0) * ih;
      var bw = Math.min(30, cw - 10);
      var r = s('rect', { x: cx - bw / 2, y: y0, width: bw, height: Math.max(4, y1 - y0), rx: 5, 'class': 'tg-c-block' + (d.flag === 'bad' ? ' tg-c-block--bad' : d.flag === 'hint' ? ' tg-c-block--hint' : '') });
      r.appendChild(s('title', null, d.label + ': ' + core.clockOf(d.startMin) + '–' + core.clockOf(d.endMin)));
      svg.appendChild(r);
      svg.appendChild(text(cx, y0 - 4, core.clockOf(d.startMin), 'tg-c-val tg-c-val--small', 'middle'));
    });
    return svg;
  }

  /* ---------- Saldo-Kurve ---------- */
  // points: [{label, value(min)}]
  function balanceLine(points, opts) {
    opts = opts || {};
    var W = opts.W || 640, H = opts.H || 260;
    var L = core.lineLayout(points, W, H, { step: 300, padL: 46 });
    var svg = svgRoot(W, H, opts.label || t('chart_balance'), 'tg-chart--line');
    for (var v = L.lo; v <= L.hi + 0.1; v += L.step) {
      var y = L.Y(v);
      svg.appendChild(s('line', { x1: L.padL, x2: W - 12, y1: y, y2: y, 'class': v === 0 ? 'tg-c-zero' : 'tg-c-grid' }));
      svg.appendChild(text(L.padL - 6, y + 3.5, (v > 0 ? '+' : v < 0 ? '−' : '') + Math.abs(Math.round(v / 60)), 'tg-c-axis', 'end'));
    }
    if (!points.length) { return svg; }
    svg.appendChild(s('path', { d: L.area, 'class': 'tg-c-area' }));
    svg.appendChild(s('path', { d: L.d, 'class': 'tg-c-line', fill: 'none' }));
    L.pts.forEach(function (q, i) {
      var c = s('circle', { cx: q.x, cy: q.y, r: i === L.pts.length - 1 ? 5 : 3.5, 'class': 'tg-c-dot' + (q.p.value < 0 ? ' tg-c-dot--neg' : '') });
      c.appendChild(s('title', null, q.p.label + ': ' + core.fmtSignedHM(q.p.value)));
      svg.appendChild(c);
      if (points.length <= 14 || i % 2 === 0 || i === points.length - 1) { svg.appendChild(text(q.x, H - 9, q.p.label, 'tg-c-axis', 'middle')); }
    });
    return svg;
  }

  /* ---------- Heatmap Woche × Person ---------- */
  // rows: [{name, days:[min x7], flags:[ '' | 'bad' | 'hint' x7 ]}], dayLabels: 7 Strings
  function heatmap(rows, dayLabels, opts) {
    opts = opts || {};
    var nameW = 120, cw = 62, rh = 38, top = 28, W = nameW + cw * 7 + 8, H = top + rows.length * rh + 6;
    var svg = svgRoot(W, H, opts.label || t('team_heat'), 'tg-chart--heat');
    dayLabels.forEach(function (l, i) { svg.appendChild(text(nameW + cw * i + cw / 2, 17, l, 'tg-c-axis' + (i > 4 ? ' tg-c-axis--we' : ''), 'middle')); });
    rows.forEach(function (r, ri) {
      var y = top + ri * rh;
      svg.appendChild(text(nameW - 10, y + rh / 2 + 4, core.clampStr(r.name, 16), 'tg-c-name', 'end'));
      r.days.forEach(function (m, di) {
        var lv = core.heatLevel(m, opts.dayTarget || 480);
        var flag = r.flags && r.flags[di];
        var g = s('g', null);
        var rc = s('rect', { x: nameW + cw * di + 2, y: y + 2, width: cw - 4, height: rh - 4, rx: 8, 'class': 'tg-heat tg-heat--' + lv + (flag === 'bad' ? ' is-bad' : flag === 'hint' ? ' is-hint' : '') });
        rc.appendChild(s('title', null, r.name + ' · ' + dayLabels[di] + ': ' + core.fmtHM(m)));
        g.appendChild(rc);
        if (m) { g.appendChild(text(nameW + cw * di + cw / 2, y + rh / 2 + 4, core.fmtH(m, TG.i18n.lang()).replace(' h', ''), 'tg-c-heatval' + (lv >= 3 ? ' is-inv' : ''), 'middle')); }
        svg.appendChild(g);
      });
    });
    return svg;
  }

  /* ---------- Horizontale Balken (Team: Netto/Woche) ---------- */
  function hBars(rows, opts) {
    opts = opts || {};
    var W = opts.W || 600, nameW = 120, rh = 34, top = 20, H = top + rows.length * rh + 6;
    var max = Math.max.apply(null, rows.map(function (r) { return r.value; }).concat([opts.target || 2400, opts.limit || 2880])) * 1.05;
    var svg = svgRoot(W, H, opts.label || '', 'tg-chart--hbars');
    var x = function (v) { return nameW + (W - nameW - 60) * v / max; };
    [[opts.target, 'tg-c-target', opts.targetLabel], [opts.limit, 'tg-c-limit', opts.limitLabel]].forEach(function (l) {
      if (!l[0]) { return; }
      svg.appendChild(s('line', { x1: x(l[0]), x2: x(l[0]), y1: top - 6, y2: H - 4, 'class': l[1] }));
      svg.appendChild(text(x(l[0]), top - 9, l[2] || '', l[1] === 'tg-c-limit' ? 'tg-c-limit-label' : 'tg-c-axis', 'middle'));
    });
    rows.forEach(function (r, i) {
      var y = top + i * rh;
      svg.appendChild(text(nameW - 10, y + rh / 2 + 4, core.clampStr(r.name, 16), 'tg-c-name', 'end'));
      var rc = s('rect', { x: nameW, y: y + 5, width: Math.max(3, x(r.value) - nameW), height: rh - 12, rx: 6, 'class': 'tg-c-bar' + (r.flag === 'bad' ? ' tg-c-bar--bad' : r.flag === 'hint' ? ' tg-c-bar--hint' : '') });
      rc.appendChild(s('title', null, r.name + ': ' + core.fmtHM(r.value)));
      svg.appendChild(rc);
      svg.appendChild(text(x(r.value) + 6, y + rh / 2 + 4, core.fmtH(r.value, TG.i18n.lang()), 'tg-c-val', 'start'));
    });
    return svg;
  }

  TG.charts = { ring: ring, updateRing: updateRing, bars: bars, dayBlocks: dayBlocks, balanceLine: balanceLine, heatmap: heatmap, hBars: hBars };
})(typeof self !== 'undefined' ? self : this);
