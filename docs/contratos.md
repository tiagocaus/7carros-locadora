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
| GET | /api/veiculos/buscar | VeiculosController@buscar | Busca server-side de veiculos disponiveis por grupo/filial |
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
- `criar($dados)` - Cria contrato com codigo automatico (`C` + 7 alfanumericos, ex: `C4Z8M2TN`)
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
- O select de veiculo usa busca server-side: abre com ate 100 disponiveis do grupo/filial e, a partir de 3 caracteres, pesquisa no conjunto completo antes de aplicar o limite
- A busca considera placa (com ou sem hifen), marca e modelo, sempre preservando os filtros de tenant, grupo, filial e disponibilidade
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
- Na renovacao automatica, `dias` e `contagem` definem o periodo renovado do contrato; o comando de parcelas define apenas vencimento/parcelamento financeiro.
- Comandos simples de dia da semana (`Seg`, `Ter`, `Qua`, `Qui`, `Sex`, `Sab` e `Dom`) geram uma unica parcela com valor cheio. Se a data-base ja cair no dia configurado, o vencimento permanece nessa mesma data; caso contrario, avanca para a proxima ocorrencia do dia, sem forcar uma semana adicional. Parcelamento semanal exige comando explicito, como `w4` ou `w4-Seg`.
- A autorenovacao so deve avancar `data_renovacao` depois que todas as parcelas esperadas forem criadas ou confirmadas como ja existentes.
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
- Em exibicoes e impressoes que usam um unico veiculo, o sistema prioriza o veiculo ativo vinculado mais recentemente. Depois da finalizacao, usa o ultimo veiculo do historico, preservando os dados que eram exibidos antes do encerramento.
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
- Lista de veiculos organizada nas colunas Veiculo, Plano, Retirada, Devolucao, Valor e Total
- A coluna Veiculo agrupa marca/modelo, placa e grupo; Retirada e Devolucao agrupam odometro e combustivel/carga
- Em Km Controlado, a franquia aparece como `{km_franquia} km/{Contagem}`, com contagem Dia, Semana, Mes ou Ano conforme o contrato
- Valor representa o plano selecionado; Total soma o plano aos seguros habilitados
- Cada veiculo possui uma linha de seguros: habilitado com valor exibe `Contratado (valor)`, habilitado sem valor exibe `Contratado` e desabilitado exibe `Nao contratado`, mesmo que exista valor residual no banco
- Taxas e servicos
- Totais e desconto
- Espaco para assinatura

