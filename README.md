# App Manager SDK

[![Latest Version on Packagist](https://img.shields.io/packagist/v/hulkapps/appmanager.svg?style=flat-square)](https://packagist.org/packages/hulkapps/appmanager)
[![Total Downloads](https://img.shields.io/packagist/dt/hulkapps/appmanager.svg?style=flat-square)](https://packagist.org/packages/hulkapps/appmanager)

[//]: # (This is where your description should go. Try and limit it to a paragraph or two, and maybe throw in a mention of what PSRs you support to avoid any confusion with users and contributors.)

* [Requirements](#step1)
* [Installation](#step2)
* [Configuration](#step3)
* [Usage](#step4)
* [Extras](#step5)

<a name="step1"></a>
### Requirements
* SQLite

<a name="step2"></a>
### Installation

You can install the package via composer:

```bash
composer require hulkapps/appmanager
```

<a name="step3"></a>
### Configuration

#### 1.Initialize App Manager Config
```php
php artisan vendor:publish --provider="HulkApps\AppManager\AppManagerServiceProvider"
```

In the case that config/app-manager.php is already present, delete it and then run the command below.

Don't forget to update secret on file `config/app-manager.php`

#### 2.Initialize App Features
According to the example in the file, list all features of the app in `config/plan-features.php`.

Ensure you use the UIID from this <a href="https://docs.google.com/spreadsheets/d/1cw2nSKxAHTGn4Cfa98RNdtfHT3zdtwu9bQD7s7hErXc/edit#gid=0">sheet</a>, and don't forget to mention the app name after using the UUID

#### 3.Initialize Fail-safe Database
Initialize SQLite Fail-safe database in `config/database.php` 
```php
'app-manager-sqlite' => [
    'driver' => 'sqlite',
    'database' => storage_path('app/app-manager/database.sqlite'),
    'prefix' => '',
    'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
];
```

<a name="step4"></a>
### Usage
Plan and feature helper functions are provided in this package.

##### Bind trait with user model
```php
use HulkApps\AppManager\app\Traits\HasPlan;

class User extends Model
{
	use HasPlan;
}
```

##### Helper functions
```php
$user->hasPlan(); // If the user has plan or not

$user->planFeatures(); // Return the active plan's features with value

$user->hasFeature($featureSlug); // Return the user has given the feature or not

$user->getFeature($featureSlug); // Return data for a feature

$user->getRemainingDays(); // Calculate the remaining days of the active plan
```

<a name="step5"></a>
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