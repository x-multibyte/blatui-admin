<?php

declare(strict_types=1);

use BlatUI\Admin\Form\Field\Datetime;
use BlatUI\Admin\Form\Field\Display;
use BlatUI\Admin\Form\Field\Hidden;
use BlatUI\Admin\Form\Field\Select;
use BlatUI\Admin\Form\Field\SwitchField;
use BlatUI\Admin\Form\Field\Text;
use BlatUI\Admin\Form\Field\Textarea;
use Database\Seeders\AdminTablesSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(AdminTablesSeeder::class);
});

test('text field fluent options rules help placeholder required default', function () {
    $field = new Text('email', 'Email Address');
    $field->required()
        ->help('We will never share your email.')
        ->placeholder('user@example.com')
        ->default('admin@example.com')
        ->rules(['email', 'max:255'], ['email' => 'Please provide a valid email address.']);

    expect($field->getColumn())->toBe('email')
        ->and($field->getLabel())->toBe('Email Address')
        ->and($field->isRequired())->toBeTrue()
        ->and($field->getHelp())->toBe('We will never share your email.')
        ->and($field->getPlaceholder())->toBe('user@example.com')
        ->and($field->getDefault())->toBe('admin@example.com')
        ->and($field->getRules())->toContain('required')
        ->and($field->getRules())->toContain('email')
        ->and($field->getValidationMessages())->toHaveKey('email');

    $html = $field->render();
    expect($html)->toContain('name="email"')
        ->and($html)->toContain('Email Address')
        ->and($html)->toContain('value="admin@example.com"')
        ->and($html)->toContain('placeholder="user@example.com"')
        ->and($html)->toContain('We will never share your email.')
        ->and($html)->toContain('required');
});

test('select field renders options and marks selected value', function () {
    $field = new Select('status', 'Account Status');
    $field->options([
        'active' => 'Active Account',
        'disabled' => 'Disabled Account',
        'pending' => 'Pending Verification',
    ])->value('disabled');

    expect($field->getOptions())->toHaveCount(3);

    $html = $field->render();
    expect($html)->toContain('<select')
        ->and($html)->toContain('name="status"')
        ->and($html)->toContain('Account Status')
        ->and($html)->toContain('value="active"')
        ->and($html)->toContain('value="disabled" selected')
        ->and($html)->toContain('Disabled Account');
});

test('textarea field supports custom rows', function () {
    $field = new Textarea('notes', 'Administrative Notes');
    $field->rows(8)->value('First line of notes.');

    expect($field->getRows())->toBe(8);

    $html = $field->render();
    expect($html)->toContain('<textarea')
        ->and($html)->toContain('rows="8"')
        ->and($html)->toContain('name="notes"')
        ->and($html)->toContain('First line of notes.');
});

test('switch field renders hidden input and toggle button', function () {
    $field = new SwitchField('notifications', 'Enable Notifications');
    $field->states([1 => 'Enabled', 0 => 'Disabled'])->value(1);

    expect($field->getStates())->toBe([1 => 'Enabled', 0 => 'Disabled']);

    $html = $field->render();
    expect($html)->toContain('role="switch"')
        ->and($html)->toContain('name="notifications"')
        ->and($html)->toContain('state: true');

    $fieldOff = new SwitchField('alerts', 'Enable Alerts');
    $fieldOff->value(0);
    $htmlOff = $fieldOff->render();
    expect($htmlOff)->toContain('state: false');
});

test('display and hidden fields render correctly', function () {
    $hidden = new Hidden('user_id');
    $hidden->value(42);
    $hiddenHtml = $hidden->render();

    expect($hiddenHtml)->toContain('type="hidden"')
        ->and($hiddenHtml)->toContain('name="user_id"')
        ->and($hiddenHtml)->toContain('value="42"');

    $display = new Display('created_at', 'Created Time');
    $display->value('2026-10-04 12:00:00')->help('Auto-generated timestamp');
    $displayHtml = $display->render();

    expect($displayHtml)->toContain('Created Time')
        ->and($displayHtml)->toContain('2026-10-04 12:00:00')
        ->and($displayHtml)->toContain('Auto-generated timestamp')
        ->and($displayHtml)->not->toContain('<input');
});

test('datetime field supports format and renders input', function () {
    $field = new Datetime('scheduled_at', 'Schedule Date');
    $field->format('Y-m-d H:i')->value('2026-12-31 23:59:00');

    expect($field->getFormat())->toBe('Y-m-d H:i');

    $html = $field->render();
    expect($html)->toContain('type="datetime-local"')
        ->and($html)->toContain('name="scheduled_at"')
        ->and($html)->toContain('value="2026-12-31 23:59:00"');
});
