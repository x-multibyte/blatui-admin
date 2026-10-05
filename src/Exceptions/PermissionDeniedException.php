<?php

declare(strict_types=1);

namespace BlatUI\Admin\Exceptions;

use Throwable;

class PermissionDeniedException extends AdminException
{
    /**
     * HTTP status code.
     */
    protected int $statusCode = 403;

    /**
     * Create a new PermissionDeniedException instance.
     */
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        if ($message === '') {
            $translated = __('blatui-admin::admin.deny');
            $message = is_string($translated) && $translated !== 'blatui-admin::admin.deny'
                ? $translated
                : 'Permission denied.';
        }

        parent::__construct($message, $code ?: 403, $previous);
    }

    /**
     * Get the error title.
     */
    public function getTitle(): string
    {
        if ($this->title !== null) {
            return $this->title;
        }

        $translated = __('blatui-admin::admin.deny');

        return is_string($translated) && $translated !== 'blatui-admin::admin.deny'
            ? $translated
            : 'Permission denied.';
    }
}
