<?php

namespace HulkApps\AppManager\Tests\Unit;

use GuzzleHttp\Psr7\Response;
use HulkApps\AppManager\Client\ClientResponse;
use HulkApps\AppManager\Contracts\ShopifyTokenResolver;
use HulkApps\AppManager\Exception\MissingShopException;
use HulkApps\AppManager\Shopify\ShopifyApi;
use PHPUnit\Framework\TestCase;

/**
 * The retry logic every SDK call to Shopify now goes through. No Laravel
 * container is needed, so this runs against a plain resolver double.
 */
class ShopifyApiTest extends TestCase
{
    /**
     * A resolver double: hands out a token, and a replacement on refresh.
     */
    private function resolver($token, $replacement = null)
    {
        return new class($token, $replacement) implements ShopifyTokenResolver
        {
            public $token;
            public $replacement;
            public $refreshes = 0;

            public function __construct($token, $replacement)
            {
                $this->token = $token;
                $this->replacement = $replacement;
            }

            public function token(string $shopDomain): ?string
            {
                return $this->token;
            }

            public function refresh(string $shopDomain): ?string
            {
                $this->refreshes++;

                return $this->replacement;
            }
        };
    }

    private function response($status, $body = '{}')
    {
        return new ClientResponse(new Response($status, [], $body));
    }

    /**
     * A send callable that replays scripted responses and records the tokens.
     */
    private function sender(array $responses, array &$tokensSeen)
    {
        return function ($token) use (&$responses, &$tokensSeen) {
            $tokensSeen[] = $token;

            return array_shift($responses);
        };
    }

    public function test_a_successful_call_is_not_touched()
    {
        $resolver = $this->resolver('shpat_live');
        $seen = [];

        $response = (new ShopifyApi($resolver))
            ->withToken('shop.myshopify.com', $this->sender([$this->response(200)], $seen));

        $this->assertSame(200, $response->status());
        $this->assertSame(['shpat_live'], $seen);
        $this->assertSame(0, $resolver->refreshes);
    }

    public function test_a_rejected_token_is_replaced_and_the_call_replayed_once()
    {
        $resolver = $this->resolver('shpat_stale', 'shpat_fresh');
        $seen = [];

        $response = (new ShopifyApi($resolver))->withToken(
            'shop.myshopify.com',
            $this->sender([$this->response(401), $this->response(200)], $seen)
        );

        $this->assertSame(200, $response->status());
        $this->assertSame(['shpat_stale', 'shpat_fresh'], $seen);
        $this->assertSame(1, $resolver->refreshes);
    }

    public function test_without_a_replacement_the_original_failure_is_returned()
    {
        // The default column resolver: it cannot mint a new token.
        $resolver = $this->resolver('shpat_stale', null);
        $seen = [];

        $response = (new ShopifyApi($resolver))
            ->withToken('shop.myshopify.com', $this->sender([$this->response(401)], $seen));

        $this->assertSame(401, $response->status());
        $this->assertSame(['shpat_stale'], $seen);
    }

    public function test_the_same_token_back_is_not_worth_a_replay()
    {
        $resolver = $this->resolver('shpat_stale', 'shpat_stale');
        $seen = [];

        $response = (new ShopifyApi($resolver))
            ->withToken('shop.myshopify.com', $this->sender([$this->response(401)], $seen));

        $this->assertSame(401, $response->status());
        $this->assertCount(1, $seen);
    }

    public function test_a_replacement_that_is_also_rejected_is_never_looped()
    {
        $resolver = $this->resolver('shpat_stale', 'shpat_fresh');
        $seen = [];

        $response = (new ShopifyApi($resolver))->withToken(
            'shop.myshopify.com',
            $this->sender([$this->response(401), $this->response(401)], $seen)
        );

        // A freshly issued token failing too means the token is not the problem.
        $this->assertSame(401, $response->status());
        $this->assertCount(2, $seen);
        $this->assertSame(1, $resolver->refreshes);
    }

    public function test_the_post_deadline_403_counts_as_a_rejected_token()
    {
        $resolver = $this->resolver('shpat_legacy', 'shpat_fresh');
        $seen = [];

        $response = (new ShopifyApi($resolver))->withToken('shop.myshopify.com', $this->sender([
            $this->response(403, '{"errors":"Non-expiring access tokens are no longer accepted for the Admin API"}'),
            $this->response(200),
        ], $seen));

        $this->assertSame(200, $response->status());
        $this->assertSame(['shpat_legacy', 'shpat_fresh'], $seen);
    }

    public function test_a_scope_403_is_not_mistaken_for_a_token_problem()
    {
        $resolver = $this->resolver('shpat_live', 'shpat_fresh');
        $seen = [];

        $response = (new ShopifyApi($resolver))->withToken('shop.myshopify.com', $this->sender([
            $this->response(403, '{"errors":"This action requires merchant approval for write_discounts scope."}'),
        ], $seen));

        $this->assertSame(403, $response->status());
        $this->assertSame(0, $resolver->refreshes);
    }

    public function test_the_fallback_token_is_used_only_when_the_resolver_has_none()
    {
        $resolver = $this->resolver(null);
        $seen = [];

        (new ShopifyApi($resolver))->withToken(
            'shop.myshopify.com',
            $this->sender([$this->response(200)], $seen),
            'shpat_from_row'
        );

        $this->assertSame(['shpat_from_row'], $seen);
    }

    public function test_a_shop_with_no_token_at_all_fails_loudly()
    {
        $this->expectException(MissingShopException::class);

        $seen = [];

        (new ShopifyApi($this->resolver(null)))
            ->withToken('shop.myshopify.com', $this->sender([], $seen));
    }
}
