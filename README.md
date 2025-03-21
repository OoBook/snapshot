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

The `HasSnapshot` trait allows you to create point-in-time copies (snapshots) of your Eloquent models while maintaining relationships. It provides both snapshot and sync capabilities for attributes and relationships.

### Basic Setup

```php
<?php

namespace App\Models;

use Oobook\Snapshot\Concerns\HasSnapshot;

class MyProduct extends Model
{
    use HasSnapshot;

    /**
     * The source model for the snapshot.
     *
     * Required - Specifies which model to snapshot from
     *
     * @var string
     */
    public static $snapshotSourceModel = YourModel::class;

    /**
     * The configuration for the snapshot behavior.
     *
     * @var array
     */
    public static $snapshotConfig = [
        // Attributes to take a point-in-time copy of
        'snapshot_attributes' => [
            'email'
        ],
        
        // Attributes to keep in sync with the source model
        'synced_attributes' => [
            'name',
            'user_type_id',
        ],
        
        // Relationships to take a point-in-time copy of
        'snapshot_relationships' => [
            'posts'
        ],
        
        // Relationships to keep in sync with the source model
        'synced_relationships' => [
            'userType',
            'fileNames'
        ],
    ];
}
```

### Configuration Options

#### Snapshot vs Sync

The trait provides two ways to handle attributes and relationships:

1. **Snapshot Mode** (`snapshot_attributes`, `snapshot_relationships`)
   - Creates a point-in-time copy of the data
   - Values remain unchanged even if the source model is updated
   - Useful for historical records or audit trails

2. **Sync Mode** (`synced_attributes`, `synced_relationships`)
   - Maintains a live connection to the source model
   - Values automatically update when the source model changes
   - Useful for maintaining current references

### Available Relationships

The snapshot model automatically provides these relationships:

```php
// Get the snapshot data
$model->snapshot;

// Access the original source model
$model->source;

// Alternative way to access source model
$model->snapshotSource;
```

### Example Usage

```php
// Create a new snapshot
$snapshot = MyProduct::create([
    'source_id' => $sourceModel->id,
    'name' => 'Custom Name',
    'posts' => [1, 2, 3] // IDs of posts to snapshot
]);

// Access snapshotted data
echo $snapshot->email; // Shows snapshotted email
echo $snapshot->name; // Shows synced name from source

// Access relationships
$snapshot->posts; // Shows snapshotted posts
$snapshot->userType; // Shows synced userType from source

// Update source model
$sourceModel->update(['name' => 'New Name']);
echo $snapshot->name; // Shows 'New Name' (synced attribute)
echo $snapshot->email; // Still shows original email (snapshotted attribute)
```

### Key Features

- **Automatic Syncing**: Synced attributes and relationships automatically update when the source model changes
- **Relationship Handling**: Supports both HasOne/HasMany and BelongsTo/BelongsToMany relationships
- **Flexible Configuration**: Choose which attributes and relationships to snapshot or sync
- **Data Integrity**: Maintains separate copies of snapshotted data while keeping synced data up-to-date

### Important Notes

1. The source model must be specified using `$snapshotSourceModel`
2. Configuration is optional - by default, all attributes and relationships will be snapshotted (relationships if only you use ManageEloquent Trait on source model)
3. Synced relationships maintain live connections and may impact performance with large datasets
4. Snapshotted relationships store a copy of the data at creation time
5. `Oobook\Database\Eloquent\Concerns\ManageEloquent` Trait is offered to be used on all related models to snapshot mode

### Best Practices

- Use snapshots for historical records or audit trails
- Use synced attributes for frequently changing data that should stay current
- Consider performance implications when syncing large relationships
- Use relationship IDs instead of full objects when creating snapshots for better performance

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
