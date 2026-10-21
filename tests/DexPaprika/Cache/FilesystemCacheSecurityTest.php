<?php

namespace DexPaprika\Tests\Cache;

use DexPaprika\Cache\FilesystemCache;
use PHPUnit\Framework\TestCase;

/**
 * On a shared host, FilesystemCache's old default directory
 * (sys_get_temp_dir() . '/dexpaprika_cache') was reused if it already
 * existed, with no check of who owned it or who could write to it, and
 * entries were PHP serialize() read back with unserialize() and no
 * allowed_classes restriction. Another local account could pre-create the
 * directory, plant forged responses, or point an entry at a symlink.
 *
 * This covers the hardening: a private, per-user default directory, a
 * refusal to trust an existing directory that is not private, JSON instead
 * of serialize()/unserialize(), and an HMAC check that makes a file this
 * class did not write (or cannot prove it wrote) behave as a cache miss
 * instead of trusted data.
 */
class FilesystemCacheSecurityTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/dexpaprika-cache-sec-test-' . uniqid();
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->cacheDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? $this->removeDirectory($path) : @unlink($path);
        }

        @rmdir($dir);
    }

    /**
     * The default directory is private to the current user and named after
     * them, rather than the one fixed, predictable path every local account
     * on the host would reuse.
     */
    public function testDefaultDirectoryIsPrivateAndPerUser(): void
    {
        $expectedSuffix = function_exists('posix_getuid') ? (string) posix_getuid() : get_current_user();
        $defaultDir = sys_get_temp_dir() . '/dexpaprika_cache_' . $expectedSuffix;

        // Start from a clean slate so a leftover directory from a previous
        // run (possibly with different permissions) cannot make this test
        // pass or fail for the wrong reason.
        $this->removeDirectoryAt($defaultDir);

        try {
            $cache = new FilesystemCache(null, 60);
            $cache->set('probe-key', 'probe-value');

            $this->assertDirectoryExists($defaultDir);

            $mode = fileperms($defaultDir) & 0777;
            $this->assertSame(0700, $mode, sprintf('Expected the default cache directory at mode 0700, got %04o', $mode));

            if (function_exists('posix_getuid')) {
                $this->assertSame(posix_getuid(), fileowner($defaultDir));
            }
        } finally {
            $this->removeDirectoryAt($defaultDir);
        }
    }

    private function removeDirectoryAt(string $dir): void
    {
        $saved = $this->cacheDir;
        $this->cacheDir = $dir;
        $this->removeDirectory($dir);
        $this->cacheDir = $saved;
    }

    /**
     * A pre-existing directory that other accounts can write into (or read)
     * must never be trusted silently; constructing the cache against it
     * refuses instead.
     */
    public function testWorldWritableExistingDirectoryIsRefused(): void
    {
        mkdir($this->cacheDir, 0700, true);
        chmod($this->cacheDir, 0777);

        $this->expectException(\RuntimeException::class);
        new FilesystemCache($this->cacheDir);
    }

    /**
     * Group-readable is still not private to the owner, even without a
     * write bit set.
     */
    public function testGroupReadableExistingDirectoryIsRefused(): void
    {
        mkdir($this->cacheDir, 0700, true);
        chmod($this->cacheDir, 0750);

        $this->expectException(\RuntimeException::class);
        new FilesystemCache($this->cacheDir);
    }

    /**
     * The flip side of the two refusals above: a directory that genuinely
     * is private to the owner is accepted and usable, so the hardening does
     * not break a caller who passes their own directory correctly.
     */
    public function testPrivateExistingDirectoryIsAccepted(): void
    {
        mkdir($this->cacheDir, 0700, true);
        chmod($this->cacheDir, 0700);

        $cache = new FilesystemCache($this->cacheDir);
        $cache->set('k', 'v');
        $this->assertSame('v', $cache->get('k'));
    }

    /**
     * The on-disk format is JSON, not PHP's serialize(), which is what made
     * an unauthenticated unserialize() of a planted file a PHP object
     * injection risk rather than just a bad-data risk.
     */
    public function testCacheFileIsJsonNotPhpSerialize(): void
    {
        mkdir($this->cacheDir, 0700, true);
        chmod($this->cacheDir, 0700);

        $cache = new FilesystemCache($this->cacheDir);
        $cache->set('json-key', ['a' => 1, 'b' => [2, 3]]);

        $cacheFile = $this->cacheDir . '/' . md5('json-key') . '.cache';
        $this->assertFileExists($cacheFile);

        $raw = file_get_contents($cacheFile);
        $this->assertNotFalse($raw);
        $this->assertStringStartsNotWith('a:', $raw);
        $this->assertStringNotContainsString('O:', $raw);
        $this->assertStringStartsWith('{', $raw);

        $decoded = json_decode($raw, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('mac', $decoded);
        $this->assertArrayHasKey('data', $decoded);

        $this->assertSame(['a' => 1, 'b' => [2, 3]], $cache->get('json-key'));
    }

    /**
     * A file at a cache path that was not produced by this class's own
     * set() (no valid mac for the per-installation key) is a cache miss,
     * not trusted data handed back to the caller.
     */
    public function testForeignCacheFileIsTreatedAsAMissNotTrustedData(): void
    {
        mkdir($this->cacheDir, 0700, true);
        chmod($this->cacheDir, 0700);

        $cache = new FilesystemCache($this->cacheDir);

        $key = 'planted-key';
        $cacheFile = $this->cacheDir . '/' . md5($key) . '.cache';
        file_put_contents($cacheFile, json_encode([
            'mac' => 'not-a-real-mac',
            'data' => json_encode(['value' => 'forged', 'expires_at' => null]),
        ]));

        $this->assertNull($cache->get($key));
        $this->assertFalse($cache->has($key));
    }

    /**
     * The old format at the same path (PHP serialize(), no mac at all) must
     * not be unserialize()'d and returned either. This is the shape the
     * pre-fix code trusted unconditionally.
     */
    public function testLegacySerializedPayloadAtCachePathIsNeverUnserialized(): void
    {
        mkdir($this->cacheDir, 0700, true);
        chmod($this->cacheDir, 0700);

        $cache = new FilesystemCache($this->cacheDir);

        $key = 'legacy-format-key';
        $cacheFile = $this->cacheDir . '/' . md5($key) . '.cache';
        file_put_contents($cacheFile, serialize(['value' => 'legacy', 'expires_at' => null]));

        $this->assertNull($cache->get($key));
    }
}
