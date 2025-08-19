# Laravel ETL Package

### A package for orchestrating ETL migrations with Laravel.

## Introduction

This Laravel package, **Redesign ETL**, simplifies the process of migrating data from a "legacy" database to a new one. It gives you all the tools you need to manage complex Extract, Transform, and Load (ETL) migrations efficiently.

**Features:**

-   **Artisan Commands:** Run your migrations with easy-to-use commands (`redesign:migrate`, `redesign:seed`, `redesign:verify`, `redesign:update`).
-   **Data Processors:** An API of processors to transform your data rows during migration.
-   **Flexible Seeders:** A system for seeding new tables that don't have a direct equivalent in the legacy database.
-   **Data Verifiers:** Components to verify and automatically update data changes in your new database.

---

## Installation

Install the package via Composer:

`composer require redesign/etl`

And migrate to get the table `migration_tracker`:
`php artisan migrate`

### Publish Configuration

Run the following Artisan command to publish the configuration file. This will create a `config/redesign.php` file, which you can customize.

`php artisan vendor:publish --provider="Redesign\ETL\Providers\ETLServiceProvider" --tag=redesign-config`

### Configure Your Database Connections

First, add your legacy database connection details to your `.env` file. For example:

```dotenv
OLD_DB_CONNECTION=mysql
OLD_DB_HOST=127.0.0.1
OLD_DB_PORT=3306
OLD_DB_DATABASE=legacy
OLD_DB_USERNAME=root
OLD_DB_PASSWORD=secret
```

Then, configure the package in `config/redesign.php`. Map your old and new tables and specify the classes you'll use for processing, seeding, and verifying.

```php
// config/redesign.php
return [
    'tables' => [
        // 'old_table_name' => 'new_table_name'
    ],

    'processors' => [
        // 'new_table_name' => ['class' => Processor::class, 'args' => []],
    ],

    'seeders' => [
        // 'new_table_name' => ['class' => Seeder::class, 'args' => []],
    ],

    'verifiers' => [
        // 'new_table_name' => ['class' => Verifier::class, 'args' => []],
    ]
];
```

---

## Usage Tutorial

### Processors, Seeders, and Verifiers

The core of this package is its components. Whether you're using a processor, seeder, or verifier, the `$row` array will contain key-value pairs from the old table and should be modified to contain the key-value pairs for the new table.

### 1. Seeders

Use seeders to fill a new table that has no direct equivalent in the legacy database.

For example, if your `old_db.old_orders` table contains both order and quotation data, and your new database has separate `orders` and `quotations` tables, you can use a seeder to extract the quotation data. The seeder will iterate through the rows of the specified source table (`old_orders` in this case).

**Example:**

```php
namespace App\Etl\Seeders;

use Illuminate\Database\Query\Builder;
use Redesign\ETL\Seeders\SeederInterface;

class QuotationSeeder implements SeederInterface
{
    // The source table from the legacy database.
    public const SOURCE = 'old_orders';

    public function modifyQuery(Builder &$query): void
    {
        // Add a WHERE clause to filter the data.
        $query->whereNotNull('quotation_id');
    }

    public function seed(array &$row): void
    {
        // Modify or add data to the row before it's inserted.
        $row['id'] = $row['quotation_id'];
        unset($row['quotation_id']);
        $row['quotation_name'] = $this->getQuotationName();
    }

    private function getQuotationName(): string
    {
        // ... custom logic
        return $name;
    }
}
```

In `config/redesign.php`, map your seeder to the `quotations` table:

```php
// config/redesign.php
'seeders' => [
    'quotations' => ['class' => QuotationSeeder::class, 'args' => []],
],
```

This configuration tells the package to use `QuotationSeeder` to populate the `quotations` table using data from the `old_orders` table.

### 2. Processors

Processors are used to modify data during the transition from the old table to the new one. You can inject data into the processor's constructor if needed.

The following example shows a simple processor that renames a column.

**Example:**

```php
namespace App\Etl\Processors;

use Redesign\ETL\Processors\ProcessorInterface;

class RenameProcessor implements ProcessorInterface
{
    use RenameTrait;

    public function __construct(
        private readonly string $sourceColumn,
        private readonly string $targetColumn
    ){}

    public function process(array &$row): void
    {
        $this->rename($row, $this->sourceColumn, $this->targetColumn);
    }
}
```

In `config/redesign.php`, you can map the processor and its arguments:

```php
// config/redesign.php
'processors' => [
    'quotations' => [
        'class' => RenameProcessor::class,
        'args' => ['owner_id', 'customer_id']
    ],
],
```

In this example, the `RenameProcessor` will be used for the `quotations` table to rename the `owner_id` column to `customer_id`.

### 3. Verifiers

Verifiers ensure the integrity of your data. A simple example is to verify that a row in the new table matches its corresponding row in the old table.

**Example:**

```php
class RowVerifier extends AbstractVerifier
{
    public function compare(array &$row): bool
    {
        // Find if a row with the same criteria exists in the new table.
        $criteria = array_intersect_key($row, array_flip($this->columns));

        return $this->new
            ->table($this->table)
            ->where($criteria)
            ->exists();
    }
}
```

In `config/redesign.php`, you can map the verifier and the columns to check:

```php
// config/redesign.php
'verifiers' => [
    'orders' => [
        'class' => RowVerifier::class,
        'args' => ['first_name', 'last_name', 'email']
    ],
],
```

A verifier must always return a boolean. If it returns `false`, an entry will be created in the `migration_tracker` table to flag that the data needs to be updated.

### 4. Migration Tracker

The `migration_tracker` table keeps a record of any legacy data that has changed since the initial migration. This is how the package can update your new database if the source data is modified.

| id  | table  | data                                       |
| --- | ------ | ------------------------------------------ |
| 1   | orders | `{ "0": { "name": "Albert", ... } }`       |
| 2   | users  | `{ "0": { "email": "test@example.com" } }` |

### 5. The `update` Service

The `UpdateService` reads the `migration_tracker` table and performs the necessary updates or inserts in your new database.

---

## Artisan Commands

### Migration

Run the main migration to extract, transform, and load your data.
`php artisan redesign:migrate --chunk-size=1000`

### Seed

Run all configured seeders.
`php artisan redesign:seed --chunk-size=1000`

### Update

Run the update service to process any changes flagged in the `migration_tracker` table.
`php artisan redesign:update`

### Verify

Run all configured verifiers to check for data integrity.
`php artisan redesign:verify`

> The `--chunk-size` option is optional and defaults to 1000. It determines the number of rows processed in a single batch, balancing the workload between your PHP server and the database.

---

## License

This project is licensed under the MIT License.

**Note**: This is my first package as a junior developer. I'd love to hear your feedback on it, so please don't hesitate to reach out!
