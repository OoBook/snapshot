<p align="center"><img src="./snapshot.png" width="200" alt="Modulariy Logo"></p>


# Eloquent Snapshot

[![Latest Version on Packagist](https://img.shields.io/packagist/v/oobook/snapshot.svg?style=flat-square)](https://packagist.org/packages/oobook/snapshot)
[![Total Downloads](https://img.shields.io/packagist/dt/oobook/snapshot.svg?style=flat-square)](https://packagist.org/packages/oobook/snapshot)
![GitHub Actions](https://github.com/oobook/snapshot/actions/workflows/main.yml/badge.svg)

This package will create easily the snapshots of your eloquent models into another eloquent model. Besides, it retains the relationships as attribute on your projected models.

## Installation

You can install the package via composer:

```bash
composer require oobook/snapshot
```

#### Publish config
Create the snapshot config file under config/ folder using **artisan**
```
php artisan vendor:publish --tag="snapshot-config"
```

## Usage

```php
<?php

namespace App\Models;

use Oobook\Snapshot\Traits\HasSnapshot;

class MyProduct extends Model
{
    use HasSnapshot;

    /**
     * The source model for the snapshot.
     *
     * Required
     *
     * @var Model
     */
    public $snapshotSourceModel = YourModel::class;

    /**
     * Fillable attributes to be copied from the source model.
     *
     * Optional
     *
     * @var array
     */
    public $snapshotSourceFillable = [];

    /**
     * Relationships to add to snapshot data.
     *
     * Optional
     *
     * @var array
     */
    public $snapshotSourceRelationships = [];
}
```
### Testing

```bash
composer test
```

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email oguz.bukcuoglu@gmail.com instead of using the issue tracker.

## Credits

-   [Oğuzhan Bükçüoğlu](https://github.com/oobook)
<!-- -   [All Contributors](../../contributors) -->

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Laravel Package Boilerplate

This package was generated using the [Laravel Package Boilerplate](https://laravelpackageboilerplate.com).