### Documento (documento.php)
- Modelo customizado da entidade `documentos` (texto rico + placeholders); cabeçalho institucional e rodapé com assinaturas são aplicados pelo controller via `SetHTMLHeader`/`SetHTMLFooter` do mPDF; margens do corpo em `PdfHelper::DOCUMENTO_HTML_*`. Detalhes: [pdf.md](./pdf.md).
- Corpo: cláusulas e dados mesclados pelo `TemplateRenderer`
- Contratos com múltiplos veículos devem usar `{{contrato.veiculos_anexo}}` quando o objetivo for um documento contratual completo. Essa variável renderiza o Anexo I com identificação dos veículos, fornecedor/investidor quando houver, plano, valores, seguros e dados de saída. `{{contrato.veiculos_tabela}}` permanece disponível como tabela resumida.
- Para campos escalares do veículo atual ou do último veículo histórico, use `{{contrato.km_saida}}`, `{{contrato.km_chegada}}`, `{{contrato.tanque_saida}}` e `{{contrato.tanque_chegada}}`. Os campos de tanque são exibidos como frações legíveis (`Reserva`, `1/2`, `Cheio`, etc.).
- Para exibir somente o nome do plano comum aos veículos relevantes, use `{{contrato.info_plano}}`. A variável retorna `Km Livre`, `Km Controlado` ou `Km Pago`, sem franquia, período ou valores. Havendo planos diferentes, retorna `Conforme relação de veículos`; em contratos finalizados sem veículo ativo, considera o histórico.
- Para o tipo de combustível cadastrado no veículo, use `{{veiculo.combustivel_tipo}}`.
- As variáveis escalares `{{veiculo.*}}` representam o veículo ativo vinculado mais recentemente ou, em contratos finalizados, o último veículo do histórico. Para relacionar todos os veículos, use `{{contrato.veiculos_anexo}}`.
- Para o valor de compra cadastrado no veículo atual ou no último veículo histórico, use `{{veiculo.valor_compra}}`; a variável lê `veiculos.valor_compra` e é formatada como moeda pelo `TemplateRenderer`.
- Para cláusulas que precisam citar o valor recorrente da parcela, use `{{contrato.valor.parcela}}`. A variável considera o valor mais comum entre as parcelas financeiras vinculadas ao contrato; se houver empate, usa o primeiro valor encontrado na ordem das parcelas.
- Para citar a periodicidade ou condição do comando de parcelas sem expor códigos técnicos, use `{{contrato.comando_parcela}}` (ex.: `w4` → `semanal`, `w4-Seg` → `segundas-feiras`, `d15` → `vencimento no dia 15`).
- Para exibir as observações cadastradas na caução, use `{{contrato.caucao_observacoes}}`.
- Para tabelas de parcelas financeiras, use `{{contrato.parcelas_tabela}}` quando quiser apenas Parcela, Vencimento e Valor; use `{{contrato.parcelas_tabela_status}}` quando também precisar da coluna Status.

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
- Cada acionamento de `Registrar leitura` cria uma nova linha em `contratos_odometros`, inclusive quando ja existe outra leitura do mesmo veiculo no mesmo dia.
- Cada veiculo exibe no proprio offcanvas as 5 leituras mais recentes. Usuarios com `contratos.editar` podem corrigir data, odometro e observacao pela rota `PUT /api/contratos/{id}/odometros/{leituraId}`.
- O historico exibe data operacional, odometro e observacao na mesma linha e, ao final de cada item, o momento original do registro (`created_at`). A observacao so ocupa espaco quando estiver preenchida. Leituras do mesmo dia sao ordenadas por `data` e `id`.
- A correcao exige data entre a saida do veiculo e o dia atual e preserva a sequencia nao decrescente entre a saida, a leitura anterior e a posterior. Mais de uma leitura na mesma data e permitida.
- Ao corrigir, o historico do veiculo e bloqueado durante a transacao e todas as diferencas cronologicas sao recalculadas. `veiculos.odometro` acompanha a nova ultima leitura apenas quando ainda refletia a antiga; um valor mais recente vindo de outro fluxo e preservado.
- Toda correcao efetiva registra auditoria apenas dos campos alterados, com os valores anteriores e novos. Reenvios sem mudanca nao atualizam o registro nem geram outra auditoria.
- Ao salvar, o sistema atualiza tambem `veiculos.odometro`, permitindo que a manutencao preventiva considere a km atual do veiculo.
- `contratos_veiculos.odometro_saida` permanece como km inicial e `contratos_veiculos.odometro_entrada` permanece reservado para devolucao/substituicao.
- Para plano `KMC`, o offcanvas exibe km rodado, franquia efetiva proporcional ao tempo de uso do veiculo, excedente e valor estimado. Nao gera cobranca automatica; a cobranca oficial continua na devolucao/substituicao.

## Planos de Veiculo

- `KL` = Km Livre (valor fixo independente da km rodada)
- `KMC` = Km Controlado (franquia + excedente)
- `KP` = Km Pago/Cobrado (valor por km rodado, antigo DI)

### Valores por Plano em `contratos_veiculos`

- `KL` persiste o valor principal em `valor_plano_km_livre`.
- `KMC` persiste o valor principal em `valor_plano_km_controlado`.
- `KP` persiste o valor principal em `valor_plano_km_pago`.
- Ao salvar/adicionar/substituir veiculo, os campos de valores de outros planos sao zerados para evitar reaproveitamento de valor antigo oculto na interface.
- No offcanvas **Adicionar veiculo**, a faixa de `grupos_precos_dias_filiais` e escolhida pela duracao total equivalente em dias (`dias * 1/7/30/365`, conforme `contagem`). O valor da faixa substitui o valor base do plano; quando nao ha faixa aplicavel, usa-se `grupos_precos_filiais`.
- O snapshot salvo em `contratos_veiculos` continua sendo o valor por unidade de contagem. Assim, a diaria resolvida e multiplicada por 7, 30 ou 365 para contratos semanais, mensais ou anuais, e o total do contrato multiplica esse valor pela quantidade de periodos.
- `valor_km_excedente` e `km_franquia` permanecem independentes e sao usados nos calculos de km controlado/pago conforme a devolucao ou substituicao.

### Franquia efetiva no plano KMC

Em contratos, `contratos_veiculos.km_franquia` representa a franquia da unidade de contagem do contrato, nao uma franquia fixa vitalicia do vinculo do veiculo. A franquia efetiva usada para estimativa e cobranca e proporcional ao tempo de uso do veiculo.

