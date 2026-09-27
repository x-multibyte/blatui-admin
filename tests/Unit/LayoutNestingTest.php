<?php

declare(strict_types=1);

use BlatUI\Admin\Layout\Column;
use BlatUI\Admin\Layout\Row;
use Illuminate\Support\Facades\View;

/**
 * Regression guard for nested Layout composition.
 *
 * `Layout\Column` contents may contain `Layout\Row` or nested `Layout\Column`
 * instances at arbitrary depth. The recursive Blade partials must resolve
 * every nesting level instead of falling through to `{{ $content }}`, which
 * raises a TypeError when handed an object.
 */
test('column partial resolves nested rows and columns at arbitrary depth', function () {
    $column = new Column(function (Column $col) {
        $col->append('top-level');
        $col->row(function (Row $row) {
            $row->column(12, 'nested-row');
        });
        $col->append(new Column('nested-column', 6));
    }, 8);

    $row = new Row;
    $row->column(8, $column);

    $html = View::make('blatui-admin::layouts.partials.row', ['row' => $row])->render();

    expect($html)->toContain('col-span-12 md:col-span-8')
        ->and($html)->toContain('top-level')
        ->and($html)->toContain('nested-row')
        ->and($html)->toContain('nested-column');
});
