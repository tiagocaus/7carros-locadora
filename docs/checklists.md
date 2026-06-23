# Checklist Digital

## Objetivo

O Checklist Digital permite que funcionários realizem vistorias de veículos pelo celular, substituindo formulários impressos. Suporta checklists vinculados (atrelados a locação/contrato) e avulsos (independentes), com questionário, fotos de vistoria e assinatura digital.

## Regras de Negócio

### Acesso e Permissões
- **Permissão**: `checklists.criar` — obrigatória para criar/editar checklists digitais
- **Plano**: Apenas planos **P3** (Avançado) e **P4** (Ilimitado)
- **Botão na listagem**: Visível sempre, habilitado apenas em **dispositivos móveis** + planos P3/P4
- **Login redirect**: Usuários sem `dashboard.visualizar` são redirecionados para `/checklists/digital` após login

### Tipos de Checklist
| Tipo | Código | Descrição |
|------|--------|-----------|
| Vinculado | `V` | Atrelado a locação ou contrato. Tem momento (saída/chegada). |
| Avulso | `A` | Independente. Sem vínculo com locação/contrato. Momento = N (não definido). |

### Momento (apenas Vinculado)
| Momento | Código | Descrição |
|---------|--------|-----------|
| Saída | `S` | Checklist de entrega do veículo ao cliente |
| Chegada | `C` | Checklist de devolução do veículo |

### Status
| Status | Significado |
|--------|-------------|
| `1` | Pendente (em preenchimento) |
| `2` | Finalizado (assinado) |

### Veículos em Vinculado
- **Locação**: Geralmente 1 veículo
- **Contrato**: Pode ter múltiplos veículos
- Reservas de locacao podem existir apenas por grupo/categoria (`id_veiculo = NULL`). Nesse caso, nao ha checklist vinculado ate a locadora alocar um veiculo especifico na saida.
- Veículos que já possuem checklist do mesmo momento ficam **desabilitados** (cinza + ✓) no select
- Após finalizar, se há mais veículos pendentes, oferece "Fazer checklist do próximo veículo"
- Cada checklist é 1 registro = 1 veículo + 1 momento

### Exclusão em Cascata
- Ao excluir uma **locação**: todos os checklists vinculados + fotos são apagados
- Ao excluir um **contrato**: todos os checklists vinculados + fotos são apagados
- Ao excluir um **checklist** diretamente: fotos de vistoria + assinatura são apagadas do storage

---

## Fluxo do Checklist (4 Abas)

### Aba 1 — Informações
| Campo | Obrigatório | Notas |
|-------|-------------|-------|
| Tipo | Sim | Toggle: Avulso / Vinculado |
| Momento | Sim (se vinculado) | Toggle: Saída / Chegada |
| Locação/Contrato | Sim (se vinculado) | Chosen-select server-side |
| Veículo | Sim | Vinculado: select dos veículos do vínculo. Avulso: chosen-select server-side |
| Modelo do checklist | Sim | Chosen-select client-side. Apenas `tipo=0` (digital) |
| Tanque | Sim | Escala 0-8. Labels dinâmicos via FuelLabels (elétrico = porcentagens) |
| Odômetro | Sim | Numérico com formatação milhar |
| Observações | Não | Texto livre |

**Ao avançar**: salva no BD via `POST /api/checklists/criar` (status=1)

### Aba 2 — Questões
- Questões carregadas do modelo selecionado (`checklist_modelos.questoes`)
- 4 opções por questão: **Confere** (verde), **Não confere** (vermelho), **Danificado** (amarelo), **N/A** (púrpura)
- **Todas** devem ser respondidas para avançar
- **Auto-save**: a cada 30 segundos via `setInterval`

### Aba 3 — Vistorias
- Itens de vistoria do modelo (`checklist_modelos.vistoria`)
- Foto tirada via câmera (`<input capture="environment">`)
- Foto enviada **imediatamente** ao servidor (resize client-side max 1200px → JPEG 85%)
- **Mínimo 1 foto** obrigatória para avançar
- Botões: câmera (tirar), caneta (abrir editor), lixeira (excluir)

#### Editor de Fotos (Canvas)
Após capturar uma foto, o usuário pode abrir o editor (ícone caneta) para anotar danos:

