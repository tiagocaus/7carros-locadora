# Modulo de Contratos

Sistema completo de gestao de contratos de locacao de veiculos.

## Visao Geral

O modulo de contratos permite gerenciar todo o ciclo de vida de uma locacao:
- Criacao de contratos com multiplos veiculos
- Devolucao e substituicao de veiculos durante o contrato
- Registro rapido de leituras de odometro durante contratos ativos
- Assinatura digital pelo cliente
- Impressao de documentos (fatura, contrato, checklist, recibo)
- Controle de autorenovacao

## Estrutura de Arquivos

```
app/
├── Models/
│   ├── Contrato.php              # Model principal
│   ├── ContratoVeiculo.php       # Veiculos do contrato
│   ├── ContratoTaxaServico.php   # Taxas e servicos
│   └── ContratoBloqueio.php     # Authorization holds (pre-autorizacao)
├── Controllers/
│   ├── ContratosController.php   # CRUD de contratos + bloqueio
│   └── AssinaturaController.php  # Pagina publica de assinatura
└── Views/
    └── pages/contratos/
        ├── index.php             # Listagem
        ├── adicionar.php         # Formulario (criar/editar)
        └── imprimir/
            ├── fatura.php        # Template de fatura
            ├── documento.php     # Template do contrato
            ├── fatura_documento.php # Fatura + Contrato
            ├── checklist.php     # Checklist do veiculo
            └── recibo.php        # Recibo de quitacao
```

## Rotas

### Paginas (requerem autenticacao)
| Metodo | Rota | Controller | Descricao |
|--------|------|------------|-----------|
| GET | /pages/contratos | ContratosController@view | Listagem |
| GET | /pages/contratos/adicionar | ContratosController@formView | Novo contrato |
| GET | /pages/contratos/adicionar/{id} | ContratosController@formView | Editar contrato |
| GET | /contratos/{id}/imprimir | ContratosController@imprimir | Impressao |

### API (requerem autenticacao)
| Metodo | Rota | Controller | Descricao |
|--------|------|------------|-----------|
| GET | /api/contratos | ContratosController@index | Lista paginada |
| GET | /api/contratos/{id} | ContratosController@show | Detalhes |
| GET | /api/contratos/grupos | ContratosController@gruposPorFilial | Grupos disponiveis |
| GET | /api/contratos/veiculos | ContratosController@veiculosPorGrupo | Veiculos disponiveis |
| GET | /api/contratos/valores-grupo/{id} | ContratosController@valoresGrupo | Valores do grupo |

### CRUD
| Metodo | Rota | Controller | Descricao |
|--------|------|------------|-----------|
| POST | /contratos/salvar | ContratosController@store | Criar |
| POST | /contratos/{id}/atualizar | ContratosController@update | Atualizar |
| POST | /contratos/{id}/excluir | ContratosController@destroy | Excluir |

### Acoes
| Metodo | Rota | Controller | Descricao |
|--------|------|------------|-----------|
| POST | /contratos/{id}/devolver | ContratosController@devolver | Devolucao |
| POST | /contratos/{id}/substituir | ContratosController@substituir | Substituicao |
| POST | /contratos/{id}/veiculos | ContratosController@adicionarVeiculo | Adicionar veiculo |
| POST | /api/contratos/{id}/odometros | ContratosController@registrarOdometro | Registrar leitura de odometro |
| POST | /contratos/{id}/limpar-assinatura | ContratosController@limparAssinatura | Limpar assinatura |

### Bloqueio (Pre-autorizacao no Cartao)
| Metodo | Rota | Controller | Descricao |
|--------|------|------------|-----------|
| POST | /api/contratos/{id}/bloqueio/criar | ContratosController@criarBloqueio | Criar authorization hold |
| POST | /api/contratos/{id}/bloqueio/capturar | ContratosController@capturarBloqueio | Capturar hold (cobrar) |
| POST | /api/contratos/{id}/bloqueio/liberar | ContratosController@liberarBloqueio | Liberar hold sem cobrar |
| GET | /api/contratos/{id}/bloqueio/status | ContratosController@statusBloqueio | Consultar status/historico |

