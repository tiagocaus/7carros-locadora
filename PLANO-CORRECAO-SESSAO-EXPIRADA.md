# Plano: correcao do falso erro de sessao expirada apos o login

## Diagnostico confirmado

O problema afeta o fluxo de autenticacao e sessao, nao as consultas do dashboard.
Os logs do servidor registraram a seguinte sequencia para o mesmo navegador:

```text
05:31:51 POST /login                    -> 302
05:31:51 GET /sw.js                     -> 404
05:31:52 GET /dashboard                 -> 200
05:31:52 GET /api/dashboard/stats       -> 401
05:31:52 GET /api/notifications/counts  -> 401
```

O `200` de `/dashboard` comprova que o login criou e validou a sessao. A perda
ocorre menos de um segundo depois, antes das primeiras APIs.

A causa e uma corrida entre `session_regenerate_id(true)` no login e uma
requisicao concorrente a `/sw.js`. O navegador afetado possui aparentemente um
registro antigo de Service Worker, embora nao exista atualmente um `sw.js` no
projeto. Como o arquivo nao existe, a requisicao passa pelo front controller,
abre a sessao PHP antiga e pode criar uma nova sessao anonima depois que o login
exclui o identificador anterior. O `Set-Cookie` dessa resposta substitui no
navegador o cookie autenticado. As APIs recebem um `PHPSESSID` existente, mas
sem `authenticated=true`, resultando em `reason=unauthenticated`.

A configuracao global do PHP usa `session.gc_maxlifetime=1440`, enquanto a
aplicacao define quatro horas antes de `session_start()`. Isso e um risco
secundario a investigar separadamente, mas nao explica este incidente, que
aconteceu em menos de um segundo.

## Alteracoes de implementacao

### Regeneracao segura no login

- Criar em `Session` uma operacao especifica para a fronteira de autenticacao.
- Regenerar o ID antes de gravar qualquer dado autenticado, preservando
  temporariamente a sessao anonima anterior com `session_regenerate_id(false)`.
- Manter a sessao anterior sem `authenticated`, `user_id` ou `chave`. Assim,
  uma requisicao concorrente antiga continua anonima, mas nao encontra uma
  sessao apagada, nao gera outro ID e nao sobrescreve o cookie autenticado.
- Fazer `Auth::login()` usar exclusivamente essa operacao. Nao alterar logout,
  timeout de quatro horas, fingerprint ou remember token.
- Documentar no metodo por que o ID anterior nao deve ser apagado imediatamente
  neste ponto. A coleta normal do PHP removera posteriormente a sessao anonima.

### Neutralizacao do Service Worker antigo

- Manter uma fonte legivel em `public/assets/js/sw-unregister.js`.
- Gerar com Terser o artefato publicado `public/sw.js`, pois o navegador solicita
  obrigatoriamente esse caminho. O Service Worker deve apenas assumir a versao
  atual e cancelar o proprio registro, sem cache, navegacao ou acesso a dados.
- Configurar `public/.htaccess` para servir especificamente `/sw.js` com
  `Cache-Control: no-store, no-cache, must-revalidate`, sobrescrevendo a regra
  geral de cache imutavel aplicada a JavaScript.
- Como `public/sw.js` passa a existir fisicamente, o Apache o servira diretamente
  e a requisicao nunca abrira sessao PHP.

### Observabilidade relacionada

- Manter os logs existentes de `Session` e `Auth` sem cookies, IDs de sessao,
  CSRF ou tokens.
- Incluir nos eventos de falha apenas um `request_id`, endpoint, presenca do
  cookie e se a sessao foi inicializada vazia naquela requisicao.
- O redirecionamento geral dos erros PHP para `storage/logs/php-error.log`, sua
  protecao e rotacao permanecem como trabalho complementar, mas nao sao
  pre-requisito para corrigir a corrida.

## Testes e criterios de aceite

- Adicionar teste concorrente com diretorio de sessao temporario:
  - iniciar uma sessao anonima;
  - autenticar e regenerar enquanto outra requisicao usa o ID anterior;
  - confirmar que a nova sessao permanece autenticada;
  - confirmar que a requisicao antiga continua no mesmo ID anonimo e nao cria
    um terceiro cookie/sessao capaz de substituir o login.
- Preservar os cenarios existentes de cookie invalido, falha de storage,
  fingerprint, inatividade, cookie legado e resposta AJAX `401`.
- Validar sintaxe PHP e executar `tests/test_session_start.php` e os testes do
  helper de API.
- Validar o JavaScript fonte e gerar `public/sw.js` com Terser.
- Em ambiente publicado, confirmar com `curl` que `/sw.js` retorna `200`, tipo
  JavaScript, cache desabilitado e nenhum `Set-Cookie`.
- Executar login controlado e confirmar a sequencia:
  - `/login` retorna sucesso/redirect;
  - `/dashboard` retorna `200`;
  - stats, notificacoes, subtabs e refresh retornam `200`;
  - nenhuma mensagem de sessao expirada e exibida.
- Repetir com duas abas e com uma requisicao concorrente a `/sw.js` durante o
  login.

## Publicacao e limites

- Publicar pelo FTP somente os arquivos deste escopo, preservando caminhos.
- Para JavaScript, editar a fonte, gerar o artefato com Terser e enviar ao FTP
  apenas o arquivo efetivamente consumido em producao (`public/sw.js`).
- Nao alterar banco de dados, queries, multi-tenancy, dashboard, timeout de
  quatro horas ou o modal global de sessao expirada.
- Apos publicar, acompanhar access log e error log para verificar ausencia do
  padrao `dashboard 200 -> APIs 401`.
- Nenhum teste deste plano envia email, SMS ou WhatsApp.

## Documentacao consultada

- `AGENTS.md`
- `docs/security.md`
- `docs/api.md`
- `docs/logs.md`
- `docs/environment.md`
- `docs/architecture.md`
- `docs/best-practices.md`
