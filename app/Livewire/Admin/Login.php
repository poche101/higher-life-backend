<?php

namespace App\Livewire\Admin;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class Login extends Component
{
    public string $email = '';
    public string $password = '';

    public function login()
    {
        $this->validate(['email' => 'required|email', 'password' => 'required']);

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password]) || ! Auth::user()->isAdmin()) {
            Auth::logout();
            $this->addError('email', 'These credentials do not match an administrator account.');
            return;
        }

        session()->regenerate();
        return redirect()->intended(route('admin.dashboard'));
    }

    public function render() { return view('livewire.admin.login'); }
}