### Publicas (nao requerem autenticacao)
| Metodo | Rota | Controller | Descricao |
|--------|------|------------|-----------|
| GET | /assinar/{codigo} | AssinaturaController@view | Pagina de assinatura |
| POST | /assinar/{codigo} | AssinaturaController@assinar | Salvar assinatura |

## Models

### Contrato.php

Model principal com trait `Auditable` para auditoria automatica.

**Metodos principais:**
- `listarPaginado()` - Lista com paginacao, busca e filtro de filial
- `buscarPorId($id)` - Busca com JOINs de cliente, filial, conta, forma pagamento
- `buscarCompleto($id)` - Busca com veiculos e taxas
- `buscarPorCodigo($codigo)` - Busca pelo codigo unico
- `criar($dados)` - Cria contrato com codigo automatico
- `atualizar($id, $dados)` - Atualiza campos do contrato
- `deletar($id)` - Remove contrato e dados relacionados
- `salvarAssinatura($id, $assinatura, $ip)` - Salva assinatura digital
- `limparAssinatura($id)` - Remove assinatura para nova
- `recalcularTotais($id)` - Recalcula valores baseado em veiculos/taxas

### ContratoVeiculo.php

Gerencia veiculos vinculados ao contrato.

**Metodos principais:**
- `listarPorContrato($contratoId)` - Lista todos os veiculos
- `buscarAtivo($contratoId)` - Busca veiculo ativo (sem data_saida)
- `adicionar($dados)` - Adiciona veiculo ao contrato
- `devolver($id, $odometro, $combustivel, $motivo)` - Registra devolucao
- `substituir($id, $dadosSaida, $dadosNovo, $manterValores)` - Substitui veiculo
- `veiculoEstaAlugado($veiculoId, $excluirContratoId)` - Verifica disponibilidade

### ContratoTaxaServico.php

Gerencia taxas e servicos adicionais.

**Metodos principais:**
- `listarPorContrato($contratoId)` - Lista taxas do contrato
- `adicionar()` - Adiciona taxa
- `sincronizar($contratoId, $taxas, $chave)` - Sincroniza lista de taxas
- `calcularTotal($contratoId)` - Calcula total das taxas

## Formulario de Contratos

O formulario possui 9 abas:

### Aba 1: Cliente
- Select de cliente (Chosen com busca server-side)
- Exibe dados do cliente selecionado

### Aba 2: Veiculos
- Lista de veiculos adicionados ao contrato
- Campos por veiculo:
  - Grupo e Veiculo (filtros cascata)
  - Plano: KL (Km Livre), KMC (Km Controlado), KP (Km Pago/Cobrado)
  - Valores do plano (podem ser editados com permissao especial)
  - Seguros (veiculo e terceiros)
  - Odometro e combustivel/carga de entrada
  - Labels se adaptam automaticamente para veiculos eletricos (HE): "Tanque" → "Bateria/Carga", fracoes → porcentagens

### Aba 3: Condutor Adicional
- Lista de condutores adicionais (nome, CPF, CNH, validade)
- Armazenado em JSON no campo `condutor_adicional`

### Aba 4: Fiador
- Lista de fiadores (selecao de clientes cadastrados)
- Armazenado em JSON no campo `array_fiadores`

### Aba 5: Avalista
- Lista de avalistas
- Armazenado em JSON no campo `array_avalistas`

### Aba 6: Testemunhas
- Lista de testemunhas
- Armazenado em JSON no campo `array_testemunhas`

