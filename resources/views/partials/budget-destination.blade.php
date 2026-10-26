{{--
    Tujuan perjalanan pengajuan anggaran untuk approver: ke mana jarak dihitung, asalnya, dan
    petanya. Titik yang dipilih sendiri di peta diberi tanda supaya diperiksa.
    Butuh: $budgetRequest
--}}
@php
    $destinationLat = $budgetRequest->destination_lat;
    $destinationLng = $budgetRequest->destination_lng;
    $destinationSource = $budgetRequest->distance_source;
    $hasPoint = $destinationLat !== null && $destinationLng !== null;
@endphp
@if($budgetRequest->destination_label || $hasPoint)
<div class="mt-2 text-[12px]" data-budget-destination>
    <div class="text-gray-700">
        <span class="font-semibold">Tujuan:</span> {{ $budgetRequest->destination_label ?? '-' }}
    </div>
    @if($destinationSource)
    <div class="mt-1 flex flex-wrap items-center gap-1.5">
        <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold {{ $destinationSource === 'map' ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-600' }}">
            {{ $budgetRequest->distance_source_label }}
        </span>
        @if($destinationSource === 'map')
        <span class="text-[11px] text-amber-700">Titik ini dipilih sendiri oleh pengaju. Periksa lokasinya di peta.</span>
        @endif
    </div>
    @endif
    @if($hasPoint)
    @php
        $pad = 0.02;
        $bbox = implode(',', [$destinationLng - $pad, $destinationLat - $pad, $destinationLng + $pad, $destinationLat + $pad]);
    @endphp
    <iframe
        class="mt-2 w-full h-40 rounded-lg border border-gray-200"
        loading="lazy"
        title="Peta tujuan"
        src="https://www.openstreetmap.org/export/embed.html?bbox={{ $bbox }}&layer=mapnik&marker={{ $destinationLat }},{{ $destinationLng }}"
    ></iframe>
    <a href="{{ $budgetRequest->destination_map_url }}" target="_blank" rel="noopener" class="mt-1 inline-flex items-center gap-1 text-[12px] font-semibold text-indigo-600 hover:underline">
        <span class="material-symbols-outlined text-[15px]">map</span>
        Lihat di Google Maps
    </a>
    @endif
</div>
@endif
