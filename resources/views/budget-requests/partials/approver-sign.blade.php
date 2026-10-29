{{-- Isi satu kotak tanda tangan approver. Butuh: $sign (null = belum disetujui), $name --}}
@if($sign && $sign['signature'])
    <div class="signature-img-box">
        <img src="{{ $sign['signature'] }}" alt="Tanda tangan {{ $name }}">
    </div>
@endif
<div class="signature-date">({{ $sign['date'] ?? '__________/__________/__________' }})</div>
<div class="signature-line"></div>
<div class="signature-name">{{ $name }}</div>
