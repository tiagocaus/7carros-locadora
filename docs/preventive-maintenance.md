# Sistema de Manutenção Preventiva

O sistema de manutenção preventiva automatiza o controle de manutenções veiculares baseado em quilometragem. Ele monitora o odômetro dos veículos e gera Ordens de Serviço (OS) automaticamente quando uma manutenção se aproxima.

## Arquitetura

```
┌─────────────────────────┐     ┌─────────────────────────┐
│   Plano de Manutenção   │     │        Veículo          │
│   (manutencoes_plano)   │     │       (veiculos)        │
├─────────────────────────┤     ├─────────────────────────┤
│ id                      │◄────│ id_plano_manutencao     │
│ nome                    │     │ plano_manutencao_array  │
│ array (JSON intervalos) │     │ odometro                │
│ status                  │     └─────────────────────────┘
└─────────────────────────┘              │
         │                               │
         │         ┌─────────────────────┘
         ▼         ▼
┌─────────────────────────────────────────┐
│     CRON: CheckPreventiveMaintenanceJob │
├─────────────────────────────────────────┤
│ 1. Compara odômetro com próxima km      │
│ 2. Verifica margem de alerta (500 km)   │
│ 3. Gera OS se dentro da margem          │
│ 4. Atualiza próxima km no veículo       │
│ 5. Notifica usuários                    │
└─────────────────────────────────────────┘
                   │
                   ▼
         ┌─────────────────┐
         │   Manutenções   │
         │  (manutencoes)  │
         └─────────────────┘
```

## Conceitos-Chave

### Plano de Manutenção (Template)

Define **intervalos** em km para cada tipo de manutenção. Template reutilizável aplicável a múltiplos veículos.

```json
{
  "motor_oleo": "10.000",
  "motor_filtrooleo": "10.000",
  "motor_correiadentada": "60.000"
}
```

- Valores = **a cada quantos km** fazer a manutenção
- `"0"` = item desativado

### Plano do Veículo (Específico)

Cada veículo tem `plano_manutencao_array` com **km absoluta** de quando fazer cada manutenção.

```json
{
  "motor_oleo": "15.000",
  "motor_filtrooleo": "15.000",
  "motor_correiadentada": "65.000"
}
```

### Diferença Crítica

| Campo | Representa | Exemplo |
|-------|-----------|---------|
| `manutencoes_plano.array` | Intervalo (a cada X km) | `"10.000"` = a cada 10k |
| `veiculos.plano_manutencao_array` | Próxima km absoluta | `"20.000"` = fazer em 20k |

## Fluxo Operacional

### 1. Criação do Plano
1. Acessa **Manutenções > Planos > Adicionar**
2. Define nome e intervalos para cada item (26 disponíveis)
3. Sistema armazena JSON de intervalos

### 2. Vinculação ao Veículo
1. Ao cadastrar/editar veículo, seleciona um plano
2. Sistema calcula: `odômetro_atual + intervalo`
3. Armazena em `plano_manutencao_array`

### 3. Monitoramento (CRON)
O `CheckPreventiveMaintenanceJob` executa periodicamente:

1. Para cada veículo com plano vinculado
2. Calcula `km_próxima - odômetro_atual`
3. Se ≤ margem (500 km): gera OS e atualiza próxima km

### 4. Após OS Gerada
```
Antes:  {"motor_oleo": "20.000"}
Depois: {"motor_oleo": "30.000"}  (20k + 10k intervalo)
```

## Regras de Negócio

| Regra | Descrição |
|-------|-----------|
| R1 | **Margem**: OS gerada quando `diferença ≤ MANUTENCAO_MARGEM_KM` (padrão 500) |
| R2 | **Desativados**: Intervalo = 0 ignora o item |
| R3 | **Código OS**: `MA` + 5 dígitos + `id_filial` |
| R4 | **Status**: OS criada com status `C` |
| R5 | **Exclusão**: Plano com veículos vinculados não pode ser excluído |
| R6 | **Multi-tenancy**: Isolamento total por `chave` |

## Itens de Manutenção

### Motor (16 itens)
`motor_oleo`, `motor_filtrooleo`, `motor_correiadentada`, `motor_correiaalternador`, `motor_correiaarcondicionado`, `motor_correiabombadagua`, `motor_filtrodear`, `motor_filtrodecabine`, `motor_filtrodecombustivel`, `motor_fluidodofreio`, `motor_fluidoembreagem`, `motor_discodeembreagem`, `motor_fluidocaixademarcha`, `motor_limpesaarrefecimento`, `motor_vejas`, `motor_bateria`

### Rodagem (5 itens)
`rodagem_pneus`, `rodagem_alinhamento`, `rodagem_pastilhasdefreio`, `rodagem_discodefreios`, `rodagem_rodiziodepneus`

### Acessórios (1 item)
`acessorio_paletasparabrisa`

## Notificações

### Para Usuários do Tenant
- **Gatilho**: OS gerada
- **Destinatários**: usuários/funcionários internos com permissão `notificacoes.manutencoes_preventivas`
- **Canais**: Email, WhatsApp, SMS
- **Observação**: não envia notificações para clientes finais da locadora

### Para Administração
- **Gatilho**: Resumo diário centralizado dos CRONs
- **Destinatário**: `APP_COMPANY_EMAIL`
- **Conteúdo**: Seção de manutenção preventiva no resumo diário dos CRONs

## Configurações

```env
MANUTENCAO_MARGEM_KM=500         # Margem em km
APP_COMPANY_EMAIL=admin@x.com    # Email para resumo diario centralizado
```

## Exemplo Prático

**Cenário:** Veículo ABC-1234, odômetro 19.600 km, próximo óleo em 20.000 km

```
1. Diferença: 20.000 - 19.600 = 400 km
2. Margem: 400 ≤ 500? SIM → Pendente!
3. Gera OS código MA789451
4. Atualiza: 20.000 + 10.000 = 30.000 km
5. Notifica usuários
```

## Integração com Estoque

OS geradas (preventivas ou manuais) podem ter **itens vinculados a produtos do estoque**. Quando um item é adicionado/alterado/removido de uma OS:

- A baixa automática de estoque ocorre via `ManutencaoItem::ajustarEstoque()`, **não no CRON**
- Apenas produtos com `baixa_automatica = 'S'` são afetados
- O UPDATE é atômico para evitar race conditions

Ver documentação completa: [Estoque](estoque.md)

## Arquivos

| Tipo | Arquivo |
|------|---------|
| CRON | `app/Crons/Jobs/CheckPreventiveMaintenanceJob.php` |
| Model | `app/Models/ManutencaoPlano.php` |
| Controller | `app/Controllers/ManutencoesPlanosController.php` |
| View Lista | `app/Views/pages/manutencoes-planos/index.php` |
| View Form | `app/Views/pages/manutencoes-planos/adicionar.php` |
| Traduções | `app/Lang/{locale}/modules/manutencao.php` |
