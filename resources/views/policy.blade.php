<x-guest-layout>
    <div class="pt-4 pb-12 bg-[var(--paper)]">
        <div class="min-h-screen flex flex-col items-center pt-6 sm:pt-0 px-4">
            <div>
                <x-authentication-card-logo />
            </div>

            <div class="w-full sm:max-w-2xl mt-6 p-6 sm:p-8 bg-white border border-[var(--line)] shadow-sm overflow-hidden sm:rounded-xl prose prose-headings:font-display prose-headings:text-[var(--ink)] prose-a:text-[var(--forest)] prose-strong:text-[var(--ink)]">
                {!! $policy !!}
            </div>
        </div>
    </div>
</x-guest-layout>
