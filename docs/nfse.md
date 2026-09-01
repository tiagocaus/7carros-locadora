# NFS-e (Nota Fiscal de Servico Eletronica)

Documento oficial do modulo NFS-e no sistema novo.

O legado `NFSE_IMPLEMENTACAO.md` pode ser usado como referencia historica, mas nao deve ser copiado diretamente. A implementacao atual deve seguir este documento, a arquitetura MVC do sistema e as regras de multi-tenancy do QueryBuilder.

---

## Visao Geral

O modulo emite NFS-e por filial a partir de configuracoes em `nfse_configuracoes`.

O contato do prestador usa o email e o telefone principais das tabelas
`contatos_emails` e `contatos_telefones`, resolvidos pela Model `MatrizFilial`.
Nao use campos diretos de contato da tabela `matrizes_filiais`.

Tipos de emissao suportados:

| tipo_emissao | Modelo | Protocolo | XML | Assinatura | Fluxo |
|--------------|--------|-----------|-----|------------|-------|
| `nacional` | Sistema Nacional SEFIN | REST + mTLS | DPS | SHA256 | Sincrono |
| `betha` | Betha Cloud DPS | SOAP 1.1 + mTLS | DPS Betha | SHA256 | Assincrono |
| `issnet` | ISSNet/ABRASF 2.04 | SOAP 1.1 + mTLS | RPS ABRASF | SHA1 | Sincrono |

Nao use fallback entre emissores. Se `tipo_emissao` nao for suportado, o sistema deve falhar com erro claro.

Fluxo de status:

```
pendente -> processando -> autorizada
                       \-> rejeitada
autorizada -> cancelada
           \-> substituida
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
app/Services/NFSe/Nacional/NFSeEventosNacional.php
app/Services/NFSe/Betha/NFSeXMLBetha.php
app/Services/NFSe/Betha/NFSeAPIBetha.php
app/Services/NFSe/ISSNet/NFSeXMLISSNet.php
app/Services/NFSe/ISSNet/NFSeAPIISSNet.php
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
| `tipo_emissao` | `nacional`, `betha` ou `issnet` |
| `ambiente` | `1=Producao`, `2=Homologacao` |
| `serie` | Serie DPS/RPS |
| `numero_atual` | Contador Nacional/Betha |
| `codigo_municipio` | Codigo IBGE de 7 digitos do prestador |
| `codigo_servico` | NBS/codigo de servico conforme emissor |
| `codigo_tributacao_nacional` | `cTribNac` opcional de 6 digitos, exclusivo da DPS Nacional e separado do NBS |
| `item_lista_servico` | Item da lista de servico ABRASF/ISSNet |
| `codigo_cnae` | CNAE ABRASF/ISSNet quando exigido |
| `codigo_tributacao_municipio` | Codigo de tributacao municipal ABRASF/ISSNet quando exigido |
| `regime_tributario` | `1=Simples ME/EPP`, `4=MEI`, `2=Lucro Presumido`, `3=Lucro Real` |
| `reg_apuracao_sn` | Regime de apuracao do Simples, quando aplicavel |
| `trib_issqn` | Tributacao ISSQN |
| `preencher_ibscbs` | Habilita preenchimento de IBS/CBS; padrao `N` |
| `c_ind_op_ibscbs` | Codigo indicador da operacao (`cIndOp`), 6 digitos |
| `cst_ibscbs` | CST do IBS/CBS, 3 digitos |
| `c_class_trib_ibscbs` | Classificacao tributaria (`cClassTrib`), 6 digitos; deve iniciar pelo CST |
| `aliquota_ibs` / `aliquota_cbs` | Campos legados; nao usar para calcular a DPS Nacional |
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
| Recuperar chave pela DPS | `GET /dps/{id}` |
| Consultar | `GET /nfse/{chave}` |
| Cancelar | `POST /nfse/{chave}/eventos` |

Bases:

- Producao: `https://sefin.nfse.gov.br/SefinNacional`
- Homologacao: `https://sefin.producaorestrita.nfse.gov.br/API/SefinNacional`

Regras de XML:

- Namespace: `http://www.sped.fazenda.gov.br/nfse`
- Root: `<DPS versao="1.01">`
- `<infDPS>` deve conter apenas `Id`; nao repetir `versao` nesse elemento.
- Elemento assinado: `infDPS`
- Atributo de ID: `Id`
- Assinatura: SHA256
- Envio: XML assinado compactado em GZip/Base64 no campo `dpsXmlGZipB64`
- Cancelamento: evento `101101` em `pedidoRegistroEventoXmlGZipB64`
- Textos enviados no XML devem ser normalizados em maiusculas e escapados como XML.
- `cTribNac` e `cNBS` sao classificacoes distintas. Quando `codigo_tributacao_nacional` estiver preenchido com 6 digitos, ele deve alimentar `cTribNac`; quando estiver nulo, manter o mapeamento legado por `trib_issqn` para preservar configuracoes existentes. `codigo_servico` continua alimentando `cNBS`.
- Para tomador brasileiro, o endereco deve ser enviado apenas quando houver CEP com 8 digitos e codigo IBGE do municipio com 7 digitos; sem esses dados, omitir o bloco `<end>`.
- Para cliente `tipo = ES`, o passaporte e apenas identificacao cadastral local: nunca enviar seu valor como CPF, CNPJ ou NIF. Gerar `<cNaoNIF>0</cNaoNIF>` e, quando o endereco exterior estiver completo, usar `<endExt>` com pais ISO alpha-2, codigo postal, cidade e estado/provincia.
- Nao enviar `<IBSCBS>` enquanto `preencher_ibscbs = N`.
- Quando `preencher_ibscbs = S`, enviar `<IBSCBS>` depois de `<valores>`, com `finNFSe`, `cIndOp`, `indDest` e `valores/trib/gIBSCBS` (`CST` e `cClassTrib`). As aliquotas e os valores sao calculados pela plataforma nacional e devem ser lidos do XML autorizado, nunca calculados pela configuracao local.
- Pela documentacao oficial RTC/Anexo VI, o grupo IBS/CBS passa a ser obrigatorio no ambiente nacional em `03/08/2026`; para optantes do Simples Nacional e MEI, somente em `01/01/2027`.
- Antes de 2027, uma flag legada ativa no Simples/MEI sem os tres codigos declaratorios completos e tratada como desativada. Isso evita que configuracoes antigas bloqueiem a emissao durante a transicao; ao salvar novamente com IBS/CBS ativo, os codigos passam a ser obrigatorios.
- O sistema homologa o preenchimento declaratorio de IBS/CBS apenas para `tipo_emissao = nacional`. Betha e ISSNet devem manter `preencher_ibscbs = N`.
- Com IBS/CBS desativado, `<totTrib>` deve usar `<pTotTrib>` zerado, nao `<vTotTrib>` calculado por aliquotas padrao.

Reconciliacao de duplicidade:

- O retorno Nacional `E0014` significa que a DPS ja foi convertida em NFS-e. Nao reenviar nem renumerar essa DPS.
- Recuperar a chave com `GET /dps/{id}` e consultar o documento por `GET /nfse/{chave}`. Antes de autorizar localmente, comparar a DPS enviada com a DPS incorporada na resposta: documento do prestador, municipio emissor, serie normalizada, numero, competencia, documento e nome normalizado do tomador e valor do servico em centavos.
- Somente uma comparacao integralmente compativel pode preencher os dados locais e registrar `reconciliacao`. A coincidencia do ID da DPS, isoladamente, nao prova que o documento foi originado pelo sistema.
- Em divergencia, registrar `DPS_CONFLITO`, manter a tentativa como `rejeitada`, apagar apenas chave, codigo de verificacao, XML de retorno e PDF externos e preservar XML enviado, numero tentado, financeiro e eventos. Nunca importar a nota externa.
- `DPS_CONFLITO` nao e recuperavel e deve aparecer na interface como **Falha na emissao**, sem acao de reenvio: `Nenhuma NFS-e foi emitida. A numeração já estava sendo utilizada em outro emissor.`
- `nfse` possui unicidade `(chave, chave_acesso)`. A mesma chave autorizada pode existir em tenants diferentes, mas nunca em duas tentativas do mesmo tenant.
- Falhas locais de configuracao IBS/CBS usam `IBSCBS_CONFIGURACAO` e nao sao recuperaveis pelo cron. Isso impede o ciclo de reenvio a cada 5 minutos.
- Para saneamento de registros antigos, executar primeiro `php scripts/reconciliar-nfse-nacional.php --env=production` e revisar o dry-run; aplicar somente depois com `--apply`.