### Aba 7: Financeiro
- Conta bancaria e forma de pagamento
- Comando de parcelas (select independente com comandos do sistema + personalizados)
- Desconto e primeiro pagamento
- Calculo automatico de totais
- O `id_comando_parcela` selecionado e persistido na tabela `contratos` para uso na renovacao automatica
- Ao salvar muitas parcelas, as sequencias financeiras sao reservadas em lote via `SequenciaHelper::proximasSequencias()` para evitar locks repetidos em `matrizes_filiais`
- Ao criar um contrato novo com parcelas geradas, o backend cria/reutiliza links em `pagamentos_links` e enfileira cobrancas `payment_reminder` para email, WhatsApp e SMS. Falhas por canal nao impedem a criacao do contrato nem das parcelas.

### Aba 8: Observacoes
- Campo de texto livre

### Aba 9: Resumo
- Resumo de valores agrupado ou detalhado
- Taxas e servicos adicionais
- Total a pagar

## Listagem de Contratos

- A coluna Veiculo exibe um resumo do primeiro veiculo vinculado ao contrato no formato `PLACA - MODELO`.
- Em contratos ativos, o resumo prioriza veiculos ainda ativos (`contratos_veiculos.data_entrada IS NULL`).
- Em contratos finalizados, quando todos os veiculos ja foram devolvidos, o resumo usa o historico de `contratos_veiculos` para evitar exibir `-`.
- Quando houver mais de um veiculo vinculado, a listagem mostra o primeiro veiculo e o sufixo `(+N)`, onde `N` representa a quantidade de veiculos adicionais alem do primeiro.
- O atalho de registro de odometro permanece disponivel apenas para contratos ativos com veiculo ativo.

## Sistema de Assinatura Digital

As assinaturas sao armazenadas em tabela dedicada `assinaturas` com arquivos WebP.

**Documentacao completa:** [assinaturas.md](assinaturas.md)

### Resumo
- Tabela dedicada `assinaturas` (nao mais na tabela contratos)
- Arquivos WebP (convertidos via ImageHelper)
- Model `Assinatura.php` para todas operacoes
- Auditoria completa: IP, user_agent, geolocalizacao, hash SHA256

## Templates de Impressao

### Fatura (fatura.php)
- Dados da empresa e cliente
- Dados do cliente incluem endereco completo quando cadastrado
- Lista de veiculos com valores
- Taxas e servicos
- Totais e desconto
- Espaco para assinatura

### Documento (documento.php)
- Modelo customizado da entidade `documentos` (texto rico + placeholders); cabeçalho institucional e rodapé com assinaturas são aplicados pelo controller via `SetHTMLHeader`/`SetHTMLFooter` do mPDF; margens do corpo em `PdfHelper::DOCUMENTO_HTML_*`. Detalhes: [pdf.md](./pdf.md).
- Corpo: cláusulas e dados mesclados pelo `TemplateRenderer`
- Contratos com múltiplos veículos devem usar `{{contrato.veiculos_anexo}}` quando o objetivo for um documento contratual completo. Essa variável renderiza o Anexo I com identificação dos veículos, fornecedor/investidor quando houver, plano, valores, seguros e dados de saída. `{{contrato.veiculos_tabela}}` permanece disponível como tabela resumida.

### Fatura + Documento (fatura_documento.php)
- Combina os dois em paginas separadas
- Util para imprimir tudo de uma vez

### Checklist (checklist.php)
- Dados do veiculo
- Niveis de entrada/saida (km, combustivel/carga)
- Para veiculos eletricos (HE): exibe "BATERIA" e porcentagens (0%-100%) ao inves de "TANQUE" e fracoes (V-C)
- Itens de verificacao (exterior, interior, pneus, acessorios)
- Espaco para observacoes

### Recibo (recibo.php)
- Recibo de quitacao elegante
- Valor por extenso
- Dados do pagamento
- Assinatura da empresa

## Permissoes

