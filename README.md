# Laravel ETL Package – Redesign ETL
> FR — Guide complet pour l’installation, la configuration et l’utilisation

> EN — Full installation, configuration and usage guide

## :fr: Introduction
Redesign ETL est un package Laravel pour orchestrer des migrations ETL (Extract – Transform – Load) depuis une base de données “ancienne” vers votre nouvelle base.
Il fournit :

- Commandes Artisan (`redesign:migrate`, `redesign:seed`)

- Une API de processors pour transformer vos lignes

- Un système de seeders pour vos nouvelles tables

Un tracker de progression (table `migration_tracker`)

## Installation
1) Installation via Composer
   ```composer require redesign/etl ```

2) Publier la configuration
```shell
# Publier tout
php artisan vendor:publish --provider="Redesign\ETL\Providers\ETLServiceProvider"

# OU uniquement la configuration
php artisan vendor:publish --provider="Redesign\ETL\Providers\ETLServiceProvider" --tag=redesign-config
```
Cela va créer config/redesign.php, que vous pouvez personnaliser.

3) Configurer vos connexions et seeders
   Dans `.env`, ajoutez par exemple :

```dotenv
OLD_DB_CONNECTION=mysql
OLD_DB_HOST=127.0.0.1
OLD_DB_PORT=3306
OLD_DB_DATABASE=legacy
OLD_DB_USERNAME=root
OLD_DB_PASSWORD=secret
```
Dans config/redesign.php, mappez vos seeders :

```php
'processors' => [
    'products' => new \Redesign\ETL\Processors\RenameProcessor('source','target'),
],
'seeders' => [
    'quotations' => new ProductsSeeder(),
],
```
## Tutoriel d’utilisation
Étape 1 — Créer un Seeder
```php
<?php

namespace App\Etl\Seeders;

use Illuminate\Database\Query\Builder;
use Redesign\ETL\Seeders\SeederInterface;

class ProductsSeeder implements SeederInterface
{
    public const SOURCE = 'legacy_products';

    public function modifyQuery(Builder &$query): void
    {
        $query->where('is_active', 1);
    }

    public function seed(array &$row): void
    {
        \DB::table('products')->insert([
            'id' => $row['id'],
            'name' => $row['name'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
```

Étape 2 — Ajouter un Processor (optionnel)
```php
use Redesign\ETL\Processors\ProcessorInterface;

class RenameProcessor implements ProcessorInterface
{
    use RenameTrait;

    public function __construct(
    private readonly string $sourceColumns, 
    private readonly string $targetColumns
    ){}
 
    public function process(array &$row): void
    {
        $this->rename($row, $this->sourceColumns, $this->targetColumns);
    }
}
```

Étape 3 — Lancer les commandes
```bash
# Migration
php artisan redesign:migrate --chunk-size=1000

#Seed
php artisan redesign:seed --chunk-size=1000
```
> l'option `chun-size` est optionnel et est par défaut à 1000.

## :us: Introduction
Redesign ETL is a Laravel package for orchestrating ETL (Extract – Transform – Load) migrations from a "legacy" database to your new one.
It provides:

- Artisan Commands (`redesign:migrate`, `redesign:seed`)
- An API of processors to transform your data rows
- A seeder system for your new tables
- A progress tracker (migration_tracker table)

# Installation
Install via Composer:
```shell
composer require redesign/etl
```

## Publish the configuration

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

In `config/redesign.php`, map your what you need:
```php
'processors' => [
    'products' => new \Redesign\ETL\Processors\RenameProcessor('source','target'),
],
'seeders' => [
    'quotations' => new ProductsSeeder(),
],
```


## Usage Tutorial
Step 1 — Create a Seeder (optional)
```php
namespace App\Etl\Seeders;

use Illuminate\Database\Query\Builder;
use Redesign\ETL\Seeders\SeederInterface;

class ProductsSeeder implements SeederInterface
{
    public const SOURCE = 'legacy_products';

    public function modifyQuery(Builder &$query): void
    {
        $query->where('is_active', 1);
    }

    public function seed(array &$row): void
    {
        \DB::table('products')->insert([
            'id' => $row['id'],
            'name' => $row['name'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
```


Step 2 — Create a Processor (optional)
```php
namespace App\Etl\Processors

use Redesign\ETL\Processors\ProcessorInterface;

class RenameProcessor implements ProcessorInterface
{
    use RenameTrait;

    public function __construct(
    private readonly string $sourceColumns,
    private readonly string $targetColumns
    ){}

    public function process(array &$row): void
    {
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
```
> The chunk-size option is optional and defaults to 1000.

MIT License
