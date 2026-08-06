# Padrões de Banco de Dados

## Verificacao Obrigatoria do Schema Antes de Alterar Codigo

Toda alteracao que crie ou modifique Models, consultas, migrations, relatorios,
gateways ou integracoes com dados deve comecar pela inspecao do banco local via
terminal. O banco configurado em `.env.development` deve usar
`DB_HOST=localhost`.

Nao deduza nomes de tabelas ou colunas pelo rotulo de um formulario, por outro
Model ou por convencao. O schema executado e a fonte de verdade para a estrutura
existente.

Checklist obrigatorio:

1. Identifique todas as tabelas lidas ou gravadas pelo fluxo.
2. Conecte ao MySQL local pelo terminal.
3. Execute `DESCRIBE`, `SHOW COLUMNS` e, quando relevante, `SHOW INDEX` em cada tabela afetada.
4. Confirme nome, tipo, tamanho, nulabilidade, indices e relacionamentos das colunas usadas.
5. Execute no localhost o `SELECT` ou operacao equivalente que sera implementada.
6. Somente depois altere o codigo.
7. Se localhost, migrations e producao divergirem, interrompa a implementacao e investigue a origem da divergencia antes de decidir a correcao.

Exemplo:

```sql
DESCRIBE matrizes_filiais;
SHOW COLUMNS FROM matrizes_filiais;
SHOW INDEX FROM matrizes_filiais;

SELECT rua, num, bairro, cidade, estado, cep
FROM matrizes_filiais
LIMIT 1;
```

O nome exibido ao usuario pode ser "Numero", mas isso nao define o nome da
coluna. No schema atual de `matrizes_filiais`, por exemplo, a coluna e `num`, e
nao `numero`.

O arquivo `temp-bd.txt` e reservado para diagnostico read-only de producao
quando a verificacao do ambiente publicado for realmente necessaria. Ele nao
substitui a inspecao primaria do localhost. Nunca copie suas credenciais para
codigo, documentacao, comandos versionados, logs ou respostas.

## Configuração Geral

### Charset e Collation

**Sempre use utf8mb4 e utf8mb4_unicode_ci:**

```sql
CREATE DATABASE 7carros_locadora
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
```

**Por quê utf8mb4?**
- Suporta emojis e caracteres especiais
- Compatível com UTF-8 completo (4 bytes)
- Padrão moderno do MySQL

### Credenciais de Acesso

```bash
# MySQL CLI
mysql -u7carros_locadora -pwCz5Ex9jQ0Xped7 7carros_locadora

# Backup
mysqldump -u7carros_locadora -pwCz5Ex9jQ0Xped7 7carros_locadora > backup.sql

# Restore
mysql -u7carros_locadora -pwCz5Ex9jQ0Xped7 7carros_locadora < backup.sql
```

### Padrao de DEFINER em Producao

Objetos MySQL executaveis, como triggers, views, routines e events, devem usar
um `DEFINER` estavel da aplicacao. Em producao, o padrao obrigatorio e:

```sql
`7carros_locador`@`localhost`
```

Nunca crie ou mantenha objetos de banco com `DEFINER` de usuario pessoal, IP
externo, wildcard `%` ou usuario inexistente. Exemplos proibidos:

```sql
`7carros_tiago`@`148.69.54.134`
`7carros_locador`@`%`
```

Para criar ou recriar triggers, views, routines ou events em producao, conecte
no MySQL como o usuario da aplicacao (`7carros_locador@localhost`) e execute o
DDL a partir dessa sessao. O MySQL gravara o `DEFINER` correto automaticamente
quando a instrucao nao declarar `CREATE DEFINER=...`.

Antes e depois de qualquer ajuste operacional, valide os definers pelo
`information_schema`:

```sql
SELECT 'TRIGGER' AS tipo, TRIGGER_SCHEMA AS schema_nome, TRIGGER_NAME AS objeto, DEFINER
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = DATABASE()
UNION ALL
SELECT 'VIEW' AS tipo, TABLE_SCHEMA AS schema_nome, TABLE_NAME AS objeto, DEFINER
FROM information_schema.VIEWS
WHERE TABLE_SCHEMA = DATABASE()
UNION ALL
SELECT 'ROUTINE' AS tipo, ROUTINE_SCHEMA AS schema_nome, ROUTINE_NAME AS objeto, DEFINER
FROM information_schema.ROUTINES
WHERE ROUTINE_SCHEMA = DATABASE()
UNION ALL
SELECT 'EVENT' AS tipo, EVENT_SCHEMA AS schema_nome, EVENT_NAME AS objeto, DEFINER
FROM information_schema.EVENTS
WHERE EVENT_SCHEMA = DATABASE();
```

