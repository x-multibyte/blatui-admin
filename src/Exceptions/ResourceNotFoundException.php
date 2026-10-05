<?php

declare(strict_types=1);

namespace BlatUI\Admin\Exceptions;

use Throwable;

class ResourceNotFoundException extends AdminException
{
    /**
     * HTTP status code.
     */
    protected int $statusCode = 404;

    /**
     * Create a new ResourceNotFoundException instance.
     */
    public function __construct(string $message = 'Resource not found.', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code ?: 404, $previous);
    }

    /**
     * Get the error title.
     */
    public function getTitle(): string
    {
        return $this->title ?? 'Resource Not Found';
    }
}
