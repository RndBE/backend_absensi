<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\AttendanceSettingController;
use App\Http\Controllers\Api\AttendanceController;
use App\Models\Setting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use Tests\TestCase;

/**
 * Batas akurasi GPS (`max_gps_accuracy_meters`): aplikasi mengambilnya dari `attendance/settings`
 * untuk mencari lokasi seakurat itu dan menolak yang lebih kasar. Admin bisa mengubahnya.
 */
class AttendanceGpsAccuracySettingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        (new ReflectionProperty(Setting::class, 'punyaKolomCompany'))->setValue(null, null);
        Schema::dropIfExists('settings');
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function test_the_api_reports_100_meters_when_nothing_is_configured(): void
    {
        $data = (new AttendanceController())->settings()->getData(true)['data'];

        $this->assertSame(100, $data['max_gps_accuracy_meters']);
    }

    public function test_the_api_reports_the_configured_limit(): void
    {
        Setting::setValue('max_gps_accuracy_meters', '60');

        $data = (new AttendanceController())->settings()->getData(true)['data'];

        $this->assertSame(60, $data['max_gps_accuracy_meters']);
    }

    public function test_the_other_settings_the_app_reads_are_still_there(): void
    {
        $data = (new AttendanceController())->settings()->getData(true)['data'];

        foreach ([
            'office_latitude', 'office_longitude', 'office_radius_meters', 'office_address',
            'require_photo', 'require_gps', 'allow_remote_clockin', 'remote_requires_notes',
        ] as $key) {
            $this->assertArrayHasKey($key, $data);
        }
    }

    public function test_admin_can_change_the_limit(): void
    {
        $this->update(['max_gps_accuracy_meters' => '40']);

        $this->assertSame('40', DB::table('settings')->where('key', 'max_gps_accuracy_meters')->value('value'));
        $this->assertSame(40, (new AttendanceController())->settings()->getData(true)['data']['max_gps_accuracy_meters']);
    }

    public function test_leaving_the_field_out_keeps_the_current_limit(): void
    {
        Setting::setValue('max_gps_accuracy_meters', '75');

        $this->update([]);

        $this->assertSame('75', Setting::getValue('max_gps_accuracy_meters', '100'));
    }

    #[DataProvider('invalidLimits')]
    public function test_values_outside_the_allowed_range_are_rejected(string $value): void
    {
        $this->expectException(ValidationException::class);

        try {
            $this->update(['max_gps_accuracy_meters' => $value]);
        } finally {
            $this->assertNull(DB::table('settings')->where('key', 'max_gps_accuracy_meters')->value('value'));
        }
    }

    public static function invalidLimits(): array
    {
        return [
            'terlalu kecil' => ['5'],
            'nol' => ['0'],
            'negatif' => ['-20'],
            'terlalu besar' => ['501'],
            'bukan angka' => ['abc'],
            'pecahan' => ['50.5'],
        ];
    }

    public function test_the_boundaries_are_accepted(): void
    {
        $this->update(['max_gps_accuracy_meters' => '10']);
        $this->assertSame('10', Setting::getValue('max_gps_accuracy_meters', '100'));

        $this->update(['max_gps_accuracy_meters' => '500']);
        $this->assertSame('500', Setting::getValue('max_gps_accuracy_meters', '100'));
    }

    /** Admin menyimpan Pengaturan Presensi; isian wajib (kantor) diisi seadanya. */
    private function update(array $fields)
    {
        $request = Request::create('/admin/attendance-settings', 'PUT', $fields + [
            'office_latitude' => '1.0456',
            'office_longitude' => '104.0305',
            'office_radius_meters' => '100',
        ]);

        return (new AttendanceSettingController())->update($request);
    }
}
