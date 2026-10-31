<?php

declare(strict_types=1);

namespace BlatUI\Admin\Tests\Unit\Console\Concerns;

use BlatUI\Admin\Application;
use BlatUI\Admin\Console\Concerns\HasBanner;
use InvalidArgumentException;
use Symfony\Component\Console\Output\BufferedOutput;

use function Termwind\renderUsing;

it('has default banner content and allows reading it', function () {
    $class = new class
    {
        use HasBanner;

        public function exposeBanner(): string
        {
            return $this->getBanner();
        }
    };

    expect($class->exposeBanner())
        ->toBeString()
        ->toContain('/ __ )/ /');
});

it('allows setting custom banner content dynamically', function () {
    $class = new class
    {
        use HasBanner;

        public function exposeSetBanner(string $banner): static
        {
            return $this->setBanner($banner);
        }

        public function exposeBanner(): string
        {
            return $this->getBanner();
        }
    };

    $class->exposeSetBanner('CUSTOM BANNER');

    expect($class->exposeBanner())->toBe('CUSTOM BANNER');
});

it('throws exception if banner is empty when rendering', function () {
    $class = new class
    {
        use HasBanner;

        public function clearAndRender(): void
        {
            $this->setBanner('');
            $this->banner();
        }
    };

    $class->clearAndRender();
})->throws(InvalidArgumentException::class, 'Banner content cannot be empty.');

it('renders banner with default version, subtitle, and rainbow gradient', function () {
    $buffer = new BufferedOutput;
    renderUsing($buffer);

    $class = new class
    {
        use HasBanner;

        public function render(): static
        {
            return $this->banner();
        }
    };

    $instance = $class->render();
    $output = $buffer->fetch();
    renderUsing(null);

    expect($instance)->toBe($class)
        ->and($output)->toContain("\033[38;2;")
        ->and($output)->toContain(sprintf('v%s', Application::VERSION))
        ->and($output)->toContain('The Blade Admin Panel for Laravel');
});

it('renders banner without gradient when disabled', function () {
    $buffer = new BufferedOutput;
    renderUsing($buffer);

    $class = new class
    {
        use HasBanner;

        public function renderPlain(): static
        {
            return $this->banner(gradient: false);
        }
    };

    $class->renderPlain();
    $output = $buffer->fetch();
    renderUsing(null);

    expect($output)
        ->not->toContain("\033[38;2;")
        ->and($output)->toContain('/ __ )/ /');
});

it('renders banner with custom version, subtitle, and palette', function () {
    $buffer = new BufferedOutput;
    renderUsing($buffer);

    $class = new class
    {
        use HasBanner;

        /**
         * @param  array<int, array{0: int, 1: int, 2: int}>  $palette
         */
        public function renderCustom(string $version, string $subtitle, array $palette): static
        {
            $this->setBannerPalette($palette);

            return $this->banner($version, $subtitle);
        }

        /**
         * @return array<int, array{0: int, 1: int, 2: int}>
         */
        public function exposePalette(): array
        {
            return $this->getBannerPalette();
        }
    };

    $customPalette = [
        [255, 0, 0],
        [0, 255, 0],
        [0, 0, 255],
    ];

    $class->renderCustom('v9.9.9', 'Next-Gen Dashboard', $customPalette);
    $output = $buffer->fetch();
    renderUsing(null);

    expect($class->exposePalette())->toBe($customPalette)
        ->and($output)->toContain('v9.9.9')
        ->and($output)->toContain('Next-Gen Dashboard')
        ->and($output)->toContain("\033[38;2;");
});
