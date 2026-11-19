/* ============================================================================
   SL Diagnostics - Inventory Management
   assets/js/app.js  (vanilla JS, no dependencies beyond Bootstrap bundle)
   ============================================================================ */
(function () {
  'use strict';

  /* --------------------------------------------------------------------- */
  /* number helpers                                                        */
  /* --------------------------------------------------------------------- */
  window.SL = {
    num: function (v) {
      var n = parseFloat(String(v === undefined || v === null ? '' : v).replace(/,/g, ''));
      return isNaN(n) ? 0 : n;
    },
    money: function (v) {
      return (Math.round(SL.num(v) * 100) / 100).toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
    },
    /** GET a JSON endpoint; always resolves to an object. */
    getJSON: function (url) {
      return fetch(url, { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : { ok: false, error: 'HTTP ' + r.status }; })
        .catch(function (e) { return { ok: false, error: String(e) }; });
    }
  };

  /* --------------------------------------------------------------------- */
  /* confirm-before-submit / before-navigate                               */
  /* --------------------------------------------------------------------- */
  document.addEventListener('click', function (e) {
    var el = e.target.closest('[data-confirm]');
    if (el && !window.confirm(el.getAttribute('data-confirm'))) {
      e.preventDefault();
      e.stopPropagation();
    }
  });

  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (f.hasAttribute('data-confirm') && !window.confirm(f.getAttribute('data-confirm'))) {
      e.preventDefault();
    }
  });

  /* --------------------------------------------------------------------- */
  /* instant client side table filter: <input data-filter="#tableId">      */
  /* --------------------------------------------------------------------- */
  document.addEventListener('input', function (e) {
    var box = e.target;
    if (!box.matches('[data-filter]')) return;
    var table = document.querySelector(box.getAttribute('data-filter'));
    if (!table) return;
    var q = box.value.toLowerCase().trim();
    var rows = table.tBodies.length ? table.tBodies[0].rows : [];
    var shown = 0;
    for (var i = 0; i < rows.length; i++) {
      var hit = rows[i].textContent.toLowerCase().indexOf(q) !== -1;
      rows[i].style.display = hit ? '' : 'none';
      if (hit) shown++;
    }
    var out = document.querySelector('[data-filter-count]');
    if (out) out.textContent = shown + ' row(s)';
  });

  /* --------------------------------------------------------------------- */
  /* auto dismiss flash messages                                           */
  /* --------------------------------------------------------------------- */
  window.setTimeout(function () {
    document.querySelectorAll('.alert-success.alert-dismissible').forEach(function (a) {
      if (window.bootstrap && bootstrap.Alert) {
        bootstrap.Alert.getOrCreateInstance(a).close();
      }
    });
  }, 7000);

})();
