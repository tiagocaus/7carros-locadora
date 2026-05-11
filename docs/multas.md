# Multas

Módulo de gestão de multas de trânsito vinculadas a contratos ou locações. Combina CRUD manual, integração com SERPRO eFrotas para consulta automática, indicação de condutor, e impressão/envio de documentos.

## Visão geral

```
┌─────────────────────────────────────────────────────────────┐
│  Central de Multas (/multas/central — central.php)          │
│  ├─ KPIs (vencidas, vencendo 30d, em dia, pagas, valor)     │
│  ├─ Filtros (tipo, pago, vencimento, origem, status)        │
│  ├─ Ranking de veículos com mais multas                     │
│  └─ Tabela com ações por linha:                             │
│     ├─ Imprimir (azul)  ← novo: 4 tipos de PDF              │
│     ├─ Editar                                               │
│     ├─ Indicar condutor (se numero_ait)                     │
│     ├─ Marcar pago/não-pago                                 │
│     └─ Excluir                                              │
└─────────────────────────────────────────────────────────────┘
```

## Tabela `multas`

Campos principais (schema completo via `DESCRIBE multas`):

| Campo | Tipo | Função |
|---|---|---|
| `id`, `chave`, `id_matriz_filial` | identificação + multi-tenancy |
| `tipo` | varchar(1) — `'C'` (contrato) ou `'L'` (locação) |
| `id_contrato`, `id_locacao` | fk para o vínculo (apenas um preenchido) |
| `id_cliente`, `id_veiculo` | quem paga + qual carro |
| `n_infracao`, `numero_ait`, `codigo_orgao`, `codigo_infracao` | identificadores oficiais |
| `orgao_autuador`, `local`, `cidade`, `estado` | onde foi |
| `data_hora`, `data_vencimento` | quando ocorreu, quando vence |
| `valor`, `valor_desconto_40` | valor cheio + 40% (boleto antecipado) |
| `pago` | varchar(1) — `'S'` ou `'N'` |
| `descri` | descrição livre |
| `foto`, `foto_radar` | imagens (WebP) |
| `origem` | enum `manual` / `serpro_consulta` / `serpro_evento` |
| `status_processamento` | enum (`novo`, `pendente_indicacao`, `indicacao_enviada`, etc.) |
| `na_pdf_path`, `np_pdf_path` | PDFs oficiais do SERPRO (Notificação de Autuação / Penalidade) |
| `id_financeiro` | fk para parcela financeira gerada quando paga |

## Model `app/Models/Multa.php`

`buscarPorId(int $id)` (linha 128) já traz todos os JOINs em uma query: `cliente_nome`, `cliente_cpf_cnpj`, `veiculo_placa/modelo/marca`, `filial_nome`, `contrato_codigo`, `locacao_codigo`. **Reuse — não duplique a query.**

`buscarResponsavel($veiculoId, $dataHoraMulta)` busca o responsável pelo veículo na data/hora da infração (delega para `ContratoVeiculo::findResponsavelByMulta` e `LocacaoVeiculo::findResponsavelByMulta`).

## Controller `app/Controllers/MultasController.php`

| Método | Rota | Função |
|---|---|---|
| `viewAdicionar` | `GET /pages/multas/adicionar` | Tela de adicionar/editar |
| `show` | `GET /api/multas/{id}` | Retorna multa em JSON |
| `buscarResponsavel` | `POST /api/multas/buscar-responsavel` | Identifica responsável por placa+data |
| `store` / `update` / `destroy` | `POST /multas/...` | CRUD |
| `marcarPago` / `marcarNaoPago` | `POST /multas/{id}/marcar-...` | Toggle de pagamento |
| **`offcanvasImpressao`** | `GET /pages/multas/offcanvas-impressao` | Renderiza offcanvas com opções de impressão |
| **`imprimir`** | `GET /multas/{id}/imprimir?tipo=X` | Gera PDF inline |
| **`enviarMulta`** | `POST /multas/{id}/enviar` | Gera PDF + enfileira via mensageria |

