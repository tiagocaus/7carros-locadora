# Migrations

## Database Schema

The system uses numbered migrations in `app/Database/migrations/`:
- Users table with API token support (`api_token`, `api_token_expires_at`)
- Multi-tenant isolation using `chave` (tenant identifier)
- Roles and permissions (many-to-many)
- User-branch associations (users assigned to branches)

## Database Migration Standards

**CRITICAL: Always follow the migration pattern below. NEVER use direct SQL in migrations unless absolutely necessary.**

### Migration Naming Convention
- Format: `XXXXX_description_of_change.php` where XXXXX is a sequential 5-digit number
- Numbers must be **strictly sequential** - check existing migrations to find the next available number
- **NEVER duplicate migration numbers** - this will break the migration system

**File Numbering:**
```bash
# Check the highest migration number before creating a new one
ls database/migrations/ | tail -1

# If last is 00035_*, your new migration should be 00036_*
```

### ✅ CORRECT PATTERN - Use Migration Fluent API

```php
<?php

use App\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // CREATE TABLE - use $this->create()
        $this->create('table_name', function ($table) {
            // Primary key
            $table->id(); // BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY

            // Multi-tenancy (for tenant-scoped tables)
            $table->string('chave', 45);

            // Column types
            $table->string('name'); // VARCHAR(255)
            $table->string('email', 100); // VARCHAR(100)
            $table->text('description')->nullable();
            $table->integer('count');
            $table->bigInteger('user_id')->unsigned();
            $table->float('price');
            $table->decimal('amount', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->date('birth_date')->nullable();
            $table->datetime('event_at');
            $table->time('start_time')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->json('metadata')->nullable();

            // Timestamps (created_at, updated_at)
            $table->timestamps();

            // Indexes
            $table->index('chave');
            $table->index('email');
            $table->index(['chave', 'status']); // Composite index
            $table->unique('email'); // Unique constraint
            $table->unique(['veiculo_id', 'cliente_id']); // Composite unique

            // Foreign keys (chave has NO foreign key - it's the root tenant identifier)
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete() // ON DELETE SET NULL
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        $this->drop('table_name');
    }
};
```

### ALTER TABLE - Adding columns/indexes

```php
public function up(): void
{
    $this->table('existing_table', function ($table) {
        // Add columns
        $table->addColumn('new_column', 'string', ['length' => 255, 'null' => true]);
        $table->addColumn('count', 'integer', ['null' => false, 'default' => 0]);
        $table->addColumn('status', 'string', ['length' => 50, 'null' => true, 'after' => 'name']);

        // Add indexes
        $table->addIndex('new_column');
        $table->addIndex(['col1', 'col2'], ['unique' => true]);
    });
}

public function down(): void
{
    $this->table('existing_table', function ($table) {
        $table->dropColumn('new_column');
        $table->dropColumn('count');
        $table->dropColumn('status');
    });
}
```

### ❌ WRONG PATTERN - Direct SQL (DO NOT USE)

```php
// ❌ NEVER DO THIS
public function up(): void
{
    $sql = "CREATE TABLE users (...)";
    $this->pdo->exec($sql);
}

// ❌ NEVER DO THIS
public function up(): void
{
    $this->pdo->exec("ALTER TABLE users ADD COLUMN name VARCHAR(255)");
}

// ❌ NEVER DO THIS
public function up(): void
{
    $result = $this->pdo->query("SHOW COLUMNS FROM table LIKE 'column'")->fetch();
    if ($result) {
        $this->pdo->exec("ALTER TABLE table DROP COLUMN column");
    }
}
```

### 🚫 ABSOLUTE PROHIBITION

**NEVER use `$this->pdo->exec()` or `$this->pdo->` in migrations.** 

The Migration class provides helper methods for all operations:
- Use `$this->table()` for ALTER TABLE operations
- Use `$this->create()` for CREATE TABLE operations
- Use `$this->db()` to get QueryBuilder instance for data operations (UPDATE, INSERT, etc.)