Se algum objeto estiver com `DEFINER` invalido, nao corrija criando o usuario
antigo. Recrie o objeto conectado como `7carros_locador@localhost`. Para os
triggers de `financeiro_itens` e `manutencoes_itens`, use as definicoes mais
recentes nas migrations correspondentes ou o script operacional
`scripts/fix-production-financeiro-definers.sql`.

Execucao do script operacional em producao:

```bash
mysql -u7carros_locador -p 7carros_locador < scripts/fix-production-financeiro-definers.sql
```

## Convenções de Nomenclatura

### Tabelas

- **snake_case** em minúsculas
- **Plural** para tabelas de entidades
- Nomes descritivos em português

```sql
-- ✅ BOM
clientes
veiculos
reservas
contratos_reserva

-- ❌ EVITAR
Cliente
tbl_cliente
client
```

### Colunas

- **snake_case** em minúsculas
- Nomes descritivos em português
- Sufixos para tipos específicos:
  - `_id` para foreign keys
  - `_at` para timestamps
  - `_data` para datas
  - `_valor` para valores monetários

```sql
-- ✅ BOM
nome_rsocial
cpf_cnpj
data_nascimento
created_at
cliente_id
valor_diaria

-- ❌ EVITAR
NomeRSocial
cpfCnpj
dt_nasc
```

### Índices

- Prefixo `idx_` para índices normais
- Prefixo `uniq_` para índices únicos
- Nome descritivo das colunas

```sql
INDEX idx_cpf_cnpj (cpf_cnpj)
INDEX idx_chave_situacao (chave, situacao)
UNIQUE INDEX uniq_email (email)
```

## Schema Multi-tenant

### Contatos de matrizes e filiais

Os contatos de matrizes/filiais usam exclusivamente as tabelas normalizadas:

- `contatos_emails`, com `entidade_tipo = 'matriz_filial'`;
- `contatos_telefones`, com `entidade_tipo = 'matriz_filial'`.

O email e o telefone de apresentacao sao os registros marcados com
`principal = 'S'`. Para WhatsApp e SMS, use as flags do canal e os metodos de
`ContatoTelefone`; para destinatarios de email, use `ContatoEmail::listarParaEnvio()`.

As colunas `matrizes_filiais.email`, `matrizes_filiais.fixo` e
`matrizes_filiais.celular` foram removidas pela migration `00417`. A Model
`MatrizFilial` entrega os aliases canonicos `email`, `telefone` e `whatsapp`
a partir das tabelas de contatos; nao recrie campos diretos para esses dados.

### Coluna `chave` (Obrigatória)

**TODAS as tabelas de dados de tenant DEVEM ter a coluna `chave`:**

```sql
CREATE TABLE clientes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    chave VARCHAR(45) NOT NULL,
    nome_rsocial VARCHAR(255) NOT NULL,
    cpf_cnpj VARCHAR(18),

    -- Sempre indexar chave
    INDEX idx_chave (chave),
    INDEX idx_chave_cpf (chave, cpf_cnpj)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Exceções (tabelas sem `chave`):**
- Tabelas compartilhadas: `cidades`, `estados`, `paises`
- Tabelas de configuração global
- Tabelas de logs centralizados (opcional)

### Padrões de Indexação Multi-tenant

```sql
-- SEMPRE criar índices compostos começando com chave
INDEX idx_chave_situacao (chave, situacao)
INDEX idx_chave_data (chave, data_criacao)
INDEX idx_chave_cliente (chave, cliente_id)

