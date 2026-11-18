/* TimeGuardian – Kamera-Scanner (getUserMedia + BarcodeDetector, Fallback jsQR). */
(function (root) {
  'use strict';
  var TG = root.TG = root.TG || {};
  var ui = TG.ui, h = ui.h;
  var t = function (k, p) { return TG.i18n.t(k, p); };

  function supported() {
    return !!(root.navigator && root.navigator.mediaDevices && root.navigator.mediaDevices.getUserMedia);
  }
  function jsqrUrl() {
    var el = root.document.getElementById('tg-root');
    return (el && el.getAttribute('data-jsqr')) || '';
  }
  var jsqrPromise = null;
  function loadJsQR() {
    if (root.jsQR) { return Promise.resolve(root.jsQR); }
    if (jsqrPromise) { return jsqrPromise; }
    jsqrPromise = new Promise(function (resolve, reject) {
      var url = jsqrUrl();
      if (!url) { reject(new Error('jsqr-missing')); return; }
      var sc = root.document.createElement('script');
      sc.src = url; sc.async = true;
      sc.onload = function () { root.jsQR ? resolve(root.jsQR) : reject(new Error('jsqr-load')); };
      sc.onerror = function () { reject(new Error('jsqr-load')); };
      root.document.head.appendChild(sc);
    });
    return jsqrPromise;
  }

  /* opts: {onText(text) -> bool|string (true=akzeptiert, string=Fehlertext/weiterscannen)} */
  function create(opts) {
    var video = h('video', { 'class': 'tg-scan__video', playsinline: '', muted: '', autoplay: '', 'aria-label': t('scan_cam_label') });
    /* iOS (Safari and installed home-screen app): without playsinline the video opens fullscreen, without muted it does not autoplay */
    video.muted = true; video.defaultMuted = true; video.playsInline = true; video.autoplay = true;
    video.setAttribute('webkit-playsinline', ''); video.setAttribute('disablepictureinpicture', '');
    var hint = h('div', { 'class': 'tg-scan__hint', role: 'status', 'aria-live': 'polite' }, t('scan_point'));
    var frame = h('div', { 'class': 'tg-scan__frame', 'aria-hidden': 'true' }, h('i'), h('i'), h('i'), h('i'));
    var msg = h('div', { 'class': 'tg-scan__msg', hidden: '' });
    var el = h('div', { 'class': 'tg-scan' }, video, frame, hint, msg);
    var stream = null, running = false, detector = null, canvas = null, ctx = null, timer = null, jsqr = null, lastBad = 0;

    function showMsg(kind, text, action) {
      msg.removeAttribute('hidden');
      ui.mount(msg, [h('div', { 'class': 'tg-scan__msgtext' }, text), action || null]);
      msg.className = 'tg-scan__msg tg-scan__msg--' + kind;
      frame.style.display = 'none'; hint.style.display = 'none';
    }
    function stop() {
      running = false;
      if (timer) { root.clearTimeout(timer); timer = null; }
      if (stream) { stream.getTracks().forEach(function (tr) { try { tr.stop(); } catch (e) { /* ignore */ } }); stream = null; }
      try { video.pause(); video.srcObject = null; } catch (e) { /* ignore */ }
    }
    function handle(text) {
      var r = opts.onText(text);
      if (r === true) { stop(); return true; }
      if (typeof r === 'string' && Date.now() - lastBad > 1500) { lastBad = Date.now(); hint.textContent = r; hint.classList.add('is-bad'); root.setTimeout(function () { hint.textContent = t('scan_point'); hint.classList.remove('is-bad'); }, 2200); }
      return false;
    }
    function loop() {
      if (!running) { return; }
      var next = function () { if (running) { timer = root.setTimeout(loop, 160); } };
      if (video.readyState < 2) { next(); return; }
      if (detector) {
        detector.detect(video).then(function (codes) {
          for (var i = 0; i < codes.length; i++) { if (codes[i].rawValue && handle(codes[i].rawValue)) { return; } }
          next();
        }, function () { next(); });
        return;
      }
      if (jsqr) {
        var w = video.videoWidth, hh = video.videoHeight;
        if (!w || !hh) { next(); return; }
        var sc = Math.min(1, 520 / w);
        canvas.width = Math.round(w * sc); canvas.height = Math.round(hh * sc);
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        var img = ctx.getImageData(0, 0, canvas.width, canvas.height);
        var res = jsqr(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' });
        if (res && res.data && handle(res.data)) { return; }
      }
      next();
    }
    function start() {
      if (!supported()) {
        var insecure = root.isSecureContext === false;
        showMsg('err', insecure ? t('scan_insecure') : t('scan_unsupported'));
        return Promise.resolve(false);
      }
      var md = root.navigator.mediaDevices;
      /* rear camera preferred; if the device rejects the constraint (OverconstrainedError) take any camera */
      return md.getUserMedia({ video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false }).catch(function (err) {
        if (err && (err.name === 'OverconstrainedError' || err.name === 'ConstraintNotSatisfiedError')) { return md.getUserMedia({ video: true, audio: false }); }
        throw err;
      }).then(function (st) {
        stream = st; video.srcObject = st;
        return video.play().catch(function () { /* autoplay-Hinweis ignorieren */ });
      }).then(function () {
        if (!stream) { return false; }
        running = true;
        if ('BarcodeDetector' in root) {
          try { detector = new root.BarcodeDetector({ formats: ['qr_code'] }); } catch (e) { detector = null; }
        }
        if (detector) { loop(); return true; }
        return loadJsQR().then(function (fn) {
          jsqr = fn; canvas = root.document.createElement('canvas'); ctx = canvas.getContext('2d', { willReadFrequently: true });
          loop(); return true;
        }, function () { stop(); showMsg('err', t('scan_unsupported')); return false; });
      }, function (err) {
        var name = err && err.name;
        var retry = h('button', { type: 'button', 'class': 'tg-btn tg-btn--primary', onclick: function () { msg.setAttribute('hidden', ''); frame.style.display = ''; hint.style.display = ''; start(); } }, t('scan_retry'));
        var ios = !!(TG.pwa && TG.pwa.platform && TG.pwa.platform().ios);
        if (name === 'NotAllowedError' || name === 'SecurityError' || name === 'PermissionDeniedError') { showMsg('err', ios ? t('scan_denied_ios') : t('scan_denied'), retry); }
        else if (name === 'NotFoundError' || name === 'OverconstrainedError') { showMsg('err', t('scan_nocam')); }
        else { showMsg('err', t('scan_failed'), retry); }
        return false;
      });
    }
    return { el: el, start: start, stop: stop };
  }

  TG.scanner = { supported: supported, create: create };
})(typeof self !== 'undefined' ? self : this);
