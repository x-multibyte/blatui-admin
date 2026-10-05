<?php

declare(strict_types=1);

namespace BlatUI\Admin\Exceptions;

use Throwable;

class ConfigurationException extends AdminException
{
    /**
     * HTTP status code.
     */
    protected int $statusCode = 500;

    /**
     * Create a new ConfigurationException instance.
     */
    public function __construct(string $message = 'Invalid configuration.', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code ?: 500, $previous);
    }

    /**
     * Get the error title.
     */
    public function getTitle(): string
    {
        return $this->title ?? 'Configuration Error';
    }
}