## Sistema de impressão

Padrão idêntico a contratos/locações: ícone na tabela → offcanvas com opções → "Gerar PDF" abre modal fullscreen, ou envio direto por email/whatsapp/sms.

### 4 tipos de PDF

Templates em `app/Views/pages/multas/imprimir/`:

| Tipo | Quando aparece | Conteúdo |
|---|---|---|
| `notificacao` | sempre | Comunicado ao cliente: dados da infração, valor, vencimento, instruções de pagamento |
| `documento` | sempre (precisa selecionar modelo) | Modelo customizado de [`Documento`](./documentos.md) com `tipo=3 (Multa)` |
| `comprovante` | só se `pago='S'` | Recibo de quitação com valor pago em destaque |
| `termo_indicacao` | só se `numero_ait` preenchido | Formulário DETRAN para identificar condutor real |

### Imagens nos PDFs

**Sempre** via [`PdfHelper::resolveImagePath`](./pdf.md#imagens-no-pdf-logos-fotos-assinaturas). Nunca passe `FileHelper::url()` (URL HMAC) nem path direto sem o helper. Detalhes do porquê: a doc de PDF.

### Tipo `documento`: cabeçalho, rodapé e margens

O PDF usa `SetHTMLHeader` / `SetHTMLFooter` como em contratos/locações; a margem inferior do corpo é menor (`PdfHelper::DOCUMENTO_MULTAS_HTML_FOOTER_MARGIN_BOTTOM_MM`), pois o rodapé é só numeração de página. Impressão inline e envio por mensageria (`enviarMulta`) compartilham o mesmo fluxo. Ver [pdf.md](./pdf.md).

### Validações no controller

- `comprovante` exige `$multa['pago'] === 'S'` → 422 caso contrário
- `termo_indicacao` exige `!empty($multa['numero_ait'])` → 422
- `documento` exige `id_documento` válido com `tipo=3` → 422

### Envio por mensageria

Botões email/whatsapp/sms condicionais ao plano do tenant (`Planos::getPlano($user['plano'])` checa `smtp`, `whatsapp`, `sms`). PDF é gerado em `storage/temp/`, enfileirado via `queue_message()`. Cliente sem email/telefone cadastrado recebe 422.

Para detalhes de mensageria: [Sistema de Mensageria](./messaging.md).

## i18n

Arquivos: `app/Lang/{pt_BR,pt_PT,en_US,es_ES,it_IT}/modules/multas.php`. Seções principais relacionadas à impressão:

- `central.actions.print` — tooltip do ícone na tabela
- `print.*` — labels do offcanvas (title, fine_label, notification, document, receipt, indication_term, generate_pdf, send_via)
- `pdf.*` — strings dentro dos templates PDF (títulos de seção, labels de campos, textos de declaração, parágrafos com placeholders `:client/:plate/:value/:due`)
- `messages.*` — alerts JS (select_doc_before_pdf, send_success, send_error, etc.)

## Integração SERPRO

Consultas online, eventos automáticos, indicação de condutor, download de NA/NP. Especificação técnica completa em [`SERPRO_CENTRAL_MULTAS.md`](./SERPRO_CENTRAL_MULTAS.md). Saldo prepago e markup em [`saldo.php`](./SERPRO_CENTRAL_MULTAS.md#saldo).

## Permissões

Sem permissões dedicadas no sistema de roles atual — qualquer usuário com acesso ao módulo (via menu) pode usar todas as ações. Se for necessário restringir impressão ou envio, criar permissão `multas.imprimir` / `multas.enviar` e adicionar `Auth::can()` nos métodos.

## Documentação relacionada

- [Documentos](./documentos.md) — modelos com `tipo=3` usados no PDF do tipo "documento"
- [Geração de PDF](./pdf.md) — `PdfHelper`, output buffering, `resolveImagePath`
- [Sistema de Mensageria](./messaging.md) — `queue_message()`, fila RabbitMQ
- [SERPRO Central de Multas](./SERPRO_CENTRAL_MULTAS.md) — integração externa
