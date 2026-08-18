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
    const entradasAr = countryData.filter((pais) => pais.code === 'AR');

    assert.equal(
        entradasAr.length,
        1,
        `${caminhoRelativo}: deve existir exatamente uma entrada AR.`
    );

    const argentina = entradasAr[0];
    assert.equal(argentina.dialCode, '+54', `${caminhoRelativo}: DDI deve ser +54.`);
    assert.equal(
        argentina.placeholder,
        '9 11 1234-5678',
        `${caminhoRelativo}: placeholder móvel incorreto.`
    );
    assert.equal(
        argentina.maskFormat('1112345678'),
        '11 1234-5678',
        `${caminhoRelativo}: máscara de telefone fixo incorreta.`
    );
    assert.equal(
        argentina.maskFormat('91112345678'),
        '9 11 1234-5678',
        `${caminhoRelativo}: máscara de celular internacional incorreta.`
    );
    assert.equal(
        argentina.maskFormat('9111234'),
        '9 11 1234-',
        `${caminhoRelativo}: máscara móvel deve ser aplicada durante a digitação.`
    );
}

testarArquivo('public/assets/js/country-data.js');
testarArquivo('public/assets/js/country-data.min.js');

console.log('OK: Argentina (+54) aceita telefone fixo e celular internacional com o 9 adicional.');
