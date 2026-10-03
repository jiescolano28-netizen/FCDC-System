<?php

test('the named public homepage serves company information and working navigation destinations', function () {
    $response = $this->get(route('home'));
    $response->assertOk()
        ->assertSee('Fabellon Construction and Development Corporation')
        ->assertSee('href="#about"', false)
        ->assertSee('href="#materials"', false)
        ->assertSee('href="#system"', false)
        ->assertSee('href="'.route('login').'"', false)
        ->assertSee('id="about"', false)
        ->assertSee('id="materials"', false)
        ->assertSee('id="system"', false)
        ->assertSee('aria-controls="navLinks"', false)
        ->assertSee('aria-expanded="false"', false)
        ->assertSee('class="material-toggle"', false)
        ->assertSee('wire:id', false);

    $this->get(route('login'))->assertOk();
});
