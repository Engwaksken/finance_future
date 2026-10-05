@props([
    'name',
    'label',
    'type' => 'text',
    'autocomplete' => null,
    'placeholder' => null,
    'autofocus' => false,
    'value' => null,
])

<div class="auth-field">
    <label for="{{ $name }}">{{ $label }}</label>
    @if($type === 'password')
        <div class="auth-password">
    @endif
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if($type !== 'password') value="{{ old($name, $value) }}" @endif
        @if($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @if($placeholder) placeholder="{{ $placeholder }}" @endif
        @if($autofocus) autofocus @endif
        @if($errors->has($name)) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
        required
    >
    @if($type === 'password')
            <button class="password-toggle" type="button" data-password-toggle="{{ $name }}"
                    aria-controls="{{ $name }}" aria-label="{{ __('Show password') }}" aria-pressed="false"
                    data-show-label="{{ __('Show password') }}" data-hide-label="{{ __('Hide password') }}" hidden>
                <svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/>
                    <circle cx="12" cy="12" r="3"/>
                    <path class="eye-slash" d="m3 3 18 18"/>
                </svg>
            </button>
        </div>
    @endif

    @if($errors->has($name))
        <ul class="auth-errors" id="{{ $name }}-error" role="alert">
            @foreach($errors->get($name) as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    @endif
</div>
