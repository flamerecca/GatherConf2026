<?php

namespace App\Http\Controllers\Member;

use App\Models\Member;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
            'email' => ['required', 'string', 'email', 'max:255'],
            'organization' => ['nullable', 'string', 'max:255'],
        ]);

        $this->takeLinkQuota($data['email']);

        $member = Member::query()->createOrFirst(
            ['email' => $data['email']],
            ['name' => $data['name'], 'organization' => $data['organization'] ?? null, 'is_active' => true],
        );

        if ($member->is_active) {
            $this->sendLoginLink($member);
        }

        return redirect($this->localizedRoute('member.register.sent'));
    }

    public function sent(): View
    {
        return view('member.register-sent');
    }
}
