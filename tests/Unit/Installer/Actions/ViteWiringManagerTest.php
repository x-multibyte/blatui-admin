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
    if (isset($this->tempDir)) {
        deleteDirectory($this->tempDir);
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

it('inserts blatui.css before user rules adhering to CSS specification and remains idempotent', function () {
    $cssDir = $this->tempDir.'/resources/css';
    mkdir($cssDir, 0755, true);
    file_put_contents($cssDir.'/app.css', "/* Custom User CSS */\n.custom-card { color: red; }\n");

    $manager = new ViteWiringManager($this->detector, $this->tempDir);
    $result = $manager->wireCSS();

    $content = file_get_contents($cssDir.'/app.css');
    $importPos = strpos($content, '@import "./blatui.css";');
    $rulePos = strpos($content, '.custom-card');

    expect($importPos)->not->toBeFalse()
        ->and($rulePos)->not->toBeFalse()
        ->and($importPos)->toBeLessThan($rulePos)
        ->and($result)->toContain('Inserted');

    // Re-run wireCSS: should be idempotent and not insert again
    $secondResult = $manager->wireCSS();
    $secondContent = file_get_contents($cssDir.'/app.css');
    expect(substr_count($secondContent, '@import "./blatui.css";'))->toBe(1)
        ->and($secondResult)->toContain('already wired');
});

it('inserts blatui.css after existing @import statements', function () {
    $cssDir = $this->tempDir.'/resources/css';
    mkdir($cssDir, 0755, true);
    file_put_contents($cssDir.'/app.css', "@import \"tailwindcss\";\n\n.my-button { padding: 4px; }\n");

    $manager = new ViteWiringManager($this->detector, $this->tempDir);
    $manager->wireCSS();

    $content = file_get_contents($cssDir.'/app.css');
    $tailwindPos = strpos($content, '@import "tailwindcss";');
    $blatuiPos = strpos($content, '@import "./blatui.css";');
    $buttonPos = strpos($content, '.my-button');

    expect($tailwindPos)->toBeLessThan($blatuiPos)
        ->and($blatuiPos)->toBeLessThan($buttonPos);
});

it('places blatui.css after bundled imports preserving external font imports first', function () {
    $cssDir = $this->tempDir.'/resources/css';
    mkdir($cssDir, 0755, true);
    $css = <<<'CSS'
@import url("https://fonts.googleapis.com/css2?family=Kreon&display=swap");
@import "tailwindcss";

.user-card { color: blue; }
CSS;
    file_put_contents($cssDir.'/app.css', $css);

    $manager = new ViteWiringManager($this->detector, $this->tempDir);
    $manager->wireCSS();

    $content = file_get_contents($cssDir.'/app.css');
    $fontPos = strpos($content, '@import url("https://fonts.googleapis.com');
    $tailwindPos = strpos($content, '@import "tailwindcss";');
    $blatuiPos = strpos($content, '@import "./blatui.css";');
    $cardPos = strpos($content, '.user-card');

    expect($fontPos)->toBeLessThan($tailwindPos)
        ->and($tailwindPos)->toBeLessThan($blatuiPos)
        ->and($blatuiPos)->toBeLessThan($cardPos);
});

it('inserts blatui.css before leading layer blocks', function () {
    $cssDir = $this->tempDir.'/resources/css';
    mkdir($cssDir, 0755, true);
    $css = <<<'CSS'
@layer base {
    html { color: red; }
}
CSS;
    file_put_contents($cssDir.'/app.css', $css);

    $manager = new ViteWiringManager($this->detector, $this->tempDir);
    $manager->wireCSS();

    $content = file_get_contents($cssDir.'/app.css');
    $blatuiPos = strpos($content, '@import "./blatui.css";');
    $layerPos = strpos($content, '@layer base {');

    expect($blatuiPos)->toBeLessThan($layerPos);
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

it('upgrades standalone JS bootstrap to alpine:init when Livewire is subsequently added', function () {
    $jsDir = $this->tempDir.'/resources/js';
    mkdir($jsDir, 0755, true);
    file_put_contents($jsDir.'/app.js', "/* Existing JS */\nimport \"./blatui.js\";\n");

    file_put_contents($this->tempDir.'/composer.json', json_encode([
        'require' => ['livewire/livewire' => '^3.5'],
    ]));

    $manager = new ViteWiringManager($this->detector, $this->tempDir);
    $result = $manager->wireJS();

    $content = file_get_contents($jsDir.'/app.js');
    expect($content)->not->toContain('import "./blatui.js"')
        ->and($content)->toContain('registerBlatUI(window.Alpine)')
        ->and($result)->toContain('Replaced standalone BlatUI import');
});

it('upgrades Livewire JS registration to include theme false when Flux is added', function () {
    $jsDir = $this->tempDir.'/resources/js';
    mkdir($jsDir, 0755, true);
    file_put_contents($jsDir.'/app.js', "import { registerBlatUI } from './blatui-core.js'\n\ndocument.addEventListener('alpine:init', () => {\n    registerBlatUI(window.Alpine)\n})\n");

    file_put_contents($this->tempDir.'/composer.json', json_encode([
        'require' => [
            'livewire/livewire' => '^3.5',
            'livewire/flux' => '^2.0',
        ],
    ]));

    $manager = new ViteWiringManager($this->detector, $this->tempDir);
    $result = $manager->wireJS();

    $content = file_get_contents($jsDir.'/app.js');
    expect($content)->toContain('registerBlatUI(window.Alpine, { theme: false })')
        ->and($result)->toContain('Upgraded BlatUI registration for Flux UI');
});

it('does not touch filesystem in dry run mode and includes accurate plan', function () {
    file_put_contents($this->tempDir.'/composer.json', json_encode([
        'require' => [
            'livewire/livewire' => '^3.5',
            'livewire/flux' => '^2.0',
        ],
    ]));

    $manager = new ViteWiringManager($this->detector, $this->tempDir);

    $planCSS = $manager->wireCSS(dryRun: true);
    $planJS = $manager->wireJS(dryRun: true);

    expect(file_exists($this->tempDir.'/resources/css/app.css'))->toBeFalse()
        ->and(file_exists($this->tempDir.'/resources/js/app.js'))->toBeFalse()
        ->and($planCSS)->toContain('blatui.css')
        ->and($planJS)->toContain('{ theme: false }');
});
