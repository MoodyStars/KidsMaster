/* assets/js/compat.js
   Compatibility layer and polyfill loader for older browsers (IE7-11, older Chrome/Firefox).
   Provides:
   - Dynamic small polyfill loading (es5-shim, html5shiv/respond for IE)
   - kmAjax: XMLHttpRequest wrapper (callback)
   - kmFetch: Promise/fetch-like wrapper (returns Promise when native Promise exists)
   - kmWebSocket: wrapper that uses native WebSocket when available, otherwise long-polling fallback
   - small shims: classList, addEventListener fallback for IE8, dataset fallback
   Notes:
   - Drop this file in pages to add legacy support. It deliberately avoids modern JS syntax
     (uses var and function) so it can be evaluated by older engines.
*/

(function (global) {
  // Basic feature detection
  var hasFetch = 'fetch' in global;
  var hasPromise = 'Promise' in global;
  var hasWebSocket = 'WebSocket' in global;
  var isIE = (function () {
    var ua = (global.navigator && global.navigator.userAgent) || '';
    return /MSIE |Trident\//.test(ua);
  })();

  // Helper: inject a script and call callback when loaded
  function loadScript(src, cb) {
    var s = document.createElement('script');
    s.src = src;
    s.async = false;
    s.onload = s.onreadystatechange = function () {
      var state = this.readyState;
      if (!cb.done && (!state || state === 'loaded' || state === 'complete')) {
        cb.done = true;
        cb();
        s.onload = s.onreadystatechange = null;
      }
    };
    s.onerror = function () { cb(); };
    (document.getElementsByTagName('head')[0] || document.documentElement).appendChild(s);
  }

  // Load minimal polyfills for very old browsers
  function ensurePolyfills(cb) {
    // If ES5 features missing, load es5-shim/es5-sham
    var needES5Shim = !Array.prototype.forEach || !Function.prototype.bind;
    var loadNext = cb || function () {};
    if (needES5Shim) {
      loadScript('https://cdnjs.cloudflare.com/ajax/libs/es5-shim/4.5.15/es5-shim.min.js', function () {
        loadScript('https://cdnjs.cloudflare.com/ajax/libs/es5-shim/4.5.15/es5-sham.min.js', loadNext);
      });
      return;
    }
    loadNext();
  }

  // AddEventListener/polyfill for IE8
  function addEvent(el, ev, fn) {
    if (!el) return;
    if (el.addEventListener) return el.addEventListener(ev, fn, false);
    // IE8 fallback
    el.attachEvent && el.attachEvent('on' + ev, function () { fn.call(el, window.event); });
  }

  // dataset fallback: simple mapping to data- attributes
  function getDataset(el) {
    if (!el) return {};
    if (el.dataset) return el.dataset;
    var attrs = el.attributes || [];
    var d = {};
    for (var i = 0; i < attrs.length; i++) {
      var name = attrs[i].name;
      if (name.indexOf('data-') === 0) {
        var key = name.substr(5).replace(/-([a-z])/g, function (m, chr) { return chr.toUpperCase(); });
        d[key] = attrs[i].value;
      }
    }
    return d;
  }

  // Simple XHR wrapper (callback style) for legacy browsers
  function kmAjax(opts, cb) {
    // opts: { method, url, headers, body, timeout }
    try {
      var xhr = new (window.XMLHttpRequest || ActiveXObject)('MSXML2.XMLHTTP.3.0');
      xhr.open(opts.method || 'GET', opts.url, true);
      xhr.timeout = opts.timeout || 0;
      if (opts.headers) {
        for (var h in opts.headers) {
          try { xhr.setRequestHeader(h, opts.headers[h]); } catch (e) {}
        }
      }
      xhr.onreadystatechange = function () {
        if (xhr.readyState !== 4) return;
        var status = xhr.status === 1223 ? 204 : xhr.status; // IE bug
        var text = xhr.responseText;
        cb(null, { status: status, text: text, xhr: xhr });
      };
      xhr.ontimeout = function () { cb(new Error('timeout')); };
      xhr.onerror = function () { cb(new Error('network')); };
      xhr.send(opts.body || null);
    } catch (err) {
      cb(err);
    }
  }

  // kmFetch: returns a Promise when Promise exists; otherwise uses callback-style fallback (returns object with then)
  function kmFetch(url, options) {
    options = options || {};
    if (hasFetch) {
      return global.fetch(url, options);
    }
    // Fallback: return a Promise if available
    if (hasPromise) {
      return new Promise(function (resolve, reject) {
        kmAjax({
          method: options.method || 'GET',
          url: url,
          headers: options.headers || {},
          body: options.body || null
        }, function (err, res) {
          if (err) return reject(err);
          // Minimal Response-like object
          resolve({
            ok: res.status >= 200 && res.status < 300,
            status: res.status,
            text: function () { return Promise.resolve(res.text); },
            json: function () {
              try { return Promise.resolve(JSON.parse(res.text)); } catch (e) { return Promise.reject(e); }
            }
          });
        });
      });
    }
    // Last fallback: provide then-able with simple emulation
    var holder = {
      then: function (onFulfilled) {
        kmAjax({ method: options.method || 'GET', url: url, headers: options.headers || {} }, function (err, res) {
          if (err) { /* ignore */ return; }
          onFulfilled({
            ok: res.status >= 200 && res.status < 300,
            status: res.status,
            text: function () { return res.text; },
            json: function () { try { return JSON.parse(res.text); } catch (e) { return null; } }
          });
        });
        return holder;
      }
    };
    return holder;
  }

  // kmWebSocket wrapper: uses native WebSocket if available, else falls back to long-polling
  function KMWebSocket(url, protocols) {
    this.url = url;
    this.protocols = protocols;
    this._open = false;
    this._onmessage = null;
    this._onopen = null;
    this._onclose = null;
    this._pollTimer = null;
    this._pollSince = 0;
    // try native
    if (hasWebSocket) {
      try {
        this._ws = protocols ? new WebSocket(url, protocols) : new WebSocket(url);
        var self = this;
        this._ws.onopen = function (ev) {
          self._open = true;
          self._onopen && self._onopen(ev);
        };
        this._ws.onmessage = function (ev) {
          self._onmessage && self._onmessage({ data: ev.data });
        };
        this._ws.onclose = function (ev) {
          self._open = false;
          self._onclose && self._onclose(ev);
        };
      } catch (e) {
        this._ws = null;
        this._startPolling();
      }
    } else {
      // start polling fallback (uses ajax/channel_api.php?action=chat_poll)
      this._startPolling();
    }
  }

  KMWebSocket.prototype._startPolling = function () {
    var self = this;
    this._open = true;
    // call onopen asynchronously to mimic WS
    setTimeout(function () { self._onopen && self._onopen({}); }, 0);
    this._pollOnce();
    // poll every 2500ms
    this._pollTimer = setInterval(function () { self._pollOnce(); }, 2500);
  };

  KMWebSocket.prototype._pollOnce = function () {
    var self = this;
    // Poll endpoint: expects since param and channel_id query param is up to client usage
    var url = '/api.php?rest=chat_poll&since=' + encodeURIComponent(self._pollSince) + (self._pollRoom ? '&media_id=' + encodeURIComponent(self._pollRoom) : '');
    kmAjax({ url: url, method: 'GET' }, function (err, res) {
      if (err) return;
      try {
        var j = JSON.parse(res.text || '[]');
        if (j && j.messages && j.messages.length) {
          for (var i = 0; i < j.messages.length; i++) {
            var m = j.messages[i];
            self._pollSince = Math.max(self._pollSince, m.id || 0);
            self._onmessage && self._onmessage({ data: JSON.stringify(m) });
          }
        }
      } catch (e) { /* ignore parse errors */ }
    });
  };

  KMWebSocket.prototype.send = function (data) {
    if (this._ws) {
      this._ws.send(data);
      return;
    }
    // fallback: POST to chat_send endpoint
    try {
      var payload = typeof data === 'string' ? JSON.parse(data) : data;
    } catch (e) {
      try { payload = eval('(' + data + ')'); } catch (ex) { payload = null; }
    }
    if (!payload) return;
    kmAjax({
      url: '/ajax/channel_api.php?action=chat_send',
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    }, function () { /* ignore ack */ });
  };

  KMWebSocket.prototype.close = function () {
    if (this._ws) {
      this._ws.close();
      return;
    }
    if (this._pollTimer) {
      clearInterval(this._pollTimer);
      this._pollTimer = null;
    }
    this._open = false;
    this._onclose && this._onclose({});
  };

  // assign event handler properties
  Object.defineProperty(KMWebSocket.prototype, 'onmessage', {
    get: function () { return this._onmessage; },
    set: function (fn) { this._onmessage = fn; }
  });
  Object.defineProperty(KMWebSocket.prototype, 'onopen', {
    get: function () { return this._onopen; },
    set: function (fn) { this._onopen = fn; }
  });
  Object.defineProperty(KMWebSocket.prototype, 'onclose', {
    get: function () { return this._onclose; },
    set: function (fn) { this._onclose = fn; }
  });

  // Export to global
  global.kmAjax = kmAjax;
  global.kmFetch = kmFetch;
  global.KMWebSocket = KMWebSocket;
  global.kmAddEvent = addEvent;
  global.kmGetDataset = getDataset;
  global.kmEnsurePolyfills = ensurePolyfills;
  global.kmIsIE = isIE;

  // Auto-load polyfills on older browsers (best-effort)
  if (isIE || !hasPromise || !('forEach' in Array.prototype)) {
    ensurePolyfills(function () {
      // load HTML5 shiv for IE <= 9 to allow styling of HTML5 elements
      if (isIE && document.createElement) {
        // conditional load for html5shiv/respond/selectivizr where appropriate
        loadScript('https://cdnjs.cloudflare.com/ajax/libs/html5shiv/3.7.3/html5shiv.min.js', function () {
          loadScript('https://cdnjs.cloudflare.com/ajax/libs/respond.js/1.4.2/respond.min.js', function () {
            // optionally also load selectivizr for CSS :before/:after selectors in IE6-8
            // loadScript('https://cdnjs.cloudflare.com/ajax/libs/selectivizr/1.0.2/selectivizr-min.js', function(){});
          });
        });
      }
    });
  }
})(this);