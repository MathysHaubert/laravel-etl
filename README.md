# Laravel ETL Package – Redesign ETL

## :us: Introduction

Redesign ETL is a Laravel package for orchestrating ETL (Extract – Transform – Load) migrations from a "legacy" database to your new one.
It provides:

-   Artisan Commands (`redesign:migrate`, `redesign:seed`, `redesign:verify`, `redesign:update`)
-   An API of processors to transform your data rows
-   A seeder system for your new tables
-   A verifier to easily update old data

# Installation

Install via Composer:

```shell
composer require redesign/etl
```

# Publish the configuration file

```shell
php artisan vendor:publish --provider="Redesign\ETL\Providers\ETLServiceProvider" --tag=redesign-config
```

This will create config/redesign.php, which you can customize.

## Configure your connections

In your .env file, add the following, for example:

```dotenv
OLD_DB_CONNECTION=mysql
OLD_DB_HOST=127.0.0.1
OLD_DB_PORT=3306
OLD_DB_DATABASE=legacy
OLD_DB_USERNAME=root
OLD_DB_PASSWORD=secret
```

In `config/redesign.php`, map what you need:

```php
    'tables' => [
        //        'old_table_name' => 'new_table_name'
    ],

    'processors' => [
        //        'new_table_name' => ['class': Processor::class, 'args':[]],
    ],

    'seeders' => [
        //        'new_table_name' =>  ['class': Seeder::class, 'args':[]],
    ],
    'verifiers' => [
        //        'new_table_name' => ['class': Verifier::class, 'args' => []],
    ]
```

## Usage Tutorial

Create a Seeder

```php
namespace App\Etl\Seeders;

use Illuminate\Database\Query\Builder;
use Redesign\ETL\Seeders\SeederInterface;

class ProductsSeeder implements SeederInterface
{
    public const SOURCE = 'legacy_products'; // important

    public function modifyQuery(Builder &$query): void
    {
        $query->where('is_active', 1); // filter if you need
    }

    public function seed(array &$row): void
    {
        // modify or add any required data data to $row
    }
}
```

Create a Processor

```php
namespace App\Etl\Processors

use Redesign\ETL\Processors\ProcessorInterface;

class RenameProcessor implements ProcessorInterface
{
    use RenameTrait;

    public function __construct( // you can pass parameters if needed
    private readonly string $sourceColumns,
    private readonly string $targetColumns
    ){}

    public function process(array &$row): void
    {
        // Just rename columns
        $this->rename($row, $this->sourceColumns, $this->targetColumns);
    }
}
```

Step 3 — Run the commands

```shell
# Migration
php artisan redesign:migrate --chunk-size=1000

# Seed
php artisan redesign:seed --chunk-size=1000

# Update
php artisan redesign:update

# Verify
php artisan redesign:verify
```

> The chunk-size option is optional and defaults to 1000.
> It balances the number of rows per chunk in a chunk.

MIT License
