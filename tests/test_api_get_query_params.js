const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '..');

function testarLayoutIframe() {
    const layout = fs.readFileSync(
        path.join(root, 'app/Views/layouts/iframe.php'),
        'utf8'
    );

    assert.match(
        layout,
        /asset\('js\/api\.min\.js'\)/,
        'O layout do iframe deve carregar o helper API minificado.'
    );
    assert.doesNotMatch(
        layout,
        /asset\('js\/api\.js'\)/,
        'O layout do iframe nao deve carregar o arquivo fonte do helper API.'
    );
}

async function testarArquivo(caminhoRelativo) {
    const requisicoes = [];
    const contexto = {
        URLSearchParams,
        console,
        fetch: async (url) => {
            requisicoes.push(url);
            return {
                status: 200,
                json: async () => ({ success: true })
            };
        },
        document: {
            querySelector: () => ({ content: 'csrf-teste' }),
            querySelectorAll: () => [],
            readyState: 'complete',
            visibilityState: 'visible'
        },
        setInterval: () => 1,
        clearInterval: () => {}
    };

    contexto.window = {
        parent: {},
        top: {},
        location: {}
    };

    vm.createContext(contexto);
    const codigo = fs.readFileSync(path.join(root, caminhoRelativo), 'utf8');
    vm.runInContext(`${codigo}\nwindow.__API_TESTE__ = API;`, contexto);
    const api = contexto.window.__API_TESTE__;

    await api.get('/api/taxas-e-servicos/buscar', { q: 'Taxa' });
    assert.equal(
        requisicoes.pop(),
        '/api/taxas-e-servicos/buscar?q=Taxa',
        `${caminhoRelativo}: deve adicionar a primeira query string com ?`
    );

    await api.get('/api/taxas-e-servicos/buscar?id_filial=762', { q: 'Taxa' });
    assert.equal(
        requisicoes.pop(),
        '/api/taxas-e-servicos/buscar?id_filial=762&q=Taxa',
        `${caminhoRelativo}: deve preservar id_filial e adicionar q com &`
    );

    await api.get('/api/taxas-e-servicos/buscar?id_filial=762', {
        q: 'Taxa de limpeza & colisão'
    });
    assert.equal(
        requisicoes.pop(),
        '/api/taxas-e-servicos/buscar?id_filial=762&q=Taxa+de+limpeza+%26+colis%C3%A3o',
        `${caminhoRelativo}: deve codificar corretamente o termo de busca`
    );
}

(async () => {
    testarLayoutIframe();
    await testarArquivo('public/assets/js/api.js');
    await testarArquivo('public/assets/js/api.min.js');
    console.log('OK: iframe carrega API minificada e API.get combina query strings existentes.');
})().catch((erro) => {
    console.error(erro);
    process.exit(1);
});
