<?php

declare(strict_types=1);

namespace DexPaprika\Tests;

use DexPaprika\Api\NetworksApi;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * getNetworkDexes, findNetwork and findDex on $client->networks never worked
 * against the live API: the first called a client property that is never set,
 * the second read a "networks" key from what is a plain list, and the third
 * matched on an "id" field that DEX rows do not carry (they have dex_id).
 * Bodies below are shaped like the live responses of 2026-09-28.
 */
class NetworksLookupTest extends TestCase
{
    private const NETWORKS = '[{"id":"ethereum","display_name":"Ethereum"},{"id":"solana","display_name":"Solana"}]';

    private const DEXES = '{"dexes":['
        . '{"chain":"ethereum","dex_id":"balancer_v2","dex_name":"Balancer V2","protocol":"balancer","volume_usd_24h":1013562.29,"txns_24h":2000,"pools_count":83},'
        . '{"chain":"ethereum","dex_id":"uniswap_v3","dex_name":"Uniswap V3","protocol":"uniswap_v3","volume_usd_24h":512000000.5,"txns_24h":90000,"pools_count":5000}'
        . '],"page_info":{"limit":10,"page":0,"total_items":0,"total_pages":0}}';

    /** @var array<int, array<string, mixed>> */
    private array $sent = [];

    private function api(string ...$bodies): NetworksApi
    {
        $this->sent = [];
        $responses = array_map(static fn (string $b) => new Response(200, [], $b), $bodies);
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->sent));

        return new NetworksApi(new Client(['handler' => $stack, 'base_uri' => 'https://api.dexpaprika.com']));
    }

    private function sentPath(int $i = 0): string
    {
        return $this->sent[$i]['request']->getUri()->getPath();
    }

    /** @return array<string, string> */
    private function sentQuery(int $i = 0): array
    {
        parse_str($this->sent[$i]['request']->getUri()->getQuery(), $query);

        return $query;
    }

    public function testGetNetworkDexesCallsTheDexesEndpoint(): void
    {
        $result = $this->api(self::DEXES)->getNetworkDexes('ethereum', ['page' => 0, 'limit' => 10]);

        $this->assertSame('/networks/ethereum/dexes', $this->sentPath());
        $this->assertSame(['page' => '0', 'limit' => '10'], $this->sentQuery());
        $this->assertSame('uniswap_v3', $result['dexes'][1]['dex_id']);
    }

    public function testGetNetworkDexesAsObjectKeepsTheOptionOffTheWire(): void
    {
        $result = $this->api(self::DEXES)->getNetworkDexes('ethereum', ['asObject' => true]);

        $this->assertIsObject($result);
        $this->assertSame('Uniswap V3', $result->dexes[1]->dex_name);
        $this->assertSame([], $this->sentQuery());
    }

    public function testListNetworkDexesIsTheSameCall(): void
    {
        $result = $this->api(self::DEXES)->listNetworkDexes('ethereum');

        $this->assertSame('/networks/ethereum/dexes', $this->sentPath());
        $this->assertCount(2, $result['dexes']);
    }

    public function testFindNetworkReadsThePlainList(): void
    {
        $network = $this->api(self::NETWORKS)->findNetwork('solana');

        $this->assertSame('/networks', $this->sentPath());
        $this->assertSame(['id' => 'solana', 'display_name' => 'Solana'], $network);
    }

    public function testFindNetworkAsObject(): void
    {
        $network = $this->api(self::NETWORKS)->findNetwork('ethereum', true);

        $this->assertIsObject($network);
        $this->assertSame('Ethereum', $network->display_name);
    }

    public function testFindNetworkStillAcceptsAWrappedList(): void
    {
        $network = $this->api('{"networks":' . self::NETWORKS . '}')->findNetwork('ethereum');

        $this->assertSame('Ethereum', $network['display_name']);
    }

    public function testFindNetworkReturnsNullForAnUnknownId(): void
    {
        $this->assertNull($this->api(self::NETWORKS)->findNetwork('nope'));
        $this->assertNull($this->api(self::NETWORKS)->findNetwork('nope', true));
    }

    public function testFindDexMatchesOnDexId(): void
    {
        $dex = $this->api(self::DEXES)->findDex('ethereum', 'uniswap_v3');

        $this->assertSame('/networks/ethereum/dexes', $this->sentPath());
        $this->assertSame('Uniswap V3', $dex['dex_name']);
    }

    public function testFindDexAsObject(): void
    {
        $dex = $this->api(self::DEXES)->findDex('ethereum', 'balancer_v2', true);

        $this->assertIsObject($dex);
        $this->assertSame('Balancer V2', $dex->dex_name);
    }

    public function testFindDexFallsBackToAnIdField(): void
    {
        $dex = $this->api('{"dexes":[{"id":"curve","name":"Curve"}]}')->findDex('ethereum', 'curve');

        $this->assertSame('Curve', $dex['name']);
    }

    public function testFindDexReturnsNullForAnUnknownId(): void
    {
        $this->assertNull($this->api(self::DEXES)->findDex('ethereum', 'uniswap-v3'));
        $this->assertNull($this->api(self::DEXES)->findDex('ethereum', 'uniswap-v3', true));
    }

    /**
     * The endpoint answers every page with the full list and total_pages 0. The
     * old loop kept going while a page held at least `limit` rows, so it fetched
     * the same list up to maxPages times, forever with maxPages 0.
     */
    public function testFetchAllNetworkDexesStopsWhenTheListIsNotPaginated(): void
    {
        $api = $this->api(self::DEXES, self::DEXES, self::DEXES);
        $calls = 0;

        $pages = $api->fetchAllNetworkDexes('ethereum', function ($dexes, int $page) use (&$calls) {
            $calls++;
            return true;
        }, ['limit' => 2, 'maxPages' => 0]);

        $this->assertSame(1, $pages);
        $this->assertSame(1, $calls);
        $this->assertCount(1, $this->sent);
    }

    public function testFetchAllNetworkDexesFollowsTotalPagesWhenGiven(): void
    {
        $paged = str_replace('"total_pages":0', '"total_pages":2', self::DEXES);
        $api = $this->api($paged, $paged, $paged);

        $pages = $api->fetchAllNetworkDexes('ethereum', fn () => true, ['limit' => 2, 'maxPages' => 0]);

        $this->assertSame(2, $pages);
        $this->assertCount(2, $this->sent);
        $this->assertSame('1', $this->sentQuery(1)['page']);
    }
}
