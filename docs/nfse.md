# NFS-e (Nota Fiscal de Servico Eletronica)

Documento oficial do modulo NFS-e no sistema novo.

O legado `NFSE_IMPLEMENTACAO.md` pode ser usado como referencia historica, mas nao deve ser copiado diretamente. A implementacao atual deve seguir este documento, a arquitetura MVC do sistema e as regras de multi-tenancy do QueryBuilder.

---

## Visao Geral

O modulo emite NFS-e por filial a partir de configuracoes em `nfse_configuracoes`.

Tipos de emissao suportados:

| tipo_emissao | Modelo | Protocolo | XML | Assinatura | Fluxo |
|--------------|--------|-----------|-----|------------|-------|
| `nacional` | Sistema Nacional SEFIN | REST + mTLS | DPS | SHA256 | Sincrono |
| `betha` | Betha Cloud DPS | SOAP 1.1 + mTLS | DPS Betha | SHA256 | Assincrono |

Nao use fallback entre emissores. Se `tipo_emissao` nao for suportado, o sistema deve falhar com erro claro.

Fluxo de status:

```
pendente -> processando -> autorizada
                       \-> rejeitada
autorizada -> cancelada
```

Betha usa `processando` enquanto aguarda consulta do protocolo.

---

## Arquitetura

Arquivos principais:

```
app/Controllers/NFSeController.php
app/Models/NFSe.php
app/Models/NFSeConfiguracao.php
app/Models/NFSeEvento.php
app/Services/NFSe/NFSeService.php
app/Services/NFSe/NFSeAssinatura.php
app/Services/NFSe/NFSeCertificado.php
app/Services/NFSe/Nacional/NFSeXMLNacional.php
app/Services/NFSe/Nacional/NFSeAPINacional.php
app/Services/NFSe/Betha/NFSeXMLBetha.php
app/Services/NFSe/Betha/NFSeAPIBetha.php
app/Crons/Jobs/NFSeEmitirAutoJob.php
app/Crons/Jobs/NFSeReenviarJob.php
app/Crons/Jobs/NFSeConsultarBethaJob.php
app/Crons/Jobs/NFSeEnviarEmailJob.php
```

Regras:

- `NFSeService` e o unico orquestrador de emissao, consulta, cancelamento e reenvio.
- Cada emissor deve ter XML/API proprios.
- Nacional e Betha nao podem compartilhar parser de retorno.
- `dhEmi` deve ser gerado no horario fiscal `America/Sao_Paulo`, nao em UTC, para evitar rejeicao por data futura em emissores municipais.
- `NFSeAssinatura::assinar()` deve receber o atributo de ID correto:
  - Nacional: `Id`
  - Betha: `id`
- Acesso ao banco deve respeitar multi-tenancy. Em CRUD normal, nunca use `withoutChave()`.
- CRONs cross-tenant podem usar `withoutChave()`, definindo `$_SESSION['chave']` antes de chamar services.

---

## Configuracao por Filial

A configuracao fica na aba NFS-e da tela de Matriz/Filial.

Tabela: `nfse_configuracoes`

Chave unica: `(chave, id_matriz_filial)`.

Campos principais:

| Campo | Uso |
|-------|-----|
| `tipo_emissao` | `nacional` ou `betha` |
| `ambiente` | `1=Producao`, `2=Homologacao` |
| `serie` | Serie DPS/RPS |
| `numero_atual` | Contador Nacional/Betha |
| `codigo_municipio` | Codigo IBGE de 7 digitos do prestador |
| `codigo_servico` | NBS/codigo de servico conforme emissor |
| `regime_tributario` | `1=Simples ME/EPP`, `4=MEI`, `2=Lucro Presumido`, `3=Lucro Real` |
| `reg_apuracao_sn` | Regime de apuracao do Simples, quando aplicavel |
| `trib_issqn` | Tributacao ISSQN |
| `enviar_im` | Envia IM no DPS Nacional/Betha somente quando necessario |
| `certificado_arquivo` | Arquivo PFX/P12 |
| `certificado_senha` | Senha criptografada |

`codigo_municipio` deve ter 7 digitos. Valores com 8 digitos geralmente sao CEP e devem ser corrigidos antes de emitir.

### Certificado Digital

- Certificados novos devem gravar a senha com o helper `encrypt()` atual do sistema.
- Certificados importados do legado podem ter senha em AES-256-CBC com a chave fixa `nfse_7carros_locadora_key`.
- O formato legado deve ser aceito apenas como fallback de leitura/migracao. Ao conseguir abrir o certificado legado, o sistema deve regravar a senha com `encrypt()` atual e atualizar `certificado_validade`.
- Falha de descriptografia, senha incorreta ou arquivo corrompido nao deve ser tratada como certificado vencido. A tela e a emissao devem diferenciar:
  - certificado realmente vencido;
  - arquivo ausente;
  - senha/descriptografia invalida;
  - erro de leitura do PFX/P12.
