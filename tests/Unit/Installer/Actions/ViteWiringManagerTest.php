<?php

declare(strict_types=1);

use BlatUI\Admin\Console\Installer\Actions\ViteWiringManager;
use BlatUI\Admin\Console\Installer\Diagnostics\EnvironmentDetector;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/blatui_vite_'.uniqid();
    mkdir($this->tempDir, 0755, true);
    $this->detector = new EnvironmentDetector($this->tempDir);
});

afterEach(function () {
    if (isset($this->tempDir) && is_dir($this->tempDir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }
        rmdir($this->tempDir);
    }
});

it('creates resources/css/app.css if missing with tailwind and blatui imports', function () {
    $manager = new ViteWiringManager($this->detector, $this->tempDir);
    $result = $manager->wireCSS();

    $cssPath = $this->tempDir.'/resources/css/app.css';
    expect(file_exists($cssPath))->toBeTrue()
        ->and(file_get_contents($cssPath))->toContain('@import "tailwindcss";')
        ->and(file_get_contents($cssPath))->toContain('@import "./blatui.css";')
        ->and($result)->toContain('Created resources/css/app.css');
});

it('appends blatui.css to existing app.css preserving user styles and remains idempotent', function () {
    $cssDir = $this->tempDir.'/resources/css';
    mkdir($cssDir, 0755, true);
    file_put_contents($cssDir.'/app.css', '/* Custom User CSS */'."\n".'.custom-card { color: red; }'."\n");

    $manager = new ViteWiringManager($this->detector, $this->tempDir);
    $result = $manager->wireCSS();

    $content = file_get_contents($cssDir.'/app.css');
    expect($content)->toContain('.custom-card { color: red; }')
        ->and($content)->toContain('@import "./blatui.css";')
        ->and($result)->toContain('Appended');

    // Re-run wireCSS: should be idempotent and not append again
    $secondResult = $manager->wireCSS();
    $secondContent = file_get_contents($cssDir.'/app.css');
    expect(substr_count($secondContent, '@import "./blatui.css";'))->toBe(1)
        ->and($secondResult)->toContain('already wired');
});

it('wires JS for greenfield projects with blatui.js bootstrap', function () {
    $manager = new ViteWiringManager($this->detector, $this->tempDir);
    $result = $manager->wireJS();

    $jsPath = $this->tempDir.'/resources/js/app.js';
    expect(file_exists($jsPath))->toBeTrue()
        ->and(file_get_contents($jsPath))->toBe('import "./blatui.js";'."\n")
        ->and($result)->toContain('Created resources/js/app.js');

    // Idempotency check
    $secondResult = $manager->wireJS();
    expect(file_get_contents($jsPath))->toBe('import "./blatui.js";'."\n")
        ->and($secondResult)->toContain('already wired');
});

it('wires JS for Livewire projects using alpine:init hook without duplicate alpine', function () {
    file_put_contents($this->tempDir.'/composer.json', json_encode([
        'require' => ['livewire/livewire' => '^3.5'],
    ]));

    $manager = new ViteWiringManager($this->detector, $this->tempDir);
    $result = $manager->wireJS();

    $jsPath = $this->tempDir.'/resources/js/app.js';
    $content = file_get_contents($jsPath);

    expect(file_exists($jsPath))->toBeTrue()
        ->and($content)->toContain("import { registerBlatUI } from './blatui-core.js'")
        ->and($content)->toContain("document.addEventListener('alpine:init'")
        ->and($content)->toContain('registerBlatUI(window.Alpine)')
        ->and($content)->not->toContain('import "./blatui.js"')
        ->and($result)->toContain('Livewire');
});

it('wires JS for Livewire and Flux projects passing theme false option', function () {
    file_put_contents($this->tempDir.'/composer.json', json_encode([
        'require' => [
            'livewire/livewire' => '^3.5',
            'livewire/flux' => '^2.0',
        ],
    ]));

    $manager = new ViteWiringManager($this->detector, $this->tempDir);
    $manager->wireJS();

    $jsPath = $this->tempDir.'/resources/js/app.js';
    $content = file_get_contents($jsPath);

    expect($content)->toContain('registerBlatUI(window.Alpine, { theme: false })');
});

it('does not touch filesystem in dry run mode', function () {
    $manager = new ViteWiringManager($this->detector, $this->tempDir);

    $planCSS = $manager->wireCSS(dryRun: true);
    $planJS = $manager->wireJS(dryRun: true);

    expect(file_exists($this->tempDir.'/resources/css/app.css'))->toBeFalse()
        ->and(file_exists($this->tempDir.'/resources/js/app.js'))->toBeFalse()
        ->and($planCSS)->toContain('blatui.css')
        ->and($planJS)->toContain('blatui');
});
