/* Think Mybiz Tech — campaign attribution and consent-based advertising tags (Meta, LinkedIn, TikTok).
   Nothing loads until the visitor accepts marketing cookies (POPIA). IDs come from /api/site-config.php (set in Admin → Settings). */
(function () {
  "use strict";
  var LS = window.localStorage, SS = window.sessionStorage;
  function get(s, k) { try { return s.getItem(k); } catch (e) { return null; } }
  function set(s, k, v) { try { s.setItem(k, v); } catch (e) {} }

  /* 1. First-touch UTM attribution (kept 90 days), copied into lead forms */
  var qs = new URLSearchParams(location.search), utm = {};
  ["utm_source", "utm_medium", "utm_campaign"].forEach(function (k) { if (qs.get(k)) utm[k] = qs.get(k).slice(0, 100); });
  if (!utm.utm_source && qs.get("fbclid")) { utm.utm_source = "facebook"; utm.utm_medium = utm.utm_medium || "social"; }
  if (!utm.utm_source && qs.get("gclid")) { utm.utm_source = "google"; utm.utm_medium = "cpc"; }
  var saved = null; try { saved = JSON.parse(get(LS, "tmbt_utm") || "null"); } catch (e) {}
  if (utm.utm_source) { utm.t = Date.now(); set(LS, "tmbt_utm", JSON.stringify(utm)); saved = utm; }
  if (saved && Date.now() - (saved.t || 0) > 90 * 864e5) saved = null;
  if (!saved && document.referrer && document.referrer.indexOf(location.host) < 0) {
    var h = ""; try { h = new URL(document.referrer).hostname.replace(/^www\./, ""); } catch (e) {}
    if (h) saved = { utm_source: h, utm_medium: "referral" };
  }
  if (saved) document.querySelectorAll("form[data-utm]").forEach(function (f) {
    ["utm_source", "utm_medium", "utm_campaign"].forEach(function (k) {
      var i = f.querySelector("[name=" + k + "]");
      if (!i) { i = document.createElement("input"); i.type = "hidden"; i.name = k; f.appendChild(i); }
      if (!i.value && saved[k]) i.value = saved[k];
    });
  });

  /* 2. Consent + tags */
  var cfg = null, loaded = false, queue = (window.TMBT_EVENTS || []).slice();
  function consent() { return get(LS, "tmbt_consent"); }
  function addScript(src) { var s = document.createElement("script"); s.async = true; s.src = src; document.head.appendChild(s); }
  function loadTags() {
    if (loaded || !cfg) return; loaded = true;
    if (cfg.meta) {
      /* Meta Pixel base code */
      !function (f, b, e, v, n, t, s) { if (f.fbq) return; n = f.fbq = function () { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments); };
        if (!f._fbq) f._fbq = n; n.push = n; n.loaded = !0; n.version = "2.0"; n.queue = []; }(window, document);
      addScript("https://connect.facebook.net/en_US/fbevents.js");
      window.fbq("init", cfg.meta); window.fbq("track", "PageView");
    }
    if (cfg.linkedin) {
      window._linkedin_partner_id = cfg.linkedin; window._linkedin_data_partner_ids = [cfg.linkedin];
      window.lintrk = window.lintrk || function (a, b) { window.lintrk.q.push([a, b]); }; window.lintrk.q = window.lintrk.q || [];
      addScript("https://snap.licdn.com/li.lms-analytics/insight.min.js");
    }
    if (cfg.tiktok) {
      var ttq = window.ttq = window.ttq || []; ttq.methods = ["page", "track", "identify", "instances", "debug", "on", "off", "once", "ready", "alias", "group", "enableCookie", "disableCookie"];
      ttq.setAndDefer = function (t, e) { t[e] = function () { t.push([e].concat(Array.prototype.slice.call(arguments, 0))); }; };
      for (var i = 0; i < ttq.methods.length; i++) ttq.setAndDefer(ttq, ttq.methods[i]);
      addScript("https://analytics.tiktok.com/i18n/pixel/events.js?sdkid=" + encodeURIComponent(cfg.tiktok) + "&lib=ttq"); ttq.page();
    }
    queue.splice(0).forEach(fire);
  }
  function fire(name) {
    if (!loaded) { queue.push(name); return; }
    if (cfg.meta && window.fbq) window.fbq("track", name);
    if (cfg.tiktok && window.ttq) window.ttq.track(name === "Lead" ? "SubmitForm" : name === "CompleteRegistration" ? "CompleteRegistration" : name);
  }
  window.tmbtTrack = fire;
  document.addEventListener("tmbt:lead", function () { fire("Lead"); });

  function names() {
    var n = []; if (cfg.meta) n.push("Meta (Facebook/Instagram)"); if (cfg.linkedin) n.push("LinkedIn"); if (cfg.tiktok) n.push("TikTok");
    return n.length > 1 ? n.slice(0, -1).join(", ") + " and " + n[n.length - 1] : n[0];
  }
  function banner() {
    var b = document.createElement("div"); b.className = "consent-bar show"; b.setAttribute("role", "dialog"); b.setAttribute("aria-label", "Cookie preferences");
    b.innerHTML = "<strong style='color:#fff'>Marketing cookies</strong><br>We would like to use advertising cookies from " + names() + " to measure our campaigns. " +
      "They load only if you agree. <a href='/privacy/'>Privacy notice</a><div class='row'><button class='yes' type='button'>Accept</button><button type='button'>No thanks</button></div>";
    var bs = b.querySelectorAll("button");
    bs[0].onclick = function () { set(LS, "tmbt_consent", "yes"); b.remove(); loadTags(); };
    bs[1].onclick = function () { set(LS, "tmbt_consent", "no"); b.remove(); };
    document.body.appendChild(b);
  }
  document.querySelectorAll("[data-consent-reset]").forEach(function (a) {
    a.addEventListener("click", function (e) { e.preventDefault(); try { LS.removeItem("tmbt_consent"); } catch (x) {} location.reload(); });
  });
  function start(c) {
    cfg = c; if (!cfg || !(cfg.meta || cfg.linkedin || cfg.tiktok)) return;
    var s = consent();
    if (s === "yes") loadTags(); else if (s !== "no") banner();
  }
  var cached = get(SS, "tmbt_cfg");
  if (cached) { try { start(JSON.parse(cached)); return; } catch (e) {} }
  fetch("/api/site-config.php", { headers: { Accept: "application/json" } }).then(function (r) { return r.json(); })
    .then(function (j) { set(SS, "tmbt_cfg", JSON.stringify(j)); start(j); }).catch(function () {});
})();
