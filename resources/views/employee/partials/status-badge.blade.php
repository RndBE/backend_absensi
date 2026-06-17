@php
    $classes = [
        'approved' => 'bg-emerald-100 text-emerald-800',
        'rejected' => 'bg-red-100 text-red-800',
        'pending' => 'bg-amber-100 text-amber-800',
        'in_review' => 'bg-blue-100 text-blue-800',
    ][$status] ?? 'bg-gray-100 text-gray-700';

    $labels = [
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'pending' => 'Pending',
        'in_review' => 'In Review',
    ];
@endphp
<span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold {{ $classes }}">
    {{ $labels[$status] ?? ucfirst($status ?? '-') }}
</span>