### Saneamento pontual TRIP10 LOCADORA

O script `scripts/sanear-nfse-trip10.php` e restrito ao tenant e aos quatro IDs auditados. Ele roda em dry-run por padrao, nao importa as notas municipais 102, 103 e 104, nao altera `numero_atual` e desativa `ativo`/`emissao_auto` apenas para a TRIP10.

Na implantacao que introduz a migracao `00427_secure_nfse_dps_reconciliation.php`, a ordem obrigatoria e:

1. executar `php scripts/sanear-nfse-trip10.php --env=production` e revisar as quatro notas;
2. executar `php scripts/sanear-nfse-trip10.php --env=production --apply`;
3. executar as migracoes normalmente; a `00427` falha de forma explicita se ainda houver qualquer chave duplicada;
4. somente depois ativar novamente a emissao da TRIP10, apos o cliente confirmar serie, numeracao e codigo de tributacao nacional.

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

## ISSNet / ABRASF 2.04

Usado quando `tipo_emissao = issnet`.

Padrao:

- SOAP 1.1 com `nfseCabecMsg` e `nfseDadosMsg`.
- Namespace das mensagens de dados: `http://www.abrasf.org.br/nfse.xsd`.
- Cabecalho ABRASF: `versao="2.04"` e `versaoDados=2.04`.
- Emissao sincronica por `GerarNfse`.
- Consulta por RPS via `ConsultarNfsePorRps`.
- Cancelamento via `CancelarNfse`.
- Assinatura XMLDSIG SHA1 no elemento `InfDeclaracaoPrestacaoServico`; cancelamento assina `InfPedidoCancelamento`.

Endpoint DF conhecido:

- Producao: `https://df.issnetonline.com.br/webservicenfse204/nfse.asmx`
- Homologacao: `https://www.issnetonline.com.br/homologa/webservicenfse204/nfse.asmx`

O endpoint e definido no codigo pelo emissor e escolhido pelo campo `ambiente`; nao deve ser configurado por tenant.

Antes de ativar ISSNet em producao:

- Confirmar certificado cadastrado/liberado no ISSNet.
- Confirmar AIDF ativa, serie e faixa de RPS autorizada.
- Preencher IM da matriz/filial.
- Preencher `item_lista_servico`; preencher CNAE e codigo de tributacao municipal quando o ente exigir.
- Confirmar com ISSNet/ente municipal se o ambiente de homologacao esta liberado antes de testes fiscais.

Tomador estrangeiro na ISSNet:

