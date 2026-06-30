<?php
/**
 * Safe JSON-lines logger with daily files. Sensitive data must be filtered before reaching this class.
 */

declare(strict_types=1);

namespace GrailHr\Shared\Infrastructure\Logging;

final class Logger
{
    public function __construct(private readonly string $logDir)
    {
    }

    /** @param array<string, mixed> $context */
    public function info(string $channel, string $message, array $context = []): void
    {
        $this->write('info', $channel, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function warning(string $channel, string $message, array $context = []): void
    {
        $this->write('warning', $channel, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function error(string $channel, string $message, array $context = []): void
    {
        $this->write('error', $channel, $message, $context);
    }


    /** @return list<array<string, mixed>> */
    public function recent(int $limit = 200): array
    {
        $file = $this->logDir . '/grail-hr-' . gmdate('Y-m-d') . '.log';

        if (!is_readable($file)) {
            return [];
        }

        $lines = array_slice(file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], -absint($limit));
        $records = [];

        foreach ($lines as $line) {
            $record = json_decode((string) $line, true);

            if (is_array($record)) {
                $records[] = $record;
            }
        }

        return array_reverse($records);
    }

    /** @param array<string, mixed> $context */
    private function write(string $level, string $channel, string $message, array $context): void
    {
        $record = [
            'time' => gmdate('c'),
            'level' => $level,
            'channel' => sanitize_key($channel),
            'message' => sanitize_text_field($message),
            'context' => $this->sanitizeContext($context),
        ];

        try {
            if (!is_dir($this->logDir)) {
                wp_mkdir_p($this->logDir);
                file_put_contents($this->logDir . '/index.php', "<?php\n// Silence is golden.\n", LOCK_EX);
                file_put_contents($this->logDir . '/.htaccess', "Deny from all\n", LOCK_EX);
            }

            $file = $this->logDir . '/grail-hr-' . gmdate('Y-m-d') . '.log';
            file_put_contents($file, wp_json_encode($record) . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (\Throwable) {
            error_log('[grail-hr] ' . $level . ' ' . $channel . ' ' . $message);
        }
    }

    /** @param array<string, mixed> $context */
    private function sanitizeContext(array $context): array
    {
        $blocked = ['token', 'api_key', 'authorization', 'cv_text', 'raw_response', 'password'];
        $safe = [];

        foreach ($context as $key => $value) {
            if (in_array(strtolower((string) $key), $blocked, true)) {
                $safe[$key] = '[redacted]';
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $safe[$key] = is_string($value) ? sanitize_text_field($value) : $value;
            }
        }

        return $safe;
    }
}
