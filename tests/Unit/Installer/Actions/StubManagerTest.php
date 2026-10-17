<?php

declare(strict_types=1);

use BlatUI\Admin\Console\Installer\Actions\StubManager;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/blatui_stub_'.uniqid();
    mkdir($this->tempDir, 0755, true);
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

it('resolves component path in resources/views/components/ui', function () {
    $manager = new StubManager($this->tempDir);

    expect($manager->componentsPath())->toBe($this->tempDir.'/resources/views/components/ui')
        ->and($manager->componentsPath('button'))->toBe($this->tempDir.'/resources/views/components/ui/button.blade.php');
});

it('detects existing and missing components', function () {
    $manager = new StubManager($this->tempDir);
    $manager->ensureComponentsDirectory();

    touch($this->tempDir.'/resources/views/components/ui/button.blade.php');

    expect($manager->hasComponent('button'))->toBeTrue()
        ->and($manager->hasComponent('card'))->toBeFalse();

    $components = ['button', 'card', 'table'];
    expect($manager->existingComponents($components))->toBe(['button'])
        ->and($manager->missingComponents($components))->toBe(['card', 'table']);
});

it('detects directory-based components', function () {
    $manager = new StubManager($this->tempDir);
    $manager->ensureComponentsDirectory();

    mkdir($this->tempDir.'/resources/views/components/ui/dropdown', 0755, true);

    expect($manager->hasComponent('dropdown'))->toBeTrue();
});

it('provides list of default admin components', function () {
    $manager = new StubManager($this->tempDir);
    $defaults = $manager->defaultAdminComponents();

    expect($defaults)->toBeArray()
        ->and($defaults)->toContain('button')
        ->and($defaults)->toContain('card')
        ->and($defaults)->toContain('input')
        ->and($defaults)->toContain('table');
});

it('ensures components directory is created recursively', function () {
    $manager = new StubManager($this->tempDir);

    expect(is_dir($manager->componentsPath()))->toBeFalse();

    $manager->ensureComponentsDirectory();

    expect(is_dir($manager->componentsPath()))->toBeTrue();
});
