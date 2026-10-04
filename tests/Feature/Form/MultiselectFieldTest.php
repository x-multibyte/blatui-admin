<?php

declare(strict_types=1);

use BlatUI\Admin\Form\Field\Multiselect;

test('multiselect keeps an array value instead of coercing it to a string', function () {
    $field = new Multiselect('permission_ids', 'Permissions');
    $field->relation('permissions');
    $field->value([3, 7]);

    $variables = $field->defaultVariables();

    // Still an array — Field::defaultVariables() would have collapsed [3, 7] to ''.
    // Keys are normalised to strings so the template's strict
    // in_array((string) $key, $value, true) matches the int ids an Eloquent
    // relation hands back, and every option renders pre-checked instead of
    // silently losing the selection on edit.
    expect($variables['value'])->toBeArray()
        ->and($variables['value'])->toBe(['3', '7']);
});

test('multiselect wraps a single scalar value into a list', function () {
    $field = new Multiselect('permission_ids', 'Permissions');
    $field->relation('permissions');
    $field->value(3);

    expect($field->defaultVariables()['value'])->toBe(['3']);
});

test('multiselect renders every option server-side', function () {
    $field = new Multiselect('permission_ids', 'Permissions');
    $field->relation('permissions');
    $field->options(['1' => 'Users', '2' => 'Roles', '3' => 'Menu']);

    $html = $field->render();

    expect($html)
        ->toContain('name="permission_ids[]"')
        ->toContain('value="1"')
        ->toContain('Users')
        ->toContain('Roles')
        ->toContain('Menu');
});

test('multiselect marks pre-selected options as checked', function () {
    $field = new Multiselect('permission_ids', 'Permissions');
    $field->relation('permissions');
    $field->options(['1' => 'Users', '2' => 'Roles']);
    $field->value(['2']);

    expect($field->render())
        ->toContain('value="2"')
        ->toContain('checked');
});

test('multiselect escapes option labels', function () {
    $field = new Multiselect('role_ids', 'Roles');
    $field->relation('roles');
    $field->options(['1' => '<script>alert(1)</script>']);

    $html = $field->render();

    // Asserting only on the escaped fragment would pass for a template that
    // emitted the payload both escaped and raw, so the raw payload is the
    // stronger assertion: it fails unless every copy is escaped.
    expect($html)
        ->not->toContain('<script>alert(1)</script>')
        ->not->toContain('</script>')
        ->not->toContain('<script')
        ->toContain('&lt;script&gt;');
});

test('multiselect is searchable by default and can be disabled', function () {
    $field = new Multiselect('role_ids', 'Roles');

    expect($field->isSearchable())->toBeTrue();

    $field->searchable(false);

    expect($field->isSearchable())->toBeFalse();
});

test('every option stays in the DOM and stays submittable when search is off', function () {
    $searchable = new Multiselect('role_ids', 'Roles');
    $searchable->relation('roles');
    $searchable->options(['1' => 'Users', '2' => 'Roles']);

    $plain = new Multiselect('role_ids', 'Roles');
    $plain->relation('roles');
    $plain->options(['1' => 'Users', '2' => 'Roles']);
    $plain->searchable(false);

    // Filtering is x-show, never conditional rendering: both variants must
    // carry the same checkbox list so the form submits identically with JS off.
    expect($searchable->render())->toContain('x-show=')
        ->and($plain->render())->not->toContain('x-show=')
        ->and($plain->render())->toContain('name="role_ids[]"')
        ->and($plain->render())->toContain('value="1"')
        ->and($plain->render())->toContain('value="2"');
});
