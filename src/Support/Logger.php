<?php

declare(strict_types=1);

namespace BlatUI\Admin\Support;

use BlatUI\Admin\Admin;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Stringable;
use Throwable;

class Logger extends AbstractLogger implements LoggerInterface
{
    /**
     * Create a new Logger instance.
     */
    public function __construct(
        protected ?LoggerInterface $logger = null,
    ) {}

    /**
     * Get the underlying logger instance.
     */
    public function getLogger(): LoggerInterface
    {
        if ($this->logger !== null) {
            return $this->logger;
        }

        $channel = config('blatui-admin.logging.channel');

        return $channel ? Log::channel((string) $channel) : Log::channel();
    }

    /**
     * Set the underlying logger instance.
     */
    public function setLogger(?LoggerInterface $logger = null): static
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * RFC 5424 / PSR-3 log level priorities.
     *
     * @var array<string, int>
     */
    protected const array LOG_LEVELS = [
        'debug' => 0,
        'info' => 1,
        'notice' => 2,
        'warning' => 3,
        'error' => 4,
        'critical' => 5,
        'alert' => 6,
        'emergency' => 7,
    ];

    /**
     * Log a message with context enrichment and level filtering.
     *
     * @param  mixed  $level
     * @param  array<string, mixed>  $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        if (! config('blatui-admin.logging.enable', true)) {
            return;
        }

        $configuredLevel = (string) config('blatui-admin.logging.level', 'debug');
        $levelStr = strtolower((string) $level);
        $configuredStr = strtolower($configuredLevel);

        if (
            isset(self::LOG_LEVELS[$levelStr], self::LOG_LEVELS[$configuredStr])
            && self::LOG_LEVELS[$levelStr] < self::LOG_LEVELS[$configuredStr]
        ) {
            return;
        }

        $enrichedContext = $this->enrichContext($context);

        $this->getLogger()->log($level, (string) $message, $enrichedContext);
    }

    /**
     * Auto-enrich log context with session and request telemetry.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function enrichContext(array $context): array
    {
        $ip = null;
        $method = null;
        $path = null;

        try {
            if (Container::getInstance()->bound('request')) {
                /** @var Request $request */
                $request = Container::getInstance()->make('request');
                $ip = $request->ip();
                $method = $request->method();
                $path = $request->path();
            }
        } catch (Throwable) {
            // Silently ignore in non-HTTP / CLI contexts
        }

        $enriched = [
            'admin_user_id' => Admin::id(),
            'admin_user' => Admin::user()?->username,
            'ip' => $ip,
            'method' => $method,
            'path' => $path,
        ];

        return array_merge($enriched, $context);
    }
}
