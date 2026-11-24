/* Think Mybiz Tech Client Hub — small UI helpers (no dependencies) */
(function () {
  "use strict";
  // Spam timing + page for public forms
  document.querySelectorAll("[data-ts]").forEach(function (i) { i.value = String(Date.now()); });
  document.querySelectorAll("[data-page]").forEach(function (i) { i.value = location.pathname + location.search; });
  // Show/hide password box when "create account" is ticked
  document.querySelectorAll("[data-toggle]").forEach(function (cb) {
    var box = document.querySelector(cb.getAttribute("data-toggle"));
    function sync() { if (!box) return; box.hidden = !cb.checked; var i = box.querySelector("input"); if (i) i.required = cb.checked; }
    cb.addEventListener("change", sync); sync();
  });
  // Copy buttons
  document.querySelectorAll("[data-copy]").forEach(function (b) {
    b.addEventListener("click", function () {
      var t = b.getAttribute("data-copy");
      (navigator.clipboard ? navigator.clipboard.writeText(t) : Promise.reject()).then(function () { b.textContent = "Copied ✓"; }, function () { window.prompt("Copy the text:", t); });
    });
  });
  // Document line editor: add/remove lines and show an estimate (the server recalculates on save)
  var body = document.getElementById("lines");
  if (body) {
    function cents(s) { s = String(s || "").replace(/[\sR]/g, ""); if (/^\d+,\d{1,2}$/.test(s)) s = s.replace(",", "."); s = s.replace(/,/g, ""); var n = parseFloat(s); return isNaN(n) ? 0 : Math.round(n * 100); }
    function fmt(c) { return "R" + (c / 100).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, " "); }
    function recalc() {
      var t = 0;
      body.querySelectorAll("tr").forEach(function (tr) {
        var q = parseFloat(String(tr.querySelector("[name='qty[]']").value || "1").replace(",", ".")) || 0;
        var a = Math.round(q * cents(tr.querySelector("[name='price[]']").value)); t += a;
        tr.querySelector(".amt").textContent = a ? fmt(a) : "";
      });
      var et = document.getElementById("esttotal"); if (et) et.textContent = fmt(t);
    }
    body.addEventListener("input", recalc);
    body.addEventListener("click", function (e) {
      if (e.target.classList.contains("rm") && body.querySelectorAll("tr").length > 1) { e.target.closest("tr").remove(); recalc(); }
    });
    document.getElementById("addline").addEventListener("click", function () {
      var tr = body.querySelector("tr").cloneNode(true);
      tr.querySelectorAll("input").forEach(function (i) { i.value = i.name === "qty[]" ? "1" : ""; });
      body.appendChild(tr); tr.querySelector("input").focus(); recalc();
    });
    recalc();
  }
})();
