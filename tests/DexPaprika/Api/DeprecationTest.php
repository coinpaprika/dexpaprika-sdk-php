<?php

namespace DexPaprika\Tests\Api;

use DexPaprika\Api\NetworksApi;
use DexPaprika\Config;
use DexPaprika\Exception\ClientException;
use DexPaprika\Exception\DeprecationException;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class DeprecationTest extends TestCase
{
    /**
     * Build a NetworksApi wired to a fixed set of mock responses.
     *
     * @param array<int, Response> $responses
     * @param array<int, mixed> $container History container passed by reference
     */
    private function apiWithResponses(array $responses, array &$container): NetworksApi
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(Middleware::history($container));
        $httpClient = new GuzzleClient(['handler' => $handlerStack]);

        $config = new Config();
        $config->setMaxRetries(0);

        return new NetworksApi($httpClient, false, $config);
    }

    /**
     * A 410 with a "replacement" hint surfaces both the message and the replacement.
     */
    public function testDeprecationSurfacesReplacement(): void
    {
        $body = json_encode([
            'code' => 410,
            'message' => 'endpoint removed',
            'replacement' => '/networks/:network/pools/search',
        ]);

        $container = [];
        $api = $this->apiWithResponses([new Response(410, [], $body)], $container);

        try {
            $api->getNetworks();
            $this->fail('Expected DeprecationException was not thrown');
        } catch (DeprecationException $e) {
            $this->assertSame(410, $e->getCode());
            $this->assertStringContainsString('endpoint removed', $e->getMessage());
            $this->assertStringContainsString('Use /networks/:network/pools/search instead.', $e->getMessage());
            $this->assertSame('/networks/:network/pools/search', $e->getReplacement());
            $this->assertIsArray($e->getErrorData());
            $this->assertSame('/networks/:network/pools/search', $e->getErrorData()['replacement']);
            // 410 is not retryable: a single request must have been made.
            $this->assertCount(1, $container);
        }
    }

    /**
     * The replacement hint is generic: any error status carrying it gets it surfaced.
     */
    public function testGenericReplacementOnNon410Status(): void
    {
        $body = json_encode([
            'message' => 'gone for good',
            'replacement' => '/v2/thing',
        ]);

        $container = [];
        $api = $this->apiWithResponses([new Response(400, [], $body)], $container);

        try {
            $api->getNetworks();
            $this->fail('Expected exception was not thrown');
        } catch (ClientException $e) {
            $this->assertStringContainsString('gone for good', $e->getMessage());
            $this->assertStringContainsString('Use /v2/thing instead.', $e->getMessage());
        }
    }

    /**
     * When no "replacement" is present, behavior falls back to the plain message.
     */
    public function testFallsBackWhenNoReplacement(): void
    {
        $body = json_encode([
            'error' => 'plain old error',
        ]);

        $container = [];
        $api = $this->apiWithResponses([new Response(400, [], $body)], $container);

        try {
            $api->getNetworks();
            $this->fail('Expected exception was not thrown');
        } catch (ClientException $e) {
            $this->assertSame('plain old error', $e->getMessage());
            $this->assertStringNotContainsString('instead.', $e->getMessage());
        }
    }

    /**
     * A non-JSON error body degrades gracefully to the generic message.
     */
    public function testNonJsonBodyFallsBack(): void
    {
        $container = [];
        $api = $this->apiWithResponses([new Response(400, [], 'not json at all')], $container);

        try {
            $api->getNetworks();
            $this->fail('Expected exception was not thrown');
        } catch (ClientException $e) {
            $this->assertSame('Unknown error', $e->getMessage());
        }
    }
}
