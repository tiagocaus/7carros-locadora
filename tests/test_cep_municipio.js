const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function criarPagina(comMunicipio = true) {
    const elementos = new Map();
    class Elemento {
        constructor(id = '') {
            this.id = id;
            this.value = '';
            this.style = {};
            this.listeners = {};
            this.disabled = false;
            this.hidden = false;
            this.classList = { toggle: (_, hidden) => { this.hidden = hidden; } };
            this.parentElement = { style: {}, appendChild: el => elementos.set(el.id, el) };
        }
        addEventListener(event, fn) { (this.listeners[event] ||= []).push(fn); }
        dispatchEvent(event) { for (const fn of this.listeners[event.type] || []) fn({ target: this }); }
        closest() { return this; }
        focus() {}
        remove() { elementos.delete(this.id); }
    }
    for (const id of ['cep', 'pais', 'rua', 'bairro', 'cidade', 'uf', 'numero']) elementos.set(id, new Elemento(id));
    if (comMunicipio) elementos.set('codigo_municipio', new Elemento('codigo_municipio'));
    elementos.get('pais').value = 'BR';
    const chamadas = [];
    let iniciar;
    const document = {
        getElementById: id => elementos.get(id),
        querySelectorAll: selector => selector === '.cep' ? [elementos.get('cep')] : [],
        createElement: () => new Elemento(),
        addEventListener: (_, fn) => { iniciar = fn; },
    };
    vm.runInNewContext(fs.readFileSync('public/assets/js/cep.js', 'utf8'), {
        document, Event: class { constructor(type) { this.type = type; } }, setTimeout: () => {},
        console: { error() {} },
        fetch: url => new Promise((resolve, reject) => chamadas.push({ url, resolve, reject })),
    });
    iniciar();
    return {
        campo: id => elementos.get(id), chamadas,
        alterar(id, valor, evento = 'input') {
            elementos.get(id).value = valor;
            elementos.get(id).dispatchEvent({ type: evento });
        },
        buscar() { elementos.get('cep').dispatchEvent({ type: 'blur' }); },
    };
}

const resposta = { ibge: '3205200', cep: '29119-021', logradouro: 'Rua de teste',
    bairro: 'Ataíde', localidade: 'Vila Velha', uf: 'ES' };
async function responder(chamada, dados = resposta) {
    chamada.resolve({ ok: true, json: async () => dados });
    await new Promise(resolve => setImmediate(resolve));
}

(async () => {
    const pagina = criarPagina();
    pagina.alterar('cep', '29119021'); pagina.buscar();
    await responder(pagina.chamadas[0]);
    assert.equal(pagina.campo('codigo_municipio').value, '3205200');
    assert.equal(pagina.campo('cidade').value, 'Vila Velha');
    for (const id of ['cep', 'cidade', 'uf']) {
        pagina.campo('codigo_municipio').value = '3205200';
        pagina.alterar(id, 'novo valor');
        assert.equal(pagina.campo('codigo_municipio').value, '', `Alterar ${id} deve limpar IBGE`);
    }
    pagina.alterar('codigo_municipio', '32x05200');
    assert.equal(pagina.campo('codigo_municipio').value, '3205200');
    pagina.alterar('pais', 'PT', 'change');
    assert.equal(pagina.campo('codigo_municipio').value, '');
    assert.equal(pagina.campo('codigo_municipio').disabled, true);
    assert.equal(pagina.campo('codigo_municipio').hidden, true);
    pagina.alterar('pais', 'BR', 'change');
    assert.equal(pagina.campo('codigo_municipio').disabled, false);

    const corrida = criarPagina();
    corrida.alterar('cep', '29119021'); corrida.buscar();
    corrida.alterar('cep', '01001000'); corrida.buscar();
    await responder(corrida.chamadas[1], { ...resposta, ibge: '3550308', localidade: 'São Paulo', uf: 'SP' });
    await responder(corrida.chamadas[0]);
    assert.equal(corrida.campo('codigo_municipio').value, '3550308', 'Resposta antiga nao pode sobrescrever a nova');
    assert.equal(corrida.campo('cidade').value, 'São Paulo');

    const manual = criarPagina();
    manual.alterar('cep', '29119021'); manual.buscar();
    manual.alterar('codigo_municipio', '3550308');
    await responder(manual.chamadas[0]);
    assert.equal(manual.campo('codigo_municipio').value, '3550308', 'Consulta pendente nao pode apagar a correcao manual');

    const falha = criarPagina();
    falha.alterar('cep', '29119021'); falha.buscar();
    await responder(falha.chamadas[0], { erro: true });
    assert.equal(falha.campo('codigo_municipio').value, '');
    falha.buscar(); falha.chamadas[1].reject(new Error('offline'));
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(falha.campo('codigo_municipio').value, '');
    falha.alterar('codigo_municipio', '3205200');
    assert.equal(falha.campo('codigo_municipio').value, '3205200', 'Falha da API deve permitir preenchimento manual');

    const estrangeiro = criarPagina();
    estrangeiro.alterar('cep', '29119021'); estrangeiro.buscar();
    estrangeiro.alterar('pais', 'PT', 'change');
    await responder(estrangeiro.chamadas[0]);
    assert.equal(estrangeiro.campo('codigo_municipio').value, '', 'Resposta brasileira pendente nao pode preencher cliente estrangeiro');

    const outroFormulario = criarPagina(false);
    outroFormulario.alterar('cep', '29119021'); outroFormulario.buscar();
    await responder(outroFormulario.chamadas[0]);
    assert.equal(outroFormulario.campo('cidade').value, 'Vila Velha', 'Helper deve continuar funcionando sem campo IBGE');
    console.log('Teste de CEP/IBGE, consulta concorrente, falha e pais estrangeiro passou.');
})().catch(error => { console.error(error); process.exitCode = 1; });
