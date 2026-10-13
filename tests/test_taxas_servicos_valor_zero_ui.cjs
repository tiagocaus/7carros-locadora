const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');

const view = fs.readFileSync('app/Views/pages/taxas-e-servicos/adicionar.php', 'utf8');
const script = view.match(/<script>([\s\S]*?)<\/script>/)[1]
    .replace(/<\?[\s\S]*?\?>/g, '')
    .replace(/\}\)\(\);\s*$/, `
        globalThis.formularioTeste = {
            salvar,
            preencherFormulario,
            configurar(id) { registroId = id; filiaisSelecionadas = [1, 2]; }
        };
    })();`);

let dadosForm;
let inputs;
const requests = [];
const messages = [];
const button = { disabled: false, innerHTML: '' };
const campoValor = { value: '', closest() { return null; } };
const context = {
    console,
    window: { parent: { postMessage(message) { messages.push(message); } } },
    document: {
        readyState: 'loading',
        addEventListener() {},
        getElementById(id) {
            return {
                formTaxaServico: {},
                tipo_valor: { value: dadosForm.tipo_valor },
                valor: campoValor,
                id: {},
                nome: {},
                base_calculo: {},
                aplicar: {},
                valorPrefix: {},
                pageTitle: {},
                filiaisIdsJson: { value: '[1,2]' },
                ondeUsarJson: { value: 'SIS' },
                btnSalvar: button,
            }[id];
        },
        querySelectorAll() { return inputs; },
    },
    FormData: class { entries() { return Object.entries(dadosForm); } },
    API: {
        async post(url, dados) {
            requests.push({ url, dados });
            return { success: true };
        },
    },
};
vm.createContext(context);
vm.runInContext(fs.readFileSync('public/assets/js/currency.js', 'utf8'), context);
vm.runInContext(script, context);

async function enviar(tipo, valores, id = null) {
    requests.length = 0;
    messages.length = 0;
    dadosForm = { nome: 'Servico de cortesia', tipo_valor: tipo, valor: valores[0] };
    campoValor.value = valores[0];
    inputs = valores.map((value, index) => ({ value, dataset: { filialId: String(index + 1) } }));
    context.formularioTeste.configurar(id);
    await context.formularioTeste.salvar({ preventDefault() {} });
}

(async () => {
    dadosForm = { tipo_valor: 'POR' };
    context.formularioTeste.preencherFormulario({ id: 7, tipo_valor: 'POR', valor: 0 });
    assert.equal(campoValor.value, '0,00', 'Reabrir percentual zero deve mostrar o valor, sem deixar o campo vazio.');

    for (const id of [null, 7]) {
        await enviar('MON', ['0,00', '0,00'], id);
        assert.equal(requests.length, 1, 'O formulario deve enviar valores monetarios zero.');
        assert.equal(requests[0].dados.valores_filiais[1], 0);
        assert.equal(requests[0].dados.valor, 0);
        assert.equal(requests[0].url, id ? '/taxas-e-servicos/7/atualizar' : '/taxas-e-servicos/salvar');
        assert.equal(button.disabled, false);

        await enviar('MON', ['0,00', '25,50'], id);
        assert.equal(requests[0].dados.valores_filiais[1], 0);
        assert.equal(requests[0].dados.valores_filiais[2], 25.5);
        assert.equal(requests[0].dados.valor, 25.5);

        await enviar('POR', ['0,00'], id);
        assert.equal(requests.length, 1, 'O formulario deve enviar percentual zero.');
        assert.equal(requests[0].dados.valor, 0);

        for (const invalido of ['', ' ', '-0,01', 'abc', '1,2,3']) {
            for (const tipo of ['MON', 'POR']) {
                await enviar(tipo, [invalido, '0,00'], id);
                assert.equal(requests.length, 0, 'Valor invalido nao deve chegar a API.');
                assert.equal(messages[0].action, 'openAlert');
            }
        }
    }
    console.log('OK: formulario envia zero e valores mistos; rejeita valores invalidos em cadastro e edicao.');
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
