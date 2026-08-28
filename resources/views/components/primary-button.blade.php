<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-5 py-2.5 bg-brand-600 border border-transparent rounded-full font-semibold text-xs text-white uppercase tracking-widest shadow-sm hover:bg-brand-700 hover:shadow focus:bg-brand-700 active:bg-brand-800 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
