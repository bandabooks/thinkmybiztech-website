/* Think Mybiz Tech — site script: nav, cart, store catalogue, blog */
(function () {
  "use strict";
  var STORE_API = "https://api-ecommerce.hostinger.com/v2";
  var CHANNEL = "scha_01M7W4WD4X1BC4AGPJEVHMWYGK";
  var CART_KEY = "tmbt_cart_v1";

  /* ---------- Navigation ---------- */
  var toggle = document.querySelector(".menu-toggle");
  var links = document.querySelector(".nav-links");
  if (toggle && links) {
    toggle.addEventListener("click", function () {
      var open = links.classList.toggle("open");
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
    });
  }
  var yr = document.getElementById("year");
  if (yr) yr.textContent = new Date().getFullYear();

  /* ---------- Helpers ---------- */
  function el(tag, cls, html) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (html != null) e.innerHTML = html;
    return e;
  }
  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }
  function fmtMoney(amount, cur) {
    var d = cur && cur.decimal_digits != null ? cur.decimal_digits : 2;
    var v = (amount / Math.pow(10, d)).toLocaleString("en-ZA", { minimumFractionDigits: d, maximumFractionDigits: d });
    if (cur && cur.template) return cur.template.replace("$1", v);
    return "R " + v;
  }
  function getJSON(url, opts) {
    return fetch(url, opts).then(function (r) {
      if (!r.ok) throw new Error("HTTP " + r.status);
      return r.json().then(function (j) { return { json: j, res: r }; });
    });
  }
  function list(j) { return Array.isArray(j) ? j : (j && (j.data || j.items)) || []; }

  /* ---------- Cart (localStorage: variant_id + quantity only) ---------- */
  function readCart() {
    try { return JSON.parse(localStorage.getItem(CART_KEY)) || []; } catch (e) { return []; }
  }
  function writeCart(c) {
    try { localStorage.setItem(CART_KEY, JSON.stringify(c)); } catch (e) {}
    renderCount();
  }
  function renderCount() {
    var n = readCart().reduce(function (a, i) { return a + i.quantity; }, 0);
    document.querySelectorAll(".cart-count").forEach(function (b) {
      b.textContent = n; b.classList.toggle("show", n > 0);
    });
    document.querySelectorAll(".cart-link").forEach(function (a) { a.style.display = n > 0 ? "" : "none"; });
  }
  function addToCart(variantId) {
    var c = readCart(), f = c.filter(function (i) { return i.variant_id === variantId; })[0];
    if (f) f.quantity += 1; else c.push({ variant_id: variantId, quantity: 1 });
    writeCart(c); openDrawer();
  }
  renderCount();

  var catalogue = {}; // variant_id -> {title, amount, currency}
  var drawer = document.querySelector(".drawer"), bg = document.querySelector(".drawer-bg");
  function openDrawer() { if (!drawer) return; renderDrawer(); drawer.classList.add("open"); bg.classList.add("open"); }
  function closeDrawer() { if (!drawer) return; drawer.classList.remove("open"); bg.classList.remove("open"); }
  document.querySelectorAll("[data-open-cart]").forEach(function (b) {
    b.addEventListener("click", function (e) { e.preventDefault(); ensureCatalogue().then(openDrawer); });
  });
  if (bg) bg.addEventListener("click", closeDrawer);
  var cx = document.querySelector(".drawer .close-x"); if (cx) cx.addEventListener("click", closeDrawer);

  function renderDrawer() {
    var box = drawer.querySelector(".items"), tot = drawer.querySelector(".total span:last-child");
    var c = readCart(); box.innerHTML = "";
    if (!c.length) { box.appendChild(el("p", "loading", "Your cart is empty.")); tot.textContent = "—"; return; }
    var total = 0, cur = null;
    c.forEach(function (i) {
      var p = catalogue[i.variant_id];
      var title = p ? p.title : "Item";
      if (p) { total += p.amount * i.quantity; cur = p.currency; }
      var line = el("div", "line");
      line.innerHTML = "<div><strong>" + esc(title) + "</strong><div class='qty'><button data-d='-1'>−</button>" + i.quantity +
        "<button data-d='1'>+</button></div></div><div>" + (p ? esc(fmtMoney(p.amount * i.quantity, p.currency)) : "") + "</div>";
      line.querySelectorAll("button").forEach(function (b) {
        b.addEventListener("click", function () {
          var cc = readCart();
          cc.forEach(function (x) { if (x.variant_id === i.variant_id) x.quantity += +b.getAttribute("data-d"); });
          writeCart(cc.filter(function (x) { return x.quantity > 0; })); renderDrawer();
        });
      });
      box.appendChild(line);
    });
    tot.textContent = cur ? fmtMoney(total, cur) : "—";
  }

  var checkoutBtn = document.querySelector("[data-checkout]");
  if (checkoutBtn) checkoutBtn.addEventListener("click", function () {
    var items = readCart().filter(function (i) { return catalogue[i.variant_id]; });
    if (!items.length) return;
    checkoutBtn.disabled = true; checkoutBtn.textContent = "Opening secure checkout…";
    var o = window.location.origin;
    getJSON(STORE_API + "/channels/" + CHANNEL + "/checkout", {
      method: "POST", headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ items: items, success_url: o + "/checkout/success/", cancel_url: o + "/checkout/cancel/", locale: "en" })
    }).then(function (r) {
      if (r.json && r.json.url) window.location.href = r.json.url; else throw new Error("No checkout URL");
    }).catch(function () {
      checkoutBtn.disabled = false; checkoutBtn.textContent = "Proceed to checkout";
      alert("Checkout is temporarily unavailable. Please email info@thinkmybiztech.com and we will invoice you directly.");
    });
  });

  var cataloguePromise = null;
  function ensureCatalogue() {
    if (cataloguePromise) return cataloguePromise;
    cataloguePromise = getJSON(STORE_API + "/channels/" + CHANNEL + "/products?limit=100").then(function (r) {
      var products = list(r.json);
      if (!products.length) return [];
      var ids = products.map(function (p) { return "product_ids[]=" + encodeURIComponent(p.id); }).join("&");
      return getJSON(STORE_API + "/channels/" + CHANNEL + "/variants?limit=100&" + ids).then(function (v) {
        var variants = list(v.json);
        return products.map(function (p) {
          var pv = variants.filter(function (x) { return x.product_id === p.id; });
          if (!pv.length && p.variants) pv = p.variants;
          var first = pv[0], price = first && first.prices && first.prices[0];
          if (first && price) catalogue[first.id] = { title: p.title || p.name, amount: price.sale_amount || price.amount, currency: price.currency };
          return { product: p, variant: first, price: price };
        }).filter(function (x) { return x.variant && x.price; });
      });
    }).catch(function () { return []; });
    return cataloguePromise;
  }

  /* Store grid on the Pricing page */
  var grid = document.getElementById("store-grid");
  if (grid) {
    ensureCatalogue().then(function (rows) {
      var note = document.getElementById("store-note");
      grid.innerHTML = "";
      if (!rows.length) { if (note) note.style.display = ""; grid.style.display = "none"; return; }
      if (note) note.style.display = "none";
      rows.forEach(function (r) {
        var p = r.product, amt = r.price.sale_amount || r.price.amount;
        var card = el("div", "card price-card");
        card.innerHTML = "<span class='badge'>Pay online</span><h3 style='margin-top:12px'>" + esc(p.title || p.name) + "</h3>" +
          "<div class='price'>" + esc(fmtMoney(amt, r.price.currency)) + "</div>" +
          "<div class='desc'>" + (p.description ? esc(String(p.description).replace(/<[^>]+>/g, "")) : "") + "</div>";
        var b = el("button", "btn btn-primary", "Add to cart");
        b.addEventListener("click", function () { addToCart(r.variant.id); });
        card.appendChild(b); grid.appendChild(card);
      });
    });
  }
  if (readCart().length) ensureCatalogue();

  /* Clear cart after a successful checkout */
  if (document.body.getAttribute("data-page") === "checkout-success") writeCart([]);

  /* ---------- Latest articles (same-origin /api/posts.php, which reads the CMS server-side) ---------- */
  function fmtDate(s) { try { return new Date(s + "T12:00:00").toLocaleDateString("en-ZA", { day: "numeric", month: "long", year: "numeric" }); } catch (e) { return ""; } }
  function postCard(p) {
    var u = "/insights/" + encodeURIComponent(p.slug) + "/", c = el("article", "card post-card");
    c.innerHTML = "<a class='thumb' href='" + u + "' aria-hidden='true' tabindex='-1'>" +
      (p.image ? "<img loading='lazy' alt='' width='1200' height='630' src='" + esc(p.image) + "'>" : "") + "</a><div class='body'><div class='meta'>" + fmtDate(p.date) +
      "</div><h3><a href='" + u + "'>" + p.title + "</a></h3><div class='excerpt'>" + esc(p.excerpt) + "</div><a href='" + u + "'>Read article →</a></div>";
    return c;
  }
  document.querySelectorAll("[data-posts]").forEach(function (box) {
    var n = box.getAttribute("data-posts") || 3;
    function hide() { if (box.hasAttribute("data-hide-empty")) box.closest("section").style.display = "none"; }
    fetch("/api/posts.php?n=" + n, { headers: { Accept: "application/json" } })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j.ok || !j.posts || !j.posts.length) { hide(); return; }
        box.innerHTML = "";
        j.posts.forEach(function (p) { box.appendChild(postCard(p)); });
      })
      .catch(hide);
  });
  /* ---------- Contact form → /api/enquiry.php (stored + emailed server-side) ---------- */
  var form = document.getElementById("enquiry");
  if (form) {
    var ts = document.getElementById("ts"), pg = document.getElementById("page");
    if (ts) ts.value = String(Date.now());
    if (pg) pg.value = location.pathname + location.search;
    var okBox = document.getElementById("enquiry-ok"), errBox = document.getElementById("enquiry-err");
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      errBox.style.display = "none";
      if (!form.checkValidity()) { form.reportValidity(); return; }
      var btn = form.querySelector("button[type=submit]"), label = btn.textContent;
      btn.disabled = true; btn.textContent = "Sending…";
      fetch(form.action, { method: "POST", body: new FormData(form), headers: { Accept: "application/json" } })
        .then(function (r) { return r.json().catch(function () { return { ok: false }; }).then(function (j) { j.status = r.status; return j; }); })
        .then(function (j) {
          if (!j.ok) throw j;
          Array.prototype.forEach.call(form.children, function (x) { if (x !== okBox) x.style.display = "none"; });
          okBox.innerHTML = "<strong>Thank you — your enquiry has been received.</strong><br>Reference <strong>" + esc(j.ref || "") +
            "</strong>. We'll be in touch, normally within one business day. For anything urgent, WhatsApp or call 063 621 5642.";
          okBox.style.display = "";
          document.dispatchEvent(new Event("tmbt:lead"));
          okBox.scrollIntoView({ behavior: "smooth", block: "center" });
        })
        .catch(function (j) {
          errBox.innerHTML = esc((j && j.error) || "We couldn't send your enquiry just now.") +
            " You can also email <a href='mailto:info@thinkmybiztech.com'>info@thinkmybiztech.com</a> or <a href='https://wa.me/27636215642' target='_blank' rel='noopener'>WhatsApp us</a>.";
          errBox.style.display = "";
        })
        .then(function () { btn.disabled = false; btn.textContent = label; });
    });
  }
})();