- A rotina `scripts/normalizar-nfse-certificados.php` pode ser usada para normalizar registros importados em lote. Por padrao ela roda em `dry-run`; use `--apply` somente depois de revisar o relatorio.

---

## Nacional SEFIN

Usado quando `tipo_emissao = nacional`.

API:

| Operacao | Endpoint |
|----------|----------|
| Emitir | `POST /nfse` |
| Consultar | `GET /nfse/{chave}` |
| Cancelar | `POST /nfse/{chave}/eventos` |

Bases:

- Producao: `https://sefin.nfse.gov.br/SefinNacional`
- Homologacao: `https://sefin.producaorestrita.nfse.gov.br/API/SefinNacional`

Regras de XML:

- Namespace: `http://www.sped.fazenda.gov.br/nfse`
- Root: `<DPS versao="1.00">`
- `<infDPS>` deve conter apenas `Id`; nao repetir `versao` nesse elemento.
- Elemento assinado: `infDPS`
- Atributo de ID: `Id`
- Assinatura: SHA256
- Envio: XML assinado compactado em GZip/Base64 no campo `dpsXmlGZipB64`
- Cancelamento: evento `101101` em `pedidoRegistroEventoXmlGZipB64`
- Textos enviados no XML devem ser normalizados em maiusculas e escapados como XML.
- Endereco do tomador deve ser enviado apenas quando houver CEP com 8 digitos e codigo IBGE do municipio do tomador com 7 digitos; sem esses dados, omitir o bloco `<end>`.

Mapeamento Simples Nacional:

| regime_tributario | opSimpNac | regApTribSN |
|-------------------|-----------|-------------|
| `1` Simples ME/EPP | `3` | Enviar |
| `4` MEI | `2` | Nao enviar |
| `2` Lucro Presumido | `1` | Nao enviar |
| `3` Lucro Real | `1` | Nao enviar |

IM no DPS:

- Enviar `<IM>` somente quando `enviar_im = S` e a filial tiver IM preenchida.
- A IM da filial vem de `matrizes_filiais.ins_muni`.
- Padrao recomendado: `enviar_im = N`.
- Ative apenas quando o cadastro do CNPJ no municipio exigir IM no DPS.

ID da DPS:

```
DPS + cMun(7) + tpInsc(1) + nInsc(14) + serie(5) + nDPS(15)
```

Exemplo:

```
DPS5300108212345678000199DPS00000000000000123
```

---

## Betha Cloud

Usado quando `tipo_emissao = betha`.

API:

- Endpoint: `https://nota-eletronica.betha.cloud/dps/ws`
- WSDL de referencia: `https://nota-eletronica.betha.cloud/dps/ws/service.wsdl`
- SOAP 1.1 + mTLS

Operacoes:

| Operacao SOAP | Uso |
|---------------|-----|
| `RecepcionarDps` | Envia DPS e retorna protocolo |
| `ConsultarStatusDps` | Consulta protocolo ate autorizar/rejeitar |
| `RecepcionarEventoCancelamento` | Cancela NFS-e Betha |

Regras de XML:

- Namespace: `http://www.betha.com.br/e-nota-dps`
- Root: `<DPS versao="1.00">`
- Elemento assinado: `infDPS`
- Atributo de ID: `id` minusculo
- Assinatura: SHA256
- Texto do servico em maiusculo
- Bloco `<trib>` deve conter `<tribMun>` e `<totTrib>`; sem `<totTrib>` a Betha rejeita a DPS por schema incompleto.
- `<dhEmi>` deve usar horario local do prestador (`America/Sao_Paulo`, offset `-03:00`), nao UTC.
- `ConsultarStatusDpsEnvio` deve enviar `<tpAmb>`, `<codigoIbge>`, `<cpfCnpjPrestador>`, `<protocolo>` e `<tipoIntegracao>`, nessa ordem.
- Para consulta de emissao Betha, `<tipoIntegracao>` deve ser `EMISSAO`.
- Resposta pode vir com prefixo `ns2:`; parsers devem usar namespace, nao string fixa.

Fluxo:

1. Gerar DPS Betha.
2. Assinar `infDPS` usando atributo `id`.
3. Enviar via `RecepcionarDps`.
4. Salvar `protocolo` e deixar NFS-e como `processando`.
5. `NFSeConsultarBethaJob` consulta `ConsultarStatusDps`.
6. Quando autorizado, atualizar `chave_acesso`, `numero`, `codigo_verificacao` e `xml_retorno`.

Numeracao:

- Usa `numero_atual`.
- Numero e reservado no envio, pois DPS recepcionada nao deve reutilizar o mesmo ID.

---

## Reenvio

Para NFS-e rejeitada:

- Maximo de 3 tentativas.
- Reenvio manual pode liberar tentativa extra somente para `XML_INVALIDO` com financeiro vinculado e causa tecnica conhecida ja corrigida no gerador XML/data fiscal.
- Reenvio automatico por CRON continua limitado a `tentativas_envio < 3`.
- Se houver `id_financeiro`, regenerar XML com os dados atuais antes de reenviar.
- Se nao houver `id_financeiro`, reaproveitar o XML salvo como fallback.
- Em Betha, regenerar evita erro de DPS ja recepcionada com mesmo ID.

