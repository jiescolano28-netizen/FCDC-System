<?php

namespace App\Livewire\Settings;

use Illuminate\Validation\Rule;
use Livewire\Component;

class SettingsPage extends Component
{
    public string $companyName = '';

    public string $address = '';

    public string $phone = '';

    public string $taxRate = '';

    public string $currency = '';

    public function mount(): void
    {
        $this->fill(session()->get($this->sessionKey(), $this->defaultSettings()));
    }

    protected function rules(): array
    {
        return [
            'companyName' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'taxRate' => ['required', 'numeric', 'between:0,100'],
            'currency' => ['required', Rule::in(['USD', 'CAD', 'EUR', 'PHP'])],
        ];
    }

    public function updated(string $property): void
    {
        if (! array_key_exists($property, $this->rules())) {
            return;
        }

        abort_unless(auth()->user()?->can('settings.update'), 403);
        $this->validate();
        session()->put($this->sessionKey(), $this->only(array_keys($this->rules())));
    }

    public function render()
    {
        return view('livewire.settings.settings-page')
            ->layout('layouts.app', ['title' => 'Settings']);
    }

    private function sessionKey(): string
    {
        return 'demo.settings.employee.'.auth()->id();
    }

    private function defaultSettings(): array
    {
        return [
            'companyName' => 'Fabellion Construction and Development Corp.',
            'address' => 'San Mateo, Rizal',
            'phone' => '(951) 555-0148',
            'taxRate' => '12',
            'currency' => 'USD',
        ];
    }
}
