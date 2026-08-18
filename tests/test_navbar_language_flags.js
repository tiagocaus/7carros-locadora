const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const flagsDirectory = path.join(root, 'public/assets/vendor/flag-icons/flags/4x3');
const localeCountries = {
    pt_BR: 'br',
    pt_PT: 'pt',
    en_US: 'us',
    es_ES: 'es',
    it_IT: 'it'
};

for (const [locale, countryCode] of Object.entries(localeCountries)) {
    const flagPath = path.join(flagsDirectory, `${countryCode}.svg`);
    assert.ok(fs.existsSync(flagPath), `${locale}: bandeira local ${countryCode}.svg ausente.`);
}

const navbar = fs.readFileSync(path.join(root, 'app/Views/partials/navbar.php'), 'utf8');
assert.match(navbar, /data-flag-src=/, 'Navbar deve fornecer a URL local da bandeira.');
assert.match(navbar, /vendor\/flag-icons\/flags\/4x3/, 'Navbar deve usar os SVGs locais.');
assert.doesNotMatch(navbar, /data-flag="/, 'Navbar não deve mais transportar emojis no dropdown.');
assert.match(navbar, /<img class="flag-icon-active"/, 'Bandeira ativa deve ser uma imagem.');

for (const relativePath of [
    'public/assets/js/dashboard.js',
    'public/assets/js/dashboard.min.js'
]) {
    const code = fs.readFileSync(path.join(root, relativePath), 'utf8');
    assert.match(code, /flagSrc/, `${relativePath}: deve ler data-flag-src.`);
    assert.match(code, /activeLanguageFlag/, `${relativePath}: deve atualizar a bandeira ativa.`);
}

for (const relativePath of [
    'public/assets/css/utilities.css',
    'public/assets/css/utilities.min.css'
]) {
    const css = fs.readFileSync(path.join(root, relativePath), 'utf8');
    assert.match(css, /flag-icon-active/, `${relativePath}: estilo da bandeira ativa ausente.`);
    assert.match(css, /object-fit\s*:\s*cover/, `${relativePath}: SVG deve preencher a área da bandeira.`);
}

console.log('OK: seletor de idiomas usa bandeiras SVG locais no navbar.');
