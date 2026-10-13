<?php

declare(strict_types=1);

namespace DexPaprika\Tests;

use DexPaprika\Api\PoolsApi;
use DexPaprika\Api\TokensApi;
use DexPaprika\Config;
use DexPaprika\Exception\ClientException;
use DexPaprika\Exception\ValidationException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * GET /networks/{network}/pools/{pool_address}/indicators and the token
 * equivalent (technical indicators, live 2026-10-13). Pool indicators work
 * on every plan with the same interval/history limits as pool OHLCV; token
 * indicators need a Dev, Pro or Enterprise plan.
 *
 * Only the parameter the caller set is ever sent: the API refuses a
 * parameter that belongs to another indicator (400 invalid_param), so
 * getPoolIndicator()/getTokenIndicator() must never fill in a default for
 * one the caller did not ask for.
 */
class IndicatorTest extends TestCase
{
    private const POOL = '0x88e6a0c2ddd26feeb64f039a2c41296fcb3f5640';
    private const TOKEN = '0xc02aaa39b223fe8d0a0e5c4f27ead9083c756cc2';

    /** @var array<int, array<string, mixed>> */
    private array $sent = [];

    private function client(string $body, int $status = 200): Client
    {
        $this->sent = [];
        $stack = HandlerStack::create(new MockHandler([new Response($status, [], $body)]));
        $stack->push(Middleware::history($this->sent));

        return new Client(['handler' => $stack, 'base_uri' => 'https://api.dexpaprika.com']);
    }

    /** @return array<string, string> */
    private function sentQuery(): array
    {
        parse_str($this->sent[0]['request']->getUri()->getQuery(), $query);

        return $query;
    }

    private function sentPath(): string
    {
        return $this->sent[0]['request']->getUri()->getPath();
    }

    // --- Query building: pool ---

    public function testGetPoolIndicatorBuildsRsiQuery(): void
    {
        $api = new PoolsApi($this->client('{"data":[]}'));
        $api->getPoolIndicator('ethereum', self::POOL, 'rsi', [
            'interval' => '1h',
            'start' => '-6h',
            'period' => 14,
        ]);

        $this->assertSame('/networks/ethereum/pools/' . self::POOL . '/indicators', $this->sentPath());

        $query = $this->sentQuery();
        $this->assertSame('rsi', $query['indicator']);
        $this->assertSame('1h', $query['interval']);
        $this->assertSame('-6h', $query['start']);
        $this->assertSame('14', $query['period']);
        $this->assertArrayNotHasKey('fast_period', $query);
        $this->assertArrayNotHasKey('std_dev', $query);
    }

    public function testGetPoolIndicatorBuildsMacdQuery(): void
    {
        $api = new PoolsApi($this->client('{"data":[]}'));
        $api->getPoolIndicator('ethereum', self::POOL, 'macd', [
            'interval' => '1h',
            'fast_period' => 12,
            'slow_period' => 26,
            'signal_period' => 9,
        ]);

        $query = $this->sentQuery();
        $this->assertSame('macd', $query['indicator']);
        $this->assertSame('12', $query['fast_period']);
        $this->assertSame('26', $query['slow_period']);
        $this->assertSame('9', $query['signal_period']);
        // macd does not take period; the caller never set it, so it must not appear.
        $this->assertArrayNotHasKey('period', $query);
    }

    public function testGetPoolIndicatorBuildsBbandsQuery(): void
    {
        $api = new PoolsApi($this->client('{"data":[]}'));
        $api->getPoolIndicator('ethereum', self::POOL, 'bbands', [
            'period' => 20,
            'std_dev' => 2,
        ]);

        $query = $this->sentQuery();
        $this->assertSame('bbands', $query['indicator']);
        $this->assertSame('20', $query['period']);
        $this->assertSame('2', $query['std_dev']);
    }

    public function testGetPoolIndicatorSendsOnlyCallerSetParams(): void
    {
        // No interval, limit, start, end, cursor or any period option was set:
        // only "indicator" may reach the wire.
        $api = new PoolsApi($this->client('{"data":[]}'));
        $api->getPoolIndicator('ethereum', self::POOL, 'vwap');

        $this->assertSame(['indicator' => 'vwap'], $this->sentQuery());
    }

