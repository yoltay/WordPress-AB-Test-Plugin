/* global jQuery, ABTI_ADMIN, Chart */
(function ($) {
    'use strict';

    var COLORS = {
        a: { line: 'rgba(34,197,94,1)',  fill: 'rgba(34,197,94,.25)',  bg: 'rgba(34,197,94,1)' },
        b: { line: 'rgba(239,68,68,1)',  fill: 'rgba(239,68,68,.25)',  bg: 'rgba(239,68,68,1)' },
        c: { line: 'rgba(59,130,246,1)', fill: 'rgba(59,130,246,.25)', bg: 'rgba(59,130,246,1)' },
        d: { line: 'rgba(245,158,11,1)', fill: 'rgba(245,158,11,.25)', bg: 'rgba(245,158,11,1)' },
        e: { line: 'rgba(168,85,247,1)', fill: 'rgba(168,85,247,.25)', bg: 'rgba(168,85,247,1)' }
    };

    /* ============================================================
     * EDIT FORM
     * ============================================================ */
    function initEditForm() {
        var $list = $('#abti-variations');
        if (!$list.length) return;

        var blank = {};
        var tplEl = document.getElementById('abti-blank-template');
        if (tplEl) {
            try { blank = JSON.parse(tplEl.textContent || '{}'); } catch (e) { blank = {}; }
        }

        function letterFor(index) { return ['a','b','c','d','e'][index]; }
        function existingKeys() {
            return $list.find('.abti-variation').map(function () {
                return $(this).data('key');
            }).get();
        }
        function nextKey() {
            var taken = existingKeys();
            for (var i = 0; i < 5; i++) {
                var k = letterFor(i);
                if (taken.indexOf(k) === -1) return k;
            }
            return null;
        }
        function reindex() {
            $list.find('.abti-variation').each(function (i) {
                $(this).find('input,select').each(function () {
                    var name = $(this).attr('name');
                    if (!name) return;
                    name = name.replace(/variations\[\d+\]/, 'variations[' + i + ']');
                    $(this).attr('name', name);
                });
            });
        }
        function updatePercTotal() {
            var total = 0;
            $list.find('.abti-vperc').each(function () {
                total += parseInt($(this).val(), 10) || 0;
            });
            $('#abti-perc-total').text(total);
        }
        function updateSelectorPrefix($v) {
            var t = $v.find('.abti-vtype').val();
            $v.find('.abti-selector-prefix').text(t === 'class' ? '.' : '#');
        }

        // Add variation.
        $('#abti-add-variation').on('click', function () {
            if ($list.find('.abti-variation').length >= 5) {
                alert(ABTI_ADMIN.i18n.maxVariations);
                return;
            }
            var key = nextKey();
            if (!key) return;
            var data = (blank && blank[key]) ? blank[key] : { key: key, name: 'Varyasyon ' + key.toUpperCase(), selector: key + '-' + Math.random().toString(36).slice(2,10), selector_type: 'id', percentage: 0 };
            var i = $list.find('.abti-variation').length;
            var tpl = '' +
                '<div class="abti-variation" data-key="' + key + '">' +
                  '<div class="abti-variation-head">' +
                    '<span class="abti-variation-letter">' + key.toUpperCase() + '</span>' +
                    '<input type="text" class="abti-vname" name="variations[' + i + '][name]" value="' + escapeAttr(data.name) + '" />' +
                    '<button type="button" class="button abti-remove-var">×</button>' +
                  '</div>' +
                  '<div class="abti-variation-body">' +
                    '<input type="hidden" name="variations[' + i + '][key]" value="' + key + '" />' +
                    '<div class="abti-field">' +
                      '<label>Selector tipi</label>' +
                      '<select name="variations[' + i + '][selector_type]" class="abti-vtype">' +
                        '<option value="id" selected>CSS ID (#)</option>' +
                        '<option value="class">CSS Class (.)</option>' +
                      '</select>' +
                    '</div>' +
                    '<div class="abti-field abti-field-grow">' +
                      '<label>CSS ID / Class</label>' +
                      '<div class="abti-selector-wrap">' +
                        '<span class="abti-selector-prefix">#</span>' +
                        '<input type="text" class="abti-vselector" name="variations[' + i + '][selector]" value="' + escapeAttr(data.selector) + '" />' +
                        '<button type="button" class="button abti-regen">↻</button>' +
                        '<button type="button" class="button abti-copy">⧉</button>' +
                      '</div>' +
                    '</div>' +
                    '<div class="abti-field abti-field-narrow">' +
                      '<label>Gösterim %</label>' +
                      '<input type="number" min="0" max="100" step="1" class="abti-vperc" name="variations[' + i + '][percentage]" value="0" />' +
                    '</div>' +
                  '</div>' +
                '</div>';
            $list.append(tpl);
            updatePercTotal();
        });

        // Remove variation.
        $list.on('click', '.abti-remove-var', function () {
            if ($list.find('.abti-variation').length <= 2) {
                alert(ABTI_ADMIN.i18n.minVariations);
                return;
            }
            $(this).closest('.abti-variation').remove();
            reindex();
            // Anahtarları yeniden ata (a, b, c... boşluksuz).
            $list.find('.abti-variation').each(function (idx) {
                var newKey = letterFor(idx);
                $(this).attr('data-key', newKey);
                $(this).find('.abti-variation-letter').text(newKey.toUpperCase());
                $(this).find('input[name$="[key]"]').val(newKey);
                // Selector'ın ön ekini de güncelleyelim.
                var $sel = $(this).find('.abti-vselector');
                var v = $sel.val();
                v = v.replace(/^[a-e]-/, newKey + '-');
                $sel.val(v);
            });
            updatePercTotal();
        });

        // Regenerate selector.
        $list.on('click', '.abti-regen', function () {
            var $row = $(this).closest('.abti-variation');
            var key = $row.data('key');
            var rand = Math.random().toString(36).replace(/[^a-z0-9]/g, '').slice(0, 8);
            while (rand.length < 8) rand += '0';
            $row.find('.abti-vselector').val(key + '-' + rand);
        });

        // Copy.
        $list.on('click', '.abti-copy', function () {
            var $btn = $(this);
            var $row = $(this).closest('.abti-variation');
            var val = $row.find('.abti-vselector').val();
            try {
                navigator.clipboard.writeText(val).then(function () {
                    var orig = $btn.text();
                    $btn.text('✓');
                    setTimeout(function () { $btn.text(orig); }, 900);
                });
            } catch (e) {
                window.prompt('Kopyalayın', val);
            }
        });

        // Selector tipi değişince ön ek güncelle.
        $list.on('change', '.abti-vtype', function () {
            updateSelectorPrefix($(this).closest('.abti-variation'));
        });
        // Yüzde değişimi.
        $list.on('input', '.abti-vperc', updatePercTotal);

        // Yüzdeleri eşitle butonu.
        $('#abti-balance').on('click', function () {
            var $items = $list.find('.abti-vperc');
            var n = $items.length;
            if (!n) return;
            var base = Math.floor(100 / n);
            var remainder = 100 - base * n;
            $items.each(function (i) {
                $(this).val(base + (i < remainder ? 1 : 0));
            });
            updatePercTotal();
        });

        // Goal type değişince ipucu metnini değiştir.
        function syncGoalHints() {
            var t = $('#abti-goal-type').val();
            if (t === 'click') {
                $('.abti-goal-hint-click').show();
                $('.abti-goal-hint-form').hide();
                $('#abti-goal-selector').prop('required', true);
            } else {
                $('.abti-goal-hint-click').hide();
                $('.abti-goal-hint-form').show();
                $('#abti-goal-selector').prop('required', false);
            }
        }
        $('#abti-goal-type').on('change', syncGoalHints);
        syncGoalHints();

        // Submit kontrolü: yüzde toplamı 100 mü?
        $('form.abti-form').on('submit', function (e) {
            var total = 0;
            $list.find('.abti-vperc').each(function () { total += parseInt($(this).val(), 10) || 0; });
            if (total !== 100) {
                e.preventDefault();
                alert(ABTI_ADMIN.i18n.sumNot100.replace('%d', total));
            }
        });

        updatePercTotal();
    }

    function escapeAttr(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' }[c];
        });
    }

    /* ============================================================
     * LIST PAGE — delete confirm + STATS — reset confirm
     * ============================================================ */
    function initListConfirm() {
        $(document).on('click', '.abti-delete[data-confirm="1"]', function (e) {
            if (!window.confirm(ABTI_ADMIN.i18n.confirmDelete)) {
                e.preventDefault();
            }
        });
        $(document).on('click', '.abti-reset-link[data-confirm="1"]', function (e) {
            if (!window.confirm(ABTI_ADMIN.i18n.confirmReset)) {
                e.preventDefault();
            }
        });
    }

    /* ============================================================
     * STATS PAGE — charts
     * ============================================================ */
    function initStatsCharts() {
        var payloadEl = document.getElementById('abti-chart-payload');
        if (!payloadEl || typeof Chart === 'undefined') return;
        var data;
        try { data = JSON.parse(payloadEl.textContent || '{}'); } catch (e) { return; }
        if (!data || !data.variations) return;

        // Renk noktalarını sayfaya yerleştir.
        $('.abti-color-dot').each(function () {
            var k = $(this).data('key');
            var c = COLORS[k] || COLORS.a;
            $(this).css('background-color', c.bg);
        });

        // Üst kartlardaki renkli barlar.
        $('.abti-card').each(function (i) {
            var k = (data.variations[i] || {}).key || ['a','b','c','d','e'][i];
            var c = COLORS[k] || COLORS.a;
            $(this).find('.abti-card-bar span').css('background-color', c.bg);
        });

        // Line chart (conversion'ları gösteriyoruz).
        var lineDatasets = data.variations.map(function (v) {
            var c = COLORS[v.key] || COLORS.a;
            return {
                label: v.name,
                data: data.labels.map(function (d) {
                    return (data.conversions[v.key] && data.conversions[v.key][d]) || 0;
                }),
                borderColor: c.line,
                backgroundColor: c.fill,
                fill: true,
                tension: 0.25,
                pointRadius: 2,
                pointHoverRadius: 4
            };
        });

        var lineCtx = document.getElementById('abti-line-chart');
        if (lineCtx) {
            new Chart(lineCtx, {
                type: 'line',
                data: { labels: data.labels, datasets: lineDatasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: { legend: { position: 'top' } },
                    scales: {
                        x: { ticks: { maxRotation: 45, minRotation: 0, autoSkip: true, maxTicksLimit: 14 } },
                        y: { beginAtZero: true, ticks: { precision: 0 } }
                    }
                }
            });
        }

        // Donut: dönüşüm dağılımı (toplam conversions).
        var donutLabels = [];
        var donutData = [];
        var donutColors = [];
        data.variations.forEach(function (v) {
            var sum = 0;
            (data.conversions[v.key] ? Object.values(data.conversions[v.key]) : []).forEach(function (n) { sum += n; });
            donutLabels.push(v.name);
            donutData.push(sum);
            donutColors.push((COLORS[v.key] || COLORS.a).bg);
        });

        var donutCtx = document.getElementById('abti-donut-chart');
        if (donutCtx) {
            new Chart(donutCtx, {
                type: 'doughnut',
                data: {
                    labels: donutLabels,
                    datasets: [{
                        data: donutData.some(function (n) { return n > 0; }) ? donutData : donutLabels.map(function () { return 1; }),
                        backgroundColor: donutColors,
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: { legend: { position: 'top' } }
                }
            });
        }
    }

    $(function () {
        initEditForm();
        initListConfirm();
        initStatsCharts();
    });
})(jQuery);
