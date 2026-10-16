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
 * A key sent to api-pro.dexpaprika.com without the api_ prefix never reaches
 * the API: Cloudflare answers with its own HTML block page ("Sorry, you have
 * been blocked"), a 403 whose body is not JSON. Before this fix that body
 * decoded to null and the SDK threw a ClientException with message
 * "Unknown error". BaseApi::createExceptionFromResponse() now recognizes a
 * non-JSON 403 body and raises a clear message instead, while a 403 with a
 * JSON body (the keyless-paging plan_required error covered by
 * PlanRequiredErrorTest) keeps its own handling untouched.
 */
class KeyRefusedByEdgeErrorTest extends TestCase
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

    public function testNonJsonForbiddenBodyGetsAClearMessage(): void
    {
        $html = '<html>Sorry, you have been blocked</html>';

        $api = $this->apiWithResponses([
            new Response(403, ['Content-Type' => 'text/html'], $html),
        ]);

        try {
            $api->getNetworks();
            $this->fail('Expected ClientException was not thrown');
        } catch (ClientException $e) {
            $this->assertSame(403, $e->getCode());
            $this->assertStringContainsString(
                'The API key was refused before it reached the API.',
                $e->getMessage()
            );
            $this->assertStringContainsString('console.dexpaprika.com', $e->getMessage());
            $this->assertStringNotContainsStringIgnoringCase('bearer', $e->getMessage());
        }
    }

    public function testJsonForbiddenBodyKeepsItsOwnMessage(): void
    {
        // Same status code as the Cloudflare block page, but a real JSON body
        // from the API itself. This must not be mistaken for the edge block.
        $body = json_encode([
            'error' => 'plan_required',
            'required_tier' => 'free',
            'param' => 'cursor',
            'message' => 'Search results beyond the first page require an API key.',
        ]);

        $api = $this->apiWithResponses([new Response(403, [], $body)]);

        try {
            $api->getNetworks();
            $this->fail('Expected ClientException was not thrown');
        } catch (ClientException $e) {
            $this->assertStringContainsString('Search results beyond the first page', $e->getMessage());
            $this->assertStringNotContainsString('refused before it reached the API', $e->getMessage());
            $this->assertSame('plan_required', $e->getErrorCode());
        }
    }
}