**NEVER do this:**
```php
// ❌ NEVER USE
$this->pdo->exec("ALTER TABLE users ADD COLUMN name VARCHAR(255)");
$this->pdo->exec("UPDATE users SET status = 'active'");
```

**Always use the Migration methods:**
```php
// ✅ CORRECT - Use $this->table() for schema changes
$this->table('users', function ($table) {
    $table->string('name')->nullable();
});

// ✅ CORRECT - Use $this->db() for data operations (QueryBuilder)

// SELECT
$result = $this->db()
    ->table('users')
    ->select(['id', 'name'])
    ->whereRaw('status = ?', ['active'])
    ->get();

// INSERT
$this->db()->table('users')->insert([
    'name' => 'João',
    'status' => 'active'
]);

// UPDATE
$this->db()
    ->table('users')
    ->whereRaw('id = ?', [1])
    ->update(['status' => 'active']);

// DELETE
$this->db()
    ->table('users')
    ->whereRaw('id = ?', [1])
    ->delete();

// Verificar existência antes de inserir
$exists = $this->db()
    ->table('users')
    ->select(['id'])
    ->whereRaw('email = ?', ['test@example.com'])
    ->first();

if (!$exists) {
    $this->db()->table('users')->insert(['email' => 'test@example.com']);
}
```

### Available Schema Methods

**Column Types:**
- `id()` - BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `string(name, length = 255)` - VARCHAR
- `text(name)` - TEXT
- `longText(name)` - LONGTEXT
- `integer(name)` - INT
- `bigInteger(name)` - BIGINT
- `float(name)` - FLOAT
- `double(name, total = 8, places = 2)` - DOUBLE
- `decimal(name, total = 8, places = 2)` - DECIMAL
- `boolean(name)` - TINYINT(1)
- `date(name)` - DATE
- `datetime(name)` - DATETIME
- `time(name)` - TIME
- `timestamp(name)` - TIMESTAMP
- `timestamps()` - created_at + updated_at
- `enum(name, [values])` - ENUM
- `json(name)` - JSON

**Column Modifiers:**
- `->unsigned()` - Make column UNSIGNED
- `->nullable()` - Allow NULL
- `->default(value)` - Set default value
- `->unique()` - Add UNIQUE constraint

**Indexes:**
- `index(column)` - Single column index
- `index([col1, col2])` - Composite index
- `unique(column)` - Unique constraint
- `unique([col1, col2])` - Composite unique

**Foreign Keys:**
- `foreign(column)->references(column)->on(table)->cascadeOnDelete()->cascadeOnUpdate()`
- `->nullOnDelete()` - ON DELETE SET NULL
- `->onDelete('action')` - Custom action (CASCADE, SET NULL, RESTRICT)
- `->onUpdate('action')` - Custom action

**Table Operations:**
- `drop(table)` - Drop a table
- `renameTable(oldName, newName)` - Rename a table

**ALTER TABLE Methods:**
- `addColumn(name, type, [options])` - Add column
- `addIndex(column, [options])` - Add index
- `dropColumn(name)` - Remove column
- `dropIndex(indexName)` - Remove index
- `dropForeign(fkName)` - Remove foreign key by constraint name
- `dropForeignKey(nameOrColumn)` - Remove FK by constraint name OR column name (auto-detects `fk_{table}_{column}` pattern)
- `renameColumn(table, oldName, newName, type, [options])` - Rename column (requires type)
- `renameColumnPreservingType(table, oldName, newName)` - Rename column preserving original type and properties
- `copyColumnData(table, sourceColumn, targetColumn, [where])` - Copy data from one column to another

### Idempotent Migration Helpers

These methods make migrations idempotent (can run multiple times without errors):

