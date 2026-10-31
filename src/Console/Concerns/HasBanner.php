<?php

declare(strict_types=1);

namespace BlatUI\Admin\Console\Concerns;

use BlatUI\Admin\Application as AdminApplication;
use InvalidArgumentException;
use Termwind\Termwind;

use function Termwind\render;

trait HasBanner
{
    /**
     * The banner content to display.
     */
    protected string $banner = <<<'ASCII'
      ____  __      __  __  ______   ___       __          _
     / __ )/ /___ _/ /_/ / / /  _/  /   | ____/ /___ ___  (_)___
    / __  / / __ `/ __/ / / // /   / /| |/ __  / __ `__ \/ / __ \
   / /_/ / / /_/ / /_/ /_/ // /   / ___ / /_/ / / / / / / / / / /
  /_____/_/\__,_/\__/\____/___/  /_/  |_\__,_/_/ /_/ /_/_/_/ /_/
ASCII;

    /**
     * RGB spectrum palette for the horizontal banner gradient.
     *
     * @var array<int, array{0: int, 1: int, 2: int}>
     */
    protected array $bannerPalette = [
        [250, 204, 21],  // Yellow
        [74, 222, 128],  // Lime green
        [6, 182, 212],   // Cyan
        [168, 85, 247],  // Purple
        [244, 63, 94],   // Rose
        [249, 115, 22],  // Orange
        [234, 179, 8],   // Yellow
        [34, 197, 94],   // Green
        [6, 182, 212],   // Cyan
        [59, 130, 246],  // Blue
        [236, 72, 153],  // Magenta
        [249, 115, 22],  // Orange
    ];

    /**
     * Display the banner to the console.
     */
    protected function banner(?string $version = null, ?string $subtitle = null, bool $gradient = true): static
    {
        if (trim($this->banner) === '') {
            throw new InvalidArgumentException('Banner content cannot be empty.');
        }

        $version ??= sprintf('v%s', AdminApplication::VERSION);
        $subtitle ??= 'The Blade Admin Panel for Laravel';

        $bannerText = $gradient
            ? $this->colorizeWithGradient($this->banner, $this->bannerPalette)
            : $this->banner;

        Termwind::getRenderer()->writeln("\n".$bannerText);

        $versionHtml = htmlspecialchars($version, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $subtitleHtml = htmlspecialchars($subtitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        render(<<<HTML
        <div class="mt-1">
            <span class="bg-gray-800 text-yellow-400 font-bold px-1 mr-1">{$versionHtml}</span>
            <span class="text-gray-400">{$subtitleHtml}</span>
        </div>
        HTML,
        );

        return $this;
    }

    /**
     * Apply horizontal RGB gradient interpolation to text.
     *
     * @param  array<int, array{0: int, 1: int, 2: int}>  $palette
     */
    protected function colorizeWithGradient(string $text, array $palette): string
    {
        if ($palette === []) {
            return $text;
        }

        $lines = explode("\n", $text);
        $maxLen = max(array_map('mb_strlen', $lines));

        $colored = array_map(
            fn (string $line): string => $this->colorizeLine($line, $maxLen, $palette),
            $lines,
        );

        return implode("\n", $colored);
    }

    /**
     * Colorize a single line of text across the horizontal gradient spectrum.
     *
     * @param  array<int, array{0: int, 1: int, 2: int}>  $palette
     */
    private function colorizeLine(string $line, int $maxLen, array $palette): string
    {
        $output = '';
        $lastColor = null;
        $paletteCount = count($palette);

        foreach (mb_str_split($line) as $col => $ch) {
            if ($ch === ' ') {
                $output .= ' ';

                continue;
            }

            $color = $this->interpolateRgb($palette, $paletteCount, $col, $maxLen);

            if ($color !== $lastColor) {
                $output .= "\033[38;2;{$color}m";
                $lastColor = $color;
            }

            $output .= $ch;
        }

        return $lastColor !== null ? "{$output}\033[0m" : $output;
    }

    /**
     * Calculate interpolated RGB color string based on column position.
     *
     * @param  array<int, array{0: int, 1: int, 2: int}>  $palette
     */
    private function interpolateRgb(array $palette, int $paletteCount, int $col, int $maxLen): string
    {
        $ratio = $maxLen > 1 ? $col / ($maxLen - 1) : 0.0;
        $scaled = $ratio * ($paletteCount - 1);
        $idx = (int) floor($scaled);
        $factor = $scaled - $idx;

        $c1 = $palette[$idx];
        $c2 = $palette[min($idx + 1, $paletteCount - 1)];

        $r = (int) round($c1[0] + ($c2[0] - $c1[0]) * $factor);
        $g = (int) round($c1[1] + ($c2[1] - $c1[1]) * $factor);
        $b = (int) round($c1[2] + ($c2[2] - $c1[2]) * $factor);

        return "{$r};{$g};{$b}";
    }

    /**
     * Set a custom banner content dynamically.
     */
    protected function setBanner(string $banner): static
    {
        $this->banner = $banner;

        return $this;
    }

    /**
     * Get the current banner content.
     */
    protected function getBanner(): string
    {
        return $this->banner;
    }

    /**
     * Set a custom RGB gradient palette.
     *
     * @param  array<int, array{0: int, 1: int, 2: int}>  $palette
     */
    protected function setBannerPalette(array $palette): static
    {
        $this->bannerPalette = $palette;

        return $this;
    }

    /**
     * Get the current banner gradient palette.
     *
     * @return array<int, array{0: int, 1: int, 2: int}>
     */
    protected function getBannerPalette(): array
    {
        return $this->bannerPalette;
    }
}
