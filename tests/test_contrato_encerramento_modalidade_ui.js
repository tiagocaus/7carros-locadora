const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const root = path.join(__dirname, '..');
const view = fs.readFileSync(path.join(root, 'app/Views/pages/contratos/devolver.php'), 'utf8');
const history = fs.readFileSync(path.join(root, 'public/assets/js/contratos.js'), 'utf8');
const elements = {};
const element = id => elements[id] ||= {innerHTML: '', disabled: true};
const context = vm.createContext({
    document: {getElementById: element},
    Currency: {format: value => Number(value).toFixed(2), parse: value => Number(value)},
    escapeHtml: value => String(value ?? ''),
    formatarDataHoraResumo: value => value,
    atualizarFinanceiroDevolucao() {},
    i18n: {},
});
vm.runInContext(view.slice(view.indexOf('    function linhaResumo('), view.indexOf('    // ==================== BOTAO CONFIRMAR')), context);
const sample = {encerramento_final: true, contagem: 'semana', modo_cobranca: 'integral', ajuste_tipo: 'N', ajuste_valor: 0,
    total_final: 19500, total_locacao: 19500, principal_lancado: 19500,
    veiculos: [{placa: 'TESTE', ciclos_completos: 25, dias_restantes: 4, ciclos_cobrados: 26,
        dias_restantes_cobrados: 0, valor_plano: 19500, valor_diaria: 107.14}]};
context.renderizarPreviewEncerramento(sample);
assert.match(element('resumoContent').innerHTML, /26 semana\(s\) — período completo/);
assert.doesNotMatch(element('resumoContent').innerHTML, /diaria proporcional|Locacao proporcional/);
assert.match(element('resumoContent').innerHTML, /Contrato conciliado/);
context.renderizarPreviewEncerramento({...sample, modo_cobranca: 'proporcional', ajuste_tipo: 'D', ajuste_valor: 321.43});
assert.match(element('resumoContent').innerHTML, /25 semana\(s\) \+ 4 diaria\(s\)/);
assert.match(element('resumoContent').innerHTML, /321.43/);
context.renderizarPreviewEncerramento({...sample, encerramento_final: false, ativos_restantes: 1});
assert.doesNotMatch(element('resumoContent').innerHTML, /26 semana|Principal ja lancado/);
const start = history.indexOf('    function atualizarResumoEncerramento(');
const end = history.indexOf('\n    /**', start);
vm.runInContext(history.slice(start, end), context);
context.contratoEncerramento = {calculo: {...sample, veiculos_historico_calculo: sample.veiculos}, total_final: 19500};
const tbody = {innerHTML: ''};
context.atualizarResumoEncerramento(tbody);
assert.match(tbody.innerHTML, /Período completo/);
assert.match(tbody.innerHTML, /26 semanas/);
delete context.contratoEncerramento.calculo.modo_cobranca;
context.atualizarResumoEncerramento(tbody);
assert.match(tbody.innerHTML, /Proporcional ao uso/);
assert.match(tbody.innerHTML, /25 semanas \+ 4 dias/);
console.log('PASS - Resumo integral, proporcional, parcial e snapshot antigo');
