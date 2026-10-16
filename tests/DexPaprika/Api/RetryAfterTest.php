<?php

namespace DexPaprika\Tests\Api;

use DexPaprika\Api\NetworksApi;
use DexPaprika\Config;
use DexPaprika\Exception\NotFoundException;
use DexPaprika\Exception\RateLimitException;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * devrel#194: the SDK retried a 429 on its own fixed schedule (100ms, 500ms,
 * 1000ms...) and never looked at what the server actually asked for. That
 * ladder tops out around 5 seconds, so a per-minute rate limit that clears in
 * 12s lost the race every time and surfaced as a hard RateLimitException.
 *
 * The API sends the wait both ways: a standard `Retry-After` header
 * (observed on keyed requests) and a `retry_after` field in the JSON body
 * (observed on keyless 429s, which carry no header at all). This exercises
 * both, the header's precedence over the body, the 60 second clamp, and that
 * an HTTP-date Retry-After (a form this SDK does not parse) falls back to the
 * local backoff rather than guessing. A real Config::setSleeper() is injected
 * everywhere so none of this actually sleeps, including the cases that ask
 * for a 60 second wait.
 */
class RetryAfterTest extends TestCase
{
    /**
     * Build a NetworksApi wired to a fixed mock response, with a sleeper
     * that records requested delays instead of sleeping.
     *
     * @param array<int, Response> $responses
     * @param array<int, int> $sleeps Filled with each requested delay, in milliseconds
     */
    private function apiWithResponses(array $responses, array &$sleeps, int $maxRetries = 1): NetworksApi
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        $httpClient = new GuzzleClient(['handler' => $handlerStack]);

        $config = new Config();
        $config->setMaxRetries($maxRetries);
        $config->setRetryDelays([100]); // Tiny local backoff so any test that
                                        // falls back to it is obviously not
                                        // using a server-supplied figure.
        $config->setSleeper(function (int $milliseconds) use (&$sleeps): void {
            $sleeps[] = $milliseconds;
        });

        return new NetworksApi($httpClient, false, $config);
    }

    public function testRetryAfterHeaderWinsOverLocalBackoff(): void
    {
        $sleeps = [];
        $api = $this->apiWithResponses([
            new Response(429, ['Retry-After' => '12'], json_encode(['error' => 'rate_limited'])),
            new Response(200, [], json_encode(['networks' => [['id' => 'ethereum']]])),
        ], $sleeps);

        $start = microtime(true);
        $result = $api->getNetworks();
        $elapsed = microtime(true) - $start;

        $this->assertSame('ethereum', $result['networks'][0]['id']);
        $this->assertCount(1, $sleeps);
        $this->assertSame(12000, $sleeps[0], 'the header asked for 12s, longer than the 100ms local backoff');
        $this->assertLessThan(1.0, $elapsed, 'the injected sleeper must not really wait 12 seconds');
    }

    public function testRetryAfterBodyFallbackWhenHeaderAbsent(): void
    {
        // The keyless shape: no Retry-After header, only the body field.
        $body = json_encode(['error' => 'rate_limited', 'tier' => 'keyless', 'retry_after' => 12]);

        $sleeps = [];
        $api = $this->apiWithResponses([
            new Response(429, [], $body),
            new Response(200, [], json_encode(['networks' => [['id' => 'ethereum']]])),
        ], $sleeps);

        $result = $api->getNetworks();

        $this->assertSame('ethereum', $result['networks'][0]['id']);
        $this->assertSame([12000], $sleeps);
    }

    public function testRetryAfterIsClampedTo60Seconds(): void
    {
        $sleeps = [];
        $api = $this->apiWithResponses([
            new Response(429, ['Retry-After' => '120'], json_encode(['error' => 'rate_limited'])),
            new Response(200, [], json_encode(['networks' => [['id' => 'ethereum']]])),
        ], $sleeps);

        $api->getNetworks();

        $this->assertSame([60000], $sleeps, 'a 120s ask must be clamped to the 60s ceiling');
    }

    public function testHttpDateRetryAfterFallsBackToLocalBackoffNotTheBody(): void
    {
        // The header is present but an HTTP-date, a form this SDK does not
        // parse. The body also carries retry_after, as a trap: a header that
        // answered, just not in a form read here, must not fall through to
        // the body either. Only the 100ms local backoff should be used.
        $body = json_encode(['error' => 'rate_limited', 'retry_after' => 99]);

        $sleeps = [];
        $api = $this->apiWithResponses([
            new Response(429, ['Retry-After' => 'Wed, 21 Oct 2026 07:28:00 GMT'], $body),
            new Response(200, [], json_encode(['networks' => [['id' => 'ethereum']]])),
        ], $sleeps);

        $api->getNetworks();

        $this->assertCount(1, $sleeps);
        $this->assertGreaterThanOrEqual(50, $sleeps[0]);
        $this->assertLessThan(1000, $sleeps[0], 'must fall back to the local backoff, not the 99s in the body');
    }

    public function test404IsNotRetried(): void
    {
        $container = [];
        $history = Middleware::history($container);

        $mock = new MockHandler([
            new Response(404, [], json_encode(['error' => 'not_found', 'message' => 'no such network'])),
            new Response(200, [], json_encode(['networks' => [['id' => 'ethereum']]])),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push($history);
        $httpClient = new GuzzleClient(['handler' => $handlerStack]);

        $config = new Config();
        $config->setMaxRetries(3);
        $config->setRetryDelays([100]);
        $sleeps = [];
        $config->setSleeper(function (int $ms) use (&$sleeps): void {
            $sleeps[] = $ms;
        });

        $api = new NetworksApi($httpClient, false, $config);

        try {
            $api->getNetworks();
            $this->fail('Expected NotFoundException was not thrown');
        } catch (NotFoundException $e) {
            $this->assertCount(1, $container, 'a 404 must not be retried');
            $this->assertSame([], $sleeps, 'no retry means no sleep either');
        }
    }

    public function testRateLimitExceptionStillThrownAfterRetriesExhausted(): void
    {
        $sleeps = [];
        $api = $this->apiWithResponses([
            new Response(429, ['Retry-After' => '1'], json_encode(['error' => 'rate_limited'])),
            new Response(429, ['Retry-After' => '1'], json_encode(['error' => 'rate_limited'])),
        ], $sleeps, 1);

        try {
            $api->getNetworks();
            $this->fail('Expected RateLimitException was not thrown');
        } catch (RateLimitException $e) {
            $this->assertSame([1000], $sleeps);
        }
    }
}
