@props(['disabled' => false])

<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-[var(--forest)] dark:focus:border-[var(--gold)] focus:ring-[var(--forest)] dark:focus:ring-[var(--gold)] rounded-md shadow-sm']) !!}>
