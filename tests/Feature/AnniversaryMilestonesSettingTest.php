<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\WorkAnniversaryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use ReflectionProperty;
use Tests\TestCase;

/**
 * Apresiasi hari jadi kerja: admin cukup memilih "Setiap tahun" atau "Tahun tertentu saja".
 * Tanpa pengaturan sama sekali, ucapan dikirim setiap tahun.
 */
class AnniversaryMilestonesSettingTest extends TestCase
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

    private function save(array $anniversary): \Illuminate\Testing\TestResponse
    {
        return $this->withoutMiddleware()
            ->withSession(['admin_id' => 1])
            ->put(route('admin.attendance-settings.update'), $anniversary + [
                'office_latitude' => '1.0456',
                'office_longitude' => '104.0305',
                'office_radius_meters' => '100',
            ]);
    }

    public function test_without_any_setting_every_year_is_celebrated(): void
    {
        $this->assertSame([], WorkAnniversaryService::milestones());
    }

    public function test_every_year_mode_is_stored_as_star_even_if_a_list_was_left_in_the_field(): void
    {
        Setting::setValue('anniversary_milestones', '1,3,5');

        $this->save(['anniversary_mode' => 'every', 'anniversary_milestones' => '1,3,5'])
            ->assertSessionHasNoErrors();

        $this->assertSame('*', Setting::getValue('anniversary_milestones'));
        $this->assertSame([], WorkAnniversaryService::milestones());
    }

    public function test_specific_years_mode_stores_the_list(): void
    {
        $this->save(['anniversary_mode' => 'milestones', 'anniversary_milestones' => '5, 1 ,3'])
            ->assertSessionHasNoErrors();

        $this->assertSame([1, 3, 5], WorkAnniversaryService::milestones());
    }

    public function test_specific_years_mode_requires_the_list(): void
    {
        $this->save(['anniversary_mode' => 'milestones', 'anniversary_milestones' => ''])
            ->assertSessionHasErrors('anniversary_milestones');

        $this->assertNull(Setting::getValue('anniversary_milestones'));
    }

    public function test_invalid_list_is_rejected(): void
    {
        $this->save(['anniversary_mode' => 'milestones', 'anniversary_milestones' => 'satu,dua'])
            ->assertSessionHasErrors('anniversary_milestones');
    }

    public function test_old_form_without_mode_still_saves_the_field_as_is(): void
    {
        $this->save(['anniversary_milestones' => '*'])->assertSessionHasNoErrors();

        $this->assertSame('*', Setting::getValue('anniversary_milestones'));
    }
}
