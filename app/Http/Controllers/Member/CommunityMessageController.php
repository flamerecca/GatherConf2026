<?php

namespace App\Http\Controllers\Member;

use App\Models\CommunityDirectory;
use App\Models\CommunityMessage;
use App\Notifications\CommunityMessageReceivedNotification;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class CommunityMessageController extends MemberController
{
    public function create(string $slug): View
    {
        $community = CommunityDirectory::find($slug);

        abort_if($community === null, 404);

        return view('member.community-message-create', [
            'communityName' => CommunityDirectory::name($community),
            'slug' => $slug,
        ]);
    }

    public function store(Request $request, string $slug): RedirectResponse
    {
        $community = CommunityDirectory::find($slug);

        abort_if($community === null, 404);

        $me = $this->guard()->user();

        foreach (['subject', 'body'] as $field) {
            $value = $request->input($field);

            $request->merge([$field => is_string($value) ? trim(str_replace("\r\n", "\n", $value)) : $value]);
        }

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:'.CommunityMessage::SUBJECT_LENGTH],
            'body' => ['required', 'string', 'max:'.CommunityMessage::BODY_LENGTH],
            'agree_conduct' => ['accepted'],
        ]);

        $communityName = CommunityDirectory::name($community, 'zh_TW');

        try {
            Cache::lock('community-message:'.$me->getKey(), 10)->block(5, function () use ($me, $data, $slug, $communityName): void {
                if (CommunityMessage::sentTodayBy($me) >= CommunityMessage::DAILY_LIMIT) {
                    throw ValidationException::withMessages(['body' => __('community-messages.errors.daily_limit')]);
                }

                CommunityMessage::query()->create([
                    'member_id' => $me->getKey(),
                    'community_slug' => $slug,
                    'community_name' => $communityName,
                    'subject' => $data['subject'],
                    'body' => $data['body'],
                ]);
            });
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages(['body' => __('community-messages.errors.busy')]);
        }

        $this->sendQuietly(function () use ($me, $communityName): void {
            $me->notify(
                (new CommunityMessageReceivedNotification($communityName, route('member.community-messages')))->locale('zh_TW'),
            );
        });

        return redirect($this->localizedRoute('member.community-messages'))
            ->with('status', __('community-messages.flash.sent'));
    }

    public function index(): View
    {
        $me = $this->guard()->user();

        return view('member.community-messages', [
            'messages' => CommunityMessage::query()
                ->where('member_id', $me->getKey())
                ->latest()
                ->get(['id', 'community_name', 'subject', 'status', 'created_at']),
        ]);
    }
}