**Verification Methods:**
- `tableExists(table): bool` - Check if table exists
- `columnExists(table, column): bool` - Check if column exists
- `indexExists(table, index): bool` - Check if index exists
- `foreignKeyExists(table, constraintName): bool` - Check if foreign key exists
- `columnIsUnsigned(table, column): bool` - Check if column is UNSIGNED

**Conditional Operations:**
- `dropColumnIfExists(table, column)` - Drop column only if it exists
- `dropIndexIfExists(table, index)` - Drop index only if it exists
- `dropForeignKeyIfExists(table, constraintName)` - Drop FK only if it exists
- `addColumnIfNotExists(table, column, type, [options])` - Add column only if it doesn't exist
- `addIndexIfNotExists(table, column, [options])` - Add index only if it doesn't exist
- `addForeignKeyIfNotExists(table, column, references, on, constraintName, [options])` - Add FK only if it doesn't exist
- `modifyColumn(table, column, type, [options])` - Modify column type/properties

**Renaming Columns:**

```php
public function up(): void
{
    // Rename column preserving its type and properties automatically
    if ($this->columnExists('matriz_filial', 'empresa')) {
        $this->renameColumnPreservingType('matriz_filial', 'empresa', 'razao_social');
    }
}

public function down(): void
{
    // Revert: rename back
    if ($this->columnExists('matriz_filial', 'razao_social')) {
        $this->renameColumnPreservingType('matriz_filial', 'razao_social', 'empresa');
    }
}
```

**Copying Data Between Columns:**

```php
public function up(): void
{
    // 1. Rename column
    if ($this->columnExists('matriz_filial', 'empresa')) {
        $this->renameColumnPreservingType('matriz_filial', 'empresa', 'razao_social');
    }

    // 2. Add new column
    if (!$this->columnExists('matriz_filial', 'nome_fantasia')) {
        $this->addColumn('matriz_filial', 'nome_fantasia', 'VARCHAR(255)', [
            'null' => true,
            'after' => 'razao_social'
        ]);
    }

    // 3. Copy data from razao_social to nome_fantasia (preserving existing records)
    if ($this->columnExists('matriz_filial', 'razao_social') &&
        $this->columnExists('matriz_filial', 'nome_fantasia')) {
        // Use copyColumnData with WHERE condition
        $this->copyColumnData(
            'matriz_filial',
            'razao_social',
            'nome_fantasia',
            "(`nome_fantasia` IS NULL OR `nome_fantasia` = '') AND `razao_social` IS NOT NULL AND `razao_social` != ''"
        );

        // Or copy all rows without condition:
        // $this->copyColumnData('matriz_filial', 'razao_social', 'nome_fantasia');
    }
}
```

**Idempotent Migration Example:**

```php
public function up(): void
{
    // Drop old columns if they exist
    $this->dropIndexIfExists('users', 'idx_old_email');
    $this->dropColumnIfExists('users', 'old_email');

    // Add new column only if it doesn't exist
    $this->addColumnIfNotExists('users', 'new_email', 'VARCHAR', [
        'length' => 255,
        'null' => true,
        'after' => 'name'
    ]);

    // Ensure column is UNSIGNED (if already existed)
    if ($this->columnExists('users', 'user_id')
        && !$this->columnIsUnsigned('users', 'user_id')) {
        $this->modifyColumn('users', 'user_id', 'BIGINT', [
            'unsigned' => true,
            'null' => false
        ]);
    }

    // Add index only if it doesn't exist
    $this->addIndexIfNotExists('users', 'new_email');

    // Add foreign key only if it doesn't exist
    $this->addForeignKeyIfNotExists(
        table: 'posts',
        column: 'author_id',
        references: 'id',
        on: 'users',
        constraintName: 'fk_posts_author',
        options: [
            'onDelete' => 'CASCADE',
            'onUpdate' => 'CASCADE'
        ]
    );
}

public function down(): void
{
    $this->dropForeignKeyIfExists('posts', 'fk_posts_author');
    $this->dropColumnIfExists('users', 'new_email');

    // Restore old column if needed
    $this->addColumnIfNotExists('users', 'old_email', 'VARCHAR', [
        'length' => 100,
        'null' => true
    ]);
}
```

