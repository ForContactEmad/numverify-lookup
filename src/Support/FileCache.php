<?php

declare(strict_types=1);

namespace Numverify\Support;

/**
 * كاش ملفات بسيط. الهدف الأساسي منه حماية الحصة الشهرية:
 * نتيجة نفس الرقم لا تتغير عملياً خلال أيام، فلا داعي لاستهلاك طلب جديد.
 */
final class FileCache
{
    public function __construct(
        private readonly string $directory,
        private readonly int $ttlSeconds = 86400,
    ) {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0775, true);
        }
    }

    /** @return array<string, mixed>|null */
    public function get(string $key): ?array
    {
        $file = $this->pathFor($key);

        if (!is_file($file)) {
            return null;
        }

        if (filemtime($file) + $this->ttlSeconds < time()) {
            @unlink($file);

            return null;
        }

        $raw = file_get_contents($file);

        if ($raw === false) {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /** @param array<string, mixed> $payload */
    public function put(string $key, array $payload): void
    {
        file_put_contents(
            $this->pathFor($key),
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }

    public function flush(): int
    {
        $files = glob($this->directory . '/*.json') ?: [];

        foreach ($files as $file) {
            @unlink($file);
        }

        return count($files);
    }

    private function pathFor(string $key): string
    {
        return $this->directory . '/' . sha1($key) . '.json';
    }
}