Bases de contagem:
- `dia` = 1 dia
- `semana` = 7 dias
- `mes` = 30 dias
- `ano` = 365 dias

Formula oficial:
```text
franquia efetiva = ceil((km_franquia / base da contagem) * dias de uso do veiculo)
km excedente = max(0, km rodados - franquia efetiva)
valor km = km excedente * valor_km_excedente
```

O periodo de uso do veiculo vai de `contratos_veiculos.data_saida` ate a data de referencia do calculo. Estimativas operacionais de odometro e substituicao podem usar no minimo 1 dia. O encerramento financeiro proporcional da devolucao usa somente blocos completos de 24 horas e, portanto, pode apurar zero diaria antes de completar o primeiro dia.

Na criacao do contrato, `contratos_veiculos.data_saida` de todos os veiculos
iniciais deve receber exatamente o `contratos.data_ini` persistido. Essa regra
vale apenas para a criacao: veiculos adicionados posteriormente usam a data/hora
da adicao, e veiculos de substituicao usam a data/hora da substituicao.

Exemplos para contrato mensal `KMC` com `km_franquia = 3.000`:
- 15 dias de uso: franquia efetiva de 1.500 km
- 30 dias de uso: franquia efetiva de 3.000 km
- 90 dias de uso: franquia efetiva de 9.000 km

## Autorenovacao

- `''` (vazio) = Desativada
- `auto` = Renovacao automatica
- `fim` = Contrato encerrado na data final
- `1x`, `2x`, etc. = Numero de renovacoes permitidas

### Regra de Datas

