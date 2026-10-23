(function () {
  'use strict';

  var form = document.querySelector('[data-offline-sales]');
  if (!form) {
    return;
  }

  var syncUrl = form.getAttribute('data-sync-url');
  var dbName = 'mke-offline';
  var storeName = 'drafts';
  var banner = document.getElementById('offline-sales-banner');

  function isBrowserOffline() {
    return typeof navigator !== 'undefined' && navigator.onLine === false;
  }

  function supportsOfflineDrafts() {
    return typeof window !== 'undefined' && 'indexedDB' in window;
  }

  function openDb() {
    return new Promise(function (resolve, reject) {
      var req = indexedDB.open(dbName, 1);
      req.onupgradeneeded = function () {
        req.result.createObjectStore(storeName, { keyPath: 'id' });
      };
      req.onsuccess = function () { resolve(req.result); };
      req.onerror = function () { reject(req.error); };
    });
  }

  function saveDraft(payload) {
    return openDb().then(function (db) {
      return new Promise(function (resolve, reject) {
        var tx = db.transaction(storeName, 'readwrite');
        tx.objectStore(storeName).put({ id: payload.idempotency_key, payload: payload, saved_at: Date.now() });
        tx.oncomplete = function () { resolve(); };
        tx.onerror = function () { reject(tx.error); };
      });
    });
  }

  function allDrafts() {
    return openDb().then(function (db) {
      return new Promise(function (resolve, reject) {
        var tx = db.transaction(storeName, 'readonly');
        var req = tx.objectStore(storeName).getAll();
        req.onsuccess = function () { resolve(req.result || []); };
        req.onerror = function () { reject(req.error); };
      });
    });
  }

  function removeDraft(id) {
    return openDb().then(function (db) {
      return new Promise(function (resolve, reject) {
        var tx = db.transaction(storeName, 'readwrite');
        tx.objectStore(storeName).delete(id);
        tx.oncomplete = function () { resolve(); };
        tx.onerror = function () { reject(tx.error); };
      });
    });
  }

  function setBanner(text) {
    if (!banner) {
      return;
    }
    banner.textContent = text;
    banner.hidden = !text;
  }

  function formPayload(action) {
    var data = new FormData(form);
    var lines = [];
    var lineIndexes = {};
    data.forEach(function (value, key) {
      var match = key.match(/^lines\[(\d+)\]\[(.+)\]$/);
      if (match) {
        var idx = match[1];
        if (!lineIndexes[idx]) {
          lineIndexes[idx] = {};
          lines.push(lineIndexes[idx]);
        }
        lineIndexes[idx][match[2]] = value;
      }
    });
    lines.sort(function (a, b) { return 0; });

    var payload = {
      number: data.get('number'),
      retailer_id: parseInt(data.get('retailer_id'), 10),
      doc_date: data.get('doc_date'),
      delivery_date: data.get('delivery_date') || null,
      sales_rep_id: data.get('sales_rep_id') ? parseInt(data.get('sales_rep_id'), 10) : null,
      route_id: data.get('route_id') ? parseInt(data.get('route_id'), 10) : null,
      vehicle_id: data.get('vehicle_id') ? parseInt(data.get('vehicle_id'), 10) : null,
      header_discount: data.get('header_discount') || '0',
      slab_adjustment: data.get('slab_adjustment') || '0',
      combo_bonus_discount: data.get('combo_bonus_discount') || '0',
      rounding_adjustment: data.get('rounding_adjustment') || '0',
      notes: data.get('notes') || '',
      action: action || data.get('action') || 'save',
      lines: lines.filter(function (line) { return line.product_id; }),
    };
    return payload;
  }

  function syncOne(entry) {
    var token = document.querySelector('meta[name="csrf-token"]');
    return fetch(syncUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
        'Idempotency-Key': entry.payload.idempotency_key,
      },
      body: JSON.stringify(entry.payload),
      credentials: 'same-origin',
    }).then(function (response) {
      if (!response.ok) {
        throw new Error('sync failed');
      }
      return response.json();
    }).then(function () {
      return removeDraft(entry.id);
    });
  }

  function syncPending() {
    if (!navigator.onLine) {
      return Promise.resolve();
    }
    return allDrafts().then(function (drafts) {
      if (drafts.length === 0) {
        setBanner('');
        return;
      }
      setBanner(form.getAttribute('data-syncing-label') || 'Syncing offline drafts…');
      return drafts.reduce(function (chain, entry) {
        return chain.then(function () { return syncOne(entry); });
      }, Promise.resolve()).then(function () {
        setBanner(form.getAttribute('data-synced-label') || 'Offline drafts synced.');
        setTimeout(function () { setBanner(''); }, 4000);
      }).catch(function () {
        setBanner(form.getAttribute('data-sync-error-label') || 'Some drafts could not sync yet.');
      });
    });
  }

  form.addEventListener('submit', function (event) {
    if (form.dataset.offlineSubmitRetry === '1') {
      delete form.dataset.offlineSubmitRetry;
      return;
    }

    if (!isBrowserOffline() || !supportsOfflineDrafts()) {
      return;
    }

    event.preventDefault();

    var actionInput = form.querySelector('[name="action"]:focus') || form.querySelector('[name="action"]');
    var action = actionInput ? actionInput.value : 'save';
    var payload = formPayload(action);
    payload.idempotency_key = payload.number + '-' + payload.doc_date + '-' + Date.now();

    saveDraft(payload).then(function () {
      setBanner(form.getAttribute('data-offline-saved-label') || 'Saved offline. It will sync when you are back online.');
    }).catch(function () {
      form.dataset.offlineSubmitRetry = '1';
      form.submit();
    });
  });

  window.addEventListener('online', syncPending);
  document.addEventListener('DOMContentLoaded', syncPending);
})();
