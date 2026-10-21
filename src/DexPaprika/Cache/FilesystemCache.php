<?php

declare(strict_types=1);

namespace DexPaprika\Cache;

/**
 * Filesystem-based cache implementation.
 *
 * Every cache file lives under a directory private to the user running the
 * process. The default directory name is suffixed with that user's id, so
 * two accounts on a shared host never land on the same path. A directory
 * that already exists there (ours from an earlier run, or one planted by
 * someone else before we ran) is trusted only after checking it: owned by
 * the current user and carrying no permission bit for anyone else. Anything
 * else throws instead of being silently reused, because another local
 * account could otherwise pre-create the directory and plant forged
 * responses, or a symlink, ahead of us.
 *
 * Entries are stored as JSON, never PHP serialize()/unserialize(), and each
 * one is authenticated with an HMAC keyed by a random, per-installation
 * secret generated once and stored privately inside the cache directory. A
 * file at a cache path that was not written through this class, or whose
 * ownership does not match the current user, is treated as a cache miss
 * rather than trusted data; it is never unserialized or returned. Writes go
 * through a temporary file and rename() so a pre-planted symlink at the
 * target path is replaced rather than written through.
 */
class FilesystemCache implements CacheInterface
{
    /**
     * @var string Cache directory path
     */
    private string $cacheDir;

    /**
     * @var int Default TTL in seconds (7 days)
     */
    private int $defaultTtl;

    /**
     * @var string Random, per-installation key used to authenticate cache entries
     */
    private string $hmacKey;

    /**
     * Constructor
     *
     * @param string|null $cacheDir Cache directory (null for a private directory under the system temp directory)
     * @param int $defaultTtl Default time-to-live in seconds
     * @throws \RuntimeException If the directory cannot be created, or an existing one is not private to the
     *  current user
     */
    public function __construct(?string $cacheDir = null, int $defaultTtl = 604800)
    {
        $this->cacheDir = $cacheDir ?? self::defaultCacheDir();
        $this->defaultTtl = $defaultTtl;

        $this->prepareDirectory($this->cacheDir);
        $this->hmacKey = $this->loadOrCreateHmacKey();
    }

    /**
     * The default cache directory: the system temp directory, suffixed with
     * an identifier for the current user so it is never shared across
     * accounts on a multi-user host.
     *
     * @return string
     */
    private static function defaultCacheDir(): string
    {
        return sys_get_temp_dir() . '/dexpaprika_cache_' . self::currentUserSuffix();
    }

    /**
     * An identifier for the user running this process, safe to use in a
     * directory name. Prefers the numeric uid (stable even if the account is
     * renamed); falls back to the process owner's login name, then to the
     * USER environment variable, when the posix extension is not loaded.
     *
     * @return string
     */
    private static function currentUserSuffix(): string
    {
        if (function_exists('posix_getuid')) {
            return (string) posix_getuid();
        }

        $user = get_current_user();
        if ($user === '' || $user === false) {
            $user = (string) getenv('USER');
        }

        $safe = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $user);

