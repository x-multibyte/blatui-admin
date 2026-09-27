<?php

declare(strict_types=1);

namespace BlatUI\Admin\Grid\Displayers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class Image extends AbstractDisplayer
{
    /**
     * Render <img> tag(s) with dimensions.
     */
    public function display(?string $server = null, int $width = 32, int $height = 32): string
    {
        if ($this->value === null || $this->value === '') {
            return '';
        }

        $images = is_array($this->value) ? $this->value : [$this->value];
        $html = [];

        foreach ($images as $image) {
            if ($image === null || $image === '') {
                continue;
            }

            $src = (string) $image;

            if (! Str::startsWith($src, ['http://', 'https://', '//', 'data:'])) {
                if ($server !== null && $server !== '') {
                    $src = rtrim($server, '/').'/'.ltrim($src, '/');
                } elseif (str_starts_with($src, '/')) {
                    $src = $src;
                } else {
                    try {
                        /** @var string $disk */
                        $disk = config('blatui-admin.upload.disk', 'public');
                        $src = Storage::disk($disk)->url($src);
                    } catch (Throwable) {
                        $src = asset($src);
                    }
                }
            }

            $html[] = sprintf(
                '<img src="%s" width="%d" height="%d" style="max-width: %dpx; max-height: %dpx;" class="rounded object-cover inline-block border border-gray-200 dark:border-gray-700" alt="" loading="lazy">',
                htmlspecialchars($src, ENT_QUOTES, 'UTF-8'),
                $width,
                $height,
                $width,
                $height,
            );
        }

        return implode(' ', $html);
    }
}
