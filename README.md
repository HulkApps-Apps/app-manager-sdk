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
* Redis Server for cache (File will work too, but not recommended)

<a name="step2"></a>
### Installation

You can install the package via composer:

```bash
composer require hulkapps/appmanager
```
```bash
composer dump-autoload
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
Initialize MYSQL Fail-safe database in `config/database.php` 
```php
'app-manager-failsafe' => [
			'driver' => 'mysql',
			'host' => env('FAILSAFE_DB_HOST', '127.0.0.1'),
			'port' => env('FAILSAFE_DB_PORT', '3306'),
			'database' => env('FAILSAFE_DB_DATABASE', 'forge'),
			'username' => env('FAILSAFE_DB_USERNAME', 'forge'),
			'password' => env('FAILSAFE_DB_PASSWORD', ''),
			'unix_socket' => env('FAILSAFE_DB_SOCKET', ''),
			'charset' => 'utf8mb4',
			'collation' => 'utf8mb4_unicode_ci',
			'prefix' => '',
			'prefix_indexes' => true,
			'strict' => false,
			'engine' => null,
			'options' => extension_loaded('pdo_mysql') ? array_filter([
				PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
			]) : [],
		];
```

#### 4.Listen Plan Activation Event
Listen and Register plan activation event in `app/Providers/EventServiceProvider`.

```php
use HulkApps\AppManager\app\Events\PlanActivated;

class EventServiceProvider extends ServiceProvider {
    protected $listen = [
		PlanActivated::class => [
			PlanActivatedListener::class,
		],
	];
}
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

$user->getPlanData(); // Return plan details

$user->getChargeData(); // Return active and recent cancelled charge

$user->setDefaultPlan($plan_id); // Set default plan_id( plan_id Optional)
```

<a name="step5"></a>
### Extras
Set Shopify API version to 2022-04.


#### Store plan's total trial Days in shop table (Optional)
Set total_trial_days field name in config/app-manager.php
```php
'total_trial_days' => env('TOTAL_TRIAL_DAYS', 'toal_trial_days'),
```

### Shopify expiring offline access tokens

Shopify requires public apps to use **expiring offline access tokens** from
1 January 2027. They live 60 minutes and carry a refresh token, so a token read
straight from the database can be dead by the time it is used.

The SDK does not store or renew tokens itself — your app does. Tell the SDK
where to get a live token by implementing the contract and pointing the config
at it. **Apps still on non-expiring tokens need do nothing.**

```php
use HulkApps\AppManager\Contracts\ShopifyTokenResolver;

class MyTokenResolver implements ShopifyTokenResolver
{
    // A token valid right now; renew it here if it is at or near expiry.
    public function token(string $shopDomain): ?string { /* ... */ }

    // Shopify rejected that token. Return a working replacement, or null.
    // The SDK replays the call once, and only if this differs from the rejected one.
    public function refresh(string $shopDomain): ?string { /* ... */ }
}
```

```php
// config/app-manager.php
'shopify_token_resolver' => \App\Services\MyTokenResolver::class,
'shopify_timeout' => 20, // seconds per Shopify call; lower it on serverless hosts
```

Both methods are called from web requests and queued work alike, so they must
be safe to call concurrently for the same shop — serialize renewals per shop.

**Your storage is your own.** The SDK asks for a string and never looks at your
schema, so how you keep tokens is entirely up to you: whatever columns you like,
under whatever names, in whatever table — or in a cache or a vault instead. The
SDK has no concept of a refresh token, an expiry or a token state, so nothing
needs to match another app's naming. The only column name it still knows is
`field_names.shopify_token`, used by the default resolver for apps that bind
none.

### Testing

```bash
composer test
```

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email shailesh@shopcircle.co instead of using the issue tracker.

## Credits

-   [Shailesh Desai](https://github.com/shailesh-hulkapps)
-   [Lamija Dzambo Bajric](https://github.com/lamijadzambo)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.