**Benefits of Idempotent Migrations:**
- Can safely re-run migrations without errors
- Easier to debug and test
- Safer for production deployments
- Handles edge cases where migration was partially applied

## Creating New Models

Models should extend `App\Core\Model` and define:
- `protected static string $table` - table name
- `protected array $fillable` - mass assignable fields
- `protected array $casts` - type casting (integer, boolean, datetime, etc.)

## Multi-tenant Data Isolation

**IMPORTANT: This system is multi-tenant.** Each rental company (tenant) has isolated data using a unique `chave` identifier.

### For models that belong to a specific tenant (rental company), you MUST:

1. Add `chave` column to the migration:
```php
$table->string('chave', 45);
$table->index('chave');
// NOTE: chave has NO foreign key - it's the root tenant identifier stored in session
```

2. Use the `TenantScoped` trait in the model:
```php
use App\Core\Model;
use App\Core\TenantScoped;

class Cliente extends Model
{
    use TenantScoped;

    protected static string $table = 'clientes';

    protected array $fillable = [
        'chave',  // Always include chave in fillable
        'nome_rsocial',
        'cpf_cnpj',
        'email',
        // ... other fields
    ];

    protected array $casts = [
        'chave' => 'string',
        // ... other casts
    ];
}
```

### What TenantScoped does:
- Automatically adds `WHERE chave = {currentChave()}` to all queries
- Auto-fills `chave` when creating/saving records
- Prevents data leakage between tenants (rental companies)

### Models that should NOT use TenantScoped:
- `User` - users can work for multiple rental companies
- `Role` - roles are global (but user_roles may have chave)
- `Permission` - permissions are global
- Any shared/public configuration tables

### Bypassing tenant scope (admin operations only):
```php
Cliente::withoutTenantScope(function() {
    return Cliente::all(); // Returns customers from ALL rental companies
});
```

### Using TenantScoped in Controllers

**IMPORTANT:** When using models with `TenantScoped`, you do NOT need to manually verify `chave`.

**❌ WRONG - Manual chave verification (unnecessary):**
```php
public function destroy(int $id): Response
{
    $currentChave = currentChave();

    $veiculo = Veiculo::find($id);

    // ❌ Redundant - TenantScoped already filtered by chave
    if (!$veiculo || $veiculo['chave'] != $currentChave) {
        return redirect('/veiculos')->with('error', 'Veículo não encontrado.');
    }

    $veiculo->delete();
}
```

**✅ CORRECT - Trust TenantScoped:**
```php
public function destroy(Request $request, int $id): Response
{
    $veiculo = Veiculo::find($id);  // Automatically filters by chave

    if (!$veiculo) {  // Returns null if not found OR doesn't belong to tenant
        return redirect('/veiculos')
            ->with('error', 'Veículo não encontrado.');
    }

    $veiculo->delete();

    return redirect('/veiculos')
        ->with('success', 'Veículo excluído com sucesso!');
}
```

**How it works:**
1. `Veiculo::find($id)` internally calls `WHERE id = $id AND chave = {currentChave()}`
2. If record doesn't exist OR doesn't belong to current tenant → returns `null`
3. Single null check handles both cases: "not found" and "doesn't belong to rental company"

## Validation

Use `$this->validate($request, $rules)` in controllers. Available rules:
- `required`, `email`, `min:X`, `max:X`, `numeric`, `integer`, `url`, `alpha`, `alphanumeric`
- `in:val1,val2`, `confirmed`, `unique:table,column,exceptId`, `exists:table,column`
- `date`, `before:date`, `after:date`

Errors are automatically flashed to session and available in views as `$errors`.