- `data_ini` e `data_fim` representam o periodo original/contratual e nao devem ser alteradas pela autorenovacao.
- `data_renovacao` representa a proxima data em que a autorenovacao deve ser executada.
- Ao renovar, o sistema deve avancar somente `data_renovacao`, usando `contagem` e `dias`.
- Para geracao financeira, o periodo de cobranca da renovacao e calculado a partir de `data_renovacao` atual ate a nova `data_renovacao`; esse periodo nao deve sobrescrever `data_ini` nem `data_fim`.
- O CRON de autorrenovacao deve descobrir tenants candidatos sem filtro por data dependente de timezone; depois deve setar o contexto do tenant, calcular `today()` e aplicar `data_renovacao <= hoje` com parametro SQL.
- Se for necessario corrigir historico em que `data_ini`/`data_fim` foram deslocadas por autorenovacao, use `scripts/corrigir-datas-contratos-autorenovacao.php` primeiro em `--dry-run` e aplique apenas apos conferir os candidatos.

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
    veiculos: [{
        id_contrato_veiculo: 123,
        data_entrada: '2026-07-01T15:30',
        odometro_entrada: 52500,
        combustivel_entrada: 6,
        acao_veiculo: 'disponivel',
        observacao: 'Devolucao normal ao final do contrato'
    }],
    taxas_extras: [{
        id_taxa: 5,
        quantidade: 1,
        valor_unitario: 50.00
    }],
    id_conta: 2,
    id_forma_pagamento: 3,
    data_venci: '2026-07-01',
    pago: 'N'
});
```

Quando `acao_veiculo = criar_os`, `observacao` e obrigatoria (maximo de 255
caracteres). A devolucao cria uma OS em `manutencoes` com status `C` (Criada),
vinculada ao veiculo e a sua filial. Os dados de devolucao preenchem
`data_enviado`, `odo_enviado`, `tanque_enviado` e `motivo`; o veiculo fica com
disponibilidade `O` (Oficina). O item correspondente em `data.devolvidos`
retorna tambem `id_manutencao` e `os`. A permissao para essa acao e a mesma da
devolucao (`contratos.devolver`) e, apos o sucesso, a interface retorna para a
listagem de contratos.

Antes da confirmacao, `POST /api/contratos/{id}/devolucao/preview` calcula o
mesmo resultado que sera persistido. Na devolucao final, o contrato e apurado
por ciclos completos e dias restantes completos de 24 horas: semana usa base
7, mes usa ciclo de calendario e diaria de base 30, e ano usa ciclo de
calendario e diaria de base 365. Fracoes inferiores a 24 horas nao geram
diaria. Tolerancias configuradas para locacoes nao se aplicam a contratos.

Cada veiculo selecionado na devolucao pode ter seus valores comerciais
ajustados pelo botao **Ajustar valores**, desde que o usuario possua
simultaneamente `contratos.devolver` e `contratos.editar_valores`. O offcanvas
mantem o plano somente para leitura e permite alterar o valor do plano, a
franquia e o valor por km aplicaveis ao tipo de plano, alem da ativacao e dos
valores dos seguros do veiculo e contra terceiros. Em contratos com varios
veiculos, cada ajuste pertence ao seu proprio card e o botao fica disponivel
somente para veiculos selecionados.

Ao clicar em **Aplicar valores**, nenhuma gravacao e feita no banco: os dados
ficam no estado da tela, recebem a indicacao **Valores ajustados** e sao enviados
ao endpoint de preview para recalcular o resumo oficial. Somente a confirmacao
da devolucao persiste os campos alterados em `contratos_veiculos`, dentro da
mesma transacao do encerramento. Veiculos nao selecionados nao enviam nem
persistem ajustes. O backend valida novamente permissao, plano, campos
aplicaveis, valores nao negativos e limites do schema; o plano nunca e alterado
por esse fluxo. Os valores efetivamente modificados tambem sao registrados no
log de auditoria.

O calculo final inclui locacao, seguros, taxas contratuais recalculadas,
adicionais de devolucao e desconto proporcional. O sistema compara esse total
com o principal ja lancado no financeiro, excluindo taxa do meio de pagamento,
juros, multa e caucao. Diferenca positiva cria receita; diferenca negativa cria
credito ao cliente. Receitas e faturas originais sao preservadas.

Na tela de devolucao, os dados do lancamento financeiro sao configurados pelo
botao **Gerar pagamento** dentro do **Resumo da Devolucao**. O offcanvas coleta
conta bancaria, forma de pagamento, vencimento e status pago; quando `pago = S`,
tambem exige data de pagamento. Esses dados sao apenas estado de tela ate o
usuario confirmar a devolucao.

O **Resumo da Devolucao** exibe separadamente ciclos completos, dias restantes,
locacao, seguros, taxas, adicionais, desconto, total final, principal lancado e
o ajuste a cobrar ou devolver. Em devolucao parcial, enquanto houver outro
veiculo ativo, o aluguel nao e conciliado: apenas os adicionais do ato geram
novo financeiro.

Em contratos com multiplos veiculos, os cards do resumo mostram somente os
veiculos selecionados na devolucao atual. Vinculos historicos e veiculos ativos
nao selecionados nao sao exibidos. Na ultima devolucao, o historico continua
participando dos totais e permanece no snapshot auditavel, sem aparecer na tela.
Em devolucao parcial, o resumo omite valores de aluguel, principal lancado e
conciliacao, informando apenas km, combustivel/carga e taxas do ato.

O resumo financeiro nunca deve usar calculo local como fallback. Qualquer
alteracao de data, odometro, combustivel/carga, selecao de veiculos ou taxas
invalida a previa anterior. Enquanto a nova previa oficial estiver pendente ou
se o backend rejeitar algum campo, os botoes de pagamento e confirmacao ficam
indisponiveis e a tela apresenta o erro no proprio resumo. Em especial, o
odometro de entrada nao pode ser inferior ao maior valor entre o odometro de
saida do vinculo e o odometro atual do cadastro do veiculo.

O encerramento final atualiza `contratos.total_fatura`, `total_pagar`, desconto,
status e data final, e grava um snapshot imutavel em
`contratos_encerramentos`. A devolucao dos veiculos, taxas, ajuste financeiro e
snapshot pertencem a uma unica transacao. Nao ha backfill de encerramentos
historicos.

Antes do resumo, a secao **Faturas em aberto do contrato** consulta
`GET /api/contratos/{id}/parcelas` e exibe somente receitas pendentes
(`tipo = R`, `pago = N`) ligadas ao contrato atual. Despesas, lancamentos pagos,
faturas de outros contratos e lancamentos de caucao nao fazem parte da lista.
A resposta preserva `data.parcelas` e `data.resumo` e acrescenta:

```json
{
  "faturas_abertas": [],
  "resumo_faturas_abertas": {
    "quantidade": 0,
    "valor_total": 0
  }
}
```

Usuarios com `financeiro.excluir` podem selecionar uma, varias ou todas as
faturas exibidas e exclui-las imediatamente. A exclusao e independente da
confirmacao da devolucao; ao terminar, a tela recarrega a lista e o resumo
financeiro. Sem essa permissao, a secao permanece somente para consulta.

Para campos de data/hora da devolucao, use a regra de datas operacionais:
`data_saida` e `data_entrada` devem ser comparadas sem conversao de timezone.
No front, use `DateHelper.toOperationalDateTimeInput()`/comparacao ISO local e
preserve segundos quando definir `min` do input `datetime-local`. No backend,
mantenha a validacao impedindo devolucao anterior a saida e retorne mensagem
com as duas datas quando houver conflito.

### Substituir veiculo
```javascript
await Api.post(`/contratos/${contratoId}/substituir`, {
    id_contrato_veiculo_antigo: 123,
    data_entrada: '2026-07-01T15:30',
    odometro_entrada: 52000,
    combustivel_entrada: 7,
    motivo_saida: 'Cliente solicitou troca por modelo maior',
    acao_veiculo: 'criar_os',
    id_veiculo_novo: 67,
    id_grupo_novo: 5,
    plano_novo: 'KL',
    odometro_saida_novo: 30000,
    combustivel_saida_novo: 8,
    manter_valores: false
});
```

Quando `acao_veiculo = criar_os`, `motivo_saida` e obrigatorio (maximo de 255
caracteres). A substituicao cria uma OS em `manutencoes` com status `C` (Criada),
vinculada ao veiculo antigo e a sua filial. A data da substituicao, odometro,
combustivel/carga e motivo preenchem os dados de envio da OS; o veiculo antigo
fica com disponibilidade `O` (Oficina). A resposta retorna `id_manutencao` e
`os` dentro de `data`. Com `acao_veiculo = disponivel`, nenhuma OS e criada.

**Rastreabilidade financeira:** Ao substituir um veiculo, as parcelas financeiras ja criadas mantêm o `financeiro.id_veiculo` do veiculo antigo. Novas parcelas geradas apos a substituicao recebem automaticamente o `id_veiculo` do novo veiculo via `ContratoVeiculo::buscarAtivo()`. Isso garante que receitas e despesas fiquem vinculadas ao veiculo correto em cada periodo. Ver [financeiro.md](financeiro.md#rastreabilidade-veicular) para detalhes.

Quando uma parcela do contrato e marcada como paga pelo resumo financeiro, o
mesmo fluxo processa a comissao do fornecedor investidor. O estorno da parcela
cancela a comissao ativa; se o repasse ja tiver sido pago, a despesa vinculada
volta para pendente. A duplicidade e impedida pelo `id_financeiro_origem`.

## Bloqueio (Pre-autorizacao no Cartao)

Reserva um valor no limite do cartao de credito do cliente sem efetuar cobranca.
Mesma mecanica usada nas locacoes — ver [gateways.md](gateways.md) para detalhes da interface.

### Tabela `contratos_bloqueios`

Estrutura identica a `locacoes_bloqueios`, mas com `id_contrato` em vez de `id_locacao`.

| Coluna | Tipo | Descricao |
|--------|------|-----------|
| id | INT PK | Primary key |
| chave | VARCHAR(45) | Tenant key |
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

1. Em contrato novo, a secao orienta o usuario a salvar antes de exibir cartao,
   valor e acao de criar bloqueio
2. Depois de salvo, o usuario seleciona cartao e valor na aba Financeiro do contrato
3. `POST /api/contratos/{id}/bloqueio/criar` → gateway cria PaymentIntent manual
4. Hold fica ativo (status=authorized) com expiracao em 7 dias
5. Cron `RotateAuthorizationHoldsJob` rotaciona holds 2 dias antes de expirar (cobre locacoes E contratos)
6. Ao capturar: cria lancamento financeiro (receita) com plano de contas 1.1.5.01
7. Ao liberar: cancela hold no gateway, limpa `id_bloqueio_ativo`

Ao excluir um contrato, todos os holds locais `pending` ou `authorized` devem
ter a liberacao confirmada no gateway. Uma falha bloqueia a exclusao e preserva
o contrato para nova tentativa. Holds ja liberados, expirados ou capturados
nao impedem a exclusao.

### Na Fatura PDF

A secao GARANTIAS aparece automaticamente na fatura do contrato quando existe bloqueio com valor > 0, mostrando descricao, status e valor.

## Padrao de Datas

Datas de inicio/fim, renovacao e devolucao de contrato sao horarios operacionais locais e devem ser exibidas com `format_operational_datetime()` / `DateHelper.formatOperationalDateTime()`, sem conversao de timezone. Caucao, bloqueio, documentos, mensagens e PDFs devem usar `DateHelper`/helpers globais conforme o tipo de dado (`format_datetime()` para instantes tecnicos, `format_operational_datetime()` para horarios operacionais). Evite `date()`, `time()`, `new DateTime()`, `new Date()` e `NOW()/CURDATE()` em regras de negocio, exibicao e filtros. Para queries, calcule a data no helper e passe como parametro sempre que a regra for tenant-scoped.