---

## Emissao Manual pelo Financeiro

A tela `GET /pages/nfse/emitir?id_financeiro={id}` emite NFS-e a partir de uma receita paga do modulo financeiro.

Regras da tela:

- Carregar `financeiro` com seus `financeiro_itens`.
- Exibir Prestador e Tomador lado a lado em desktop e empilhados no mobile.
- Exibir os itens do lancamento com checkbox `Trib.?`.
- Todos os itens financeiros iniciam marcados como tributaveis.
- Quando o usuario desmarca um item, ele passa a compor `itens_nao_tributaveis`.
- O botao `Adicionar item nao tributavel` adiciona uma linha manual ja nao tributavel.
- `valor_deducoes` deve ser a soma dos itens nao tributaveis.
- `base_calculo` deve ser `valor_servicos - valor_deducoes`, nunca negativa.
- O email editado na tela prevalece sobre o email cadastrado do cliente apenas para essa emissao.
- Ausencia de configuracao NFS-e ou certificado nao deve redirecionar para a listagem. A tela deve permanecer aberta, mostrar aviso especifico e bloquear somente o botao de emissao.

Persistencia:

| Campo `nfse` | Origem |
|--------------|--------|
| `valor_servicos` | `financeiro.valor_total` |
| `valor_deducoes` | Soma dos itens nao tributaveis |
| `itens_nao_tributaveis` | JSON `[{descricao, valor}]` dos itens desmarcados/manuais |
| `base_calculo` | `valor_servicos - valor_deducoes` |

Nao crie tabela separada para itens nao tributaveis sem necessidade fiscal nova. O campo JSON atual preserva o historico da emissao e ja atende ao fluxo manual.

---

## CRON Jobs

| Job | Frequencia | Limite | Descricao |
|-----|------------|--------|-----------|
| `NFSeEmitirAutoJob` | 5min | 50 | Emite NFS-e de pagamentos confirmados |
| `NFSeReenviarJob` | 5min | 20 | Reenvia rejeitadas recuperaveis |
| `NFSeConsultarBethaJob` | 1min | 20 | Consulta protocolos Betha em processamento |
| `NFSeEnviarEmailJob` | 5min | 30 | Envia PDF por email |

---

## Rotas

Views:

```
GET /pages/nfse
GET /pages/nfse/emitir
GET /pages/nfse/{id}/visualizar
GET /pages/nfse/{id}/cancelar
GET /pages/nfse/configuracoes -> redireciona para /pages/matrizes-filiais
```

API:

```
GET  /api/nfse
GET  /api/nfse/estatisticas
GET  /api/nfse/configuracoes
GET  /api/nfse/{id}
GET  /api/nfse/{id}/eventos
POST /nfse/emitir
POST /nfse/{id}/cancelar
POST /nfse/{id}/consultar
POST /nfse/{id}/reenviar
POST /nfse/{id}/email
GET  /nfse/{id}/pdf
GET  /nfse/{id}/xml/{tipo}
POST /nfse/configuracoes/salvar
POST /nfse/configuracoes/certificado
POST /nfse/configuracoes/certificado/remover
POST /nfse/configuracoes/testar-conexao
```

---

## DANFSE em PDF

- A DANFSE deve ser gerada por `NFSePDF` usando `PdfHelper`.
- A logo da matriz/filial deve usar `PdfHelper::resolveImagePath()`; nunca use URL tokenizada do `FileHelper` dentro do mPDF.
- O cabecalho deve exibir logo no canto superior esquerdo e QR Code no canto superior direito.
- O QR Code deve abrir a consulta publica `https://www.nfse.gov.br/ConsultaPublica/?tpc=1&chave={chave_acesso}`.
- O link da consulta publica deve aparecer tambem no fim da pagina.
- O bloco de valores deve exibir aliquota ISS, valor ISS, ISS retido, valor ISS retido e valor liquido.
- O download regenera o PDF para entregar sempre o layout fiscal atual.

---

## Checklist de Desenvolvimento

Antes de alterar NFS-e:

1. Ler `docs/querybuilder.md`.
2. Ler `docs/architecture.md`.
3. Ler este documento.
4. Confirmar se a alteracao e especifica de Nacional ou Betha.
5. Nao alterar XML/API de outro emissor sem necessidade.
6. Validar `php -l` nos arquivos alterados.
7. Para testes com envio real, usar homologacao e somente tenant `chave = 1111111111111`, salvo aprovacao explicita.

---

## Deploy

1. Enviar arquivos PHP, views, idiomas, cron e docs alterados.
2. Garantir que `cron.php` esteja agendado.
3. Confirmar que `NFSeConsultarBethaJob` esta executando.
4. Validar configuracao de filial com `tipo_emissao = betha`.
5. Testar conexao com certificado.
6. Emitir primeiro em homologacao quando possivel.