- O prestador continua sendo a empresa brasileira configurada na filial.
- Para cliente `tipo = ES`, omitir `IdentificacaoTomador/CpfCnpj` e `NifTomador`; passaporte nao e NIF.
- Enviar `EnderecoExterior`, usando em `CodigoPais` o codigo BACEN de 4 digitos cadastrado em `paises.codigo_bacen` e um `EnderecoCompletoExterior` de ate 255 caracteres.
- Bloquear a emissao localmente quando faltar codigo BACEN ou endereco exterior minimo (logradouro, numero, cidade e estado/provincia).

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
- Root: `<DPS versao="1.01">`
- Elemento assinado: `infDPS`
- Atributo de ID: `id` minusculo
- Assinatura: SHA256
- Texto do servico em maiusculo
- Bloco `<trib>` deve conter `<tribMun>` e `<totTrib>`; sem `<totTrib>` a Betha rejeita a DPS por schema incompleto.
- `<dhEmi>` deve usar horario local do prestador (`America/Sao_Paulo`, offset `-03:00`), nao UTC.
- Para tomador brasileiro, o endereco deve seguir a mesma regra conservadora do Nacional: enviar `<end>` apenas quando houver CEP com 8 digitos e codigo IBGE do municipio do tomador com 7 digitos. Para cliente `tipo = ES`, nunca enviar passaporte como identificacao fiscal: gerar `<cNaoNIF>0</cNaoNIF>` e usar `<endExt>` quando o endereco exterior estiver completo. Betha deve gerar o endereco no namespace Betha, nunca reaproveitar XML Nacional/ABRASF.
- Para cliente `tipo = ES` e pais diferente de `BR`, enviar `<comExt>` imediatamente apos `<cServ>`. Para locacao comum prestada no Brasil, usar `mdPrestacao=2`, `vincPrest=0` (sem vinculo), `mecAFComexP=1` e `mecAFComexT=1` (nenhum mecanismo de apoio), `movTempBens=1` (nao vinculada a movimentacao temporaria) e `mdic=0`. Os codigos de desconhecido `vincPrest=9`, `mecAFComexP=0`, `mecAFComexT=0` e `movTempBens=0` sao exclusivos do compartilhamento municipal de nota de origem e nao devem ser usados na DPS do contribuinte. `tpMoeda` usa o codigo BACEN da moeda da filial (BRL `790`, USD `220`, EUR `978`, GBP `540`) e `vServMoeda` recebe o valor do servico. Moeda sem mapeamento deve bloquear a emissao localmente.
- No schema Betha aceito pelo SOAP, `<valores>` deve vir logo apos `</serv>`. Nao enviar `<cLocalidadeIncid>` nesse ponto; esse elemento pertence ao XML nacional/NFS-e gerado posteriormente, nao ao DPS Betha.
- Betha v1.01 deve manter o namespace `http://www.betha.com.br/e-nota-dps`; nao trocar o DPS para namespace SPED.
- Embora a NT004 Betha descreva o grupo `<IBSCBS>`, o preenchimento ainda nao esta homologado neste sistema para o emissor Betha. Manter `preencher_ibscbs = N`.
- Com IBS/CBS sem aliquotas/valores informados, Betha v1.01 deve seguir os exemplos oficiais NT004 v2.0 e gerar `<totTrib><indTotTrib>0</indTotTrib></totTrib>`, sem `<pTotTrib>` ou `<vTotTrib>` zerados.
- `999999999` nao e NBS valido para a calculadora nacional acionada pela Betha NT004. Para a descricao padrao de locacao de veiculo automotor sem condutor, converter esse placeholder para `111011100` (`1.1101.11`) antes de enviar.
- Se `preencher_ibscbs = S`, a emissao Betha deve falhar com erro de configuracao claro.
- `ConsultarStatusDpsEnvio` deve enviar `<tpAmb>`, `<codigoIbge>`, `<cpfCnpjPrestador>`, `<protocolo>` e `<tipoIntegracao>`, nessa ordem.
- Para consulta de emissao Betha, `<tipoIntegracao>` deve ser `EMISSAO`.
- Resposta pode vir com prefixo `ns2:`; parsers devem usar namespace, nao string fixa.

Cancelamento Betha:

- `RecepcionarEventoCancelamentoEnvio` deve conter `<evento versao="1.0">`, `infEvento`, `pedRegEvento`, `infPedReg` e `e101101`; nao enviar `chaveAcesso` e `motivo` diretamente sob `evento`.
- O `infEvento` usa atributo `id` minusculo e deve ser assinado com XMLDSIG SHA256. A assinatura fica como filha de `evento`.
- O pedido usa `cMotivo=9` (Outros) e envia a justificativa informada pelo usuario em `xMotivo`.
- A recepcao do pedido e assincrona. O HTTP 2xx com protocolo apenas confirma o recebimento e nunca deve marcar a NFS-e como cancelada.
- Qualquer resposta de recepcao com protocolo, sem sucesso ou erro final explicito, deve manter `nfse.status = autorizada` e `cancelamento_status = processando`. Isso inclui `Nao processado` e `Aguardando validacao do ambiente nacional`; nao dependa de transliteracao ou de uma unica frase do provedor.
- Consultar o protocolo com `ConsultarStatusDpsEnvio` e `tipoIntegracao=CANCELAMENTO`.
- Somente `Processado com sucesso` altera a NFS-e para `cancelada`. Em `Processado com erro`, manter a nota autorizada, marcar o pedido com erro e registrar o retorno em `nfse_eventos`.
- Nao aceitar outro pedido enquanto houver cancelamento Betha em processamento.

Fluxo:

