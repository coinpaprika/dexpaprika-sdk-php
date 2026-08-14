<?php

declare(strict_types=1);

namespace DexPaprika\Tests;

use DexPaprika\Client;
use DexPaprika\Config;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * Optional API key, and the header rules that go with it.
 *
 * Keyless is the default and must keep working untouched. The Bearer rule is the
 * regression this file exists for: `Authorization: Bearer api_...` returns 401
 * because the API checksums the raw header value, and the mistake has resurfaced
 * three times in four months.
 */
class ApiKeyTest extends TestCase
{
    protected function setUp(): void
    {
        putenv(Config::API_KEY_ENV_VAR);
    }

    protected function tearDown(): void
    {
        putenv(Config::API_KEY_ENV_VAR);
    }

    /**
     * Headers that actually reached the wire, rather than what was stored.
     *
     * @return array<string, array<int, string>>
     */
    private function headersFor(?Config $config = null): array
    {
        $sent = [];
        $stack = HandlerStack::create(new MockHandler([new Response(200, [], '[]')]));
        $stack->push(Middleware::history($sent));

        $config ??= new Config();
        $headers = [
            'Accept' => 'application/json',
            'User-Agent' => 'dexpaprika-sdk-php/' . Client::VERSION,
        ];
        $apiKey = $config->getApiKey();
        if ($apiKey !== null) {
            $headers['Authorization'] = $apiKey;
        }

        $guzzle = new GuzzleClient([
            'handler' => $stack,
            'base_uri' => $config->getBaseUrl(),
            'headers' => $headers,
        ]);
        $guzzle->request('GET', '/networks');

        /** @var Request $request */
        $request = $sent[0]['request'];

        return $request->getHeaders();
    }

    private function headerValue(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $values) {
            if (strcasecmp($key, $name) === 0) {
                return $values[0];
            }
        }

        return null;
    }

    // ── The Bearer rule ─────────────────────────────────────────────────────

    public function testKeyIsTheEntireAuthorizationValue(): void
    {
        $config = (new Config())->setApiKey('api_abc123');
        $this->assertSame('api_abc123', $this->headerValue($this->headersFor($config), 'Authorization'));
    }

    public function testNoSchemeWordIsEverPrepended(): void
    {
        $config = (new Config())->setApiKey('api_abc123');
        $value = (string) $this->headerValue($this->headersFor($config), 'Authorization');

        foreach (['Bearer', 'Token', 'ApiKey', 'Basic', 'Key'] as $scheme) {
            $this->assertStringStartsNotWith($scheme, $value);
            $this->assertStringStartsNotWith(strtolower($scheme), strtolower($value));
        }
    }

    // ── Keyless stays the default ───────────────────────────────────────────

    public function testNoKeySendsNoAuthorizationHeader(): void
    {
        $this->assertNull($this->headerValue($this->headersFor(), 'Authorization'));
    }

    public function testBlankKeyIsKeylessNotAnEmptyHeader(): void
    {
        foreach (['', '   ', "\t"] as $blank) {
            $config = (new Config())->setApiKey($blank);
            $this->assertNull($config->getApiKey(), 'blank key should resolve to keyless');
        }
    }

    // ── Precedence ──────────────────────────────────────────────────────────

    public function testEnvironmentVariableIsUsedWhenNoKeyIsSet(): void
    {
        putenv(Config::API_KEY_ENV_VAR . '=api_from_env');
        $this->assertSame('api_from_env', (new Config())->getApiKey());
    }

    public function testExplicitKeyBeatsTheEnvironment(): void
    {
        putenv(Config::API_KEY_ENV_VAR . '=api_from_env');
        $this->assertSame('api_explicit', (new Config())->setApiKey('api_explicit')->getApiKey());
    }

    public function testSurroundingWhitespaceIsTrimmed(): void
    {
        putenv(Config::API_KEY_ENV_VAR . "=  api_padded\n");
        $this->assertSame('api_padded', (new Config())->getApiKey());
    }

    // ── Header injection ────────────────────────────────────────────────────

    public function testKeyWithControlCharactersIsDropped(): void
    {
        foreach (["api_a\r\nX-Evil: 1", "api_a\nb", "api_a\0b"] as $hostile) {
            $this->assertNull(
                (new Config())->setApiKey($hostile)->getApiKey(),
                'a key carrying control characters must be dropped, not mangled'
            );
        }
    }

    // ── Identification ──────────────────────────────────────────────────────

    public function testUserAgentCarriesTheVersion(): void
    {
        $this->assertSame(
            'dexpaprika-sdk-php/' . Client::VERSION,
            $this->headerValue($this->headersFor(), 'User-Agent')
        );
    }

    // ── Host rules ──────────────────────────────────────────────────────────

    public function testAKeyAloneNeverChangesTheHost(): void
    {
        // Free keys are served from the default host; only Pro moves, and a free
        // key sent to the Pro host returns 403.
        $config = (new Config())->setApiKey('api_abc123');
        $this->assertSame('https://api.dexpaprika.com', $config->getBaseUrl());
    }

    public function testProCustomersSetTheHostExplicitly(): void
    {
        $config = (new Config())
            ->setApiKey('api_pro')
            ->setBaseUrl('https://api-pro.dexpaprika.com');
        $this->assertSame('https://api-pro.dexpaprika.com', $config->getBaseUrl());
    }

    public function testTheClientSendsTheKeyItWasConfiguredWith(): void
    {
        // End to end through the real constructor, not a hand-built Guzzle client.
        $config = (new Config())->setApiKey('api_end_to_end');
        $client = new Client(null, null, false, $config);

        $headers = $client->getHttpClient()->getConfig('headers');
        $this->assertSame('api_end_to_end', $headers['Authorization'] ?? null);
        $this->assertStringStartsNotWith('Bearer', (string) ($headers['Authorization'] ?? ''));
    }
}
