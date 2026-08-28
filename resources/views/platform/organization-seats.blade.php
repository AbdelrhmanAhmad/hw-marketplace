<x-platform-layout>
    <div class="bg-gradient-to-l from-brand-700 to-brand-600 text-white">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-14">
            <h1 class="text-2xl font-bold mb-1">إدارة المقاعد — {{ $organization->name }}</h1>
            <p class="text-brand-50 text-sm">وزّع اشتراكات مؤسستك على أعضاء محدَّدين.</p>
        </div>
    </div>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-10">
        @if (session('seat_assigned'))
            <div class="bg-brand-50 border border-brand-100 text-brand-700 rounded-2xl px-5 py-4 text-sm font-medium">
                تم منح مقعد لـ"{{ session('seat_assigned') }}".
            </div>
        @endif

        @if (session('seat_released'))
            <div class="bg-gray-50 border border-gray-100 text-gray-600 rounded-2xl px-5 py-4 text-sm font-medium">
                تم سحب المقعد وإبطال الوصول فورًا.
            </div>
        @endif

        @error('seat')
            <div class="bg-maroon-50 border border-maroon-100 text-maroon-700 rounded-2xl px-5 py-4 text-sm font-medium">
                {{ $message }}
            </div>
        @enderror

        @if ($subscriptions->isEmpty())
            <div class="bg-white border border-gray-100 rounded-2xl p-12 text-center text-gray-500">
                لا يوجد اشتراك مؤسسي فعّال بعد.
            </div>
        @else
            @foreach ($subscriptions as $subscription)
                @php
                    $activeSeats = $subscription->seats->where('status', 'assigned');
                    $seatLimit = $subscription->plan->seat_limit;
                    $assignedUserIds = $activeSeats->pluck('user_id');
                    $availableMembers = $members->reject(fn ($m) => $assignedUserIds->contains($m->user_id));
                @endphp
                <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-6">
                    <div class="flex items-center justify-between mb-5">
                        <div>
                            <h2 class="font-bold text-gray-900">{{ $subscription->marketplaceItem->name }}</h2>
                            <p class="text-xs text-gray-500">{{ $subscription->plan->name }} — {{ $activeSeats->count() }} من {{ $seatLimit ?? '∞' }} مقعد مستخدَم</p>
                        </div>
                    </div>

                    <div class="space-y-2 mb-5">
                        @forelse ($activeSeats as $seat)
                            <div class="flex items-center justify-between bg-gray-50 rounded-xl px-4 py-2.5">
                                <span class="text-sm text-gray-700">{{ $seat->user->name }}</span>
                                <form action="{{ route('organization-seats.release', [$organization, $seat]) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="text-xs text-gray-400 hover:text-maroon-700 transition-colors">سحب المقعد</button>
                                </form>
                            </div>
                        @empty
                            <p class="text-sm text-gray-400">لا مقاعد مخصَّصة بعد.</p>
                        @endforelse
                    </div>

                    @if ($seatLimit === null || $activeSeats->count() < $seatLimit)
                        @if ($availableMembers->isNotEmpty())
                            <div class="flex flex-wrap gap-2">
                                @foreach ($availableMembers as $membership)
                                    <form action="{{ route('organization-seats.assign', [$organization, $subscription, $membership->user]) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="text-xs px-3 py-1.5 rounded-full border border-gray-200 text-gray-600 hover:border-brand-300 hover:text-brand-700 transition-colors">
                                            + منح مقعد لـ{{ $membership->user->name }}
                                        </button>
                                    </form>
                                @endforeach
                            </div>
                        @endif
                    @else
                        <p class="text-xs text-gray-400">لا مقاعد متاحة — تم الوصول للحد الأقصى.</p>
                    @endif
                </div>
            @endforeach
        @endif
    </div>
</x-platform-layout>
