# NFS-e (Nota Fiscal de Servico Eletronica)

Modulo de emissao de NFS-e integrado ao sistema financeiro da locadora.

---

## Visao Geral

O modulo suporta dois modelos de emissao via Strategy Pattern:
- **Nacional (SEFIN)**: REST + mTLS, XML DPS, SHA256
- **ABRASF (Municipal)**: SOAP 1.1, XML RPS v2.04, SHA1

Fluxo de status: `pendente` -> `processando` -> `autorizada` | `rejeitada` -> `cancelada`

---

## Estrutura de Arquivos

```
app/
├── Controllers/NFSeController.php        # 19 metodos (views, API, acoes, config)
├── Models/
│   ├── NFSe.php                          # CRUD + queries paginadas + CRON
│   ├── NFSeConfiguracao.php              # Config por filial, proximoNumero atomico
│   └── NFSeEvento.php                    # Log de eventos
├── Services/NFSe/
│   ├── NFSeService.php                   # Orquestrador principal
│   ├── NFSeErros.php                     # 70+ codigos de erro mapeados
│   ├── NFSeCertificado.php               # Upload/validacao PFX, extracao PEM
│   ├── NFSeAssinatura.php                # XMLDSIG (SHA256/SHA1)
│   ├── NFSePDF.php                       # DANFSE via PdfHelper::saveToFile()
│   ├── NFSeXMLInterface.php              # Interface XML
│   ├── NFSeAPIInterface.php              # Interface API
│   ├── Nacional/
│   │   ├── NFSeXMLNacional.php           # XML DPS, gzip+base64, MAIUSCULO
│   │   └── NFSeAPINacional.php           # REST mTLS via cURL
│   └── ABRASF/
│       ├── NFSeXMLAbrasf.php             # XML RPS v2.04
│       └── NFSeAPIAbrasf.php             # SOAP 1.1 via cURL
├── Views/pages/nfse/
│   ├── index.php                         # Listagem com filtros e estatisticas
│   ├── emitir.php                        # Formulario de emissao
│   ├── visualizar.php                    # Detalhes + timeline eventos
│   ├── cancelar.php                      # Cancelamento com motivo
│   └── configuracoes.php                 # Config certificado/tributacao/ABRASF
├── Crons/Jobs/
│   ├── NFSeEmitirAutoJob.php             # Emissao automatica (50/exec, 5min)
│   ├── NFSeReenviarJob.php               # Reenvio rejeitadas (20/exec, 5min)
│   └── NFSeEnviarEmailJob.php            # Envio email PDF (30/exec, 5min)
├── Database/migrations/
│   ├── 00269_create_nfse_configuracoes.php
│   ├── 00270_create_nfse.php
│   ├── 00271_create_nfse_eventos.php
│   └── 00272_create_nfse_permissions.php
└── lang/{pt_BR,en_US,es_ES,it_IT,pt_PT}/modules/nfse.php
```

---

## Tabelas

### `nfse_configuracoes`
Config por filial. UNIQUE (chave, id_matriz_filial).

| Campo | Descricao |
|-------|-----------|
| tipo_emissao | 1=Nacional, 2=ABRASF |
| ambiente | 1=Producao, 2=Homologacao |
| certificado_path | Caminho PFX em storage/certificates/{chave}/ |
| certificado_senha | Encrypted via encrypt() |
| numero_atual | Proximo numero (atomico via UPDATE+1) |
| emissao_auto | S/N - emissao automatica para pagamentos |
| enviar_email | S/N - envio automatico de email |
| Campos ABRASF | codigo_municipio, codigo_servico, aliquota_iss, etc. |

### `nfse`
Tabela principal. 10 indexes.

| Campo | Descricao |
|-------|-----------|
| id_financeiro | FK para financeiro (nullable) |
| id_matriz_filial | FK para matrizes_filiais |
| status | ENUM: pendente, processando, autorizada, rejeitada, cancelada |
| dados prestador | cnpj, razao_social, inscricao_municipal |
| dados tomador | nome, cpf_cnpj, email, endereco |
| dados servico | descricao, codigo_servico, valor_servico |
| tributos | iss, pis, cofins, ir, csll, inss, ibs, cbs |
| xml_envio/retorno | XML completo |
| pdf_url | URL do DANFSE gerado |
| tentativas_envio | Contador para reenvio (max 3) |

### `nfse_eventos`
Log de auditoria com FK CASCADE para nfse.

---

## Permissoes

| Permissao | Descricao |
|-----------|-----------|
| nfse.visualizar | Ver listagem e detalhes |
| nfse.criar | Emitir e reenviar NFS-e |
| nfse.excluir | Cancelar NFS-e |
| nfse.configurar | Acessar configuracoes |

