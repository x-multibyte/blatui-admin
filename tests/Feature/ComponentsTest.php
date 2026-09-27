<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

test('blatui core components can be rendered', function () {
    $buttonHtml = Blade::render('<x-blatui-admin::ui.button>Click Me</x-blatui-admin::ui.button>');
    expect($buttonHtml)->toContain('Click Me', 'button');

    $cardHtml = Blade::render('<x-blatui-admin::ui.card title="Overview">Card Content</x-blatui-admin::ui.card>');
    expect($cardHtml)->toContain('Overview', 'Card Content');

    $badgeHtml = Blade::render('<x-blatui-admin::ui.badge variant="success">Active</x-blatui-admin::ui.badge>');
    expect($badgeHtml)->toContain('Active');

    $separatorHtml = Blade::render('<x-blatui-admin::ui.separator />');
    expect($separatorHtml)->toContain('<div');

    $avatarHtml = Blade::render('<x-blatui-admin::ui.avatar name="John Doe" />');
    expect($avatarHtml)->toContain('JD');

    $inputHtml = Blade::render('<x-blatui-admin::ui.input name="username" placeholder="Username" />');
    expect($inputHtml)->toContain('name="username"', 'placeholder="Username"');

    $sonnerHtml = Blade::render('<x-blatui-admin::ui.sonner />');
    expect($sonnerHtml)->toContain('x-data');

    $dropdownHtml = Blade::render('<x-blatui-admin::ui.dropdown><x-slot:trigger><button>Open</button></x-slot:trigger><div>Menu</div></x-blatui-admin::ui.dropdown>');
    expect($dropdownHtml)->toContain('Open', 'Menu');
});
