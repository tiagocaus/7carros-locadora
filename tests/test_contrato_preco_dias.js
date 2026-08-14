#!/usr/bin/env node

const assert = require('assert');
require('../public/assets/js/grupo-preco-periodo.js');

const payload = {
    valores: {
        valor_plano_km_pago: 90,
        valor_plano_km_controlado: 110,
        valor_plano_km_livre: 135.71,
        valor_seguro_carro: 10,
        valor_seguro_terceiros: 5,
        km_franquia: 100
    },
    precos_dias: {
        diaria: [],
        km_controlado: [],
        km_livre: [
            { dia_inicio: 1, dia_fim: 7, valor: 183.21 },
            { dia_inicio: 8, dia_fim: 30, valor: 169.63 },
            { dia_inicio: 31, dia_fim: 400, valor: 135.71 }
        ]
    }
};

assert.strictEqual(GrupoPrecoPeriodo.calcularQuantidadeDias(1, 'semana'), 7);
assert.strictEqual(GrupoPrecoPeriodo.calcularQuantidadeDias(1, 'mes'), 30);
assert.strictEqual(GrupoPrecoPeriodo.calcularQuantidadeDias(2, 'mes'), 60);

assert.strictEqual(GrupoPrecoPeriodo.resolverValorDiario(payload, 'KL', 7), 183.21);
assert.strictEqual(GrupoPrecoPeriodo.resolverValorDiario(payload, 'KL', 8), 169.63);
assert.strictEqual(GrupoPrecoPeriodo.resolverValorDiario(payload, 'KL', 31), 135.71);
assert.strictEqual(GrupoPrecoPeriodo.resolverValorDiario(payload, 'KMC', 7), 110);

const semana = GrupoPrecoPeriodo.resolverValoresPorPeriodo(payload, 1, 'semana');
assert.strictEqual(semana.valor_plano_km_livre, 1282.47);
assert.strictEqual(semana.valor_seguro_carro, 70);
assert.strictEqual(semana.km_franquia, 700);

const mes = GrupoPrecoPeriodo.resolverValoresPorPeriodo(payload, 1, 'mes');
assert.strictEqual(mes.valor_plano_km_livre, 5088.9);

const doisMeses = GrupoPrecoPeriodo.resolverValoresPorPeriodo(payload, 2, 'mes');
assert.strictEqual(doisMeses.valor_plano_km_livre, 4071.3);

console.log('PASS: preços progressivos do contrato por duração e contagem');