**Ferramentas disponíveis:**
| Ferramenta | Ícone | Descrição |
|------------|-------|-----------|
| Desenho livre | `fa-pen` | Traço com 8 cores (branco, preto, vermelho, azul, amarelo, verde, cinza, roxo) |
| Marcadores | `fa-map-marker-alt` | 6 tipos de dano: Amassado (vermelho), Falta (cinza), Quebrado (laranja), Riscado (azul), Trincado (verde), Outros (roxo) |
| Desfazer/Refazer | `fa-undo` / `fa-redo` | Histórico de ações (strokes + marcadores) |
| Zoom | `fa-search-plus` / `fa-search-minus` | Zoom via botões (0.5x–4x) + pinch-to-zoom no mobile |
| Limpar tudo | `fa-trash` | Remove todos traços e marcadores (com confirmação) |

**Arquitetura do editor:**
- Overlay fullscreen (`#photoEditor`, z-index 55) com canvas 2D
- Imagem original preservada em `img_original_url` (client-side) para re-edição na sessão
- Anotações (drawings + markers) armazenadas no `vistoriaState` do JS (não persistem no servidor)
- Ao salvar: composita imagem original + strokes + marcadores em canvas temporário → exporta JPEG 0.85 → re-upload via `uploadVistoria`
- Marcadores são renderizados como SVG pins com labels no overlay, e "baked" na imagem JPEG ao salvar
- Coordenadas armazenadas em espaço da imagem (resolution-independent)
- Lazy initialization: DOM do editor é inicializado na primeira abertura (`initEditorDOM` + `initEditorEvents`)

### Aba 4 — Assinatura
- Canvas HTML5 com suporte touch + mouse
- Suporta rotação do celular (canvas redimensiona mantendo conteúdo)
- Exporta como JPEG com fundo branco (evita fundo preto no PDF)
- **Ao salvar**: antes de finalizar, atualiza `veiculos.odometro` e `veiculos.tanque_fracao` somente para checklist avulso ou vinculado de chegada; vinculado de saída não altera o veículo. Depois o status muda para `2` (finalizado)

---

## Retomar Checklist Pendente

- URL: `/checklists/novo?retomar={id}`
- Carrega dados salvos e preenche aba Infor (tipo, momento, vínculo, veículo, modelo, tanque, odômetro)
- Posiciona na aba correta:
  - Questões incompletas → aba Questões
  - Todas questões respondidas, sem foto → aba Vistorias
  - Tudo preenchido → aba Assinatura

---

## Páginas Standalone Mobile

| Página | Rota | Descrição |
|--------|------|-----------|
| Listagem | `/checklists/digital` | Cards com borda colorida (azul=vinculado, marrom=avulso). Infinite scroll, busca, legenda. |
| Criar/Editar | `/checklists/novo` | 4 abas: Infor → Questões → Vistorias → Assinatura |
| Visualizar | `/checklists/visualizar/{id}` | Read-only. Avulso: fotos grandes. Vinculado: saída/chegada lado a lado. |

Todas são HTML standalone (não usam template de iframe do dashboard).

---

## Impressão (PDF)

### Avulso (`template.php`)
- Layout coluna única
- Fotos: detecta orientação — **landscape**: 2 por linha, **portrait**: 3 por linha
- Fotos sem `max-height` (tamanho grande para ver detalhes)

### Vinculado (`template-vinculado.php`)
- Layout duas colunas: esquerda = saída, direita = chegada
- 1 foto por linha em cada coluna
- Pareamento automático via `buscarPar()`: mesmo `id_locacao/id_contrato` + `id_veiculo` + momento oposto

### Busca para impressão
- Busca por FK (`id_locacao`/`id_contrato`) primeiro, fallback por `codigo` para registros legados

---

## Banco de Dados

### Tabela `checklist`
```sql
id                INT UNSIGNED PK AUTO_INCREMENT
chave             VARCHAR(45) NOT NULL          -- tenant
codigo            VARCHAR(50)                    -- código curto (CK + id 5 dígitos + 2 letras)
tipo              VARCHAR(1)                     -- V=vinculado, A=avulso
momento           VARCHAR(1)                     -- S=saída, C=chegada, N=não definido
id_locacao        INT UNSIGNED NULL FK           -- locacoes.id ON DELETE SET NULL
id_contrato       INT UNSIGNED NULL FK           -- contratos.id ON DELETE SET NULL
id_veiculo        INT UNSIGNED NULL              -- veículos.id
id_modelo         INT UNSIGNED NULL              -- checklist_modelos.id
tanque            VARCHAR(10) NULL               -- 0-8 (escala numérica)
odometro          INT UNSIGNED NULL
questoes          MEDIUMTEXT NULL                -- JSON respostas
vistoria          LONGTEXT NULL                  -- JSON itens com fotos
assinatura_unica  MEDIUMTEXT NULL                -- filename assinatura
obs_unica         MEDIUMTEXT NULL
data_checklist    DATETIME NULL                  -- data/hora finalização (backend, APP_TIMEZONE)
id_funcionario    INT UNSIGNED NULL
status            VARCHAR(3)                     -- 1=pendente, 2=finalizado
created_at        TIMESTAMP
updated_at        DATETIME
```