1. Gerar DPS Betha.
2. Assinar `infDPS` usando atributo `id`.
3. Enviar via `RecepcionarDps`.
4. Salvar `protocolo` e deixar NFS-e como `processando`.
5. `NFSeConsultarBethaJob` consulta `ConsultarStatusDps`.
6. Quando autorizado, atualizar `chave_acesso`, `numero`, `codigo_verificacao` e `xml_retorno`.

O mesmo job consulta cancelamentos Betha pendentes pelo protocolo especifico de cancelamento, sem reutilizar ou sobrescrever o protocolo historico da emissao.

### Sincronizacao de situacao fiscal Betha

Cancelamentos e substituicoes podem ser registrados fora do 7Carros, por exemplo pela contabilidade. Notas Betha autorizadas devem ser reconciliadas pela API de contribuintes do Ambiente de Dados Nacional (ADN), usando o mesmo certificado A1 da filial e sem credenciais Betha adicionais.

| Ambiente | Base ADN contribuintes |
|----------|------------------------|
| Producao | `https://adn.nfse.gov.br/contribuintes` |
| Homologacao | `https://adn.producaorestrita.nfse.gov.br/contribuintes` |

- Consultar `GET /nfse/{chaveAcesso}/eventos` com mTLS.
- Validar que `chNFSe`/`chaveAcesso` do evento corresponde exatamente a chave local antes de alterar o registro.
- Evento `101101` confirma cancelamento e altera o status local para `cancelada`.
- Evento `105102` confirma cancelamento por substituicao e altera o status local para `substituida`.
- Ausencia desses eventos significa situacao `N` (normal) e mantem a nota `autorizada`.
- Persistir `situacao_fiscal` (`N`, `C` ou `S`) e `situacao_fiscal_consultada_em` somente apos consulta ADN bem-sucedida.
- Registrar mudancas externas em `nfse_eventos` como `reconciliacao`, preservando a resposta bruta para auditoria.
- A acao manual `Consultar Status` usa essa consulta para nota Betha autorizada; notas Betha ainda em `processando` continuam consultando o protocolo de emissao.
- O cron consulta no maximo 20 notas por execucao, prioriza a consulta mais antiga e aplica intervalo minimo de 15 minutos por nota.
- Se a Betha rejeitar o cancelamento porque a nota nao esta mais na situacao `N - Normal`, consultar o ADN antes de registrar erro final.

Numeracao:

- Usa `numero_atual`.
- Falhas locais antes do envio externo nao devem consumir numero: validacao, geracao XML, certificado e assinatura precisam concluir antes da reserva.
- A reserva do numero deve ser atomica e ocorrer somente com XML assinado pronto para envio.
- Numero enviado ou possivelmente enviado ao provedor deve ser preservado, mesmo quando a DPS voltar rejeitada. DPS recepcionada nao deve reutilizar o mesmo ID.

---

## Reenvio

Para NFS-e rejeitada:

- Maximo regular de 5 envios totais: o envio inicial e ate 4 reenvios.
- O limite deve vir de `App\Config\NFSe::MAX_ENVIOS`; Service e busca do CRON nao podem declarar valores independentes.
- Reenvio manual pode liberar exatamente uma tentativa extra somente para `XML_INVALIDO` com financeiro vinculado e causa tecnica conhecida ja corrigida no gerador XML/data fiscal, incluindo erros Betha de schema por `cLocalidadeIncid`.
- A tentativa extra deve registrar `reenvio_manual` com codigo `LIMITE_TECNICO` antes do envio externo. A existencia desse evento bloqueia novas excecoes, inclusive quando o provedor rejeitar a tentativa adicional.
- Reenvio automatico por CRON continua limitado a `tentativas_envio < 5` e nunca utiliza a tentativa extra manual.
- Se houver `id_financeiro`, regenerar XML com os dados atuais antes de reenviar.
- Se nao houver `id_financeiro`, reaproveitar o XML salvo como fallback.
- Em Betha, regenerar evita erro de DPS ja recepcionada com mesmo ID.
- Quando o reenvio regenerar XML e reservar novo numero, registrar evento com o numero anterior e o novo para manter rastreabilidade.

---

## Emissao Manual pelo Financeiro

