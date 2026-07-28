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

- Depois de publicar os arquivos do sistema principal, simule a atualizacao dos
  sites ativos:
  `php scripts/publicar-atualizacao-websites.php --env=production`.
- Execute primeiro um piloto com a chave de um site de teste:
  `php scripts/publicar-atualizacao-websites.php --env=production --usuario-ftp=USUARIO --apply --confirm=VERSAO`
  (ou use `--chave=CHAVE`).
- Valide o site piloto e, somente depois, publique nos demais:
  `php scripts/publicar-atualizacao-websites.php --env=production --apply --confirm=VERSAO`.
- O comando ignora sites inativos, sem credenciais, sem token da API ou que ja
  estejam na mesma versao (ou em versao superior). Falhas em um site nao
  interrompem os demais, salvo quando `--stop-on-error` for informado.
- A execucao pode ser retomada com o mesmo comando: sites atualizados com
  sucesso sao ignorados pela comparacao de versao.
- A publicacao individual pelo painel continua disponivel para o cliente.
- O build copia `versao.json` e gera CSS/JS finais para o FTP do cliente.

## Checklist final

- Validar JSON quando `versao.json` for alterado.
- Confirmar que toda mudanca no template incrementou e enviou `versao.json`.
- Conferir `git diff` para garantir o escopo.
- Minificar CSS/JS quando aplicavel.
- Enviar ao FTP os arquivos corretos.
- Registrar na resposta final quais documentos foram consultados.