### Tabela `checklist_modelos`
```sql
id        INT UNSIGNED PK
chave     VARCHAR(45) NOT NULL
nome      VARCHAR(50)
tipo      INT(1) NOT NULL DEFAULT 0    -- 0=digital, 1=impresso
questoes  MEDIUMTEXT                    -- JSON template questões
vistoria  MEDIUMTEXT                    -- JSON template itens vistoria
status    VARCHAR(1)                    -- A=ativo, I=inativo
```

### Permissões
| Chave | Descrição |
|-------|-----------|
| `checklists.visualizar` | Visualizar e listar checklists |
| `checklists.criar` | Criar checklists digitais via mobile |
| `checklists.excluir` | Excluir checklists |

---

## API Endpoints

### Páginas (GET, retorna HTML)
| Rota | Método Controller | Descrição |
|------|-------------------|-----------|
| `/checklists/digital` | `viewDigital()` | Listagem mobile |
| `/checklists/novo` | `viewNovo()` | Criar checklist |
| `/checklists/visualizar/{id}` | `viewVisualizar()` | Visualizar checklist |

### API CRUD (JSON)
| Rota | Método | Descrição |
|------|--------|-----------|
| `POST /api/checklists/criar` | `criar()` | Cria checklist (aba Infor) |
| `POST /api/checklists/{id}/questoes` | `salvarQuestoes()` | Salva questionário |
| `POST /api/checklists/{id}/vistoria/upload` | `uploadVistoria()` | Upload foto vistoria |
| `POST /api/checklists/{id}/vistoria/{itemId}/excluir` | `excluirVistoria()` | Remove foto |
| `POST /api/checklists/{id}/assinar` | `assinar()` | Salva assinatura + finaliza |
| `GET /api/checklists/novo/{id}` | `show()` | Dados do checklist (para retomar) |

### API Busca (JSON)
| Rota | Descrição | Retorno |
|------|-----------|---------|
| `GET /api/checklists/buscar-locacoes?q=` | Locações ativas | `{id, codigo, cliente, id_veiculo, veiculo, text}` |
| `GET /api/checklists/buscar-contratos?q=` | Contratos ativos | `{id, codigo, cliente, id_veiculo, veiculo, text}` |
| `GET /api/checklists/buscar-veiculos?q=` | Veículos disponíveis | `{id, placa, modelo, marca, odometro, tipo_combustivel, text}` |
| `GET /api/checklists/buscar-vinculos?q=` | Locações + contratos combinados | `{id: "L-123", text: "[Locação] ...", id_veiculo, veiculo}` |
| `GET /api/checklists/veiculos-vinculo?tipo=L&id=123&momento=S` | Veículos de um vínculo | `{id_veiculo, placa, modelo, checklist_feito}` |

---

## Arquivos

| Arquivo | Descrição |
|---------|-----------|
| `app/Controllers/ChecklistNovoController.php` | Controller principal (criar, editar, APIs) |
| `app/Controllers/ChecklistsController.php` | Controller listagem iframe + impressão PDF |
| `app/Models/Checklist.php` | Model com queries e lógica de dados |
| `app/Models/ChecklistModelo.php` | Model dos templates de checklist |
| `app/Views/pages/checklists/novo.php` | Página standalone criação (4 abas) |
| `app/Views/pages/checklists/digital.php` | Página standalone listagem mobile |
| `app/Views/pages/checklists/visualizar.php` | Página standalone visualização |
| `app/Views/pages/checklists/index.php` | Listagem iframe (dashboard) |
| `app/Views/pages/checklists/imprimir/template.php` | Template PDF avulso |
| `app/Views/pages/checklists/imprimir/template-vinculado.php` | Template PDF vinculado |
| `app/Lang/*/modules/checklists.php` | Traduções (pt_BR, en_US, es_ES, pt_PT, it_IT) |

---

## Sessão e Segurança

- **Timeout**: 4 horas (`gc_maxlifetime` + `cookie_lifetime`)
- **Validação**: Apenas User-Agent (sem IP — celulares mudam de IP frequentemente)
- **CSRF**: Token via `<meta name="csrf-token">` + header `X-CSRF-TOKEN`
- **Multi-tenancy**: Filtro por `chave` em todas as queries
- **Intended URL**: Se sessão expirar, redireciona para login e volta à página após autenticação
