{{-- Finans sayı alanları: TR format (1.250,50 / %40) · data-fin-num="money|pct|rate" --}}
<style>
    .fin-num-wrap { position: relative; }
    .fin-num-wrap .fin-num-suffix {
        position: absolute; right: .65rem; top: 50%; transform: translateY(-50%);
        color: #94a3b8; font-size: .8rem; font-weight: 600; pointer-events: none;
    }
    .fin-num-wrap input.fin-num {
        padding-right: 1.85rem; font-variant-numeric: tabular-nums; text-align: right;
    }
    .fin-num-wrap input.fin-num.is-invalid-fin { border-color: #dc2626 !important; }
    </style>
    <script>
    (function () {
        function parseFinNum(raw) {
            if (raw === null || raw === undefined) return NaN;
            var s = String(raw).trim().replace(/[\s\u00a0]/g, '').replace(/%/g, '');
            if (s === '' || s === '-' || s === ',' || s === '.') return NaN;

            if (s.indexOf(',') >= 0) {
                // 1.250,50 → binlik nokta + ondalık virgül
                s = s.replace(/\./g, '').replace(',', '.');
            } else if (s.indexOf('.') >= 0) {
                var i = s.lastIndexOf('.');
                var frac = s.slice(i + 1);
                var intp = s.slice(0, i).replace(/\./g, '');
                if (frac.length <= 2) {
                    s = intp + '.' + frac; // 40.5
                } else {
                    s = intp + frac; // 1.250 → 1250
                }
            }

            s = s.replace(/[^0-9.\-]/g, '');
            var n = parseFloat(s);
            return isFinite(n) ? n : NaN;
        }

        function formatFinNum(n, kind) {
            if (!isFinite(n)) return '';
            var dec = 2;
            if (kind === 'pct') {
                dec = Math.abs(n - Math.round(n)) < 0.0001 ? 0 : 1;
            } else if (kind === 'rate') {
                dec = Math.abs(n - Math.round(n)) < 0.0001 ? 0 : 2;
            }
            return n.toLocaleString('tr-TR', {
                minimumFractionDigits: dec,
                maximumFractionDigits: dec,
                useGrouping: true
            });
        }

        function bindInput(el) {
            if (!el || el._finNumBound) return;
            el._finNumBound = true;
            var kind = el.getAttribute('data-fin-num') || 'money';
            var suffix = el.getAttribute('data-fin-suffix');
            if (suffix === null) {
                suffix = kind === 'pct' ? '%' : (kind === 'rate' ? '₺' : '₺');
            }

            var wrap = document.createElement('div');
            wrap.className = 'fin-num-wrap';
            el.parentNode.insertBefore(wrap, el);
            wrap.appendChild(el);
            el.classList.add('fin-num');

            if (suffix !== '') {
                var suf = document.createElement('span');
                suf.className = 'fin-num-suffix';
                suf.textContent = suffix;
                wrap.appendChild(suf);
            }

            var initial = parseFinNum(el.value);
            if (isFinite(initial)) {
                el.value = formatFinNum(initial, kind);
            }

            el.setAttribute('inputmode', 'decimal');
            el.setAttribute('autocomplete', 'off');
            if (el.getAttribute('type') === 'number') {
                el.setAttribute('type', 'text');
            }

            el.addEventListener('focus', function () {
                var n = parseFinNum(el.value);
                if (isFinite(n)) {
                    // Odakta binlik yok, virgüllü ondalık
                    var raw = String(n);
                    if (raw.indexOf('e') >= 0 || raw.indexOf('E') >= 0) {
                        raw = n.toFixed(kind === 'pct' ? 1 : 2);
                    }
                    el.value = raw.replace('.', ',');
                }
                el.select();
            });

            el.addEventListener('blur', function () {
                var n = parseFinNum(el.value);
                if (!isFinite(n)) {
                    if (el.value.trim() !== '') el.classList.add('is-invalid-fin');
                    return;
                }
                el.classList.remove('is-invalid-fin');
                el.value = formatFinNum(n, kind);
            });

            el.addEventListener('input', function () {
                var cleaned = el.value.replace(/[^0-9.,\-]/g, '');
                if (cleaned !== el.value) el.value = cleaned;
                el.classList.remove('is-invalid-fin');
            });
        }

        function prepareForm(form) {
            form.querySelectorAll('[data-fin-num]').forEach(function (el) {
                var n = parseFinNum(el.value);
                if (!isFinite(n)) {
                    if (el.required || el.value.trim() !== '') {
                        el.classList.add('is-invalid-fin');
                        el.focus();
                        throw new Error('invalid');
                    }
                    el.value = '';
                    return;
                }
                // Sunucuya noktalı ondalık (1250.5)
                var rounded = Math.round(n * 10000) / 10000;
                el.value = String(rounded);
            });
        }

        function initFinNumbers(root) {
            (root || document).querySelectorAll('[data-fin-num]').forEach(bindInput);
            (root || document).querySelectorAll('form').forEach(function (form) {
                if (!form.querySelector('[data-fin-num]') || form._finNumSubmit) return;
                form._finNumSubmit = true;
                form.addEventListener('submit', function (e) {
                    try {
                        prepareForm(form);
                    } catch (err) {
                        e.preventDefault();
                        if (window.toastr) toastr.error('Sayısal alanları kontrol edin (örn. 1.250,50 veya 40).');
                        else alert('Sayısal alanları kontrol edin.');
                    }
                });
            });
        }

        window.FinNumber = { parse: parseFinNum, format: formatFinNum, init: initFinNumbers };
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () { initFinNumbers(); });
        } else {
            initFinNumbers();
        }
    })();
    </script>
