# Procedimento do Template Website

Este arquivo define a rotina obrigatoria para qualquer alteracao dentro de
`storage/templates/website`.

## Antes de alterar

- Consulte `AGENTS.md` e `docs/website.md`.
- Edite sempre o arquivo fonte. Nunca edite arquivos `*.min.*` manualmente.
- Verifique se a alteracao afeta CSS, JS, PHP, idiomas, includes, assets ou seed.

## Versao

- Toda alteracao dentro de `storage/templates/website` deve atualizar
  `storage/templates/website/versao.json` para a proxima versao.
- Use incremento patch para ajustes pequenos: `1.2.8` para `1.2.9`.
- Use incremento minor apenas para mudancas maiores de template.

## CSS e JS

- CSS: edite o arquivo fonte `.css`, gere o `.min.css` correspondente e envie ao
  FTP apenas o arquivo minificado.
- JS: edite o arquivo fonte `.js`, gere o `.min.js` correspondente e envie ao FTP
  apenas o arquivo minificado.

## Outros arquivos

- Para PHP, includes, idiomas, imagens, seed, JSON e markdown, envie o arquivo
  alterado ao FTP no mesmo caminho relativo.
- Nao envie arquivos fora do escopo da alteracao.

## Deploy do site publicado

- Depois de alterar o template, execute deploy/publicacao do tenant quando a
  mudanca precisar aparecer no site publicado.
- O build copia `versao.json` e gera CSS/JS finais para o FTP do cliente.

## Checklist final

- Validar JSON quando `versao.json` for alterado.
- Conferir `git diff` para garantir o escopo.
- Minificar CSS/JS quando aplicavel.
- Enviar ao FTP os arquivos corretos.
- Registrar na resposta final quais documentos foram consultados.
