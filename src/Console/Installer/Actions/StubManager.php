<?php

declare(strict_types=1);

namespace BlatUI\Admin\Console\Installer\Actions;

use RuntimeException;

final class StubManager
{
    private string $basePath;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = rtrim($basePath ?? (function_exists('base_path') ? base_path() : (getcwd() ?: '.')), '/');
    }

    public function componentsPath(string $component = ''): string
    {
        $root = $this->basePath.'/resources/views/components/ui';

        if ($component === '') {
            return $root;
        }

        return $root.'/'.ltrim($component, '/').'.blade.php';
    }

    public function hasComponent(string $component): bool
    {
        $filePath = $this->componentsPath($component);
        $dirPath = $this->basePath.'/resources/views/components/ui/'.ltrim($component, '/');

        return file_exists($filePath) || is_dir($dirPath);
    }

    /**
     * @param  array<int, string>  $components
     * @return array<int, string>
     */
    public function existingComponents(array $components): array
    {
        return array_values(array_filter(
            $components,
            fn (string $c): bool => $this->hasComponent($c),
        ));
    }

    /**
     * @param  array<int, string>  $components
     * @return array<int, string>
     */
    public function missingComponents(array $components): array
    {
        return array_values(array_filter(
            $components,
            fn (string $c): bool => ! $this->hasComponent($c),
        ));
    }

    /**
     * Default core UI components needed for the BlatUI Admin panel.
     *
     * @return array<int, string>
     */
    public function defaultAdminComponents(): array
    {
        return [
            'button',
            'card',
            'input',
            'dropdown',
            'table',
            'badge',
            'modal',
        ];
    }

    public function ensureComponentsDirectory(): void
    {
        $path = $this->componentsPath();

        if (! is_dir($path) && ! @mkdir($path, 0755, true) && ! is_dir($path)) {
            throw new RuntimeException("Failed to create components directory [{$path}]. Check directory permissions.");
        }
    }
}
