<?php

declare(strict_types=1);

namespace BlatUI\Admin\Console\Installer\Actions;

use BlatUI\Admin\Console\Installer\Diagnostics\EnvironmentDetector;
use RuntimeException;

final class ViteWiringManager
{
    private EnvironmentDetector $detector;

    private string $basePath;

    public function __construct(
        ?EnvironmentDetector $detector = null,
        ?string $basePath = null,
    ) {
        $this->detector = $detector ?? new EnvironmentDetector($basePath);
        $this->basePath = $this->detector->basePath();
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
            $this->writeFile($cssPath, '@import "tailwindcss";'."\n".'@import "./blatui.css";'."\n");

            return 'Created resources/css/app.css with Tailwind CSS and BlatUI imports';
        }

        $content = (string) file_get_contents($cssPath);

        if (str_contains($content, 'blatui.css')) {
            return 'resources/css/app.css is already wired with blatui.css';
        }

        $updated = $this->insertCSSImport($content, '@import "./blatui.css";');
        $this->writeFile($cssPath, $updated);

        return 'Inserted @import "./blatui.css"; into resources/css/app.css';
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
                $this->writeFile($jsPath, $stub."\n");

                return 'Created resources/js/app.js and registered BlatUI onto Livewire\'s Alpine';
            }

            $content = (string) file_get_contents($jsPath);

            // Handle upgrade from Greenfield (standalone blatui.js) to Livewire
            if (str_contains($content, 'import "./blatui.js";') || str_contains($content, "import './blatui.js';")) {
                $replaced = str_replace(
                    ['import "./blatui.js";', "import './blatui.js';"],
                    $stub,
                    $content,
                );
                $this->writeFile($jsPath, $replaced);

                return 'Replaced standalone BlatUI import with Livewire alpine:init hook in resources/js/app.js';
            }

            // Handle upgrade from Livewire to Flux ({ theme: false })
            if ($hasFlux && str_contains($content, 'registerBlatUI(window.Alpine)') && ! str_contains($content, 'theme: false')) {
                $replaced = str_replace(
                    'registerBlatUI(window.Alpine)',
                    'registerBlatUI(window.Alpine, { theme: false })',
                    $content,
                );
                $this->writeFile($jsPath, $replaced);

                return 'Upgraded BlatUI registration for Flux UI ({ theme: false }) in resources/js/app.js';
            }

            if (str_contains($content, 'blatui-core.js')) {
                return 'resources/js/app.js is already wired with blatui-core.js';
            }

            $this->writeFile($jsPath, rtrim($content)."\n\n".$stub."\n");

            return 'Registered BlatUI onto Livewire\'s Alpine in resources/js/app.js';
        }

        // Greenfield / Standalone setup
        if (! file_exists($jsPath)) {
            $this->writeFile($jsPath, 'import "./blatui.js";'."\n");

            return 'Created resources/js/app.js with BlatUI bootstrap';
        }

        $content = (string) file_get_contents($jsPath);

        if (str_contains($content, 'blatui.js') || str_contains($content, 'blatui-core.js')) {
            return 'resources/js/app.js is already wired with blatui.js';
        }

        $this->writeFile($jsPath, rtrim($content)."\n\n".'import "./blatui.js";'."\n");

        return 'Added import "./blatui.js"; to resources/js/app.js';
    }

    /**
     * Insert @import rule before non-import rules, complying with CSS specification.
     */
    public function insertCSSImport(string $contents, string $import = '@import "./blatui.css";'): string
    {
        if (str_contains($contents, 'blatui.css')) {
            return $contents;
        }

        // Check if there are existing @import rules; if so, insert right after the last one
        if (preg_match_all('/^@import\s+[^;]+;\s*$/m', $contents, $matches, PREG_OFFSET_CAPTURE) === 1 && ! empty($matches[0])) {
            $lastMatch = end($matches[0]);
            /** @var array{0: string, 1: int} $lastMatch */
            $insertPos = $lastMatch[1] + strlen($lastMatch[0]);

            return substr($contents, 0, $insertPos)."\n".$import.substr($contents, $insertPos);
        }

        // Otherwise find the first line that is not a comment, empty line, or @charset/@layer
        $lines = explode("\n", $contents);
        $insertIndex = 0;
        $inComment = false;

        foreach ($lines as $i => $line) {
            $trimmed = trim($line);

            if ($inComment) {
                if (str_contains($trimmed, '*/')) {
                    $inComment = false;
                }
                $insertIndex = $i + 1;

                continue;
            }

            if (str_starts_with($trimmed, '/*')) {
                if (! str_contains($trimmed, '*/')) {
                    $inComment = true;
                }
                $insertIndex = $i + 1;

                continue;
            }

            if (str_starts_with($trimmed, '@charset') || str_starts_with($trimmed, '@layer')) {
                $insertIndex = $i + 1;

                continue;
            }

            if ($trimmed === '') {
                $insertIndex = $i + 1;

                continue;
            }

            // Encountered the first real CSS selector or rule, insert before it
            $insertIndex = $i;

            break;
        }

        array_splice($lines, $insertIndex, 0, [$import]);

        return implode("\n", $lines);
    }

    public function getPlanForCSS(): string
    {
        return 'Add to resources/css/app.css: @import "./blatui.css"; (before user rules)';
    }

    public function getPlanForJS(): string
    {
        if ($this->detector->hasLivewire()) {
            $options = $this->detector->hasFlux() ? ' with { theme: false }' : '';

            return "Add to resources/js/app.js: registerBlatUI onto Livewire's Alpine via alpine:init{$options}";
        }

        return 'Add to resources/js/app.js: import "./blatui.js";';
    }

    private function ensureDirectory(string $path): void
    {
        $dir = dirname($path);

        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException("Failed to create directory [{$dir}]. Check directory permissions.");
        }
    }

    private function writeFile(string $path, string $content): void
    {
        $this->ensureDirectory($path);

        if (@file_put_contents($path, $content) === false) {
            throw new RuntimeException("Failed to write to file [{$path}]. Check directory permissions.");
        }
    }
}
