/* TimeGuardian – Früh-Start (als erstes Skript jeder Seite, keine Abhängigkeiten).
 * 1. Nextcloud setzt im <head> ein eigenes <link rel="manifest"> (Theming). Das gewinnt gegen ein Manifest im Body.
 *    Darum: fremde Manifest-Links entfernen, unseres in den <head> setzen und dort halten.
 * 2. Das Ereignis beforeinstallprompt feuert früh: hier einfangen, damit die Seite später „Als App installieren“ anbieten kann.
 * 3. Plattform-Erkennung (iPhone/iPad, Android, Safari) und „läuft schon als App“. */
(function (root) {
  'use strict';
  var TG = root.TG = root.TG || {};
  var doc = root.document;
  var INSTALLED_KEY = 'tg.installed';

  function store(op, key, val) {
    try {
      if (op === 'get') { return root.localStorage.getItem(key); }
      if (op === 'set') { root.localStorage.setItem(key, val); return true; }
      root.localStorage.removeItem(key); return true;
    } catch (e) { return op === 'get' ? null : false; }
  }

  /* Reine Funktion (getestet): ua = User-Agent, platform = navigator.platform, touch = maxTouchPoints */
  function detect(ua, platform, touch) {
    ua = String(ua || ''); platform = String(platform || ''); touch = Number(touch) || 0;
    var ipadOs = platform === 'MacIntel' && touch > 1; // iPadOS meldet sich als Mac
    var ios = /iPhone|iPad|iPod/.test(ua) || ipadOs;
    var android = /Android/i.test(ua);
    var cios = /CriOS|FxiOS|EdgiOS|OPiOS/.test(ua);
    var safari = ios ? (!cios && /Safari/.test(ua)) : (/Safari/.test(ua) && !/Chrome|Chromium|Edg|OPR|SamsungBrowser/.test(ua));
    var chromium = /Chrome|Chromium|CriOS|Edg|SamsungBrowser/.test(ua) && !ios;
    return { ios: ios, android: android, safari: safari, chromium: chromium, mobile: ios || android };
  }

  function standalone() {
    try {
      if (root.navigator && root.navigator.standalone === true) { return true; }
      if (root.matchMedia) {
        return root.matchMedia('(display-mode: standalone)').matches || root.matchMedia('(display-mode: fullscreen)').matches || root.matchMedia('(display-mode: minimal-ui)').matches;
      }
    } catch (e) { /* ignore */ }
    return false;
  }

  /* ---------- Manifest ---------- */
  function ourManifestUrl() {
    var el = doc && doc.getElementById('tg-root');
    var u = el && el.getAttribute('data-manifest');
    if (u) { return u; }
    var m = /^(.*?\/apps\/timeguardian)(?:\/|$)/.exec(root.location.pathname);
    return (m ? m[1] : '/apps/timeguardian') + '/manifest';
  }
  function pathOf(href) {
    try { return new root.URL(href, root.location.href).pathname; } catch (e) { return String(href || ''); }
  }
  /* Entfernt jedes fremde Manifest, hält genau unseres im <head>. Gibt die Zahl der Änderungen zurück. */
  function fixManifest() {
    var head = doc && doc.head;
    if (!head) { return 0; }
    var ours = ourManifestUrl(), ourPath = pathOf(ours), changes = 0, keep = null;
    var links = doc.querySelectorAll('link[rel~="manifest"]');
    for (var i = 0; i < links.length; i++) {
      var l = links[i];
      if (!keep && l.parentNode === head && pathOf(l.getAttribute('href')) === ourPath) { keep = l; continue; }
      if (l.parentNode) { l.parentNode.removeChild(l); changes++; }
    }
    if (!keep) {
      var n = doc.createElement('link');
      n.setAttribute('rel', 'manifest'); n.setAttribute('href', ours);
      head.appendChild(n); changes++;
    }
    return changes;
  }
  function ensureMeta(name, content) {
    var head = doc && doc.head;
    if (!head || head.querySelector('meta[name="' + name + '"]')) { return; }
    var m = doc.createElement('meta'); m.setAttribute('name', name); m.setAttribute('content', content); head.appendChild(m);
  }

  /* ---------- Installationsaufforderung ---------- */
  var listeners = [];
  var pwa = {
    prompt: null,
    detect: detect,
    standalone: standalone,
    fixManifest: fixManifest,
    installedFlag: function () { return store('get', INSTALLED_KEY) === '1'; },
    isInstalled: function () { return standalone() || store('get', INSTALLED_KEY) === '1'; },
    platform: function () { var n = root.navigator || {}; return detect(n.userAgent, n.platform, n.maxTouchPoints); },
    onChange: function (fn) { listeners.push(fn); },
    /* öffnet den Installationsdialog des Browsers; true wenn er gezeigt wurde */
    install: function () {
      var p = pwa.prompt;
      if (!p || !p.prompt) { return Promise.resolve(false); }
      pwa.prompt = null; notify();
      p.prompt();
      return Promise.resolve(p.userChoice).then(function (c) {
        if (c && c.outcome === 'accepted') { store('set', INSTALLED_KEY, '1'); notify(); }
        return !!(c && c.outcome === 'accepted');
      }, function () { return false; });
    }
  };
  function notify() { listeners.forEach(function (f) { try { f(); } catch (e) { /* ignore */ } }); }

  TG.pwa = pwa;
  if (typeof module === 'object' && module.exports) { module.exports = { detect: detect }; }
  if (!doc || !root.addEventListener) { return; }

  root.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    pwa.prompt = e; notify();
  });
  root.addEventListener('appinstalled', function () {
    pwa.prompt = null; store('set', INSTALLED_KEY, '1'); notify();
  });

  var run = function () { fixManifest(); };
  run();
  doc.addEventListener('DOMContentLoaded', run);
  root.addEventListener('load', function () { run(); root.setTimeout(run, 1500); });
  // Nextcloud kann den Link später erneut setzen: beobachten, bis die Seite fertig geladen ist.
  try {
    if (root.MutationObserver) {
      var obs = new root.MutationObserver(function (muts) {
        for (var i = 0; i < muts.length; i++) {
          var added = muts[i].addedNodes;
          for (var j = 0; j < added.length; j++) {
            var n = added[j];
            if (n.nodeType === 1 && n.tagName === 'LINK' && /(^|\s)manifest(\s|$)/.test(n.getAttribute('rel') || '')) { fixManifest(); return; }
          }
        }
      });
      obs.observe(doc.documentElement, { childList: true, subtree: true });
      root.addEventListener('load', function () { root.setTimeout(function () { obs.disconnect(); }, 5000); });
    }
  } catch (e) { /* ignore */ }
  // Name und Symbol für „Zum Home-Bildschirm“ (iOS liest das, nicht das Manifest): Nextcloud setzt hier eigene Werte
  var NAMES = { de: 'Zeit-Wächter', en: 'TimeGuardian', it: 'Guardiano ore' };
  function appName() {
    var l = '';
    try { l = String((root.navigator.languages && root.navigator.languages[0]) || root.navigator.language || '').slice(0, 2).toLowerCase(); } catch (e) { l = ''; }
    return NAMES[l] || NAMES.de;
  }
  function setIosMeta() {
    var head = doc.head, el = doc.getElementById('tg-root');
    if (!head) { return; }
    var m = head.querySelector('meta[name="apple-mobile-web-app-title"]');
    if (!m) { m = doc.createElement('meta'); m.setAttribute('name', 'apple-mobile-web-app-title'); head.appendChild(m); }
    m.setAttribute('content', appName());
    var apple = el && el.getAttribute('data-apple');
    if (apple) {
      var icons = head.querySelectorAll('link[rel^="apple-touch-icon"]'), have = false;
      for (var i = 0; i < icons.length; i++) {
        if (pathOf(icons[i].getAttribute('href')) === pathOf(apple) && !have) { have = true; } else if (icons[i].parentNode) { icons[i].parentNode.removeChild(icons[i]); }
      }
      if (!have) { var l = doc.createElement('link'); l.setAttribute('rel', 'apple-touch-icon'); l.setAttribute('href', apple); head.appendChild(l); }
    }
  }
  var ready = function () { ensureMeta('mobile-web-app-capable', 'yes'); ensureMeta('apple-mobile-web-app-capable', 'yes'); setIosMeta(); };
  if (doc.readyState === 'loading') { doc.addEventListener('DOMContentLoaded', ready); } else { ready(); }
})(typeof self !== 'undefined' ? self : this);
