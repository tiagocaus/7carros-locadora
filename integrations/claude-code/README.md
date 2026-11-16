# 7Carros para Claude Code — 1.0.0

Plugin com um servidor MCP remoto HTTP em `https://locadora.7carros.com/mcp`.
Inclui `.claude-plugin/plugin.json`, `.mcp.json`, `assets/logo.png` e este README.
Não contém tokens, senhas, segredos, chaves de IA ou arquivos de ambiente.

## Instalação a partir do ZIP

Extraia em uma pasta dedicada e informe seu caminho absoluto ao Claude Code:

```bash
unzip 7carros-claude-code-1.0.0.zip -d 7carros
claude plugin validate ./7carros
claude --plugin-dir "$PWD/7carros"
```

Use `--plugin-dir` nas sessões em que quiser carregar esse pacote. Não é um upload
de ZIP pelo aplicativo Claude Desktop nem uma instalação automática permanente.
Dentro do Claude Code, abra `/mcp`, selecione o servidor do plugin 7carros e inicie
a autenticação. No navegador, entre no 7Carros e revise as categorias antes de
confirmar o consentimento. O fluxo exige liberação do seu usuário e da locadora.
Depois pergunte: “Consulte o 7Carros: quantos veículos tenho no total?”.

A porta local 8765 precisa estar livre para o retorno OAuth. Se estiver ocupada,
libere-a antes de autenticar; mudar a porta no JSON exige cadastrar e aprovar
explicitamente o novo callback no servidor. Não use segredos ou bearer fixo no ZIP.

## Autenticação e pré-requisitos do servidor

O pacote usa cliente público pré-cadastrado `7carros-claude-code`, PKCE S256 e
`oauth.callbackPort=8765`. O operador deve publicar a compatibilidade OAuth,
aprovar esse cliente e incluí-lo na lista de aplicativos do piloto, preservando
os tenants e funcionários já liberados. Sem isso, a autenticação será recusada.
Não há cadastro dinâmico público nem necessidade de chave da API da Anthropic
para autenticar este MCP. O próprio Claude Code tem seus requisitos de conta.

Somente os callbacks exatos abaixo são aceitos para esse cliente:

- `http://localhost:8765/callback`
- `http://127.0.0.1:8765/callback`

Os demais clientes continuam exigindo HTTPS. As permissões, filiais, categorias,
suspensão, renovação e revogação são verificadas pelo 7Carros em cada fluxo.
O endpoint remoto permanece HTTPS. A logo é a PNG original 7Carros; o campo
`icon` atende aos metadados do diretório, mas o Claude Code não o usa ao carregar.
Versões anteriores a 2.1.281 podem avisar sobre esse campo; ele não é uma credencial
nem muda o MCP. A validação do ZIP não comprova autenticação externa concluída.

## Gerar novamente

Na raiz desta pasta:

```bash
python3 -m zipfile -c 7carros-claude-code-1.0.0.zip .claude-plugin .mcp.json assets README.md
python3 -m zipfile -t 7carros-claude-code-1.0.0.zip
```

## Referências

- https://code.claude.com/docs/en/plugins-reference
- https://code.claude.com/docs/en/mcp#use-pre-configured-oauth-credentials
