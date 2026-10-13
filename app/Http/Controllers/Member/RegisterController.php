<?php

namespace App\Http\Controllers\Member;

use App\Models\Member;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterController extends MemberController
{
    public function create(): View
    {
        return view('member.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('members', 'email')],
            'organization' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $member = Member::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'organization' => $data['organization'] ?? null,
            'password' => $data['password'],
        ]);

        $this->sendQuietly(fn () => event(new Registered($member)));

        $this->guard()->login($member);
        $request->session()->regenerate();

        return redirect($this->localizedRoute('member.verification.notice'));
    }
}
