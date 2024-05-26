Doctrine Extensions
===================
[![Latest Stable Version](https://poser.pugx.org/nucleos/doctrine-extensions/v/stable)](https://packagist.org/packages/nucleos/doctrine-extensions)
[![Latest Unstable Version](https://poser.pugx.org/nucleos/doctrine-extensions/v/unstable)](https://packagist.org/packages/nucleos/doctrine-extensions)
[![License](https://poser.pugx.org/nucleos/doctrine-extensions/license)](LICENSE.md)

[![Total Downloads](https://poser.pugx.org/nucleos/doctrine-extensions/downloads)](https://packagist.org/packages/nucleos/doctrine-extensions)
[![Monthly Downloads](https://poser.pugx.org/nucleos/doctrine-extensions/d/monthly)](https://packagist.org/packages/nucleos/doctrine-extensions)
[![Daily Downloads](https://poser.pugx.org/nucleos/doctrine-extensions/d/daily)](https://packagist.org/packages/nucleos/doctrine-extensions)

[![Continuous Integration](https://github.com/nucleos/nucleos-doctrine-extensions/actions/workflows/continuous-integration.yml/badge.svg?event=push)](https://github.com/nucleos/nucleos-doctrine-extensions/actions?query=workflow%3A"Continuous+Integration"+event%3Apush)
[![Code Coverage](https://codecov.io/gh/nucleos/nucleos-doctrine-extensions/graph/badge.svg)](https://codecov.io/gh/nucleos/nucleos-doctrine-extensions)
[![Type Coverage](https://shepherd.dev/github/nucleos/nucleos-doctrine-extensions/coverage.svg)](https://shepherd.dev/github/nucleos/nucleos-doctrine-extensions)

This library provides adds some useful doctrine hooks.

## Installation

Open a command console, enter your project directory and execute the following command to download the latest stable version of this library:

```
composer require nucleos/doctrine-extensions
```

## Usage

### Confirmable entities

If you need entities that needs to be confirmed, just implement the `Nucleos\Doctrine\Model\ConfirmableInterface`
in your entity class.

If you don't need the symfony framework, you need to register the `Nucleos\Doctrine\EventListener\ORM\ConfirmableListener`.

### Deleteable entities

If you need entities that should be soft deleted, just implement the `Nucleos\Doctrine\Model\DeletableInterface`
in your entity class.

If you don't need the symfony framework, you need to register the `Nucleos\Doctrine\EventListener\ORM\DeletableListener`.

### Lifecyle aware enties

If you need lifecyle information (creation / update date), just implement the `Nucleos\Doctrine\Model\LifecycleDateTimeInterface`
in your entity class.

If you don't need the symfony framework, you need to register the `Nucleos\Doctrine\EventListener\ORM\LifecycleDateListener`.

### Position aware entities

If you need sortable entities, just implement the `Nucleos\Doctrine\Model\PositionAwareInterface`
in your entity class.

If you don't need the symfony framework, you need to register the `Nucleos\Doctrine\EventListener\ORM\SortableListener`.

### Unique active entities

If you need entities that should only have one active state, just implement the `Nucleos\Doctrine\Model\UniqueActiveInterface`
in your entity class.

If you don't need the symfony framework, you need to register the `Nucleos\Doctrine\EventListener\ORM\UniqueActiveListener`.

### Table prefix

If you need a prefix for all of you application tables and sequences, you could use the  `TablePrefixEventListener`.
If the table name does already start with the defined prefix, it will be ignored.

If you don't need the symfony framework, you need to register the `Nucleos\Doctrine\EventListener\ORM\TablePrefixEventListener`.

## Migration usage

1. Update your `id` column from `integer` to `guid`.

2. Create a new migration:

```php
// src/Migrations/Version123.php
<?php

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Nucleos\Doctrine\Migration\IdToUuidMigration;

class Version123 extends AbstractMigration
{
    private IdToUuidMigration $idToUuidMigration;

    public function __construct(Connection $connection, LoggerInterface $logger)
    {
        parent::__construct($connection, $logger);

        $this->idToUuidMigration = new IdToUuidMigration($this->connection, $logger);
    }


    public function postUp(Schema $schema): void
    {
        $this->idToUuidMigration->migrate('my_table_name');
    }
}
```

## Symfony usage

If you want to use this library inside symfony, you can use a bridge.

### Enable the Bundle

Then, enable the bundle by adding it to the list of registered bundles in `config/bundles.php` file of your project:

```php
// config/bundles.php

return [
    // ...
    Nucleos\Doctrine\Bridge\Symfony\Bundle\NucleosDoctrineBundle::class => ['all' => true],
];
```


### Configure the Bundle

Create a configuration file called `nucleos_doctrine.yaml`:

```yaml
# config/packages/nucleos_doctrine.yaml

nucleos_doctrine:
    table:
        prefix: 'acme_'
```


## License

This library is under the [MIT license](LICENSE.md).