-- Para buscas por texto
INDEX idx_chave_nome (chave, nome_rsocial)
```

## Estrutura de Tabela Padrão

### Template Base

```sql
CREATE TABLE nome_tabela (
    -- Primary Key
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,

    -- Multi-tenancy (obrigatório)
    chave VARCHAR(45) NOT NULL,

    -- Campos específicos da entidade
    campo1 VARCHAR(255),
    campo2 TEXT,
    campo3 DECIMAL(10,2),

    -- Foreign keys
    entidade_relacionada_id INT UNSIGNED,

    -- Campos de controle
    situacao CHAR(1) DEFAULT 'A' COMMENT 'A=Ativo, I=Inativo, E=Excluído',

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Índices
    INDEX idx_chave (chave),
    INDEX idx_chave_situacao (chave, situacao),
    INDEX idx_chave_created (chave, created_at),

    -- Foreign key constraints
    FOREIGN KEY (entidade_relacionada_id)
        REFERENCES outra_tabela(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Tipos de Dados Recomendados

### Identificadores

```sql
-- Primary keys
id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT

-- Foreign keys
cliente_id INT UNSIGNED
```

### Strings

```sql
-- Textos curtos (nomes, emails)
nome VARCHAR(255)
email VARCHAR(255)

-- Textos médios (descrições)
descricao TEXT

-- Textos longos (observações, notas)
observacoes LONGTEXT

-- Códigos fixos (CPF, placa)
cpf_cnpj VARCHAR(18)
placa_veiculo VARCHAR(10)

-- Enums simples (use CHAR para performance)
situacao CHAR(1) DEFAULT 'A'
tipo CHAR(1)
```

### Números

```sql
-- Inteiros
quantidade INT
idade TINYINT UNSIGNED

-- Decimais (valores monetários)
valor_total DECIMAL(10,2)
valor_diaria DECIMAL(8,2)

-- Percentuais
desconto_percentual DECIMAL(5,2)
```

### Datas e Timestamps

```sql
-- Datas
data_nascimento DATE
data_inicio DATE
data_fim DATE

-- Timestamps automáticos
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
```

### Booleanos

```sql
-- Usar TINYINT(1) ou BOOLEAN
ativo BOOLEAN DEFAULT TRUE
enviado TINYINT(1) DEFAULT 0

-- Ou CHAR(1) para compatibilidade
ativo CHAR(1) DEFAULT 'S' COMMENT 'S=Sim, N=Não'
```

## Exclusão de Registros

> **Importante:** o projeto **não usa soft-delete**. Para excluir um registro, use `DELETE` direto (ex: `$qb->delete()`). Não criar coluna `deleted_at` em novas tabelas.

A migration `00332_drop_deleted_at_columns.php` removeu `deleted_at` de
`configuracoes`, `funcionarios`, `funcionarios_roles` e `matrizes_filiais`.
Nao use filtros como `deleted_at IS NULL` ao consultar essas tabelas; isso causa
erro de coluna inexistente.

```php
// Exclusão direta — sem coluna deleted_at
$qb->table('clientes')->where('id', '=', $id)->delete();
```

## Foreign Keys

### Estratégias de Integridade

```sql
-- RESTRICT: Impede exclusão se houver registros relacionados (padrão recomendado)
FOREIGN KEY (cliente_id) REFERENCES clientes(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE

-- CASCADE: Exclui registros relacionados em cascata (use com cuidado!)
FOREIGN KEY (reserva_id) REFERENCES reservas(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE

-- SET NULL: Define NULL ao excluir o registro pai
FOREIGN KEY (veiculo_id) REFERENCES veiculos(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
```

**Recomendação:** Use `RESTRICT` por padrão e implemente lógica de exclusão no código.

## Exemplos de Tabelas Comuns

### Tabela de Clientes

```sql
CREATE TABLE clientes (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    chave VARCHAR(45) NOT NULL,

    -- Identificação
    nome_rsocial VARCHAR(255) NOT NULL,
    nome_fantasia VARCHAR(255),
    tipo_pessoa CHAR(1) NOT NULL COMMENT 'F=Física, J=Jurídica',
    cpf_cnpj VARCHAR(18) NOT NULL,
    rg_ie VARCHAR(20),

    -- Contato
    email VARCHAR(255),
    telefone VARCHAR(20),
    celular VARCHAR(20),

    -- Endereço
    cep VARCHAR(10),
    logradouro VARCHAR(255),
    numero VARCHAR(10),
    complemento VARCHAR(100),
    bairro VARCHAR(100),
    cidade VARCHAR(100),
    estado CHAR(2),

    -- Controle
    situacao CHAR(1) DEFAULT 'A' COMMENT 'A=Ativo, I=Inativo, B=Bloqueado',
    observacoes TEXT,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Índices
    INDEX idx_chave (chave),
    INDEX idx_chave_situacao (chave, situacao),
    INDEX idx_chave_cpf (chave, cpf_cnpj),
    INDEX idx_chave_nome (chave, nome_rsocial),
    INDEX idx_email (email),

    UNIQUE INDEX uniq_chave_cpf (chave, cpf_cnpj)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Tabela de Veículos

```sql
CREATE TABLE veiculos (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    chave VARCHAR(45) NOT NULL,

    -- Identificação
    placa VARCHAR(10) NOT NULL,
    renavam VARCHAR(20),
    chassi VARCHAR(20),

    -- Especificações
    marca VARCHAR(100),
    modelo VARCHAR(100),
    ano_fabricacao YEAR,
    ano_modelo YEAR,
    cor VARCHAR(50),
    combustivel VARCHAR(20),
    cambio VARCHAR(20),

    -- Valores
    valor_diaria DECIMAL(8,2),
    valor_fipe DECIMAL(10,2),
    km_atual INT,

    -- Controle
    situacao CHAR(1) DEFAULT 'D' COMMENT 'D=Disponível, R=Reservado, L=Locado, M=Manutenção, I=Inativo',
    observacoes TEXT,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Índices
    INDEX idx_chave (chave),
    INDEX idx_chave_situacao (chave, situacao),
    INDEX idx_chave_placa (chave, placa),

    UNIQUE INDEX uniq_placa (placa)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Tabela de Reservas

```sql
CREATE TABLE reservas (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    chave VARCHAR(45) NOT NULL,

    -- Relacionamentos
    cliente_id INT UNSIGNED NOT NULL,
    veiculo_id INT UNSIGNED NOT NULL,
    filial_id INT UNSIGNED,

    -- Período
    data_inicio DATE NOT NULL,
    data_fim DATE NOT NULL,
    hora_inicio TIME,
    hora_fim TIME,

    -- Valores
    valor_diaria DECIMAL(8,2) NOT NULL,
    quantidade_dias INT NOT NULL,
    valor_total DECIMAL(10,2) NOT NULL,
    valor_desconto DECIMAL(10,2) DEFAULT 0,
    valor_final DECIMAL(10,2) NOT NULL,

    -- Status
    situacao CHAR(1) DEFAULT 'P' COMMENT 'P=Pendente, C=Confirmada, A=Ativa, F=Finalizada, X=Cancelada',

    -- Observações
    observacoes TEXT,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Índices
    INDEX idx_chave (chave),
    INDEX idx_chave_situacao (chave, situacao),
    INDEX idx_chave_cliente (chave, cliente_id),
    INDEX idx_chave_veiculo (chave, veiculo_id),
    INDEX idx_chave_data_inicio (chave, data_inicio),
    INDEX idx_chave_data_fim (chave, data_fim),

    -- Foreign keys
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (veiculo_id) REFERENCES veiculos(id) ON DELETE RESTRICT ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Otimização de Queries

### Índices Compostos

**Ordem importa! Coloque colunas mais seletivas primeiro após `chave`:**

```sql
-- ✅ BOM: chave + campo mais seletivo
INDEX idx_chave_cpf (chave, cpf_cnpj)
INDEX idx_chave_email (chave, email)

-- ✅ Para ordenação
INDEX idx_chave_nome (chave, nome_rsocial)

-- ✅ Para range queries
INDEX idx_chave_data (chave, created_at)
```

### EXPLAIN para Análise

```sql
EXPLAIN SELECT * FROM clientes
WHERE chave = 'ABC123'
AND situacao = 'A'
ORDER BY nome_rsocial;

-- Verificar:
-- - type = ref (ideal) ou index
-- - key = nome do índice sendo usado
-- - rows = quantidade de linhas examinadas
```

## Backup e Restore

### Backup Completo

```bash
# Dump completo do banco
mysqldump -u7carros_locadora -pwCz5Ex9jQ0Xped7 7carros_locadora > backup_$(date +%Y%m%d_%H%M%S).sql

# Com compressão
mysqldump -u7carros_locadora -pwCz5Ex9jQ0Xped7 7carros_locadora | gzip > backup_$(date +%Y%m%d_%H%M%S).sql.gz
```

### Backup de Tabela Específica

```bash
mysqldump -u7carros_locadora -pwCz5Ex9jQ0Xped7 7carros_locadora clientes veiculos > backup_core.sql
```

### Restore

```bash
# Restore completo
mysql -u7carros_locadora -pwCz5Ex9jQ0Xped7 7carros_locadora < backup.sql

# Restore de gzip
gunzip < backup.sql.gz | mysql -u7carros_locadora -pwCz5Ex9jQ0Xped7 7carros_locadora
```

## Migrations

Para criar e gerenciar migrations, veja a documentação completa em **[migrations.md](./migrations.md)**.

**Executar migrations:**
```bash
php migrate.php
```

## Documentação Relacionada

- **[QueryBuilder](./querybuilder.md)** - Camada de abstração de queries
- **[Multi-tenancy](./multi-tenancy.md)** - Isolamento de dados por tenant
- **[Migrations](./migrations.md)** - Gerenciamento de schema
- **[Best Practices](./best-practices.md)** - Guidelines de segurança
