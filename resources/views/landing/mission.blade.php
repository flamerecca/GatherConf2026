<section class="bg-ink text-parchment">
    <div class="mx-auto grid max-w-6xl items-center gap-10 px-4 py-16 md:grid-cols-5">
        <img src="{{ Vite::asset('resources/images/npc/merchant.jpeg') }}" alt="{{ __('landing.npc_alt') }}"
             class="rounded-2xl shadow-xl md:col-span-2">
        <div class="md:col-span-3">
            <h2 class="text-3xl font-bold">{{ __('landing.mission_title') }}</h2>
            <p class="mt-6 text-lg leading-relaxed">{{ __('landing.mission_lead') }}</p>
            <p class="mt-4 leading-relaxed text-parchment/80">{{ __('landing.mission_body') }}</p>
            @if (__('landing.mission_quote') !== 'landing.mission_quote' && filled(__('landing.mission_quote')))
                <p class="mt-8 border-l-4 border-copper pl-4 text-sm leading-relaxed text-parchment/70 italic" lang="en">
                    {{ __('landing.mission_quote') }}
                </p>
            @endif
        </div>
    </div>
</section>
