# App Manager SDK

[![Latest Version on Packagist](https://img.shields.io/packagist/v/hulkapps/appmanager.svg?style=flat-square)](https://packagist.org/packages/hulkapps/appmanager)
[![Total Downloads](https://img.shields.io/packagist/dt/hulkapps/appmanager.svg?style=flat-square)](https://packagist.org/packages/hulkapps/appmanager)

[//]: # (This is where your description should go. Try and limit it to a paragraph or two, and maybe throw in a mention of what PSRs you support to avoid any confusion with users and contributors.)

## Installation Guide:
* [Download the Package](#step1)
* [Initialization](#step2)
* [Extras](#step3)

<a name="step1"></a>
### Download the Package

You can install the package via composer:

```bash
composer require hulkapps/appmanager
```

<a name="step2"></a>
### Initialization

#### 1.Initialize App Manager Config
```php
php artisan vendor:publish --provider="HulkApps\AppManager\AppManagerServiceProvider"
```

In the case that config/app-manager.php is already present, delete it and then run the command below.
Don't forget to update secret on file `config/app-manager.php`

#### 2.Initialize App Features
According to the example in the file, list all features of the app in `config/plan-features.php.` Make sure to use the UIID from this sheet.

#### 3.Migration
Migrate all existing plans, charges and plan-features to app manager. Don't forget to update bearer token in `config/app-manager.php` while migrating plans
```php
php artisan migrate:app-manager-plans
```

#### 4.Initialize Fail-safe Database
Initialize SQLite Fail-safe database in `config/database.php` 
```php
'app-manager-sqlite' => [
    'driver' => 'sqlite',
    'database' => storage_path('app/app-manager/database.sqlite'),
    'prefix' => '',
    'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
]
```

<a name="step3"></a>
### Extras
Set Shopify API version to 2022-04.

Initialize the task scheduling.

There may be permission issues with database storage, so change permissions on the storage directory
```bash
sudo chown -R www-data:www-data storage
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

If you discover any security related issues, please email divyank@hulkapps.com instead of using the issue tracker.

## Credits

-   [Divyank](https://github.com/hulkapps)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.