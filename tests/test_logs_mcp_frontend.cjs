// Usage: node tests/test_logs_mcp_frontend.cjs /path/to/rendered-logs-view.js
// Exercises the rendered view's asynchronous requests and untrusted text escaping.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const elements = new Map();
let detailButtons = [];
const messages = [];
const element = id => {
    if (!elements.has(id)) elements.set(id, {
        innerHTML: '', textContent: '', listeners: {},
        classList: { add() {}, remove() {}, toggle() {} }, setAttribute() {},
        addEventListener(event, callback) { this.listeners[event] = callback; },
        querySelectorAll(selector) {
            if (selector !== '.btn-mcp-detalhe') return [];
            detailButtons = [...this.innerHTML.matchAll(/data-id="(\d+)"/g)].map(match => ({
                listeners: {}, getAttribute() { return match[1]; },
                addEventListener(event, callback) { this.listeners[event] = callback; },
            }));
            return detailButtons;
        },
    });
    return elements.get(id);
};
const requests = [];
const context = {
    document: { getElementById: element, querySelector: selector => element(selector.replace(/^#/, '')) },
    window: { parent: { postMessage: message => messages.push(message) } }, console, setTimeout, clearTimeout,
    DateHelper: { formatDateTime: value => value },
    API: { get: (url, params) => new Promise(resolve => requests.push({ url, params, resolve })) },
};
vm.runInNewContext(fs.readFileSync(process.argv[2], 'utf8'), context);
const flush = () => new Promise(resolve => setImmediate(resolve));
const pagination = { page: 1, perPage: 10, total: null, totalPages: null, hasNext: false, hasPrev: false, returned: 1 };
(async () => {
    assert.equal(requests[0].url, '/api/logs');
    element('tabMcp').listeners.click();
    assert.equal(requests[1].url, '/api/logs/mcp');
    requests[1].resolve({ success: true, pagination, data: [{
        data: '2026-11-16 14:46:57', usuario_nome: '<img src=x onerror=alert(1)>', aplicativo: 'App & tenant',
        operacao: 'Listar veículos', evento: 'listar_veiculos', resultado: 'blocked', registros: 0, duracao_ms: 12,
        request_id: '\" onmouseover=\"alert(2)',
    }] });
    await flush();
    const table = element('logsTableBody');
    assert.ok(table.innerHTML.includes('&lt;img'));
    assert.ok(!table.innerHTML.includes('<img'));
    assert.ok(table.innerHTML.includes('App &amp; tenant'));
    assert.ok(table.innerHTML.includes('&quot; onmouseover=&quot;'));
    assert.ok(table.innerHTML.includes('Não concluída'));
    const current = table.innerHTML;
    requests[0].resolve({ success: true, pagination, data: [{ mensagem: 'STALE_AUDIT_RESPONSE', data: '' }] });
    await flush();
    assert.equal(table.innerHTML, current, 'An older tab response must not replace the MCP table.');
    context.window.irParaPagina(2);
    assert.equal(requests[2].params.page, 2);
    requests[2].resolve({ success: true, data: [], pagination: { ...pagination, page: 2, returned: 0, hasPrev: true } });
    await flush();
    assert.ok(table.innerHTML.includes('Nenhum log encontrado'));
    assert.ok(element('registrosInfo').textContent.includes('0-0'));
    element('tabEnvios').listeners.click();
    assert.equal(requests[3].url, '/api/logs/envios');
    element('tabMcp').listeners.click();
    requests[4].resolve({ success: false, message: '<script>private error</script>' });
    await flush();
    const failed = table.innerHTML;
    assert.ok(failed.includes('Erro ao carregar logs'));
    assert.ok(!failed.includes('private error'));
    requests[3].resolve({ success: true, data: [], pagination });
    await flush();
    assert.equal(table.innerHTML, failed);
    element('tabMcp').listeners.click();
    requests.at(-1).resolve({ success: true, pagination, data: [{
        id: 42, operacao: 'Listar veículos', evento: 'listar_veiculos', resultado: 'success',
        pergunta_previa: '<img src=x> Á🚙…', solicitacao_previa: 'HIDDEN_REQUEST_JSON', retorno_previa: 'HIDDEN_RESPONSE', request_id: 'ref',
    }] });
    await flush();
    assert.ok(table.innerHTML.includes('&lt;img'));
    assert.equal((element('tableHeaderRow').innerHTML.match(/<th /g) || []).length, 8);
    assert.ok(!element('tableHeaderRow').innerHTML.includes('Registros retornados'));
    assert.ok(!element('tableHeaderRow').innerHTML.includes('Retorno do MCP'));
    assert.ok(!table.innerHTML.includes('HIDDEN_RESPONSE') && !table.innerHTML.includes('HIDDEN_REQUEST_JSON'));
    detailButtons[0].listeners.click();
    assert.equal(requests.at(-1).url, '/api/logs/mcp/42');
    requests.at(-1).resolve({success: true, data: {
        operacao: 'Listar veículos', evento: 'listar_veiculos', resultado: 'success', registros: 19,
        pergunta_usuario: '  Pergunta fiel? Á🚙\n<script>evil</script>',
        solicitacao: JSON.stringify({busca: '<script>evil</script>'}),
        retorno: JSON.stringify({dados: ['Á🚙', '<img src=x onerror=evil>']}),
    }});
    await flush();
    const panel = messages.at(-1);
    assert.equal(panel.action, 'openOffcanvasContent');
    assert.equal(panel.width, '40%', 'MCP details use the same desktop width as message details.');
    assert.equal((panel.content.match(/grid grid-cols-3 border-b/g) || []).length, 8);
    assert.equal((panel.content.match(/<section class="border border-slate-200 rounded-lg overflow-hidden">/g) || []).length, 3);
    assert.ok(panel.content.includes('col-span-2 px-3 py-2 text-sm text-slate-800 break-words'));

    assert.ok(panel.content.includes('Registros retornados') && panel.content.includes('19'));
    assert.ok(panel.content.includes('&lt;script&gt;') && !panel.content.includes('<script>'));
    assert.ok(panel.content.includes('Pergunta do cliente (informada pela IA)') && panel.content.includes('  Pergunta fiel? Á🚙\n'));
    assert.ok(panel.content.includes('Á🚙') && !panel.content.includes('<img'));
    detailButtons[0].listeners.click();
    requests.at(-1).resolve({success:true, data:{solicitacao:null,retorno:null}});
    await flush();
    assert.ok(messages.at(-1).content.includes('Conteúdo não registrado nesta chamada'));
    assert.ok(messages.at(-1).content.includes('Pergunta não informada pela IA'));
    detailButtons[0].listeners.click();
    const oldDetail = requests.at(-1);
    element('tabAuditoria').listeners.click();
    const countBefore = messages.length;
    oldDetail.resolve({success:true,data:{retorno:'STALE_DETAIL'}});
    await flush();
    assert.equal(messages.length, countBefore, 'Stale details cannot open after switching tabs.');
    console.log('OK: MCP view escaping, tab/request races, empty state, pagination and safe errors.');
})().catch(error => { console.error(error); process.exitCode = 1; });
