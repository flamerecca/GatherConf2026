<?php

namespace App\Http\Controllers\Member;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfileController extends MemberController
{
    public function edit(): View
    {
        return view('member.profile', ['member' => $this->guard()->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $this->guard()->user()->update(['name' => $data['name']]);

        return redirect($this->localizedRoute('member.profile.edit'))->with('status', __('member.profile.saved'));
    }
}
