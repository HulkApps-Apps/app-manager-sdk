# App Manager SDK

[![Latest Version on Packagist](https://img.shields.io/packagist/v/hulkapps/appmanager.svg?style=flat-square)](https://packagist.org/packages/hulkapps/appmanager)
[![Total Downloads](https://img.shields.io/packagist/dt/hulkapps/appmanager.svg?style=flat-square)](https://packagist.org/packages/hulkapps/appmanager)

[//]: # (This is where your description should go. Try and limit it to a paragraph or two, and maybe throw in a mention of what PSRs you support to avoid any confusion with users and contributors.)

## Installation

You can install the package via composer:

```bash
composer require hulkapps/appmanager
```

## Usage

```php
php artisan vendor:publish --provider="HulkApps\AppManager\AppManagerServiceProvider"
```

Don't forget to update secret on file config/app-manager.php


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