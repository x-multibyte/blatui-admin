<?php

declare(strict_types=1);

namespace BlatUI\Admin\Console\Installer\Actions;

use Illuminate\Support\Facades\Artisan;

final class ArtisanRunner
{
    /**
     * @var (callable(string, array<string, mixed>): int)|null
     */
    private $caller;

    private string $lastOutput = '';

    /**
     * @param  (callable(string, array<string, mixed>): int)|null  $caller
     */
    public function __construct(?callable $caller = null)
    {
        $this->caller = $caller;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function call(string $command, array $arguments = []): int
    {
        if ($this->caller !== null) {
            return ($this->caller)($command, $arguments);
        }

        $exitCode = Artisan::call($command, $arguments);
        $this->lastOutput = Artisan::output();

        return $exitCode;
    }

    public function publish(string $tag, bool $force = false): int
    {
        return $this->call('vendor:publish', [
            '--tag' => $tag,
            '--force' => $force,
        ]);
    }

    public function migrate(bool $fresh = false, bool $force = true): int
    {
        $command = $fresh ? 'migrate:fresh' : 'migrate';

        return $this->call($command, [
            '--force' => $force,
        ]);
    }

    /**
     * @param  array<int, string>  $components
     */
    public function blatuiAdd(array $components, bool $force = false): int
    {
        return $this->call('blatui:add', [
            'components' => $components,
            '--force' => $force,
        ]);
    }

    public function output(): string
    {
        return $this->lastOutput;
    }

    public function getPlanForPublish(string $tag, bool $force = false): string
    {
        $cmd = "php artisan vendor:publish --tag={$tag}";

        return $force ? "{$cmd} --force" : $cmd;
    }

    public function getPlanForMigrate(bool $fresh = false, bool $force = true): string
    {
        $cmd = $fresh ? 'php artisan migrate:fresh' : 'php artisan migrate';

        return $force ? "{$cmd} --force" : $cmd;
    }

    /**
     * @param  array<int, string>  $components
     */
    public function getPlanForBlatuiAdd(array $components, bool $force = false): string
    {
        $cmd = 'php artisan blatui:add '.implode(' ', $components);

        return $force ? "{$cmd} --force" : $cmd;
    }
}
