<?php

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('the homepage requires authentication and serves company information to signed-in employees', function () {
    $this->get(route('home'))->assertRedirect(route('login'));

    $employee = Employee::create([
        'username' => 'homepage.employee',
        'email' => 'homepage@example.com',
        'password' => Hash::make('homepage-password'),
    ]);

    $response = $this->actingAs($employee)->get(route('home'));

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
