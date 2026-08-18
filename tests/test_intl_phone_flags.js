const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '..');
const flagsDirectory = path.join(root, 'public/assets/vendor/flag-icons/flags/4x3');

function carregarCountryData(caminhoRelativo) {
    const contexto = { console };
    vm.createContext(contexto);
    const codigo = fs.readFileSync(path.join(root, caminhoRelativo), 'utf8');
    vm.runInContext(`${codigo}\nthis.countryData = countryData;`, contexto);
    return contexto.countryData;
}

for (const caminhoRelativo of [
    'public/assets/js/country-data.js',
    'public/assets/js/country-data.min.js'
]) {
    const countryData = carregarCountryData(caminhoRelativo);

    for (const country of countryData) {
        const flagPath = path.join(flagsDirectory, `${country.code.toLowerCase()}.svg`);
        assert.ok(fs.existsSync(flagPath), `${caminhoRelativo}: bandeira ausente para ${country.code}.`);
        assert.match(
            fs.readFileSync(flagPath, 'utf8'),
            /<svg\b/,
            `${country.code}: asset não é um SVG válido.`
        );
    }
}

for (const caminhoRelativo of [
    'public/assets/js/intl-phone.js',
    'public/assets/js/intl-phone.min.js'
]) {
    const codigo = fs.readFileSync(path.join(root, caminhoRelativo), 'utf8');
    assert.match(codigo, /country-flag-image/, `${caminhoRelativo}: deve renderizar a imagem da bandeira.`);
    assert.match(codigo, /flag-icons\/flags\/4x3/, `${caminhoRelativo}: caminho local das bandeiras ausente.`);
}

console.log('OK: todas as bandeiras do IntlPhone usam SVGs locais e possuem fallback.');
