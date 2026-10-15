<?php

namespace Database\Seeders;

use App\Models\CommunicationDevice;
use App\Models\Company;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

/**
 * Kendaraan dan HT milik PT Arta Teknologi Comunindo, diambil dari surat tugas Word 2026
 * (001–070/ST-ATC/2026). Kendaraan pribadi, rental, kereta, dan pesawat tidak dimasukkan:
 * itu diketik manual di surat.
 *
 * Aman dijalankan berulang: item yang sudah ada (plat/kode sama) tidak ditimpa, jadi
 * perubahan yang dibuat GA&HSE lewat halaman Kendaraan & HT tetap terjaga.
 *
 *   php artisan db:seed --class=AssignmentFacilitySeeder
 */
class AssignmentFacilitySeeder extends Seeder
{
    private const VEHICLES = [
        ['name' => 'Alvez', 'plate_number' => 'K 1734 AT'],
        ['name' => 'Xenia', 'plate_number' => 'K 1509 GK'],
        ['name' => 'Terios', 'plate_number' => 'K 1464 PK'],
        // Di surat Word tertulis "Granmax" dan "Grandmax" untuk plat yang sama.
        ['name' => 'Granmax', 'plate_number' => 'AB 8262 CU'],
        ['name' => 'Almaz', 'plate_number' => 'K 1988 PK'],
        ['name' => 'Traga', 'plate_number' => 'AB 6063 CU'],
        ['name' => 'Vario', 'plate_number' => 'K 2691 AMB'],
    ];

    private const DEVICES = [
        'HT 002/ATC/VIII/2024',
        'HT 003/ATC/VIII/2024',
        'HT 009/ATC/VIII/2024',
        'HT 010/ATC/VIII/2024',
        'HT 016/ATC/VIII/2024',
        'INV/ATC/HT-001/HSE/2024',
    ];

    public function run(): void
    {
        $company = Company::where('name', 'like', '%Arta Teknologi%')->first();

        if (! $company) {
            $this->command?->error('Perusahaan PT Arta Teknologi Comunindo tidak ditemukan. Tidak ada yang diisi.');

            return;
        }

        $vehiclesAdded = 0;
        foreach (self::VEHICLES as $vehicle) {
            $row = Vehicle::firstOrCreate(
                ['company_id' => $company->id, 'plate_number' => $vehicle['plate_number']],
                ['name' => $vehicle['name'], 'ownership' => 'company', 'is_active' => true]
            );
            $vehiclesAdded += (int) $row->wasRecentlyCreated;
        }

        $devicesAdded = 0;
        foreach (self::DEVICES as $code) {
            $row = CommunicationDevice::firstOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                ['name' => 'HT', 'is_active' => true]
            );
            $devicesAdded += (int) $row->wasRecentlyCreated;
        }

        $this->command?->info("Kendaraan ditambahkan: {$vehiclesAdded} dari ".count(self::VEHICLES).'.');
        $this->command?->info("Perangkat komunikasi ditambahkan: {$devicesAdded} dari ".count(self::DEVICES).'.');
    }
}
