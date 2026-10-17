<?php

declare(strict_types=1);

namespace BlatUI\Admin\Console\Installer\Diagnostics;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * @implements IteratorAggregate<int, CheckResult>
 */
final class DiagnosticReport implements Countable, IteratorAggregate
{
    /**
     * @var array<int, CheckResult>
     */
    private array $results;

    /**
     * @param  array<int, CheckResult>  $results
     */
    public function __construct(array $results = [])
    {
        $this->results = array_values($results);
    }

    public function add(CheckResult $result): self
    {
        $this->results[] = $result;

        return $this;
    }

    /**
     * @return array<int, CheckResult>
     */
    public function results(): array
    {
        return $this->results;
    }

    public function count(): int
    {
        return count($this->results);
    }

    /**
     * @return Traversable<int, CheckResult>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->results);
    }

    public function passCount(): int
    {
        return count(array_filter($this->results, fn (CheckResult $r): bool => $r->isPass()));
    }

    public function warnCount(): int
    {
        return count(array_filter($this->results, fn (CheckResult $r): bool => $r->isWarn()));
    }

    public function failCount(): int
    {
        return count(array_filter($this->results, fn (CheckResult $r): bool => $r->isFail()));
    }

    public function hasCriticalFailures(): bool
    {
        foreach ($this->results as $result) {
            if ($result->isCritical()) {
                return true;
            }
        }

        return false;
    }

    public function hasWarnings(): bool
    {
        return $this->warnCount() > 0;
    }

    /**
     * @return array<int, CheckResult>
     */
    public function failures(): array
    {
        return array_values(array_filter(
            $this->results,
            fn (CheckResult $r): bool => $r->isFail(),
        ));
    }

    /**
     * @return array<int, CheckResult>
     */
    public function warnings(): array
    {
        return array_values(array_filter(
            $this->results,
            fn (CheckResult $r): bool => $r->isWarn(),
        ));
    }

    /**
     * Format check results as rows for laravel/prompts table().
     *
     * @return array<int, array<int, string>>
     */
    public function toTableRows(): array
    {
        return array_map(function (CheckResult $result): array {
            $details = $result->message;

            if ($result->suggestedFix !== null && $result->suggestedFix !== '') {
                $details .= " (Fix: {$result->suggestedFix})";
            }

            return [
                "{$result->status->icon()} {$result->status->label()}",
                $result->name,
                $details,
            ];
        }, $this->results);
    }
}
