<?php

use Tests\TestCase;

uses(TestCase::class);
it('blocks a guest from the assigned Blade business queue', function (): void {
    config(['admin.panel_enabled' => true]);
    visit('/admin-business/assigned-profiles')->assertSee('TutorLink')->assertNoJavascriptErrors();
});
