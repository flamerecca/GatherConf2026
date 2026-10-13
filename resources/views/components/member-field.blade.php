@props(['name', 'label', 'type' => 'text', 'value' => null, 'autocomplete' => null, 'required' => false])
<div>
    <label for="{{ $name }}" class="block text-sm font-semibold">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
           value="{{ $type === 'password' ? '' : old($name, $value) }}"
           @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
           @required($required)
           class="mt-2 w-full rounded-xl border border-ink/30 bg-parchment px-4 py-3 focus:border-moss focus:outline-none focus:ring-2 focus:ring-moss/40">
    @error($name)
        <p class="mt-2 text-sm text-copper" role="alert">{{ $message }}</p>
    @enderror
</div>
