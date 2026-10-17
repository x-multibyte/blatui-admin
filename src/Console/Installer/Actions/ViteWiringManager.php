<?php

declare(strict_types=1);

namespace BlatUI\Admin\Console\Installer\Actions;

use BlatUI\Admin\Console\Installer\Diagnostics\EnvironmentDetector;

final class ViteWiringManager
{
    private EnvironmentDetector $detector;

    private string $basePath;

    public function __construct(
        ?EnvironmentDetector $detector = null,
        ?string $basePath = null,
    ) {
        $this->basePath = rtrim($basePath ?? (function_exists('base_path') ? base_path() : (getcwd() ?: '.')), '/');
        $this->detector = $detector ?? new EnvironmentDetector($this->basePath);
    }

    public function cssPath(): string
    {
        return $this->basePath.'/resources/css/app.css';
    }

    public function jsPath(): string
    {
        return $this->basePath.'/resources/js/app.js';
    }

    public function isCSSWired(): bool
    {
        $path = $this->cssPath();

        return file_exists($path) && str_contains((string) file_get_contents($path), 'blatui.css');
    }

    public function isJSWired(): bool
    {
        $path = $this->jsPath();

        if (! file_exists($path)) {
            return false;
        }

        $content = (string) file_get_contents($path);

        if ($this->detector->hasLivewire()) {
            return str_contains($content, 'blatui-core.js');
        }

        return str_contains($content, 'blatui.js') || str_contains($content, 'blatui-core.js');
    }

    public function wireCSS(bool $dryRun = false): string
    {
        if ($dryRun) {
            return $this->getPlanForCSS();
        }

        $cssPath = $this->cssPath();

        if (! file_exists($cssPath)) {
            $dir = dirname($cssPath);

            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($cssPath, '@import "tailwindcss";'."\n".'@import "./blatui.css";'."\n");

            return 'Created resources/css/app.css with Tailwind CSS and BlatUI imports';
        }

        $content = (string) file_get_contents($cssPath);

        if (str_contains($content, 'blatui.css')) {
            return 'resources/css/app.css is already wired with blatui.css';
        }

        file_put_contents($cssPath, "\n".'@import "./blatui.css";'."\n", FILE_APPEND);

        return 'Appended @import "./blatui.css"; to resources/css/app.css';
    }

    public function wireJS(bool $dryRun = false): string
    {
        if ($dryRun) {
            return $this->getPlanForJS();
        }

        $jsPath = $this->jsPath();
        $hasLivewire = $this->detector->hasLivewire();
        $hasFlux = $this->detector->hasFlux();

        if ($hasLivewire) {
            $registration = $hasFlux
                ? 'registerBlatUI(window.Alpine, { theme: false })'
                : 'registerBlatUI(window.Alpine)';

            $stub = <<<JS
import { registerBlatUI } from './blatui-core.js'

document.addEventListener('alpine:init', () => {
    {$registration}
})
JS;

            if (! file_exists($jsPath)) {
                $dir = dirname($jsPath);

                if (! is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }

                file_put_contents($jsPath, $stub."\n");

                return 'Created resources/js/app.js and registered BlatUI onto Livewire\'s Alpine';
            }

            $content = (string) file_get_contents($jsPath);

            if (str_contains($content, 'blatui-core.js')) {
                return 'resources/js/app.js is already wired with blatui-core.js';
            }

            file_put_contents($jsPath, "\n".$stub."\n", FILE_APPEND);

            return 'Registered BlatUI onto Livewire\'s Alpine in resources/js/app.js';
        }

        // Greenfield / Standalone setup
        if (! file_exists($jsPath)) {
            $dir = dirname($jsPath);

            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($jsPath, 'import "./blatui.js";'."\n");

            return 'Created resources/js/app.js with BlatUI bootstrap';
        }

        $content = (string) file_get_contents($jsPath);

        if (str_contains($content, 'blatui.js') || str_contains($content, 'blatui-core.js')) {
            return 'resources/js/app.js is already wired with blatui.js';
        }

        file_put_contents($jsPath, "\n".'import "./blatui.js";'."\n", FILE_APPEND);

        return 'Added import "./blatui.js"; to resources/js/app.js';
    }

    public function getPlanForCSS(): string
    {
        return 'Add to resources/css/app.css: @import "./blatui.css";';
    }

    public function getPlanForJS(): string
    {
        if ($this->detector->hasLivewire()) {
            return "Add to resources/js/app.js: registerBlatUI onto Livewire's Alpine via alpine:init";
        }

        return 'Add to resources/js/app.js: import "./blatui.js";';
    }
}
