/* Think Mybiz Tech — site script: nav, cart, store catalogue, blog */
(function () {
  "use strict";
  var STORE_API = "https://api-ecommerce.hostinger.com/v2";
  var CHANNEL = "scha_01M7W4WD4X1BC4AGPJEVHMWYGK";
  var CMS = "https://cms.thinkmybiztech.com/wp-json/wp/v2";
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

  /* ---------- Blog (WordPress REST API, fetched at runtime) ---------- */
  function fmtDate(s) { try { return new Date(s).toLocaleDateString("en-ZA", { day: "numeric", month: "long", year: "numeric" }); } catch (e) { return ""; } }
  function postCard(p) {
    var img = p._embedded && p._embedded["wp:featuredmedia"] && p._embedded["wp:featuredmedia"][0] && p._embedded["wp:featuredmedia"][0].source_url;
    var c = el("article", "card post-card");
    c.innerHTML = "<a class='thumb' href='/insights/post/?slug=" + encodeURIComponent(p.slug) + "' aria-hidden='true' tabindex='-1'>" +
      (img ? "<img loading='lazy' alt='' src='" + esc(img) + "'>" : "") + "</a><div class='body'><div class='meta'>" + fmtDate(p.date) +
      "</div><h3><a href='/insights/post/?slug=" + encodeURIComponent(p.slug) + "'>" + p.title.rendered + "</a></h3><div class='excerpt'>" +
      String(p.excerpt.rendered).replace(/<[^>]+>/g, "").slice(0, 180) + "</div><a href='/insights/post/?slug=" + encodeURIComponent(p.slug) + "'>Read article →</a></div>";
    return c;
  }
  document.querySelectorAll("[data-posts]").forEach(function (box) {
    var n = box.getAttribute("data-posts") || 9, page = 1;
    var more = document.querySelector("[data-more-posts]");
    function load() {
      getJSON(CMS + "/posts?per_page=" + n + "&page=" + page + "&_embed").then(function (r) {
        if (page === 1) box.innerHTML = "";
        var posts = list(r.json);
        if (!posts.length && page === 1) {
          box.innerHTML = "<p class='notice'>New articles on software, automation and compliance are on the way. Check back soon.</p>";
          if (box.hasAttribute("data-hide-empty")) box.closest("section").style.display = "none";
          return;
        }
        posts.forEach(function (p) { box.appendChild(postCard(p)); });
        var pages = +r.res.headers.get("X-WP-TotalPages") || 1;
        if (more) more.style.display = page < pages ? "" : "none";
      }).catch(function () {
        box.innerHTML = "<p class='notice'>Articles are temporarily unavailable. Please try again shortly.</p>";
        if (box.hasAttribute("data-hide-empty")) box.closest("section").style.display = "none";
      });
    }
    if (more) more.addEventListener("click", function () { page++; load(); });
    load();
  });
  var article = document.getElementById("article");
  if (article) {
    var slug = new URLSearchParams(location.search).get("slug");
    if (!slug) { location.href = "/insights/"; return; }
    getJSON(CMS + "/posts?slug=" + encodeURIComponent(slug) + "&_embed").then(function (r) {
      var p = list(r.json)[0];
      if (!p) { article.innerHTML = "<p class='notice'>That article could not be found. <a href='/insights/'>View all insights</a>.</p>"; return; }
      var tmp = el("div"); tmp.innerHTML = p.title.rendered;
      document.title = tmp.textContent + " | Think Mybiz Tech";
      article.innerHTML = "<a href='/insights/'>← All insights</a><h1 style='margin-top:14px'>" + p.title.rendered + "</h1><div class='meta'>" +
        fmtDate(p.date) + "</div><div class='content'>" + p.content.rendered + "</div>";
    }).catch(function () { article.innerHTML = "<p class='notice'>This article is temporarily unavailable.</p>"; });
  }

  /* ---------- Contact form → email compose ---------- */
  var form = document.getElementById("enquiry");
  if (form) form.addEventListener("submit", function (e) {
    e.preventDefault();
    var f = new FormData(form), lines = [];
    ["name", "company", "email", "phone", "service", "budget", "message"].forEach(function (k) {
      if (f.get(k)) lines.push(k.charAt(0).toUpperCase() + k.slice(1) + ": " + f.get(k));
    });
    var subj = "Project enquiry — " + (f.get("company") || f.get("name") || "website");
    window.location.href = "mailto:info@thinkmybiztech.com?subject=" + encodeURIComponent(subj) + "&body=" + encodeURIComponent(lines.join("\n"));
    var ok = document.getElementById("enquiry-ok"); if (ok) ok.style.display = "";
  });
})();
