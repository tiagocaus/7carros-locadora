const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '..');

function carregarCountryData(caminhoRelativo) {
    const contexto = { console };
    vm.createContext(contexto);
    const codigo = fs.readFileSync(path.join(root, caminhoRelativo), 'utf8');
    vm.runInContext(`${codigo}\nthis.countryData = countryData;`, contexto);
    return contexto.countryData;
}

function testarArquivo(caminhoRelativo) {
    const countryData = carregarCountryData(caminhoRelativo);
    const entradasNl = countryData.filter((pais) => pais.code === 'NL');

    assert.equal(
        entradasNl.length,
        1,
        `${caminhoRelativo}: deve existir exatamente uma entrada NL.`
    );

    const nl = entradasNl[0];
    assert.equal(nl.dialCode, '+31', `${caminhoRelativo}: DDI deve ser +31.`);
    assert.equal(nl.name, 'Países Baixos', `${caminhoRelativo}: nome incorreto.`);
    assert.equal(nl.flag, '🇳🇱', `${caminhoRelativo}: bandeira incorreta.`);
    assert.equal(nl.placeholder, '6 12345678', `${caminhoRelativo}: placeholder incorreto.`);
    assert.equal(
        nl.maskFormat('612345678'),
        '6 12345678',
        `${caminhoRelativo}: máscara móvel incorreta.`
    );

    for (const dialCode of ['+32', '+33', '+34']) {
        const ocorrencias = countryData.filter((pais) => pais.dialCode === dialCode);
        assert.equal(
            ocorrencias.length,
            1,
            `${caminhoRelativo}: deve existir exatamente uma entrada com DDI ${dialCode}.`
        );
    }
}

testarArquivo('public/assets/js/country-data.js');
testarArquivo('public/assets/js/country-data.min.js');

console.log('OK: NL (+31) presente no catálogo fonte e minificado, com máscara e DDIs europeus preservados.');