    public function testGetPoolIndicatorForwardsCursorAndLimit(): void
    {
        $api = new PoolsApi($this->client('{"data":[]}'));
        $api->getPoolIndicator('ethereum', self::POOL, 'ema', [
            'cursor' => '1791882000',
            'limit' => 50,
        ]);

        $query = $this->sentQuery();
        $this->assertSame('1791882000', $query['cursor']);
        $this->assertSame('50', $query['limit']);
        $this->assertArrayNotHasKey('start', $query);
    }

    // --- Query building: token ---

    public function testGetTokenIndicatorBuildsRsiQuery(): void
    {
        $config = new Config();
        $config->setApiKey('api_dev_key');
        $api = new TokensApi($this->client('{"data":[]}'), false, $config);

        $api->getTokenIndicator('ethereum', self::TOKEN, 'rsi', [
            'interval' => '1h',
            'start' => '-6h',
        ]);

        $this->assertSame('/networks/ethereum/tokens/' . self::TOKEN . '/indicators', $this->sentPath());

        $query = $this->sentQuery();
        $this->assertSame('rsi', $query['indicator']);
        $this->assertSame('1h', $query['interval']);
        $this->assertSame('-6h', $query['start']);
    }

    public function testGetTokenIndicatorSendsOnlyCallerSetParams(): void
    {
        $api = new TokensApi($this->client('{"data":[]}'));
        $api->getTokenIndicator('ethereum', self::TOKEN, 'macd', ['fast_period' => 12]);

        $this->assertSame(['indicator' => 'macd', 'fast_period' => '12'], $this->sentQuery());
    }

    // --- Validation ---

    public function testGetPoolIndicatorRejectsUnknownIndicator(): void
    {
        $api = new PoolsApi($this->client('{}'));

        $this->expectException(ValidationException::class);
        $api->getPoolIndicator('ethereum', self::POOL, 'sma');
    }

    public function testGetTokenIndicatorRejectsUnknownIndicator(): void
    {
        $api = new TokensApi($this->client('{}'));

        $this->expectException(ValidationException::class);
        $api->getTokenIndicator('ethereum', self::TOKEN, 'sma');
    }

    public function testGetPoolIndicatorRejectsOutOfRangeLimit(): void
    {
        $api = new PoolsApi($this->client('{}'));

        $this->expectException(ValidationException::class);
        $api->getPoolIndicator('ethereum', self::POOL, 'rsi', ['limit' => 1001]);
    }

    public function testGetTokenIndicatorValidatesNetworkAndTokenAddress(): void
    {
        $api = new TokensApi($this->client('{}'));

        try {
            $api->getTokenIndicator('', self::TOKEN, 'rsi');
            $this->fail('Expected ValidationException was not thrown for an empty network ID');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Network ID', $e->getMessage());
        }

        try {
            $api->getTokenIndicator('ethereum', '', 'rsi');
            $this->fail('Expected ValidationException was not thrown for an empty token address');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Token address', $e->getMessage());
        }
    }

    // --- Response parsing (live samples, captured 2026-10-13) ---

    public function testGetPoolIndicatorParsesRsiShape(): void
    {
        // Live GET /networks/ethereum/pools/0x88e6.../indicators?indicator=rsi&interval=1h
        $body = '{"network":"ethereum","pool_address":"0x88e6a0c2ddd26feeb64f039a2c41296fcb3f5640","indicator":"rsi","interval":"1h","parameters":{"period":14},"data":[{"timestamp":"2026-10-13T06:00:00Z","value":54.38666301724629},{"timestamp":"2026-10-13T07:00:00Z","value":53.184343874602874},{"timestamp":"2026-10-13T08:00:00Z","value":51.087952277014196}],"has_next_page":true,"next_cursor":"1791882000"}';

        $api = new PoolsApi($this->client($body));
        $result = $api->getPoolIndicator('ethereum', self::POOL, 'rsi', ['interval' => '1h']);

        $this->assertSame('rsi', $result['indicator']);
        $this->assertSame(14, $result['parameters']['period']);
        $this->assertCount(3, $result['data']);
        $this->assertSame('2026-10-13T06:00:00Z', $result['data'][0]['timestamp']);
        $this->assertEqualsWithDelta(54.38666301724629, $result['data'][0]['value'], 0.0000001);
        $this->assertTrue($result['has_next_page']);
        $this->assertSame('1791882000', $result['next_cursor']);
    }

