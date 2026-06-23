/* ab-test-int — frontend tracking
 *
 * Picker (head içinde inline) varyasyonu zaten seçti ve görünür yaptı.
 * Bu script footer'da defer ile yüklenir; sadece view & conversion event'lerini
 * REST API'ye gönderir. Rendering'i bloklamaz.
 *
 * window.ABTI_CONFIG  → konfigürasyon (picker tarafından set edildi)
 * window.ABTI_ASSIGN  → { test_id: 'a' | 'b' | ... }  (picker tarafından set edildi)
 */
(function () {
    'use strict';

    if (!window.ABTI_CONFIG || !window.ABTI_CONFIG.tests || !window.ABTI_CONFIG.tests.length) return;

    // Picker head'de başarısız olduysa: PHP index-0 varyasyonunu zaten görünür
    // bıraktı (diğerleri gizli). İki element aynı anda görünmemesi için style'a
    // dokunmuyoruz; sadece tracking'i atlıyoruz.
    if (!window.ABTI_PICKER_DONE) { return; }

    if (!window.ABTI_ASSIGN) return;

    var cfg = window.ABTI_CONFIG;
    var ASSIGN = window.ABTI_ASSIGN;
    var STORAGE_PREFIX = 'abti_';
    var VISITOR_KEY = STORAGE_PREFIX + 'visitor';
    var SESSION_VIEW_PREFIX = STORAGE_PREFIX + 'view_';
    var CONV_PREFIX = STORAGE_PREFIX + 'conv_';

    /* ---------- helpers ---------- */

    function safeStorage(type) {
        try {
            var s = window[type];
            var k = '__abti_test__';
            s.setItem(k, '1');
            s.removeItem(k);
            return s;
        } catch (e) { return null; }
    }
    var ls = safeStorage('localStorage');
    var ss = safeStorage('sessionStorage');
    function lsGet(k) { return ls ? ls.getItem(k) : null; }
    function lsSet(k, v) { if (ls) { try { ls.setItem(k, v); } catch (e) {} } }
    function ssGet(k) { return ss ? ss.getItem(k) : null; }
    function ssSet(k, v) { if (ss) { try { ss.setItem(k, v); } catch (e) {} } }

    function getVisitorId() {
        var id = lsGet(VISITOR_KEY);
        if (!id) {
            id = 'v_' + Math.random().toString(36).slice(2, 10) + Date.now().toString(36);
            lsSet(VISITOR_KEY, id);
        }
        return id;
    }

    /* ---------- tracking ---------- */

    function track(testId, variationKey, eventType) {
        var visitorId = getVisitorId();
        var payload = JSON.stringify({
            test_id: testId,
            variation_key: variationKey,
            event_type: eventType,
            visitor_id: visitorId
        });

        try {
            if (navigator.sendBeacon) {
                var blob = new Blob([payload], { type: 'application/json' });
                if (navigator.sendBeacon(cfg.rest, blob)) return;
            }
        } catch (e) {}

        try {
            fetch(cfg.rest, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: payload,
                keepalive: true,
                credentials: 'same-origin'
            }).catch(function () {});
        } catch (e) {
            try {
                var xhr = new XMLHttpRequest();
                xhr.open('POST', cfg.rest, true);
                xhr.setRequestHeader('Content-Type', 'application/json');
                xhr.send(payload);
            } catch (e2) {}
        }
    }

    /* ---------- per-test tracking setup ---------- */

    function processTest(test) {
        var chosenKey = ASSIGN[test.id];
        if (!chosenKey) return;

        // View — her oturumda 1 kez.
        var viewKey = SESSION_VIEW_PREFIX + test.id;
        if (!ssGet(viewKey)) {
            track(test.id, chosenKey, 'view');
            ssSet(viewKey, '1');
        }

        // Conversion listeners.
        var goalType = test.goal_type;
        var goalSel = (test.goal_selector || '').trim();
        var convertedKey = CONV_PREFIX + test.id;

        function fireConversion() {
            if (lsGet(convertedKey)) return;
            lsSet(convertedKey, '1');
            track(test.id, chosenKey, 'conversion');
        }

        if (goalType === 'click') {
            if (!goalSel) return;
            document.addEventListener('click', function (e) {
                try {
                    var target = e.target;
                    if (!target || !target.closest) return;
                    if (target.closest(goalSel)) fireConversion();
                } catch (err) {}
            }, true);
        } else if (goalType === 'form_submit') {
            document.addEventListener('submit', function (e) {
                try {
                    var form = e.target;
                    if (!goalSel) { fireConversion(); return; }
                    if (form.matches && (form.matches(goalSel) || (form.querySelector && form.querySelector(goalSel)))) {
                        fireConversion();
                    }
                } catch (err) {}
            }, true);

            // Yaygın form eklentileri (popup formları dahil).
            document.addEventListener('wpcf7mailsent', fireConversion, false);
            document.addEventListener('wpformsAjaxSubmitSuccess', fireConversion, false);
            document.addEventListener('fluentform_submission_success', fireConversion, false);
            if (window.jQuery) {
                // Elementor Forms (Elementor Pro 3.7+ submit_success event'ini dispatch eder)
                window.jQuery(document).on('submit_success', fireConversion);
                // Gravity Forms
                window.jQuery(document).on('gform_confirmation_loaded', fireConversion);
            }
        }
    }

    function init() {
        for (var i = 0; i < cfg.tests.length; i++) {
            try { processTest(cfg.tests[i]); } catch (e) {}
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
