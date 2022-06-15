<?php


namespace HulkApps\AppManager\Tests\Unit;

use Orchestra\Testbench\TestCase;
use HulkApps\AppManager\Client\Client;

class MarketingBannerTest extends TestCase
{
    protected $client = null;
    public function setUp() : void {
        parent::setUp();
        $this->client = Client::withHeaders(['token' => env('APP_TOKEN')])->withoutVerifying()->baseUri(env('API_URL'));
    }

    function test_sdk_receive_response_from_app_manager() {
        $response = $this->client->get('get-status');
        $response->getStatusCode() == 200 ? $this->assertTrue(true) : $this->assertTrue(false);
    }

    function test_sdk_receive_correct_content() {
        $response = $this->client->get('static-contents');

        $assertFlag = false;
        if ($response->getStatusCode() == 200) {
            $data = json_decode($response->getBody()->getContents(), true);
            if (count($data) > 0) {
                $assertFlag = true;
            }
        }
        $this->assertTrue($assertFlag);
    }
}