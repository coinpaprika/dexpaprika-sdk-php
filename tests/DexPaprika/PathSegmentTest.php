<?php

namespace DexPaprika\Tests;

use DexPaprika\Api\DexesApi;
use DexPaprika\Api\NetworksApi;
use DexPaprika\Api\PoolsApi;
use DexPaprika\Api\TokensApi;
use DexPaprika\Api\TransactionsApi;
use DexPaprika\Exception\ValidationException;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

/**
 * Ids that go into the request path.
 *
 * Every method that puts a network id, pool address or token address into the
 * URL path must refuse a value that would change that path or add a query, and
 * must send a real id exactly as given. The handler records requests instead of
 * sending them, so nothing leaves the process.
 */
class PathSegmentTest extends TestCase
{
    private const POOL = '0x88e6a0c2ddd26feeb64f039a2c41296fcb3f5640';
    private const TOKEN = '0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2';

    private const BAD = [
        '.', '..', '../../usage', '../../../stats', 'eth/../x', 'a\\b', 'x?limit=1000', 'x#y',
        '%2e%2e', '..%2Fusage', 'a b', "a\nb", "a\0b",
    ];

    /** @var string[] */
    private array $seen = [];

    private function client(): Client
    {
        $this->seen = [];
        $handler = function (RequestInterface $request) {
            $this->seen[] = $request->getUri()->getPath();
            return Create::promiseFor(new Response(200, [], '{}'));
        };
        return new Client(['handler' => HandlerStack::create($handler)]);
    }

    /**
     * Every method that builds a path from caller input. Each takes the network
     * id and the address; only addressCalls() put the address into the path.
     *
     * @return array<string, callable(Client, string, string): mixed>
     */
    private function networkCalls(): array
    {
        return [
            'Pools::getNetworkPools' => fn (Client $c, string $n) => (new PoolsApi($c))->getNetworkPools($n),
            'Pools::filterPools' => fn (Client $c, string $n) => (new PoolsApi($c))->filterPools($n),
            'Tokens::getTokenPools' => fn (Client $c, string $n) => (new TokensApi($c))->getTokenPools($n, self::TOKEN),
            'Tokens::getTopTokens' => fn (Client $c, string $n) => (new TokensApi($c))->getTopTokens($n),
            'Tokens::filterTokens' => fn (Client $c, string $n) => (new TokensApi($c))->filterTokens($n),
            'Tokens::getMultiPrices' => fn (Client $c, string $n) => (new TokensApi($c))->getMultiPrices($n, [self::TOKEN]),
            'Dexes::getNetworkDexes' => fn (Client $c, string $n) => (new DexesApi($c))->getNetworkDexes($n),
            'Dexes::getDexPools' => fn (Client $c, string $n) => (new DexesApi($c))->getDexPools($n, 'uniswap_v3'),
            'Networks::getNetworkDexes' => fn (Client $c, string $n) => (new NetworksApi($c))->getNetworkDexes($n),
        ];
    }

    /** @return array<string, callable(Client, string, string): mixed> */
    private function addressCalls(): array
    {
        return [
            'Pools::getPoolDetails' => fn (Client $c, string $n, string $a) => (new PoolsApi($c))->getPoolDetails($n, $a),
            'Pools::getPoolOHLCV' => fn (Client $c, string $n, string $a) => (new PoolsApi($c))->getPoolOHLCV($n, $a, '-24h', ['interval' => '1h']),
            'Pools::getPoolIndicator' => fn (Client $c, string $n, string $a) => (new PoolsApi($c))->getPoolIndicator($n, $a, 'rsi'),
            'Pools::getPoolTransactions' => fn (Client $c, string $n, string $a) => (new PoolsApi($c))->getPoolTransactions($n, $a),
            'Tokens::getTokenDetails' => fn (Client $c, string $n, string $a) => (new TokensApi($c))->getTokenDetails($n, $a),
            'Tokens::getTokenOHLCV' => fn (Client $c, string $n, string $a) => (new TokensApi($c))->getTokenOHLCV($n, $a, '-24h', ['interval' => '1h']),
            'Tokens::getTokenIndicator' => fn (Client $c, string $n, string $a) => (new TokensApi($c))->getTokenIndicator($n, $a, 'rsi'),
            'Transactions::getPoolTransactions' => fn (Client $c, string $n, string $a) => (new TransactionsApi($c))->getPoolTransactions($n, $a),
        ];
    }

    /**
     * @param array<string, callable> $calls
     * @return string[] what went wrong, one line per case
     */
    private function refusals(array $calls, bool $badNetwork): array
    {
        $problems = [];
        foreach ($calls as $name => $call) {
            foreach (self::BAD as $bad) {
                $client = $this->client();
                $label = sprintf('%s(%s=%s)', $name, $badNetwork ? 'network' : 'address', json_encode($bad));
                try {
                    $badNetwork ? $call($client, $bad, self::POOL) : $call($client, 'ethereum', $bad);
                    $problems[] = "$label: no exception";
                } catch (ValidationException $e) {
                    // expected
                }
                if ($this->seen !== []) {
                    $problems[] = "$label: sent " . $this->seen[0];
                }
            }
        }
        return $problems;
    }

    public function testNetworkIdThatLeavesItsSegmentIsRefusedBeforeSending(): void
    {
        $this->assertSame([], $this->refusals($this->networkCalls() + $this->addressCalls(), true));
    }

    public function testAddressThatLeavesItsSegmentIsRefusedBeforeSending(): void
    {
        $this->assertSame([], $this->refusals($this->addressCalls(), false));
    }

    /** @return array<string, array{string, string}> */
    public function realIds(): array
    {
        return [
            'ethereum' => ['ethereum', self::POOL],
            'bob_network' => ['bob_network', self::TOKEN],
            'solana' => ['solana', 'So11111111111111111111111111111111111111112'],
            'sui' => ['sui', '0x0000000000000000000000000000000000000000000000000000000000000002::sui::SUI'],
            'ton' => ['ton', 'EQA-X_yo3fzzbDbJ_0bzFWKqtRuZFIRa1sJsveZJ1YpViO3r'],
        ];
    }

    /** @dataProvider realIds */
    public function testRealIdsReachThePathUnchanged(string $network, string $address): void
    {
        $client = $this->client();
        (new TokensApi($client))->getTokenDetails($network, $address);
        (new PoolsApi($client))->getPoolDetails($network, $address);
        $this->assertSame([
            "/networks/$network/tokens/$address",
            "/networks/$network/pools/$address",
        ], $this->seen);
    }
}