        return $safe !== '' && $safe !== null ? $safe : 'default';
    }

    /**
     * Make sure $dir exists and is private to the current user, creating it
     * if needed.
     *
     * A fresh directory is created with mode 0700, then chmod()'d to 0700
     * again right after: mkdir()'s mode argument is itself narrowed by the
     * process umask, so the requested 0700 can come out wider than asked.
     * Whether the directory was just created or already existed, it is
     * checked before being trusted; a directory swapped in between the
     * mkdir() and this check (or simply pre-existing from another account)
     * is caught the same way.
     *
     * @throws \RuntimeException
     */
    private function prepareDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0700, true) && !is_dir($dir)) {
                throw new \RuntimeException("Could not create cache directory: $dir");
            }
            @chmod($dir, 0700);
        }

        $this->assertPathIsPrivate($dir, 'Cache directory');
    }

    /**
     * Refuse a path that is a symlink, not owned by the current user, or
     * carries any permission bit for group or other.
     *
     * @throws \RuntimeException
     */
    private function assertPathIsPrivate(string $path, string $label): void
    {
        $stat = @lstat($path);
        if ($stat === false) {
            throw new \RuntimeException("Could not stat $label: $path");
        }

        if ((($stat['mode'] ?? 0) & 0170000) === 0120000) {
            throw new \RuntimeException("$label is a symlink, refusing to use it: $path");
        }

        $mode = $stat['mode'] & 0777;
        if (($mode & 0077) !== 0) {
            throw new \RuntimeException(sprintf(
                '%s %s is readable or writable by other accounts (mode %04o). Remove it and let '
                    . 'the SDK recreate it privately, or point the SDK at a directory only you can access.',
                $label,
                $path,
                $mode
            ));
        }

        if (function_exists('posix_getuid') && isset($stat['uid']) && $stat['uid'] !== posix_getuid()) {
            throw new \RuntimeException("$label $path is not owned by the current user.");
        }
    }

    /**
     * Whether $path is safe to read as this process's own cache data: not a
     * symlink, and owned by the user running this process. Used on every
     * read so a file that was not written through this class, by this user,
     * is never unserialized or returned, even if it happens to carry a
     * valid-looking name.
     *
     * @return bool
     */
    private function isOwnedByCurrentUser(string $path): bool
    {
        $stat = @lstat($path);
        if ($stat === false) {
            return false;
        }

        if ((($stat['mode'] ?? 0) & 0170000) === 0120000) {
            return false;
        }

        if (function_exists('posix_getuid') && isset($stat['uid']) && $stat['uid'] !== posix_getuid()) {
            return false;
        }

        return true;
    }

    /**
     * Load the per-installation HMAC key from the cache directory, creating
     * one if it is missing. The key is random (random_bytes), generated
     * once, and stored in a file only the owner can read.
     *
     * @throws \RuntimeException
     */
    private function loadOrCreateHmacKey(): string
    {
        $keyFile = $this->cacheDir . '/.hmac_key';

        if (is_file($keyFile) && $this->isOwnedByCurrentUser($keyFile)) {
            $key = file_get_contents($keyFile);
            if (is_string($key) && $key !== '') {
                return $key;
            }
        }

        $key = random_bytes(32);
        $this->writePrivateFile($keyFile, $key);

        return $key;
    }

    /**
     * Write $contents to $path without ever writing through an existing
     * symlink at that path, and without a reader observing a half-written
     * file. The content lands in a uniquely named temporary file in the same
     * directory, then rename() replaces whatever was at $path, link or
     * file, with it atomically.
     *
     * @throws \RuntimeException
     */
    private function writePrivateFile(string $path, string $contents): void
    {
        $tmpPath = $path . '.tmp-' . bin2hex(random_bytes(8));

        if (file_put_contents($tmpPath, $contents) === false) {
            throw new \RuntimeException("Could not write cache file: $tmpPath");
        }
        chmod($tmpPath, 0600);

        if (!rename($tmpPath, $path)) {
            @unlink($tmpPath);
            throw new \RuntimeException("Could not finalize cache file: $path");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key): mixed
    {
        $filename = $this->getFilename($key);

        if (!is_file($filename) || !$this->isOwnedByCurrentUser($filename)) {
            return null;
        }

        $content = file_get_contents($filename);
        if ($content === false) {
            return null;
        }

        $entry = $this->decodeAuthenticated($content);
        if ($entry === null) {
            return null;
        }

        // Check if item is expired
        if ($entry['expires_at'] !== null && $entry['expires_at'] < time()) {
            $this->delete($key);
            return null;
        }

        return $entry['value'];
    }

    /**
     * Verify and decode a cache entry written by {@see set()}.
     *
     * The file holds {"mac": "...", "data": "<json>"}. "data" is the exact
     * JSON string the mac was computed over, kept as a string rather than a
     * re-encoded structure so verification never depends on json_encode()
     * producing byte-identical output on read and write. Anything that does
     * not parse, is missing either field, or whose mac does not match is
     * not our data; null is returned so the caller treats it as a miss
     * rather than trusting or throwing on it.
     *
     * @return array{value: mixed, expires_at: int|null}|null
     */
    private function decodeAuthenticated(string $content): ?array
    {
        $wrapper = json_decode($content, true);
        if (
            !is_array($wrapper)
            || !isset($wrapper['mac'], $wrapper['data'])
            || !is_string($wrapper['mac'])
            || !is_string($wrapper['data'])
        ) {
            return null;
        }

        $expectedMac = hash_hmac('sha256', $wrapper['data'], $this->hmacKey);
        if (!hash_equals($expectedMac, $wrapper['mac'])) {
            return null;
        }

        $entry = json_decode($wrapper['data'], true);
        if (!is_array($entry) || !array_key_exists('value', $entry) || !array_key_exists('expires_at', $entry)) {
            return null;
        }

        return $entry;
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $filename = $this->getFilename($key);
        $ttl = $ttl ?? $this->defaultTtl;

        $entry = [
            'value' => $value,
            'expires_at' => $ttl > 0 ? time() + $ttl : null,
        ];

        $dataJson = json_encode($entry);
        if ($dataJson === false) {
            return false;
        }

        $mac = hash_hmac('sha256', $dataJson, $this->hmacKey);
        $wrapped = json_encode(['mac' => $mac, 'data' => $dataJson]);
        if ($wrapped === false) {
            return false;
        }

        try {
            $this->writePrivateFile($filename, $wrapped);
        } catch (\RuntimeException $e) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        $filename = $this->getFilename($key);

        if (file_exists($filename)) {
            return unlink($filename);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        $files = glob($this->cacheDir . '/*.cache');

        if ($files === false) {
            return false;
        }

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        return true;
    }

    /**
     * Get the cache filename for a key
     *
     * @param string $key Cache key
     * @return string Full path to cache file
     */
    private function getFilename(string $key): string
    {
        $safeName = md5($key);
        return $this->cacheDir . '/' . $safeName . '.cache';
    }
}