---

## Rotas

### Views (iframe)
```
GET /pages/nfse                    -> view (listagem)
GET /pages/nfse/emitir             -> viewEmitir (?id_financeiro=)
GET /pages/nfse/{id}/visualizar    -> viewVisualizar
GET /pages/nfse/{id}/cancelar      -> viewCancelar
GET /pages/nfse/configuracoes      -> viewConfiguracoes
```

### API (leitura)
```
GET /api/nfse                      -> index (paginado + filtros)
GET /api/nfse/estatisticas         -> estatisticas
GET /api/nfse/configuracoes        -> getConfiguracoes
GET /api/nfse/{id}                 -> show
GET /api/nfse/{id}/eventos         -> eventos
```

### Acoes (escrita)
```
POST /nfse/emitir                  -> emitir
POST /nfse/{id}/cancelar           -> cancelar
POST /nfse/{id}/consultar          -> consultar
POST /nfse/{id}/reenviar           -> reenviar
POST /nfse/{id}/email              -> enviarEmail
GET  /nfse/{id}/pdf                -> downloadPdf
```

### Configuracoes
```
POST /nfse/configuracoes/salvar              -> salvarConfiguracoes
POST /nfse/configuracoes/certificado         -> uploadCertificado
POST /nfse/configuracoes/certificado/remover -> removerCertificado
POST /nfse/configuracoes/testar-conexao      -> testarConexao
```

---

## Strategy Pattern

O `NFSeService` roteia para a implementacao correta via `match()`:

```php
match ((int) $config['tipo_emissao']) {
    1 => new NFSeXMLNacional(),   // + NFSeAPINacional
    2 => new NFSeXMLAbrasf(),     // + NFSeAPIAbrasf
};
```

**Nacional (tipo_emissao=1)**:
- XML DPS com namespace SPED, ID 45 chars
- Textos convertidos para MAIUSCULO
- Conteudo compactado com gzip+base64
- REST API com mTLS (certificado digital)
- Assinatura SHA256

**ABRASF (tipo_emissao=2)**:
- XML RPS v2.04 com namespace ABRASF
- SOAP 1.1 via cURL (nao SoapClient)
- Assinatura SHA1

---

## Certificado Digital

- Upload PFX via `NFSeCertificado::upload()`
- Armazenado em `storage/certificates/{chave}/`
- Senha encrypted no BD via `encrypt()`
- Extracao PEM temporaria em `/tmp/` para cURL mTLS
- Cleanup em `finally` blocks
- Validacao: formato, senha, validade, permissao 0600

---

## CRON Jobs

| Job | Frequencia | Limite | Descricao |
|-----|-----------|--------|-----------|
| NFSeEmitirAutoJob | 5min | 50/exec | Emite NFS-e para pagamentos confirmados |
| NFSeReenviarJob | 5min | 20/exec | Reenvia rejeitadas com tentativas < 3 |
| NFSeEnviarEmailJob | 5min | 30/exec | Envia PDF por email (configs com enviar_email=S) |

Todos usam `withoutChave()` + definem `$_SESSION['chave']` antes de chamar Services.

---

## Integracao com Financeiro

- Botao "Emitir NFS-e" na listagem de financeiro (condicional: pago + receita + sem NFS-e)
- Subquery `tem_nfse` no `Financeiro::listarPaginado()` conta NFS-e nao-canceladas
- Menu NFS-e no submenu Financeiro do navbar (condicionado a `nfse.visualizar`)

---

## Tributos

| Tributo | Descricao |
|---------|-----------|
| ISS | Imposto sobre servicos (aliquota configuravel) |
| PIS | 0.65% |
| COFINS | 3.00% |
| IR | 1.50% |
| CSLL | 1.00% |
| INSS | 0.00% (default) |
| IBS | 0.10% (novo, transicao 2026) |
| CBS | 0.90% (novo, transicao 2026) |

---

## Erros e Recuperacao

`NFSeErros::isRecuperavel()` classifica erros:
- **Recuperaveis**: timeout, conexao recusada, servico indisponivel -> reenvio automatico
- **Nao recuperaveis**: CNPJ invalido, certificado expirado, duplicidade -> requer correcao manual

---

## Checklist de Deploy

1. Rodar migrations: `php migrate.php`
2. Configurar certificado digital por filial
3. Testar conexao em homologacao (ambiente=2)
4. Emitir NFS-e de teste com chave 1111111111111
5. Validar PDF gerado e envio de email
6. Mudar para producao (ambiente=1) quando pronto
