const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const php = fs.readFileSync(path.resolve(__dirname, '../includes/class-abti-frontend.php'), 'utf8');
const match = php.match(/return <<<'JS'\r?\n([\s\S]*?)\r?\nJS;/);
assert.ok(match, 'Inline picker heredoc must be extractable');
const picker = match[1];
new Function(picker);

function storage(seed) {
    const values = new Map(Object.entries(seed || {}));
    return {
        getItem(key) { return values.has(key) ? values.get(key) : null; },
        setItem(key, value) { values.set(key, String(value)); },
        removeItem(key) { values.delete(key); },
        values
    };
}

function runPicker(options) {
    const style = {
        id: 'abti-v131-hide-all',
        tagName: 'STYLE',
        textContent: '#b-id{display:none !important;}',
        getAttribute(name) {
            if (name === 'data-abti') return '1';
            if (name === 'data-abti-rucss') return 'skip';
            return null;
        }
    };
    const staleUsedCss = options.staleUsedCss ? {
        id: 'wpr-usedcss',
        tagName: 'STYLE',
        textContent: options.staleUsedCss,
        getAttribute() { return null; }
    } : null;
    const localStorage = storage(options.storage);
    const requests = [];
    class XHR {
        open(method, url, async) { this.method = method; this.url = url; this.async = async; }
        setRequestHeader() {}
        send(body) {
            requests.push({ method: this.method, url: this.url, async: this.async, body: JSON.parse(body) });
            this.status = options.status === undefined ? 200 : options.status;
            this.responseText = JSON.stringify(options.response || {});
        }
    }
    const window = {
        ABTI_CONFIG: {
            assign: '/wp-json/abti/v1/assign',
            tests: [{
                id: 7,
                variations: [
                    { key: 'a', selector: 'a-id', selector_type: 'id', percentage: 50 },
                    { key: 'b', selector: 'b-id', selector_type: 'id', percentage: 50 }
                ]
            }]
        },
        CSS: { escape(value) { return value; } }
    };
    const context = {
        window,
        document: {
            getElementById(id) {
                if (id === 'abti-v131-hide-all' || id === 'abti-hide-all') return style;
                if (id === 'wpr-usedcss') return staleUsedCss;
                return null;
            },
            currentScript: null,
            getElementsByTagName(name) {
                if (String(name).toLowerCase() === 'style') {
                    return staleUsedCss ? [style, staleUsedCss] : [style];
                }
                return [];
            }
        },
        localStorage,
        XMLHttpRequest: XHR,
        CSS: window.CSS,
        Date,
        Math,
        JSON
    };
    vm.runInNewContext(picker, context);
    return { window, style, staleUsedCss, localStorage, requests };
}

{
    const result = runPicker({ response: { ok: true, variation_key: 'b' } });
    assert.equal(result.requests.length, 1, 'First visit must request a server assignment');
    assert.equal(result.requests[0].async, false, 'First assignment must block body parsing');
    assert.equal(result.window.ABTI_ASSIGN[7], 'b');
    assert.equal(result.localStorage.getItem('abti_v3_test_7'), 'b');
    assert.match(result.style.textContent, /#a-id/);
    assert.doesNotMatch(result.style.textContent, /#b-id/);
}

{
    const result = runPicker({ storage: { abti_v3_visitor: 'v_saved', abti_v3_test_7: 'b' } });
    assert.equal(result.requests.length, 0, 'Returning browser must use its saved assignment');
    assert.equal(result.window.ABTI_ASSIGN[7], 'b');
}

{
    const result = runPicker({
        storage: { abti_test_7: 'a' },
        response: { ok: true, variation_key: 'b' }
    });
    assert.equal(result.requests.length, 1, 'Legacy v1.2 assignment must be ignored');
    assert.equal(result.window.ABTI_ASSIGN[7], 'b');
}

{
    const result = runPicker({
        storage: { abti_v3_test_7: 'b' },
        response: { ok: true, variation_key: 'a' }
    });
    assert.equal(result.requests.length, 1, 'Assignment without its visitor ID must be revalidated by the server');
    assert.equal(result.window.ABTI_ASSIGN[7], 'a');
}

{
    const result = runPicker({ status: 503, response: { ok: false } });
    assert.equal(result.window.ABTI_ASSIGN[7], 'a', 'Network failure must keep PHP index-0 fallback');
    assert.equal(result.localStorage.getItem('abti_v3_test_7'), null, 'Fallback must not become a permanent assignment');
    assert.match(result.style.textContent, /#b-id/);
}

{
    const result = runPicker({
        response: { ok: true, variation_key: 'b' },
        staleUsedCss: '#b-id{display:none!important}.kept{color:red}#a-id{display:none!important}'
    });
    assert.equal(result.window.ABTI_ASSIGN[7], 'b');
    assert.equal(result.style.textContent, '#a-id{display:none !important;}');
    assert.equal(result.staleUsedCss.textContent, '.kept{color:red}', 'WP Rocket Used CSS must not keep stale ABTI hide rules');
}

console.log('inline picker behavior: PASS');
