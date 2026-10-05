<?php

declare(strict_types=1);

namespace BlatUI\Admin\Exceptions;

use Illuminate\Contracts\Support\MessageBag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class FormValidationException extends AdminException
{
    /**
     * HTTP status code.
     */
    protected int $statusCode = 422;

    /**
     * Create a new FormValidationException instance.
     *
     * @param  array<string, mixed>|MessageBag  $errors
     */
    public function __construct(string $message = 'The given data was invalid.', array|MessageBag $errors = [], int $code = 0, ?Throwable $previous = null)
    {
        $this->errors = $errors instanceof MessageBag ? $errors->toArray() : $errors;

        parent::__construct($message, $code ?: 422, $previous);
    }

    /**
     * Get the error title.
     */
    public function getTitle(): string
    {
        return $this->title ?? 'Validation Error';
    }

    /**
     * Self-render HTTP response for JSON and Web channels.
     */
    public function render(Request $request): Response
    {
        if ($request->expectsJson() || $request->ajax() || $request->isJson()) {
            return new JsonResponse([
                'status' => false,
                'message' => $this->getMessage(),
                'code' => $this->getStatusCode(),
                'errors' => $this->getErrors(),
            ], $this->getStatusCode());
        }

        return redirect()->back()->withInput()->withErrors($this->getErrors());
    }
}
