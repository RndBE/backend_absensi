<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Setting extends Model
{
    protected $fillable = ['company_id', 'key', 'value'];

    /** Cache hasil pengecekan kolom agar tidak query skema tiap kali setValue dipanggil. */
    private static ?bool $punyaKolomCompany = null;

    public static function getValue(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    public static function setValue(string $key, $value): void
    {
        $atribut = ['value' => $value];

        // Tabel `settings` di server sudah punya `company_id` NOT NULL dengan unique
        // (company_id, key) — perubahan itu tidak ada di folder migrasi repo ini. Tanpa
        // mengisinya, membuat KUNCI BARU selalu gagal ("Field 'company_id' doesn't have a
        // default value"), sementara memperbarui kunci lama tetap jalan. Karena itu
        // kegagalannya baru terlihat saat ada pengaturan baru ditambahkan.
        if (self::punyaKolomCompany()) {
            $atribut['company_id'] = auth()->user()?->company_id ?? Company::query()->min('id') ?? 1;
        }

        static::updateOrCreate(['key' => $key], $atribut);
    }

    private static function punyaKolomCompany(): bool
    {
        return self::$punyaKolomCompany ??= Schema::hasColumn('settings', 'company_id');
    }
}
