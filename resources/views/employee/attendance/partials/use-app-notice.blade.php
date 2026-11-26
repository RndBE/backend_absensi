{{--
    Pengganti tombol Clock In / Clock Out saat presensi lewat portal ditutup (allow_web_clockin = 0).
    Judulnya menyebut keduanya, bukan hanya aksi berikutnya: yang membaca "Clock In lewat aplikasi"
    di pagi hari jangan sampai mengira sore nanti masih bisa clock out dari portal.
    Variabel: $links (['android' => ?string, 'ios' => ?string]).
--}}
<div class="rounded-lg border border-indigo-100 bg-indigo-50/60 p-4">
    <div class="flex items-start gap-3">
        <div class="w-9 h-9 rounded-lg bg-white text-indigo-600 border border-indigo-100 flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-[20px]">smartphone</span>
        </div>
        <div>
            <div class="text-[13px] font-bold text-gray-900">
                Clock In &amp; Clock Out lewat aplikasi HP
            </div>
            <p class="text-[12px] text-gray-600 mt-0.5">
                Clock in dan clock out tidak lagi bisa dilakukan dari portal web. Buka aplikasi HRIS Beacon di HP Anda.
                Belum punya aplikasinya? Unduh di bawah.
            </p>
        </div>
    </div>

    <div class="mt-3 flex flex-col sm:flex-row gap-2">
        @if($links['android'])
            <a href="{{ $links['android'] }}" target="_blank" rel="noopener noreferrer"
               class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 text-[13px] font-bold text-white bg-gradient-to-br from-indigo-600 to-indigo-500 rounded-lg shadow-sm hover:-translate-y-0.5 transition-all press">
                <span class="material-symbols-outlined text-[18px]">android</span>
                Unduh untuk Android
            </a>
        @endif
        @if($links['ios'])
            <a href="{{ $links['ios'] }}" target="_blank" rel="noopener noreferrer"
               class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 text-[13px] font-bold text-indigo-700 bg-white border border-indigo-200 rounded-lg hover:bg-indigo-50 transition-all press">
                <span class="material-symbols-outlined text-[18px]">phone_iphone</span>
                Unduh untuk iPhone
            </a>
        @endif
    </div>

    @if(! $links['ios'])
        <p class="mt-2 text-[11px] text-gray-500">Pengguna iPhone: hubungi HRD untuk mendapatkan aplikasinya.</p>
    @endif
</div>
