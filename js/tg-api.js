/* TimeGuardian – API-Schicht. Einzige Stelle, die Endpunkte und Antwortformen kennt. */
(function (root) {
  'use strict';
  var TG = root.TG = root.TG || {};
  var DEVICE_KEY = 'tg.device';
  var LABEL_KEY = 'tg.deviceName';

  function ApiError(status, code, message, data) {
    this.name = 'ApiError';
    this.status = status;
    this.code = code || ('http_' + status);
    this.message = message || this.code;
    this.data = data || null;
  }
  ApiError.prototype = Object.create(Error.prototype);
  ApiError.prototype.constructor = ApiError;

  function store(op, key, val) {
    try {
      if (op === 'get') { return root.localStorage.getItem(key); }
      if (op === 'set') { root.localStorage.setItem(key, val); return true; }
      root.localStorage.removeItem(key); return true;
    } catch (e) { return op === 'get' ? null : false; }
  }

  function baseUrl() {
    if (TG.base) { return TG.base; }
    var el = root.document && root.document.getElementById('tg-root');
    var b = el && el.getAttribute('data-base');
    if (!b) {
      var m = /^(.*?\/apps\/timeguardian)(?:\/|$)/.exec(root.location.pathname);
      b = m ? m[1] : '/apps/timeguardian';
    }
    TG.base = String(b).replace(/\/+$/, '');
    return TG.base;
  }
  function requestToken() {
    try {
      if (root.OC && root.OC.requestToken) { return root.OC.requestToken; }
      var h = root.document.head;
      return (h && h.getAttribute('data-requesttoken')) || '';
    } catch (e) { return ''; }
  }

  function qs(params) {
    var out = [];
    Object.keys(params || {}).forEach(function (k) {
      var v = params[k];
      if (v !== undefined && v !== null && v !== '') { out.push(encodeURIComponent(k) + '=' + encodeURIComponent(v)); }
    });
    return out.length ? '?' + out.join('&') : '';
  }

  /* opts: {query, body, device: 'auto'|'only'|'never'} */
  function request(method, path, opts) {
    opts = opts || {};
    var headers = { Accept: 'application/json', 'OCS-APIRequest': 'true' };
    var dev = store('get', DEVICE_KEY);
    var mode = opts.device || 'never';
    var useDevice = !!dev && mode !== 'never';
    if (useDevice) { headers['X-TG-Device'] = dev; }
    var tok = requestToken();
    if (tok && !useDevice) { headers.requesttoken = tok; }
    var init = { method: method, headers: headers, credentials: useDevice ? 'omit' : 'same-origin', cache: 'no-store' };
    if (opts.body !== undefined) { init.body = JSON.stringify(opts.body); headers['Content-Type'] = 'application/json'; }
    var url = baseUrl() + path + qs(opts.query);
    return root.fetch(url, init).then(function (res) {
      return res.text().then(function (txt) {
        var data = null;
        if (txt) { try { data = JSON.parse(txt); } catch (e) { data = null; } }
        if (res.ok) { return data === null ? {} : data; }
        var err = new ApiError(res.status, data && data.error, data && data.message, data);
        // widerrufenes/abgelaufenes Gerät: Token verwerfen und (falls Session vorhanden) erneut versuchen
        if (useDevice && res.status === 401 && mode === 'auto' && !opts._retried) {
          var wasRevoked = err.code === 'device_revoked' || err.code === 'device_expired' || err.code === 'device_invalid' || err.code === 'unauthorized';
          if (wasRevoked) {
            store('del', DEVICE_KEY);
            opts._retried = true;
            return request(method, path, opts).catch(function (e2) {
              if (e2 instanceof ApiError && e2.status === 401) { e2.code = 'not_paired'; }
              throw e2;
            });
          }
        }
        throw err;
      });
    }, function () {
      throw new ApiError(0, 'offline', 'offline');
    });
  }

  function dl(path, query) {
    // Dateidownload über Session (Chef/Nutzer-Export)
    return baseUrl() + path + qs(query);
  }

  var api = {
    ApiError: ApiError,
    base: baseUrl,
    req: request,
    qs: qs,
    getDevice: function () { return store('get', DEVICE_KEY); },
    setDevice: function (t) { return store('set', DEVICE_KEY, t); },
    clearDevice: function () { return store('del', DEVICE_KEY); },
    getDeviceName: function () { return store('get', LABEL_KEY) || ''; },
    setDeviceName: function (n) { return store('set', LABEL_KEY, n); },
    store: store,
    page: function (path) { return baseUrl() + path; },
    downloadUrl: dl,

    me: function () { return request('GET', '/api/me'); },
    status: function () { return request('GET', '/api/status', { device: 'auto' }); },
    punch: function (body) { return request('POST', '/api/punch', { body: body, device: 'auto' }); },
    entries: function (from, to) { return request('GET', '/api/entries', { query: { from: from, to: to } }); },
    addEntry: function (b) { return request('POST', '/api/entries', { body: b }); },
    updateEntry: function (id, b) { return request('PUT', '/api/entries/' + encodeURIComponent(id), { body: b }); },
    deleteEntry: function (id) { return request('DELETE', '/api/entries/' + encodeURIComponent(id)); },
    week: function (date) { return request('GET', '/api/week', { query: { date: date } }); },
    month: function (ym) { return request('GET', '/api/month', { query: { ym: ym } }); },
    account: function () { return request('GET', '/api/account'); },
    violations: function (from, to) { return request('GET', '/api/violations', { query: { from: from, to: to } }); },
    pairCreate: function () { return request('POST', '/api/pair', { device: 'never' }); },
    pairRedeem: function (token, label) { return request('POST', '/api/pair/redeem', { body: { token: token, label: label }, device: 'never' }); },
    devices: function () { return request('GET', '/api/devices', { device: 'never' }); },
    revokeDevice: function (id) { return request('DELETE', '/api/devices/' + encodeURIComponent(id), { device: 'never' }); },
    absences: function (scope, from, to) { return request('GET', '/api/absences', { query: { scope: scope, from: from, to: to }, device: 'never' }); },
    addAbsence: function (b) { return request('POST', '/api/absences', { body: b, device: 'never' }); },
    deleteAbsence: function (id) { return request('DELETE', '/api/absences/' + encodeURIComponent(id), { device: 'never' }); },
    decideAbsence: function (id, decision, note) { return request('POST', '/api/absences/' + encodeURIComponent(id) + '/decide', { body: { decision: decision, note: note || undefined }, device: 'never' }); },
    corrections: function (scope, status) { return request('GET', '/api/corrections', { query: { scope: scope, status: status }, device: 'never' }); },
    addCorrection: function (b) { return request('POST', '/api/corrections', { body: b, device: 'never' }); },
    decideCorrection: function (id, decision, note) { return request('POST', '/api/corrections/' + encodeURIComponent(id) + '/decide', { body: { decision: decision, note: note || undefined }, device: 'never' }); },
    teamOverview: function (week) { return request('GET', '/api/team/overview', { query: { week: week }, device: 'never' }); },
    teamPerson: function (uid, from, to) { return request('GET', '/api/team/person/' + encodeURIComponent(uid), { query: { from: from, to: to }, device: 'never' }); },
    setTarget: function (uid, b) { return request('PUT', '/api/team/person/' + encodeURIComponent(uid) + '/target', { body: b, device: 'never' }); },
    audit: function (uid, from, to) { return request('GET', '/api/audit', { query: { uid: uid, from: from, to: to }, device: 'never' }); },
    kioskCode: function () { return request('GET', '/api/kiosk/code', { device: 'never' }); },
    setup: function () { return request('GET', '/api/setup', { device: 'never' }); },
    config: function () { return request('GET', '/api/config', { device: 'never' }); },
    saveConfig: function (b) { return request('PUT', '/api/config', { body: b, device: 'never' }); },
    settings: function () { return request('GET', '/api/settings', { device: 'never' }); },
    saveSettings: function (b) { return request('PUT', '/api/settings', { body: b, device: 'never' }); },
    exportCsvUrl: function (ym, format) { return dl('/api/export.csv', { ym: ym, format: format }); },
    exportJsonUrl: function () { return dl('/api/export.json'); },
    teamExportUrl: function (ym, format) { return dl('/api/team/export.csv', { ym: ym, format: format }); }
  };
  TG.api = api;
})(typeof self !== 'undefined' ? self : this);