    public function testGetPoolIndicatorParsesMacdShape(): void
    {
        // Live GET /networks/ethereum/pools/0x88e6.../indicators?indicator=macd&interval=1h
        $body = '{"network":"ethereum","pool_address":"0x88e6a0c2ddd26feeb64f039a2c41296fcb3f5640","indicator":"macd","interval":"1h","parameters":{"fast_period":12,"signal_period":9,"slow_period":26},"data":[{"histogram":11.220674833348312,"macd":-11.534946379135818,"signal":-22.75562121248413,"timestamp":"2026-10-13T06:00:00Z"},{"histogram":11.175592243450591,"macd":-8.78613090817089,"signal":-19.96172315162148,"timestamp":"2026-10-13T07:00:00Z"},{"histogram":10.220464690057415,"macd":-7.186142289049712,"signal":-17.406606979107128,"timestamp":"2026-10-13T08:00:00Z"}],"has_next_page":true,"next_cursor":"1791882000"}';

        $api = new PoolsApi($this->client($body));
        $result = $api->getPoolIndicator('ethereum', self::POOL, 'macd', ['interval' => '1h']);

        $this->assertSame('macd', $result['indicator']);
        $this->assertSame(12, $result['parameters']['fast_period']);
        $this->assertSame(26, $result['parameters']['slow_period']);
        $this->assertCount(3, $result['data']);
        $this->assertEqualsWithDelta(-11.534946379135818, $result['data'][0]['macd'], 0.0000001);
        $this->assertEqualsWithDelta(-22.75562121248413, $result['data'][0]['signal'], 0.0000001);
        $this->assertEqualsWithDelta(11.220674833348312, $result['data'][0]['histogram'], 0.0000001);
    }

    public function testGetPoolIndicatorAsObjectTransformsMacdShape(): void
    {
        $body = '{"network":"ethereum","pool_address":"0x88e6a0c2ddd26feeb64f039a2c41296fcb3f5640","indicator":"macd","interval":"1h","parameters":{"fast_period":12,"signal_period":9,"slow_period":26},"data":[{"histogram":11.220674833348312,"macd":-11.534946379135818,"signal":-22.75562121248413,"timestamp":"2026-10-13T06:00:00Z"}],"has_next_page":false,"next_cursor":""}';

        $api = new PoolsApi($this->client($body));
        $result = $api->getPoolIndicator('ethereum', self::POOL, 'macd', ['interval' => '1h', 'asObject' => true]);

        $this->assertIsObject($result);
        $this->assertSame('macd', $result->indicator);
        $this->assertEqualsWithDelta(-11.534946379135818, $result->data[0]->macd, 0.0000001);
    }

    // --- Errors ---

    public function testGetTokenIndicatorOnA403SurfacesPlanRequired(): void
    {
        // Token indicators need a Dev, Pro or Enterprise plan; a keyless or
        // free-key call gets 403 plan_required with required_tier "dev".
        $body = json_encode([
            'error' => 'plan_required',
            'message' => 'this endpoint requires a Dev, Pro or Enterprise plan',
            'required_tier' => 'dev',
        ]);

        $api = new TokensApi($this->client((string) $body, 403));

        try {
            $api->getTokenIndicator('ethereum', self::TOKEN, 'rsi', ['interval' => '1h', 'start' => '-6h']);
            $this->fail('Expected ClientException was not thrown');
        } catch (ClientException $e) {
            $this->assertSame(403, $e->getCode());
            $this->assertSame('this endpoint requires a Dev, Pro or Enterprise plan', $e->getMessage());
            $this->assertSame('dev', $e->getErrorData()['required_tier']);
        }
    }

    public function testGetPoolIndicatorOnInvalidParamSurfacesTheApiMessage(): void
    {
        // indicator=rsi&fast_period=3 is refused: fast_period is macd-only.
        $body = json_encode([
            'error' => 'invalid_param',
            'message' => 'invalid query parameters: fast_period (unsupported parameter for rsi)',
            'param' => 'fast_period',
        ]);

        $api = new PoolsApi($this->client((string) $body, 400));

        try {
            // The SDK does not cross-validate parameters against the chosen
            // indicator client-side; it forwards whatever the caller sets and
            // lets the API's own 400 surface through the exception.
            $api->getPoolIndicator('ethereum', self::POOL, 'rsi', ['fast_period' => 3]);
            $this->fail('Expected ClientException was not thrown');
        } catch (ClientException $e) {
            $this->assertSame(400, $e->getCode());
            $this->assertStringContainsString('fast_period', $e->getMessage());
            $this->assertSame('fast_period', $e->getErrorData()['param']);
        }
    }
}
