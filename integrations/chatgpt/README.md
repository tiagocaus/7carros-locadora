# Plugin privado 7Carros — 1.0.1

Fontes do pacote Agent Plugins para o piloto privado no ChatGPT. O ZIP contém
somente `plugin.json` e `mcp.json` na raiz. Não contém credenciais, tokens,
configurações de ambiente ou arquivos do servidor PHP.

## Instalação

1. No ChatGPT, abrir **Plugins → Adicionar → Enviar arquivo compactado do plugin**.
   Esse caminho foi conferido na interface da conta em 16/11/2026.
2. Selecionar `7carros-chatgpt-1.0.1.zip` desta pasta. Usar o upload do pacote para
   importar os metadados; o formulário de adicionar servidor por URL não contém
   todos esses campos.
3. Conferir nome **7Carros**, site **https://7carros.com** e desenvolvedor informado
   como **7Carros**. A apresentação da autoria pode depender da identidade da conta;
   para uma publicação pública, a OpenAI usa a identidade verificada selecionada.
4. Instalar/conectar o plugin, entrar no 7Carros e revisar o consentimento.
   O piloto existente continua restrito ao usuário, tenant e cliente permitidos.
5. Testar uma consulta e confirmar depois renovação e revogação. O upload e a
   instalação real deste pacote ainda precisam ser validados pelo usuário.

Se o nome já existir, procurar o plugin em **Pessoal → Criados por você** e usar
**Mais ações → Enviar nova versão**, em vez de criar um cadastro duplicado.
O pacote não deve ser submetido ou publicado no diretório público nesta etapa.

## Metadados e OAuth

- `plugin.json`: nome portátil `7carros`, versão `1.0.1`, autor, homepage e os
  campos de apresentação `extensions.com.openai.interface`, incluindo
  `developerName` e `websiteURL`.
- `mcp.json`: um servidor Streamable HTTP em `https://locadora.7carros.com/mcp`,
  com `extensions.com.openai.auth` no servidor e `client.mode: cimd`. Endpoints e
  escopos são obtidos pela descoberta existente. Não declarar client ID ou segredo
  nesse objeto CIMD. Nenhuma implementação DCR foi acrescentada ao servidor.
- Servidor PHP: anuncia título, descrição e `websiteUrl` no `serverInfo`, que o
  SDK 0.10.0 serializa em `result._meta["io.modelcontextprotocol/serverInfo"]`
  na revisão 2026-07-28. Esse campo não substitui o `websiteURL` do pacote.

O site escolhido, `https://7carros.com`, foi consultado e respondeu HTTP 200 após
redirecionar para `https://www.7carros.com.br/` em 16/11/2026.

## Validação e divergência das referências

O `plugin.json` passou no schema oficial Agent Plugins 1.0.0. O núcleo portátil
de `mcp.json`, sem a extensão OpenAI, também passou no schema oficial. A extensão
OAuth/CIMD foi conferida separadamente contra a documentação oficial da OpenAI.

Existe uma divergência nas referências consultadas em 16/11/2026: o schema
genérico de MCP usa `additionalProperties: false` e rejeita `extensions`, mas os
exemplos oficiais da OpenAI exigem esse campo para configurar OAuth. O pacote
preserva o formato documentado pela OpenAI. Portanto, não se afirma validação
integral do `mcp.json` estendido pelo schema genérico, nem aceitação do ZIP pelo
ChatGPT antes do upload real.

Referências:

- [Manifest de apresentação](https://developers.openai.com/plugins/deploy/submission#listing-metadata)
- [OAuth no pacote](https://developers.openai.com/plugins/build/plugins#configure-mcp-authentication)
- [Schema plugin.json](https://agent-plugins.org/schemas/1.0.0/plugin.schema.json)
- [Schema mcp.json](https://agent-plugins.org/schemas/1.0.0/mcp.schema.json)

## Gerar novamente o ZIP

Na raiz do projeto, após revisar as versões nos manifests e no servidor:

```bash
cd integrations/chatgpt
python3 -m zipfile -c 7carros-chatgpt-1.0.1.zip plugin.json mcp.json
python3 -m zipfile -t 7carros-chatgpt-1.0.1.zip
```

O pacote é entregue localmente para upload no ChatGPT, não é um arquivo de runtime
do servidor. Esta atualização não exige Composer, migrations ou alterações nas ENVs.
