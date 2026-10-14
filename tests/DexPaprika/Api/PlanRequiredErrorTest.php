<?php

namespace DexPaprika\Tests\Api;

use DexPaprika\Api\NetworksApi;
use DexPaprika\Config;
use DexPaprika\Exception\ClientException;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * The keyless-paging 403 a search endpoint sends past the first page
 * ({"error": "plan_required", "tier": "keyless", "required_tier": "free",
 * "param": "cursor", ...}) becomes a plain ClientException. This confirms
 * BaseApi::createExceptionFromResponse() threads that body into errorData,
 * where DexPaprikaApiException's typed accessors can read it back, without
 * needing a dedicated exception subclass. Exercised here through
 * NetworksApi (any BaseApi subclass goes through the same code path); no
 * live network call is made.
 */
class PlanRequiredErrorTest extends TestCase
{
    /**
     * Build a NetworksApi wired to a fixed mock response.
     *
     * @param array<int, Response> $responses
     */
    private function apiWithResponses(array $responses): NetworksApi
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        $httpClient = new GuzzleClient(['handler' => $handlerStack]);

        $config = new Config();
        $config->setMaxRetries(0);

        return new NetworksApi($httpClient, false, $config);
    }

    public function testKeylessCursorPagingErrorExposesTypedFields(): void
    {
        $body = json_encode([
            'error' => 'plan_required',
            'tier' => 'keyless',
            'required_tier' => 'free',
            'param' => 'cursor',
            'message' => 'Search results beyond the first page require an API key (free key: all pages). '
                . 'Create one in under a minute at https://console.dexpaprika.com',
            'links' => [
                'register' => 'https://console.dexpaprika.com',
                'pricing' => 'https://dexpaprika.com/api/pricing',
                'docs' => 'https://docs.dexpaprika.com/knowledge-base/rate-limits#pagination-rules',
            ],
        ]);

        $api = $this->apiWithResponses([new Response(403, [], $body)]);

        try {
            $api->getNetworks();
            $this->fail('Expected ClientException was not thrown');
        } catch (ClientException $e) {
            $this->assertSame(403, $e->getCode());
            $this->assertStringContainsString('Search results beyond the first page', $e->getMessage());
            $this->assertSame('plan_required', $e->getErrorCode());
            $this->assertSame('free', $e->getRequiredTier());
            $this->assertSame('cursor', $e->getParam());
            $this->assertIsArray($e->getErrorData());
            $this->assertSame('keyless', $e->getErrorData()['tier']);
        }
    }

    public function testOrdinaryClientErrorLeavesTypedFieldsNull(): void
    {
        $body = json_encode(['error' => 'validation_error', 'message' => 'bad input']);

        $api = $this->apiWithResponses([new Response(400, [], $body)]);

        try {
            $api->getNetworks();
            $this->fail('Expected ClientException was not thrown');
        } catch (ClientException $e) {
            $this->assertSame('validation_error', $e->getErrorCode());
            $this->assertNull($e->getRequiredTier());
            $this->assertNull($e->getParam());
        }
    }
}
