/* TimeGuardian – QR-Darstellung als SVG (qrcode.js muss geladen sein). Keine Nutzerdaten als HTML. */
(function (root) {
  'use strict';
  var TG = root.TG = root.TG || {};
  var ui = TG.ui;

  function matrix(text, ecc) {
    var qr = root.qrcode(0, ecc || 'M');
    qr.addData(String(text), 'Byte');
    qr.make();
    var n = qr.getModuleCount(), rows = [];
    for (var r = 0; r < n; r++) { var row = []; for (var c = 0; c < n; c++) { row.push(qr.isDark(r, c)); } rows.push(row); }
    return rows;
  }
  // Pfad aus horizontalen Läufen (kompakt, scharf)
  function pathFor(rows, quiet) {
    var d = [];
    rows.forEach(function (row, r) {
      var c = 0;
      while (c < row.length) {
        if (row[c]) { var s = c; while (c < row.length && row[c]) { c++; } d.push('M' + (s + quiet) + ' ' + (r + quiet) + 'h' + (c - s) + 'v1h-' + (c - s) + 'z'); } else { c++; }
      }
    });
    return d.join('');
  }
  function svg(text, opts) {
    opts = opts || {};
    var quiet = opts.quiet != null ? opts.quiet : 3, rows = matrix(text, opts.ecc || 'M'), n = rows.length + quiet * 2;
    var el = ui.s('svg', { viewBox: '0 0 ' + n + ' ' + n, 'class': 'tg-qr', role: 'img', 'aria-label': opts.label || 'QR', 'shape-rendering': 'crispEdges', preserveAspectRatio: 'xMidYMid meet' },
      ui.s('rect', { width: n, height: n, fill: '#fff' }), ui.s('path', { d: pathFor(rows, quiet), fill: '#000' }));
    return el;
  }
  TG.qr = { svg: svg, matrix: matrix, available: function () { return typeof root.qrcode === 'function'; } };
})(typeof self !== 'undefined' ? self : this);