| Permissao | Descricao |
|-----------|-----------|
| `contratos.visualizar` | Listar e visualizar contratos |
| `contratos.criar` | Criar novos contratos |
| `contratos.editar` | Editar contratos existentes |
| `contratos.editar_valores` | Alterar valores originais do grupo |
| `contratos.excluir` | Excluir contratos |
| `contratos.devolver` | Registrar devolucao de veiculo |
| `contratos.substituir` | Substituir veiculo |
| `contratos.assinatura` | Gerenciar assinatura digital |
| `contratos.imprimir` | Imprimir documentos |

Atendentes devem possuir `contratos.editar`, `contratos.devolver` e
`contratos.substituir` para adicionar veiculo ao contrato, devolver veiculos,
substituir veiculos e fechar contrato. Essas permissoes nao liberam exclusao nem
edicao especial de valores.

## Status do Contrato

- `A` = Ativo (contrato em andamento)
- `F` = Finalizado (todos os veiculos devolvidos)

## Registro Rapido de Odometro

- A listagem de contratos possui um icone de odometro antes da coluna Seq para contratos ativos com veiculos ativos.
- O offcanvas lista todos os veiculos ativos do contrato; com um unico veiculo, o formulario abre direto.
- As leituras intermediarias sao gravadas em `contratos_odometros`, uma por contrato/veiculo/data. Nova leitura no mesmo dia atualiza o registro existente.
- Ao salvar, o sistema atualiza tambem `veiculos.odometro`, permitindo que a manutencao preventiva considere a km atual do veiculo.
- `contratos_veiculos.odometro_saida` permanece como km inicial e `contratos_veiculos.odometro_entrada` permanece reservado para devolucao/substituicao.
- Para plano `KMC`, o offcanvas exibe km rodado, franquia, excedente e valor estimado. Nao gera cobranca automatica; a cobranca oficial continua na devolucao/substituicao.

## Planos de Veiculo

- `KL` = Km Livre (valor fixo independente da km rodada)
- `KMC` = Km Controlado (franquia + excedente)
- `KP` = Km Pago/Cobrado (valor por km rodado, antigo DI)

### Valores por Plano em `contratos_veiculos`

- `KL` persiste o valor principal em `valor_plano_km_livre`.
- `KMC` persiste o valor principal em `valor_plano_km_controlado`.
- `KP` persiste o valor principal em `valor_plano_km_pago`.
- Ao salvar/adicionar/substituir veiculo, os campos de valores de outros planos sao zerados para evitar reaproveitamento de valor antigo oculto na interface.
- `valor_km_excedente` e `km_franquia` permanecem independentes e sao usados nos calculos de km controlado/pago conforme a devolucao ou substituicao.

## Autorenovacao

- `''` (vazio) = Desativada
- `auto` = Renovacao automatica
- `fim` = Contrato encerrado na data final
- `1x`, `2x`, etc. = Numero de renovacoes permitidas

## Auditoria

O sistema usa o trait `Auditable` para registrar automaticamente:
- Criacao de contratos
- Alteracoes em campos
- Exclusao de contratos

Handler de auditoria especializado em `public/assets/js/audit-handlers/contratos-adicionar.js`

## Exemplos de Uso

### Criar contrato via API
```javascript
const response = await Api.post('/contratos/salvar', {
    id_matriz_filial_retirada: 1,
    id_cliente: 123,
    data_ini: '2024-01-15T08:00',
    data_fim: '2024-01-22T08:00',
    contagem: 'dia',
    dias: 7,
    veiculos: [{
        id_veiculo: 45,
        id_grupo: 3,
        plano: 'KL',
        valor_plano_km_livre: 100.00,
        odometro_entrada: 50000,
        combustivel_entrada: 8
    }],
    taxas: [{
        id_taxa: 5,
        nome: 'Taxa de Limpeza',
        quantidade: 1,
        valor_unitario: 50.00
    }]
});
```

### Registrar devolucao
```javascript
await Api.post(`/contratos/${contratoId}/devolver`, {
    id_contrato_veiculo: 123,
    odometro_saida: 52500,
    combustivel_saida: 6,
    motivo_saida: 'Devolucao normal ao final do contrato'
});
```

