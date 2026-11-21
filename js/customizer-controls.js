/**
 * Golden Hive Blocks — the Customizer's list and checklist controls
 * (includes/customizer-controls.php).
 *
 * A list keeps its rows in one setting, an array of objects. Every edit sets
 * a fresh copy, so the preview refreshes and "Pubblica" saves it. Fixed rows
 * (the shop filters) come from the control: the saved ones in their order,
 * then any the store has gained since, switched off. A checklist keeps the
 * values of its ticked boxes, in the control's order.
 *
 * Pure helpers (no DOM) are exported under Node for tests/customizer-test.js.
 */
(function () {
    'use strict';

    var has = Object.prototype.hasOwnProperty;

    /** One row with exactly the control's fields: checkboxes as booleans, the rest as strings. */
    function rowFrom(raw, fields) {
        var row = {};
        (fields || []).forEach(function (f) {
            var v = raw && has.call(raw, f.key) ? raw[f.key] : undefined;
            if (f.type === 'checkbox') {
                row[f.key] = v === true || v === 1 || v === '1' || v === 'yes';
            } else if (v === undefined || v === null) {
                row[f.key] = f.type === 'select' && f.choices && f.choices.length ? String(f.choices[0][0]) : '';
            } else {
                row[f.key] = String(v);
            }
        });
        return row;
    }

    /**
     * The rows a list shows for a setting value. Free lists: the saved rows,
     * at most max. Fixed lists: each saved key still offered, in its saved
     * order, then every offered key not saved yet, switched off ("on": false)
     * and labelled with the row's default label ([key, title, label]; the
     * title when there is none).
     */
    function rowsFor(value, params) {
        var fields = params.fields || [];
        var saved = Array.isArray(value) ? value : [];
        var fixed = params.fixed || [];
        if (!fixed.length) {
            var rows = saved.filter(function (r) { return r && typeof r === 'object'; })
                .map(function (r) { return rowFrom(r, fields); });
            return params.max ? rows.slice(0, params.max) : rows;
        }
        var titles = {};
        var labels = {};
        fixed.forEach(function (f) {
            titles[String(f[0])] = String(f[1]);
            labels[String(f[0])] = String(f[2] !== undefined ? f[2] : f[1]);
        });
        var out = [];
        var seen = {};
        saved.forEach(function (r) {
            var key = r && r.key !== undefined ? String(r.key) : '';
            if (!key || !has.call(titles, key) || seen[key]) return;
            seen[key] = true;
            var row = rowFrom(r, fields);
            row.key = key;
            out.push(row);
        });
        fixed.forEach(function (f) {
            var key = String(f[0]);
            if (seen[key]) return;
            var row = rowFrom({ label: labels[key], on: false }, fields);
            row.key = key;
            out.push(row);
        });
        return out;
    }

    /** Move rows[index] by dir (-1 up, 1 down), in place; false when it can't move. */
    function move(rows, index, dir) {
        var to = index + dir;
        if (index < 0 || index >= rows.length || to < 0 || to >= rows.length) return false;
        var item = rows[index];
        rows[index] = rows[to];
        rows[to] = item;
        return true;
    }

    /** The checklist's value: the ticked values, in the options' order. */
    function picked(options, isTicked) {
        return (options || []).map(function (o) { return String(o[0]); }).filter(isTicked);
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { rowFrom: rowFrom, rowsFor: rowsFor, move: move, picked: picked };
        return;
    }

    var api = window.wp && window.wp.customize;
    if (!api) return;

    var uid = 0;

    function el(tag, cls, text) {
        var node = document.createElement(tag);
        if (cls) node.className = cls;
        if (text !== undefined && text !== null) node.textContent = text;
        return node;
    }

    function clone(rows) {
        return JSON.parse(JSON.stringify(rows));
    }

    api.controlConstructor.ghb_list = api.Control.extend({
        ready: function () {
            var control = this;
            var params = control.params;
            var root = control.container[0];
            var box = root.querySelector('[data-ghb-list]');
            var add = root.querySelector('[data-ghb-list-add]');
            var fixed = (params.fixed || []).length > 0;
            var titles = {};
            (params.fixed || []).forEach(function (f) { titles[String(f[0])] = String(f[1]); });
            var rows = rowsFor(control.setting.get(), params);
            var ours = false;

            function full() {
                return !!(params.max && rows.length >= params.max);
            }

            function save() {
                ours = true;
                control.setting.set(clone(rows));
                ours = false;
                if (add) add.disabled = full();
            }

            function button(text, label, disabled, onClick) {
                var b = el('button', 'button-link ghb-list__tool', text);
                b.type = 'button';
                b.setAttribute('aria-label', label);
                b.disabled = disabled;
                b.addEventListener('click', onClick);
                return b;
            }

            function field(row, f) {
                var id = 'ghb-list-' + (++uid);
                var wrap = el('p', 'ghb-list__field ghb-list__field--' + f.type);
                var label = el('label', '', f.label);
                label.htmlFor = id;
                var input;
                if (f.type === 'checkbox') {
                    input = el('input');
                    input.type = 'checkbox';
                    input.id = id;
                    input.checked = !!row[f.key];
                    input.addEventListener('change', function () { row[f.key] = input.checked; save(); });
                    wrap.appendChild(input);
                    wrap.appendChild(label);
                    return wrap;
                }
                wrap.appendChild(label);
                if (f.type === 'select') {
                    input = el('select');
                    (f.choices || []).forEach(function (c) {
                        var option = el('option', '', String(c[1]));
                        option.value = String(c[0]);
                        input.appendChild(option);
                    });
                    input.value = row[f.key];
                    input.addEventListener('change', function () { row[f.key] = input.value; save(); });
                } else {
                    input = el(f.type === 'textarea' ? 'textarea' : 'input');
                    if (f.type === 'textarea') {
                        input.rows = 3;
                    } else {
                        input.type = 'text';
                    }
                    input.value = row[f.key];
                    input.addEventListener('input', function () { row[f.key] = input.value; save(); });
                }
                input.id = id;
                input.className = 'widefat';
                wrap.appendChild(input);
                return wrap;
            }

            function render() {
                box.textContent = '';
                rows.forEach(function (row, i) {
                    var item = el('div', 'ghb-list__row');
                    var head = el('div', 'ghb-list__head');
                    head.appendChild(el('span', 'ghb-list__title', fixed ? titles[row.key] : params.i18n.row.replace('%d', i + 1)));
                    var tools = el('span', 'ghb-list__tools');
                    tools.appendChild(button('↑', params.i18n.up, i === 0, function () {
                        if (move(rows, i, -1)) { save(); render(); }
                    }));
                    tools.appendChild(button('↓', params.i18n.down, i === rows.length - 1, function () {
                        if (move(rows, i, 1)) { save(); render(); }
                    }));
                    if (!fixed) {
                        tools.appendChild(button('×', params.i18n.remove, false, function () {
                            rows.splice(i, 1);
                            save();
                            render();
                        }));
                    }
                    head.appendChild(tools);
                    item.appendChild(head);
                    (params.fields || []).forEach(function (f) { item.appendChild(field(row, f)); });
                    box.appendChild(item);
                });
                if (add) add.disabled = full();
            }

            if (add) {
                add.addEventListener('click', function () {
                    if (full()) return;
                    rows.push(rowFrom({}, params.fields));
                    save();
                    render();
                    var input = box.lastElementChild && box.lastElementChild.querySelector('input, select, textarea');
                    if (input) input.focus();
                });
            }

            // A value set elsewhere (a reset, another control): show it.
            control.setting.bind(function (value) {
                if (ours) return;
                rows = rowsFor(value, params);
                render();
            });

            render();
        }
    });

    api.controlConstructor.ghb_checklist = api.Control.extend({
        ready: function () {
            var control = this;
            var params = control.params;
            var box = control.container[0].querySelector('[data-ghb-checklist]');
            var options = params.options || [];
            var current = (Array.isArray(control.setting.get()) ? control.setting.get() : []).map(String);

            if (!options.length) {
                box.appendChild(el('p', 'description', params.i18n.empty));
                return;
            }
            options.forEach(function (o) {
                var id = 'ghb-check-' + (++uid);
                var row = el('p', 'ghb-checklist__item');
                var input = el('input');
                input.type = 'checkbox';
                input.id = id;
                input.value = String(o[0]);
                input.checked = current.indexOf(String(o[0])) !== -1;
                var label = el('label', '', String(o[1]));
                label.htmlFor = id;
                row.appendChild(input);
                row.appendChild(label);
                box.appendChild(row);
            });
            box.addEventListener('change', function () {
                var ticked = {};
                box.querySelectorAll('input[type="checkbox"]').forEach(function (input) {
                    if (input.checked) ticked[input.value] = true;
                });
                control.setting.set(picked(options, function (v) { return !!ticked[v]; }));
            });
        }
    });
})();
