<?php

test('the named public homepage serves company information and working navigation destinations', function () {
    $response = $this->get(route('home'));
    $response->assertOk()
        ->assertSee('Fabellon Construction and Development Corporation')
        ->assertSee('href="#home"', false)
        ->assertSee('href="#about"', false)
        ->assertSee('href="#materials"', false)
        ->assertSee('href="#contact"', false)
        ->assertSee('href="'.route('login').'"', false)
        ->assertSee('id="home"', false)
        ->assertSee('id="about"', false)
        ->assertSee('id="materials"', false)
        ->assertSee('id="contact"', false)
        ->assertSee('Get Started');

    $this->get(route('login'))->assertOk();
});
