<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <div class="mb-6">
            <p class="font-mono text-[11px] tracking-[0.14em] uppercase text-[var(--soil)] dark:text-gray-500 mb-1.5">Dot.Farms</p>
            <h1 class="font-display font-semibold text-2xl text-[var(--ink)] dark:text-gray-100">Verify your email</h1>
        </div>

        <div class="mb-4 text-sm text-[var(--ink-soft)] dark:text-gray-400">
            {{ __('Before continuing, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
        </div>

        @if (session('status') == 'verification-link-sent')
            <div class="mb-4 font-medium text-sm text-green-600 dark:text-green-400">
                {{ __('A new verification link has been sent to the email address you provided in your profile settings.') }}
            </div>
        @endif

        <div class="mt-4 flex items-center justify-between">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf

                <div>
                    <x-button type="submit">
                        {{ __('Resend Verification Email') }}
                    </x-button>
                </div>
            </form>

            <div>
                <a
                    href="{{ route('profile.show') }}"
                    class="text-sm text-[var(--ink-soft)] dark:text-gray-400 hover:text-[var(--forest)] dark:hover:text-[var(--gold)] underline rounded-md focus:outline-none focus:ring-2 focus:ring-[var(--gold)] focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition-colors"
                >
                    {{ __('Edit Profile') }}</a>

                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf

                    <button type="submit" class="text-sm text-[var(--ink-soft)] dark:text-gray-400 hover:text-[var(--forest)] dark:hover:text-[var(--gold)] underline rounded-md focus:outline-none focus:ring-2 focus:ring-[var(--gold)] focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition-colors ms-2">
                        {{ __('Log Out') }}
                    </button>
                </form>
            </div>
        </div>
    </x-authentication-card>
</x-guest-layout>
