<x-guest-layout>
    <div class="pt-4 pb-12 bg-[var(--paper)] dark:bg-gray-900">
        <div class="min-h-screen flex flex-col items-center pt-6 sm:pt-0 px-4">
            <div>
                <x-authentication-card-logo />
            </div>

            <div class="w-full sm:max-w-2xl mt-6 p-6 sm:p-8 bg-white dark:bg-gray-800 border border-[var(--line)] dark:border-gray-700 shadow-sm overflow-hidden sm:rounded-xl prose dark:prose-invert prose-headings:font-display prose-headings:text-[var(--ink)] dark:prose-headings:text-gray-100 prose-a:text-[var(--forest)] dark:prose-a:text-[var(--gold)] prose-strong:text-[var(--ink)] dark:prose-strong:text-gray-100">
                {!! $terms !!}
            </div>
        </div>
    </div>
</x-guest-layout>
