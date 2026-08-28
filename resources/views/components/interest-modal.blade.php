<div
    x-data="{ open: false, serviceKey: '', serviceName: '' }"
    x-on:open-interest-modal.window="open = true; serviceKey = $event.detail.key; serviceName = $event.detail.name"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
>
    <div class="absolute inset-0 bg-gray-900/50" x-on:click="open = false"></div>

    <div
        x-show="open"
        x-transition
        x-on:click.outside="open = false"
        class="relative bg-white rounded-2xl shadow-xl max-w-md w-full p-7"
    >
        <button type="button" x-on:click="open = false" class="absolute top-5 left-5 text-gray-400 hover:text-gray-600">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>

        <span class="inline-flex h-11 w-11 rounded-xl bg-gold-50 text-gold-600 items-center justify-center mb-4">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0z" />
            </svg>
        </span>

        <h3 class="text-lg font-bold text-gray-900 mb-1">سجّل اهتمامك</h3>
        <p class="text-sm text-gray-500 mb-5">راح نراسلك أول ما تكون <span class="font-medium text-gray-700" x-text="serviceName"></span> جاهزة.</p>

        <form method="POST" action="{{ route('service-interest.store') }}">
            @csrf
            <input type="hidden" name="service_key" x-bind:value="serviceKey">
            <input type="hidden" name="service_name" x-bind:value="serviceName">

            <label class="block text-sm font-medium text-gray-700 mb-1.5">بريدك الإلكتروني</label>
            <input
                type="email"
                name="email"
                required
                placeholder="you@example.com"
                class="w-full rounded-xl border-gray-200 focus:ring-brand-500 focus:border-brand-500 mb-5"
            >

            <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white rounded-full py-3 font-semibold shadow-sm hover:shadow transition-all">
                أعلمني عند التوفر
            </button>
        </form>
    </div>
</div>