### Substituir veiculo
```javascript
await Api.post(`/contratos/${contratoId}/substituir`, {
    id_contrato_veiculo_antigo: 123,
    odometro_saida: 52000,
    combustivel_saida: 7,
    motivo_saida: 'Cliente solicitou troca por modelo maior',
    id_veiculo_novo: 67,
    id_grupo_novo: 5,
    plano_novo: 'KL',
    odometro_entrada: 30000,
    combustivel_entrada: 8,
    manter_valores: false
});
```

**Rastreabilidade financeira:** Ao substituir um veiculo, as parcelas financeiras ja criadas mantêm o `financeiro.id_veiculo` do veiculo antigo. Novas parcelas geradas apos a substituicao recebem automaticamente o `id_veiculo` do novo veiculo via `ContratoVeiculo::buscarAtivo()`. Isso garante que receitas e despesas fiquem vinculadas ao veiculo correto em cada periodo. Ver [financeiro.md](financeiro.md#rastreabilidade-veicular) para detalhes.

## Bloqueio (Pre-autorizacao no Cartao)

Reserva um valor no limite do cartao de credito do cliente sem efetuar cobranca.
Mesma mecanica usada nas locacoes — ver [gateways.md](gateways.md) para detalhes da interface.

### Tabela `contratos_bloqueios`

Estrutura identica a `locacoes_bloqueios`, mas com `id_contrato` em vez de `id_locacao`.

| Coluna | Tipo | Descricao |
|--------|------|-----------|
| id | INT PK | Primary key |
| chave | VARCHAR(20) | Tenant key |
| id_contrato | INT UNSIGNED | FK contratos |
| id_cliente | INT UNSIGNED | FK clientes |
| id_cartao | INT UNSIGNED | FK clientes_cartoes |
| id_gateway | INT UNSIGNED | FK gateways_pagamento |
| gateway_code | VARCHAR(50) | stripe, square |
| external_id | VARCHAR(255) | ID do hold no gateway (pi_xxx) |
| valor | DECIMAL(10,2) | Valor do hold |
| moeda | VARCHAR(3) | Moeda (default BRL) |
| status | ENUM | pending, authorized, captured, released, expired, failed |
| autorizado_em | DATETIME | Timestamp da autorizacao |
| capturado_em | DATETIME | Timestamp da captura |
| liberado_em | DATETIME | Timestamp da liberacao |
| expira_em | DATETIME | Expiracao do hold (7 ou 31 dias) |
| valor_capturado | DECIMAL(10,2) | Valor da captura parcial |
| payload | JSON | Resposta raw do gateway |

A tabela `contratos` tem coluna `id_bloqueio_ativo` (FK para `contratos_bloqueios.id`).

### Model `ContratoBloqueio`

- `criar(array $dados): int`
- `buscarPorId(int $id): ?array`
- `buscarAtivoPorContrato(int $idContrato): ?array`
- `listarPorContrato(int $idContrato): array`
- `buscarPorExternalId(string $externalId): ?array`
- `atualizarStatus(int $id, string $status, array $extras): int`

### Fluxo

1. Usuario seleciona cartao e valor na aba Financeiro do contrato
2. `POST /api/contratos/{id}/bloqueio/criar` → gateway cria PaymentIntent manual
3. Hold fica ativo (status=authorized) com expiracao em 7 dias
4. Cron `RotateAuthorizationHoldsJob` rotaciona holds 2 dias antes de expirar (cobre locacoes E contratos)
5. Ao capturar: cria lancamento financeiro (receita) com plano de contas 1.1.5.01
6. Ao liberar: cancela hold no gateway, limpa `id_bloqueio_ativo`

### Na Fatura PDF

A secao GARANTIAS aparece automaticamente na fatura do contrato quando existe bloqueio com valor > 0, mostrando descricao, status e valor.
