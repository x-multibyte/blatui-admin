<?php

declare(strict_types=1);

namespace BlatUI\Admin\Grid\Displayers;

class Badge extends AbstractDisplayer
{
    /**
     * Variant class mapping.
     *
     * @var array<string, string>
     */
    protected static array $variantClasses = [
        'secondary' => 'border-transparent bg-gray-100 text-gray-900 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-100',
        'destructive' => 'border-transparent bg-red-600 text-white hover:bg-red-700',
        'danger' => 'border-transparent bg-red-600 text-white hover:bg-red-700',
        'outline' => 'text-gray-900 dark:text-gray-100 border-gray-300 dark:border-gray-700',
        'success' => 'border-transparent bg-emerald-600 text-white hover:bg-emerald-700',
        'warning' => 'border-transparent bg-amber-500 text-white hover:bg-amber-600',
        'info' => 'border-transparent bg-sky-500 text-white hover:bg-sky-600',
        'primary' => 'border-transparent bg-blue-600 text-white hover:bg-blue-700',
        'default' => 'border-transparent bg-blue-600 text-white hover:bg-blue-700',
    ];

    /**
     * Render the badge span.
     *
     * @param  string|array<mixed, string>  $variant
     * @param  array<mixed, string>  $map
     */
    public function display(string|array $variant = 'default', array $map = []): string
    {
        if ($this->value === null || $this->value === '') {
            return '';
        }

        $activeVariant = 'default';
        $key = is_scalar($this->value) ? (string) $this->value : '';

        if (is_array($variant)) {
            $activeVariant = $variant[$this->value] ?? ($variant[$key] ?? 'default');
            $text = $map[$this->value] ?? ($map[$key] ?? (string) $this->value);
        } else {
            $activeVariant = $variant;
            $text = $map[$this->value] ?? ($map[$key] ?? (string) $this->value);

            // If variant was left default and map value matches a variant name, treat map value as variant
            if ($variant === 'default' && isset(self::$variantClasses[$text]) && ! isset(self::$variantClasses[$key])) {
                $activeVariant = $text;
                $text = (string) $this->value;
            }
        }

        $baseClasses = 'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold transition-colors';
        $variantClass = self::$variantClasses[$activeVariant] ?? self::$variantClasses['default'];
        $classes = trim("{$baseClasses} {$variantClass}");

        return sprintf(
            '<span class="%s">%s</span>',
            htmlspecialchars($classes, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($text, ENT_QUOTES, 'UTF-8'),
        );
    }
}
