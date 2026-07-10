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
- Esta regra vale sem excecao para PHP, CSS, JS, idiomas, includes, imagens,
  seeds, JSON e markdown do template, mesmo quando apenas um arquivo mudar.
- A alteracao nao esta concluida enquanto a versao nao tiver sido incrementada,
  validada e incluida entre os arquivos enviados ao FTP.
- Use incremento patch para ajustes pequenos: `1.2.8` para `1.2.9`.
- Use incremento minor apenas para mudancas maiores de template.

## CSS e JS

- CSS: edite o arquivo fonte `.css`, gere o `.min.css` correspondente e envie ao
  FTP apenas o arquivo minificado.
- JS: edite o arquivo fonte `.js`, gere o `.min.js` correspondente e envie ao FTP
  apenas o arquivo minificado.
- O `WebsiteBuilderService` copia o `.min.js` previamente gerado para o build; ele
  nao minifica nem usa o arquivo fonte existente no servidor.

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
- Confirmar que toda mudanca no template incrementou e enviou `versao.json`.
- Conferir `git diff` para garantir o escopo.
- Minificar CSS/JS quando aplicavel.
- Enviar ao FTP os arquivos corretos.
- Registrar na resposta final quais documentos foram consultados.
