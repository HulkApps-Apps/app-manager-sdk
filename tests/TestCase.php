<?php


namespace HulkApps\AppManager\Tests;

use HulkApps\AppManager\AppManager;
use HulkApps\AppManager\AppManagerServiceProvider;

class TestCase extends \Orchestra\Testbench\TestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders()
    {
        return [
            AppManagerServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp()
    {
        // perform environment setup
    }
}