A tela `GET /pages/nfse/emitir?id_financeiro={id}` emite NFS-e a partir de uma
receita paga ou pendente do modulo financeiro. A listagem exibe a acao manual
nos dois estados; a emissao automatica continua restrita a pagamentos
confirmados.

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
- Nome, tipo, pais e documento exibidos na tela sao somente leitura e sempre vem do cadastro do cliente; a requisicao manual nao pode sobrescreve-los.
- Antes de gerar, assinar e enviar a DPS, o sistema deve validar o tomador:
  - nome obrigatorio;
  - para PF/PJ, CPF com 11 digitos validos ou CNPJ com 14 digitos validos;
  - para ES, passaporte cadastral com ate 40 caracteres e pais diferente de BR;
  - ausencia de documento deve bloquear a emissao localmente com mensagem clara para corrigir o cadastro do cliente.
- Rejeicoes SEFIN de schema `E1235` no bloco `<toma>` por `<xNome>` antes de `CNPJ`/`CPF`/`NIF` devem ser exibidas ao usuario como erro de CPF/CNPJ do cliente ausente, preservando o retorno tecnico em eventos/logs para suporte.
- O codigo IBGE do municipio do tomador brasileiro pode ser informado nessa tela para permitir envio de endereco completo em emissores DPS quando o cadastro do cliente ainda nao tiver esse dado. Para tomador estrangeiro, exibir o pais e ocultar o codigo IBGE.
- Ausencia de configuracao NFS-e ou certificado nao deve redirecionar para a listagem. A tela deve permanecer aberta, mostrar aviso especifico e bloquear somente o botao de emissao.

Persistencia:

| Campo `nfse` | Origem |
|--------------|--------|
| `valor_servicos` | `financeiro.valor_total` |
| `valor_deducoes` | Soma dos itens nao tributaveis |
| `itens_nao_tributaveis` | JSON `[{descricao, valor}]` dos itens desmarcados/manuais |
| `base_calculo` | `valor_servicos - valor_deducoes` |
| `tomador_cpf_cnpj` | CPF/CNPJ ou passaporte cadastral, preservado apenas no historico local |
| `tomador_tipo` / `tomador_pais` | Tipo e pais do cliente no momento da emissao |

Nao crie tabela separada para itens nao tributaveis sem necessidade fiscal nova. O campo JSON atual preserva o historico da emissao e ja atende ao fluxo manual.

---

## CRON Jobs

| Job | Frequencia | Limite | Descricao |
|-----|------------|--------|-----------|
| `NFSeEmitirAutoJob` | 5min | 50 | Emite NFS-e de pagamentos confirmados |
| `NFSeReenviarJob` | 5min | 20 | Reenvia rejeitadas recuperaveis |
| `NFSeConsultarBethaJob` | 1min | 20 por fluxo | Consulta protocolos Betha e reconcilia eventos fiscais no ADN |
| `NFSeEnviarEmailJob` | 5min | 30 | Envia PDF por email |

`NFSeConsultarBethaJob` deve considerar atividade recente por `COALESCE(updated_at, created_at)`, nao apenas `created_at`, porque reenvios Betha reutilizam o registro rejeitado e atualizam protocolo/status no mesmo registro.

Consultas Betha que retornam apenas "ainda em processamento" nao devem gerar evento em `nfse_eventos`; registrar somente emissao/recepcao, autorizacao, rejeicao, erro ou outra mudanca relevante de estado.

Enquanto a Betha mantiver a DPS como recebida e sem resultado final, o banco deve continuar com `nfse.status = processando`. A API pode expor metadados derivados de exibicao (`processamento_minutos`, `processamento_alerta`, `processamento_demorado`, `mensagem_processamento`) para evitar que a tela pareca travada:

- ate 15 minutos: exibir como `Processando`;
- a partir de 15 minutos: exibir como `Aguardando validação Betha`;
- a partir de 60 minutos: exibir alerta `Validação demorada no provedor`.

Esse tratamento e apenas visual/API. Nao transformar em rejeitada, nao liberar reenvio e nao consumir novo numero enquanto houver protocolo Betha em processamento sem rejeicao real do provedor.

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
- Notas `substituida` devem exibir esse estado no cabecalho e o bloco de substituicao no PDF.
- O download gera o PDF sob demanda em memoria para entregar sempre o layout fiscal atual, sem persistir arquivo em `storage/uploads`.
- O envio por email gera arquivo temporario apenas para anexo; o arquivo deve ser removido apos envio bem-sucedido.

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
