<?php

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('the synthetic demo banner is shown only when demo mode is enabled', function () {
    $employee = Employee::create([
        'username' => 'demo.banner.viewer',
        'email' => 'demo.banner.viewer@example.test',
        'password' => Hash::make('demo-only-password'),
    ]);

    $this->actingAs($employee)
        ->get(route('profile'))
        ->assertOk()
        ->assertDontSee('SYNTHETIC DEMO DATA');

    config(['demo.enabled' => true]);

    $this->actingAs($employee)
        ->get(route('profile'))
        ->assertOk()
        ->assertSee('SYNTHETIC DEMO DATA')
        ->assertSee('not FCDC official financial balances, records, or approvals')
        ->assertSee('Do not file, audit, or use them in production.');
});
