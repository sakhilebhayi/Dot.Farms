<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <div class="mb-6">
            <p class="font-mono text-[11px] tracking-[0.14em] uppercase text-[var(--soil)] dark:text-gray-500 mb-1.5">Dot.Farms</p>
            <h1 class="font-display font-semibold text-2xl text-[var(--ink)] dark:text-gray-100">Create your account</h1>
        </div>

        <x-validation-errors class="mb-4" />

        <form method="POST" action="{{ route('register') }}">
            @csrf

            <div>
                <x-label for="name" value="{{ __('Name') }}" />
                <x-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            </div>

            <div class="mt-4">
                <x-label for="email" value="{{ __('Email') }}" />
                <x-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            </div>

            <div class="mt-4">
                <x-label for="password" value="{{ __('Password') }}" />
                <x-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
            </div>

            <div class="mt-4">
                <x-label for="password_confirmation" value="{{ __('Confirm Password') }}" />
                <x-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
            </div>

            @if (Laravel\Jetstream\Jetstream::hasTermsAndPrivacyPolicyFeature())
                <div class="mt-4">
                    <x-label for="terms">
                        <div class="flex items-center">
                            <x-checkbox name="terms" id="terms" required />

                            <div class="ms-2">
                                {!! __('I agree to the :terms_of_service and :privacy_policy', [
                                        'terms_of_service' => '<a target="_blank" href="'.route('terms.show').'" class="text-sm text-[var(--ink-soft)] dark:text-gray-400 hover:text-[var(--forest)] dark:hover:text-[var(--gold)] underline rounded-md focus:outline-none focus:ring-2 focus:ring-[var(--gold)] focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition-colors">'.__('Terms of Service').'</a>',
                                        'privacy_policy' => '<a target="_blank" href="'.route('policy.show').'" class="text-sm text-[var(--ink-soft)] dark:text-gray-400 hover:text-[var(--forest)] dark:hover:text-[var(--gold)] underline rounded-md focus:outline-none focus:ring-2 focus:ring-[var(--gold)] focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition-colors">'.__('Privacy Policy').'</a>',
                                ]) !!}
                            </div>
                        </div>
                    </x-label>
                </div>
            @endif

            <div class="flex items-center justify-end mt-4">
                <a class="text-sm text-[var(--ink-soft)] dark:text-gray-400 hover:text-[var(--forest)] dark:hover:text-[var(--gold)] underline rounded-md focus:outline-none focus:ring-2 focus:ring-[var(--gold)] focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition-colors" href="{{ route('login') }}">
                    {{ __('Already registered?') }}
                </a>

                <x-button class="ms-4">
                    {{ __('Register') }}
                </x-button>
            </div>
        </form>
    </x-authentication-card>
</x-guest-layout>
