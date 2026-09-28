<?php

declare(strict_types=1);

namespace DexPaprika\Tests;

use DexPaprika\Api\PoolsApi;
use DexPaprika\Api\TokensApi;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * Since dexpaprika-go #2421 the API takes "-1h", RFC 3339 and YYYY-MM-DD next
 * to Unix seconds for from/to and created_after/created_before. The SDK only
 * has to forward them untouched, so these tests read the query that reached
 * the wire rather than what the SDK meant to send.
 */
class TimeFilterTest extends TestCase
{
    private const POOL = '0x88e6a0c2ddd26feeb64f039a2c41296fcb3f5640';

    /** @var array<int, array<string, mixed>> */
    private array $sent = [];

    private function client(string $body): Client
    {
        $this->sent = [];
        $stack = HandlerStack::create(new MockHandler([new Response(200, [], $body)]));
        $stack->push(Middleware::history($this->sent));

        return new Client(['handler' => $stack, 'base_uri' => 'https://api.dexpaprika.com']);
    }

    /** @return array<string, string> */
    private function sentQuery(): array
    {
        parse_str($this->sent[0]['request']->getUri()->getQuery(), $query);

        return $query;
    }

    public function testPoolTransactionsForwardFromAndTo(): void
    {
        $api = new PoolsApi($this->client('{"transactions":[],"page_info":{}}'));
        $api->getPoolTransactions('ethereum', self::POOL, [
            'limit' => 20,
            'from' => '-1h',
            'to' => '2026-09-28T12:00:00+02:00',
        ]);

        $query = $this->sentQuery();
        $this->assertSame('-1h', $query['from']);
        // A "+" left unencoded would reach the API as a space.
        $this->assertSame('2026-09-28T12:00:00+02:00', $query['to']);
        $this->assertSame('20', $query['limit']);
    }

    public function testPoolTransactionsKeepUnixSeconds(): void
    {
        $api = new PoolsApi($this->client('{"transactions":[],"page_info":{}}'));
        $api->getPoolTransactions('ethereum', self::POOL, ['from' => 1790000000]);

        $this->assertSame('1790000000', $this->sentQuery()['from']);
    }

    public function testFilterPoolsForwardsRelativeCreatedAfter(): void
    {
        $api = new PoolsApi($this->client('{"results":[],"has_next_page":false,"next_cursor":""}'));
        $api->filterPools('ethereum', ['createdAfter' => '-24h', 'createdBefore' => '2026-09-28']);

        $query = $this->sentQuery();
        $this->assertSame('-24h', $query['created_after']);
        $this->assertSame('2026-09-28', $query['created_before']);
    }

    public function testFilterTokensForwardsRelativeCreatedAfter(): void
    {
        $api = new TokensApi($this->client('{"results":[],"has_next_page":false,"next_cursor":""}'));
        $api->filterTokens('ethereum', ['createdAfter' => '-7d']);

        $this->assertSame('-7d', $this->sentQuery()['created_after']);
    }
}
