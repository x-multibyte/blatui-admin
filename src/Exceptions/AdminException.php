<?php

declare(strict_types=1);

namespace BlatUI\Admin\Exceptions;

use BlatUI\Admin\Admin;
use BlatUI\Admin\Layout\Content;
use Illuminate\Contracts\Support\MessageBag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AdminException extends RuntimeException implements Throwable
{
    /**
     * HTTP status code.
     */
    protected int $statusCode = 500;

    /**
     * Contextual debug data.
     *
     * @var array<string, mixed>
     */
    protected array $context = [];

    /**
     * Error page title.
     */
    protected ?string $title = null;

    /**
     * Validation error messages.
     *
     * @var array<string, mixed>
     */
    protected array $errors = [];

    /**
     * Create a new AdminException instance.
     */
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        if ($code >= 100 && $code <= 599) {
            $this->statusCode = $code;
        }

        parent::__construct($message, $code ?: $this->statusCode, $previous);
    }

    /**
     * Get the HTTP status code.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Set the HTTP status code.
     */
    public function setStatusCode(int $statusCode): static
    {
        if ($statusCode >= 100 && $statusCode <= 599) {
            $this->statusCode = $statusCode;

            if ($this->code === 0 || ($this->code >= 100 && $this->code <= 599)) {
                $this->code = $statusCode;
            }
        }

        return $this;
    }

    /**
     * Get the contextual data.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Get contextual data for Laravel exception reporting.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->getContext();
    }

    /**
     * Merge contextual data.
     *
     * @param  array<string, mixed>  $context
     */
    public function withContext(array $context): static
    {
        $this->context = array_merge($this->context, $context);

        return $this;
    }

    /**
     * Get the error title.
     */
    public function getTitle(): string
    {
        return $this->title ?? 'Error';
    }

    /**
     * Set the error title.
     */
    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get validation errors.
     *
     * @return array<string, mixed>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Set validation errors.
     *
     * @param  array<string, mixed>|MessageBag  $errors
     */
    public function withErrors(array|MessageBag $errors): static
    {
        $this->errors = $errors instanceof MessageBag ? $errors->toArray() : $errors;

        return $this;
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
                'code' => $this->getCode() ?: $this->getStatusCode(),
                'errors' => $this->getErrors(),
            ], $this->getStatusCode());
        }

        try {
            $content = Admin::content(function (Content $content): void {
                $content->title($this->getTitle());
                $content->bodyView('blatui-admin::errors.page', [
                    'title' => $this->getTitle(),
                    'message' => $this->getMessage(),
                    'statusCode' => $this->getStatusCode(),
                ]);
            });

            return response($content->render(), $this->getStatusCode(), [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]);
        } catch (Throwable) {
            return response(view('blatui-admin::errors.page', [
                'title' => $this->getTitle(),
                'message' => $this->getMessage(),
                'statusCode' => $this->getStatusCode(),
            ])->render(), $this->getStatusCode(), [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]);
        }
    }
}
