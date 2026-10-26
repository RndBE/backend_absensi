<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\PlaceController;
use App\Http\Controllers\Api\RegencyController;
use App\Http\Controllers\Api\TravelZoneController;
use App\Models\CityDistance;
use App\Models\Regency;
use App\Support\MapPoint;
use App\Support\RegencyMatcher;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Kota tujuan dipetakan ke kabupaten/kota, dan jaraknya satu per kabupaten/kota, apa pun cara
 * penulisannya ("Kendal", "Kab. Kendal", "Kendal, Jawa Tengah", "KebupatenKendal").
 */
class RegencyDistanceTest extends TestCase
{
    private const KENDAL = 3324;
    private const KAB_SEMARANG = 3322;
    private const KOTA_SEMARANG = 3374;
    private const SLEMAN = 3404;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['regencies', 'city_distances', 'settings', 'travel_zones'] as $table) {
            Schema::dropIfExists($table);
        }
        (require database_path('migrations/2026_10_24_100000_create_regencies_table.php'))->up();
        (require database_path('migrations/2026_06_22_000000_create_city_distances_table.php'))->up();
        (require database_path('migrations/2026_10_24_100001_add_regency_id_to_city_distances_table.php'))->up();
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
        Schema::create('travel_zones', function (Blueprint $table) {
            $table->id();
            $table->string('zone');
            $table->string('name');
            $table->unsignedInteger('min_km');
            $table->unsignedInteger('max_km')->nullable();
            $table->decimal('meal_allowance', 15, 2)->default(0);
            $table->timestamps();
        });

        DB::table('settings')->insert([
            ['key' => 'office_latitude', 'value' => '-7.7829', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'office_longitude', 'value' => '110.4087', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('travel_zones')->insert([
            ['zone' => 'A', 'name' => 'Zona A', 'min_km' => 0, 'max_km' => 100, 'meal_allowance' => 50000, 'created_at' => now(), 'updated_at' => now()],
            ['zone' => 'B', 'name' => 'Zona B', 'min_km' => 101, 'max_km' => null, 'meal_allowance' => 75000, 'created_at' => now(), 'updated_at' => now()],
        ]);
        config(['services.tomtom.key' => 'kunci-uji']);
    }

    // ── data master ───────────────────────────────────────────────────────────

    public function test_the_master_has_all_514_regencies_with_current_names_provinces_and_capitals(): void
    {
        $this->assertSame(514, Regency::count());
        $this->assertSame(0, Regency::whereNull('capital')->orWhere('capital', '')->count(), 'semua punya ibukota');
        $this->assertSame('Kabupaten Kendal, Jawa Tengah', Regency::find(self::KENDAL)->fullLabel());
        $this->assertSame('Ungaran', Regency::find(self::KAB_SEMARANG)->capital);
        $this->assertSame('Semarang', Regency::find(self::KOTA_SEMARANG)->capital, 'ibukota kota = kota itu sendiri');
        $this->assertSame('Papua Selatan', Regency::find(9401)->province, 'Merauke');
        $this->assertSame('Papua Barat Daya', Regency::find(9171)->province, 'Kota Sorong');
        $this->assertSame('Toba', Regency::find(1206)->name);
        $this->assertSame('Siak', Regency::find(1405)->name);
        $this->assertSame('Pasangkayu', Regency::find(7605)->name, 'dulu Mamuju Utara');
        $this->assertSame('Kepulauan Tanimbar', Regency::find(8101)->name, 'dulu Maluku Tenggara Barat');
        $this->assertSame('Pangkajene dan Kepulauan', Regency::find(7309)->name);
    }

    public function test_the_distance_target_names_the_capital(): void
    {
        $this->assertSame('Ungaran (ibukota Kabupaten Semarang)', Regency::find(self::KAB_SEMARANG)->distanceTarget());
        $this->assertSame('Kota Semarang', Regency::find(self::KOTA_SEMARANG)->distanceTarget());
    }

    // ── mengenali tulisan ─────────────────────────────────────────────────────

    #[DataProvider('kendalSpellings')]
    public function test_every_way_of_writing_kendal_is_the_same_regency(string $text): void
    {
        $this->assertSame(self::KENDAL, RegencyMatcher::match($text)?->id, $text);
    }

    public static function kendalSpellings(): array
    {
        return array_map(fn ($text) => [$text], [
            'kendal' => 'Kendal',
            'huruf besar' => 'KENDAL',
            'dengan provinsi' => 'Kendal, Jawa Tengah',
            'provinsi tanpa koma' => 'kendal jawa tengah',
            'singkatan provinsi' => 'Kendal, Jateng',
            'kabupaten' => 'Kabupaten Kendal',
            'kab titik' => 'Kab. Kendal',
            'kab' => 'kab kendal',
            'salah ketik kabupaten menempel' => 'KebupatenKendal',
            'kabupaten menempel' => 'kabupatenkendal',
            'kab menempel' => 'kabkendal',
            'salah ketik nama' => 'Kendall',
            'spasi berlebih' => '  kabupaten   kendal  ',
            'kota padahal kabupaten' => 'Kota Kendal',
            'dengan negara' => 'Kendal, Jawa Tengah, Indonesia',
        ]);
    }

    public function test_kota_and_kabupaten_with_the_same_name_stay_apart(): void
    {
        $this->assertSame(self::KOTA_SEMARANG, RegencyMatcher::match('Semarang')?->id, 'nama polos = kota');
        $this->assertSame(self::KOTA_SEMARANG, RegencyMatcher::match('Kota Semarang')?->id);
        $this->assertSame(self::KAB_SEMARANG, RegencyMatcher::match('Kabupaten Semarang')?->id);
        $this->assertSame(self::KAB_SEMARANG, RegencyMatcher::match('Kab Semarang, Jawa Tengah')?->id);
        $this->assertSame(3308, RegencyMatcher::match('kab. magelang')?->id);
        $this->assertSame(3371, RegencyMatcher::match('Magelang')?->id);
    }

    public function test_names_that_look_like_a_prefix_or_a_province_are_read_whole(): void
    {
        $this->assertSame(1571, RegencyMatcher::match('Kota Jambi')?->id, 'bukan provinsi Jambi');
        $this->assertSame(1571, RegencyMatcher::match('Jambi')?->id);
        $this->assertSame(3471, RegencyMatcher::match('Kota Yogyakarta')?->id, 'bukan provinsi DI Yogyakarta');
        $this->assertSame(6302, RegencyMatcher::match('Kotabaru')?->id, 'Kabupaten Kotabaru, bukan "Kota" Baru');
        $this->assertSame(7174, RegencyMatcher::match('Kotamobagu')?->id);
    }

    public function test_popular_names_and_province_hints(): void
    {
        $this->assertSame(3372, RegencyMatcher::match('Solo')?->id, 'Kota Surakarta');
        $this->assertSame(3471, RegencyMatcher::match('Jogja')?->id, 'Kota Yogyakarta');
        $this->assertSame(self::SLEMAN, RegencyMatcher::match('Sleman, DIY')?->id);
        $this->assertSame(self::SLEMAN, RegencyMatcher::match('Sleman, Daerah Istimewa Yogyakarta')?->id);
        $this->assertSame(1206, RegencyMatcher::match('Toba Samosir')?->id, 'nama lama Kabupaten Toba');
        $this->assertSame(8101, RegencyMatcher::match('Kab. Maluku Tenggara Barat')?->id, 'nama lama Kepulauan Tanimbar');
        $this->assertSame(3403, RegencyMatcher::match('Gunung Kidul')?->id, 'ejaan lama Gunungkidul');
        $this->assertSame(6302, RegencyMatcher::match('Kota Baru')?->id, 'ejaan lama Kotabaru');
    }

    public function test_unknown_text_is_not_guessed(): void
    {
        $this->assertNull(RegencyMatcher::match('Balai Besar Wilayah Sungai Serayu Opak'));
        $this->assertNull(RegencyMatcher::match('Kend'), 'terlalu pendek untuk ditebak');
        $this->assertNull(RegencyMatcher::match(''));
    }

    public function test_an_address_from_the_geocoder_maps_to_its_regency(): void
    {
        $this->assertSame(self::SLEMAN, RegencyMatcher::fromAddress(['county' => 'Sleman', 'state' => 'Daerah Istimewa Yogyakarta'])?->id);
        $this->assertSame(self::KAB_SEMARANG, RegencyMatcher::fromAddress(['town' => 'Ungaran', 'county' => 'Kabupaten Semarang', 'state' => 'Jawa Tengah'])?->id);
        $this->assertSame(self::KOTA_SEMARANG, RegencyMatcher::fromAddress(['city' => 'Kota Semarang', 'state' => 'Jawa Tengah'])?->id);
        $this->assertNull(RegencyMatcher::fromAddress(['state' => 'Jawa Tengah']));
    }

    // ── isian otomatis ────────────────────────────────────────────────────────

    public function test_search_suggests_the_closest_names_first(): void
    {
        $this->assertSame([self::KENDAL, 7471], $this->searchIds('kend'), 'Kendal lalu Kota Kendari');
        $this->assertSame(self::KAB_SEMARANG, $this->searchIds('kab sema')[0], 'jenis yang diketik didahulukan');
        $this->assertSame(self::KOTA_SEMARANG, $this->searchIds('semarang')[0], 'nama polos: kota dulu');
        $this->assertContains(self::KAB_SEMARANG, $this->searchIds('semarang'));
        $this->assertSame(3372, $this->searchIds('solo')[0]);
        $this->assertSame(self::KENDAL, $this->searchIds('KebupatenKendal')[0]);
        $this->assertSame(self::KENDAL, $this->searchIds('Kabupaten Kendal, Jawa Tengah')[0], 'label pilihan dicari ulang');
        $this->assertSame([], $this->searchIds('k'));
        $this->assertLessThanOrEqual(8, count($this->searchIds('ka')));
    }

    public function test_the_search_endpoint_returns_labels_for_the_app(): void
    {
        $response = (new RegencyController())->index(Request::create('/api/regencies', 'GET', ['q' => 'kendal']));
        $first = $response->getData(true)['data'][0];

        $this->assertSame([
            'id' => self::KENDAL, 'type' => 'kabupaten', 'name' => 'Kendal', 'province' => 'Jawa Tengah', 'capital' => 'Kendal',
            'label' => 'Kabupaten Kendal', 'full_label' => 'Kabupaten Kendal, Jawa Tengah',
        ], $first);
    }

    // ── jarak ─────────────────────────────────────────────────────────────────

    public function test_all_spellings_of_one_regency_get_one_distance_computed_once(): void
    {
        $this->fakeMaps(nominatim: $this->town(-6.9217033, 110.2040344, ['town' => 'Kendal', 'county' => 'Kendal', 'state' => 'Jawa Tengah']), meters: 98400);

        $results = array_map(fn ($city) => $this->estimate(['city' => $city]), ['Kendal', 'Kab. Kendal', 'Kendal, Jawa Tengah', 'KebupatenKendal']);

        foreach ($results as $data) {
            $this->assertSame(98, $data['distance_km']);
            $this->assertSame('Kabupaten Kendal, Jawa Tengah', $data['city']);
            $this->assertSame('Kendal (ibukota Kabupaten Kendal)', $data['distance_to']);
            $this->assertSame(self::KENDAL, $data['regency']['id']);
            $this->assertSame('A', $data['zone']['zone']);
        }
        Http::assertSentCount(2); // satu geocoding + satu rute, untuk keempat tulisan
        $this->assertSame(98, Regency::find(self::KENDAL)->distance_km);
        $this->assertSame('routing', Regency::find(self::KENDAL)->distance_source);
        $this->assertSame(-6.9217033, Regency::find(self::KENDAL)->lat, 'titik ibukota disimpan');
    }

    public function test_the_regency_point_is_its_capital_town_not_the_middle_of_the_area(): void
    {
        $this->fakeMaps(nominatim: $this->town(-7.1290892, 110.4038912, ['town' => 'Ungaran']), meters: 61000);

        $data = $this->estimate(['regency_id' => self::KAB_SEMARANG]);

        $this->assertSame('Kabupaten Semarang, Jawa Tengah', $data['city']);
        $this->assertSame('Ungaran (ibukota Kabupaten Semarang)', $data['distance_to']);
        $this->assertSame(61, $data['distance_km']);
        $sent = $this->sentQuery();
        $this->assertStringContainsString('q=Ungaran, Kabupaten Semarang, Jawa Tengah, Indonesia', $sent);
        $this->assertStringContainsString('featureType=settlement', $sent, 'dibatasi ke permukiman, bukan batas wilayah');
        Http::assertSent(fn (HttpRequest $r) => str_contains(urldecode($r->url()), '-7.1290892,110.4038912/json'));
    }

    public function test_the_whole_area_is_used_when_the_capital_town_is_not_found(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::sequence()
                ->push([])
                ->push([$this->area(-7.2876561, 110.4019427, ['county' => 'Kabupaten Semarang', 'state' => 'Jawa Tengah'])]),
            'api.tomtom.com/*' => Http::response(['routes' => [['summary' => ['lengthInMeters' => 75000]]]]),
        ]);

        $data = $this->estimate(['regency_id' => self::KAB_SEMARANG]);

        $this->assertSame(75, $data['distance_km']);
    }

    public function test_an_unknown_regency_id_is_refused(): void
    {
        $response = $this->estimateResponse(['regency_id' => 9999]);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_a_stored_distance_needs_no_maps_and_no_office(): void
    {
        Http::fake();
        DB::table('settings')->delete();
        Regency::find(self::KENDAL)->update(['distance_km' => 120, 'distance_source' => 'manual']);

        $data = $this->estimate(['city' => 'kab kendal']);

        $this->assertSame(120, $data['distance_km']);
        $this->assertSame('B', $data['zone']['zone']);
        Http::assertNothingSent();
    }

    public function test_a_specific_place_is_measured_to_its_own_point_and_remembered(): void
    {
        $this->fakeMaps(nominatim: [
            'osm_type' => 'node', 'addresstype' => 'office', 'lat' => '-7.7766', 'lon' => '110.4141',
            'address' => ['office' => 'Balai Besar Wilayah Sungai Serayu Opak', 'county' => 'Sleman', 'state' => 'Daerah Istimewa Yogyakarta'],
        ], meters: 7300);
        Regency::find(self::SLEMAN)->update(['distance_km' => 25, 'distance_source' => 'routing']);

        $first = $this->estimate(['city' => 'Balai Besar Wilayah Sungai Serayu Opak']);
        $second = $this->estimate(['city' => 'Balai Besar Wilayah Sungai Serayu Opak']);

        $this->assertSame(7, $first['distance_km'], 'jarak ke tempat itu sendiri, bukan ke ibukota Sleman');
        $this->assertSame('Balai Besar Wilayah Sungai Serayu Opak', $first['city']);
        $this->assertSame('Balai Besar Wilayah Sungai Serayu Opak (Kabupaten Sleman)', $first['distance_to']);
        $this->assertSame(self::SLEMAN, $first['regency']['id'], 'kabupatennya tetap disebut');
        $this->assertSame($first, $second);
        Http::assertSentCount(2); // geocoding + rute pertama; berikutnya dari simpanan
        $row = CityDistance::where('city_key', 'balai besar wilayah sungai serayu opak')->firstOrFail();
        $this->assertSame(7, $row->distance_km);
        $this->assertSame(self::SLEMAN, $row->regency_id);
    }

    public function test_a_town_inside_a_regency_is_measured_to_the_town(): void
    {
        $this->fakeMaps(nominatim: $this->town(-7.1290892, 110.4038912, ['town' => 'Ungaran', 'county' => 'Kabupaten Semarang', 'state' => 'Jawa Tengah']), meters: 58000);

        $data = $this->estimate(['city' => 'Ungaran']);

        $this->assertSame(58, $data['distance_km']);
        $this->assertSame('Ungaran (Kabupaten Semarang)', $data['distance_to']);
        $this->assertNull(Regency::find(self::KAB_SEMARANG)->distance_km, 'jarak kabupaten tidak tertimpa');
    }

    public function test_unrecognised_text_that_turns_out_to_be_a_whole_regency_uses_its_capital(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::sequence()
                ->push([$this->area(-7.0190965, 110.1374757, ['county' => 'Kendal', 'state' => 'Jawa Tengah'])])
                ->push([$this->town(-6.9217033, 110.2040344, ['town' => 'Kendal'])]),
            'api.tomtom.com/*' => Http::response(['routes' => [['summary' => ['lengthInMeters' => 98400]]]]),
        ]);

        $data = $this->estimate(['city' => 'Kendal Regency']);

        $this->assertSame(98, $data['distance_km']);
        $this->assertSame('Kendal (ibukota Kabupaten Kendal)', $data['distance_to']);
        $this->assertSame(-6.9217033, Regency::find(self::KENDAL)->lat, 'titik ibukota, bukan titik tengah wilayah');
        $this->assertDatabaseHas('city_distances', ['city_key' => 'kendal regency', 'regency_id' => self::KENDAL, 'source' => 'alias']);
    }

    public function test_a_place_outside_any_regency_keeps_its_own_distance(): void
    {
        $this->fakeMaps(nominatim: ['lat' => '1.3521', 'lon' => '103.8198', 'address' => ['country' => 'Singapore']], meters: 1520000);

        $data = $this->estimate(['city' => 'Singapura']);

        $this->assertSame(1520, $data['distance_km']);
        $this->assertNull($data['regency']);
        $this->assertDatabaseHas('city_distances', ['city_key' => 'singapura', 'regency_id' => null, 'distance_km' => 1520]);
    }

    public function test_a_failed_route_falls_back_to_a_straight_line_without_storing_it(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([['lat' => '-7.0190965', 'lon' => '110.1374757', 'address' => []]]),
            'api.tomtom.com/*' => Http::response([], 500),
        ]);

        $data = $this->estimate(['city' => 'Kendal']);

        $this->assertGreaterThan(0, $data['distance_km']);
        $this->assertNull(Regency::find(self::KENDAL)->distance_km, 'garis lurus tidak dikunci');
        $this->assertNotNull(Regency::find(self::KENDAL)->lat, 'lokasi tetap disimpan, tidak dicari ulang');
    }

    public function test_text_that_cannot_be_found_is_refused_with_a_hint(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([])]);

        $response = $this->estimateResponse(['city' => 'Xyzabc']);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('Kota tidak ditemukan. Pilih kabupaten/kota dari daftar.', $response->getData(true)['message']);
    }

    // ── tombol "Cari tempat" ──────────────────────────────────────────────────

    public function test_place_search_lists_up_to_five_places_with_full_addresses(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([
            $this->place('N101', 'Pasar Baru', 'Pasar Baru, Jalan Pasar Baru, Sawah Besar, Jakarta Pusat, Daerah Khusus Ibukota Jakarta, 10710, Indonesia', -6.1662, 106.8343, ['city' => 'Kota Jakarta Pusat', 'state' => 'Daerah Khusus Ibukota Jakarta']),
            $this->place('W202', 'Pasar Baru', 'Pasar Baru, Jalan Otto Iskandar Dinata, Bandung, Jawa Barat, 40181, Indonesia', -6.9175, 107.6038, ['city' => 'Kota Bandung', 'state' => 'Jawa Barat']),
            $this->place('W203', 'Pasar Baru', 'Pasar Baru, Jalan Otto Iskandar Dinata, Bandung, Jawa Barat, 40181, Indonesia', -6.9176, 107.6039, ['city' => 'Kota Bandung', 'state' => 'Jawa Barat']),
            ['osm_type' => 'relation', 'osm_id' => 303, 'addresstype' => 'county', 'name' => 'Kendal', 'display_name' => 'Kendal, Jawa Tengah, Indonesia', 'lat' => '-7.0190965', 'lon' => '110.1374757', 'address' => ['county' => 'Kendal', 'state' => 'Jawa Tengah']],
        ])]);

        $data = $this->places('Pasar Baru');

        $this->assertCount(3, $data, 'alamat kembar ditampilkan sekali');
        $this->assertSame('Pasar Baru', $data[0]['name']);
        $this->assertSame('Pasar Baru, Jalan Pasar Baru, Sawah Besar, Jakarta Pusat, Daerah Khusus Ibukota Jakarta, 10710', $data[0]['address']);
        $this->assertSame(3173, $data[0]['regency']['id'], 'Kota Jakarta Pusat');
        $this->assertNull($data[0]['regency_id'], 'tempat: jarak ke titiknya sendiri');
        $this->assertNotEmpty($data[0]['place_token']);
        $this->assertSame(3273, $data[1]['regency']['id'], 'Kota Bandung');
        $this->assertSame(self::KENDAL, $data[2]['regency_id'], 'seluruh wilayah kabupaten: pakai ibukotanya');
        Http::assertSent(fn (HttpRequest $r) => str_contains(urldecode($r->url()), 'limit=5') && str_contains($r->url(), 'countrycodes=id'));
    }

    public function test_place_search_results_are_kept_instead_of_asking_again(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([
            $this->place('N101', 'Pasar Baru', 'Pasar Baru, Sawah Besar, Jakarta Pusat, Indonesia', -6.1662, 106.8343),
        ])]);

        $this->places('Pasar Baru');
        $this->places('pasar baru');

        Http::assertSentCount(1);
    }

    public function test_place_search_reports_when_the_map_service_is_down(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([], 500)]);

        $response = (new PlaceController())->index(Request::create('/api/places', 'GET', ['q' => 'Pasar Baru']));

        $this->assertSame(503, $response->getStatusCode());
    }

    public function test_place_search_needs_at_least_three_letters(): void
    {
        $this->expectException(ValidationException::class);

        (new PlaceController())->index(Request::create('/api/places', 'GET', ['q' => 'ps']));
    }

    public function test_a_chosen_place_is_measured_to_its_own_point_and_remembered(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                $this->place('W202', 'Pasar Baru', 'Pasar Baru, Bandung, Jawa Barat, Indonesia', -6.9175, 107.6038, ['city' => 'Kota Bandung', 'state' => 'Jawa Barat']),
            ]),
            'api.tomtom.com/*' => Http::response(['routes' => [['summary' => ['lengthInMeters' => 412300]]]]),
        ]);
        $token = $this->places('Pasar Baru')[0]['place_token'];

        $first = $this->estimate(['place_token' => $token]);
        $second = $this->estimate(['place_token' => $token]);

        $this->assertSame(412, $first['distance_km']);
        $this->assertSame('Pasar Baru', $first['city']);
        $this->assertSame('Pasar Baru (Kota Bandung)', $first['distance_to']);
        $this->assertSame('B', $first['zone']['zone']);
        $this->assertSame($first, $second);
        Http::assertSent(fn (HttpRequest $r) => str_contains(urldecode($r->url()), '-6.9175,107.6038/json'));
        Http::assertSentCount(2); // satu pencarian + satu rute; pilihan kedua dari simpanan
        $this->assertDatabaseHas('city_distances', ['city_key' => 'place:w202', 'distance_km' => 412, 'regency_id' => 3273]);
    }

    public function test_a_forged_place_token_is_refused(): void
    {
        Http::fake();

        $response = $this->estimateResponse(['place_token' => 'bukan-token-asli']);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('Pilihan tempat tidak dikenali. Cari ulang tempatnya.', $response->getData(true)['message']);
        Http::assertNothingSent();
    }

    // ── saran tempat sambil mengetik (TomTom) ─────────────────────────────────

    public function test_typing_suggests_places_from_tomtom_without_whole_regions(): void
    {
        Http::fake(['api.tomtom.com/search/*' => Http::response(['results' => [
            ['type' => 'Geography', 'entityType' => 'Municipality', 'id' => 'kota', 'address' => ['municipality' => 'Semarang', 'countrySubdivisionName' => 'Jawa Tengah', 'freeformAddress' => 'Semarang, Jawa Tengah'], 'position' => ['lat' => -6.99, 'lon' => 110.42]],
            $this->tomtomPoi('abc', 'Simpang Lima', 'Jalan Simpang Lima, Kelurahan Pleburan, Jawa Tengah 50241', 'Semarang', -6.9903, 110.4231),
            ['type' => 'Geography', 'entityType' => 'MunicipalitySecondarySubdivision', 'id' => 'kel', 'address' => ['municipalitySecondarySubdivision' => 'Ungaran', 'municipalitySubdivision' => 'Ungaran Barat', 'municipality' => 'Semarang', 'countrySubdivisionName' => 'Jawa Tengah', 'freeformAddress' => 'Ungaran, Ungaran Barat Semarang, Jawa Tengah'], 'position' => ['lat' => -7.13, 'lon' => 110.40]],
        ]])]);

        $data = $this->suggest('simpang lima');

        $this->assertSame(['Simpang Lima', 'Ungaran'], array_column($data, 'name'), 'kabupaten/kota utuh dibuang, sudah ada di saran kabupaten/kota');
        $this->assertSame('Jalan Simpang Lima, Kelurahan Pleburan, Jawa Tengah 50241', $data[0]['address']);
        $this->assertSame('Semarang, Jawa Tengah', $data[0]['where'], 'letak apa adanya, tidak ditebak Kota atau Kabupaten');
        $this->assertNull($data[0]['regency']);
        $this->assertNotEmpty($data[0]['place_token']);
        Http::assertSent(function (HttpRequest $r) {
            $url = urldecode($r->url());

            return str_contains($url, '/search/2/search/simpang lima.json')
                && str_contains($url, 'typeahead=true') && str_contains($url, 'countrySet=ID') && str_contains($url, 'language=id-ID');
        });
    }

    public function test_the_same_typing_is_answered_from_storage(): void
    {
        Http::fake(['api.tomtom.com/search/*' => Http::response(['results' => [
            $this->tomtomPoi('abc', 'Simpang Lima', 'Jalan Simpang Lima', 'Semarang', -6.9903, 110.4231),
        ]])]);

        $this->suggest('simpang lima');
        $this->suggest('Simpang Lima');

        Http::assertSentCount(1);
    }

    public function test_without_a_tomtom_key_or_when_it_fails_there_are_no_place_suggestions(): void
    {
        Http::fake(['api.tomtom.com/search/*' => Http::response([], 500)]);

        $this->assertSame([], $this->suggest('simpang lima'), 'TomTom gagal');

        config(['services.tomtom.key' => '']);
        $this->assertSame([], $this->suggest('pasar baru'), 'key kosong');
        Http::assertSentCount(1);
    }

    public function test_a_suggested_place_is_measured_to_its_point_and_named_as_tomtom_wrote_it(): void
    {
        Http::fake([
            'api.tomtom.com/search/*' => Http::response(['results' => [
                $this->tomtomPoi('abc', 'Simpang Lima', 'Jalan Simpang Lima', 'Semarang', -6.9903, 110.4231),
            ]]),
            'api.tomtom.com/routing/*' => Http::response(['routes' => [['summary' => ['lengthInMeters' => 120400]]]]),
        ]);
        $token = $this->suggest('simpang lima')[0]['place_token'];

        $data = $this->estimate(['place_token' => $token]);

        $this->assertSame(120, $data['distance_km']);
        $this->assertSame('Simpang Lima (Semarang, Jawa Tengah)', $data['distance_to']);
        $this->assertNull($data['regency']);
        Http::assertSent(fn (HttpRequest $r) => str_contains(urldecode($r->url()), '-6.9903,110.4231/json'));
        $this->assertDatabaseHas('city_distances', ['city_key' => 'place:tabc', 'distance_km' => 120]);
    }

    public function test_typing_suggestions_are_limited_per_user(): void
    {
        $route = Route::getRoutes()->match(Request::create('/api/places/suggest', 'GET'));

        $this->assertSame(PlaceController::class.'@suggest', $route->getActionName());
        $this->assertContains('throttle:30,1', $route->gatherMiddleware());
        $this->assertContains('auth:sanctum', $route->gatherMiddleware());
    }

    // ── pilih titik di peta ───────────────────────────────────────────────────

    public function test_a_map_point_is_measured_to_itself_and_marked_as_chosen_by_hand(): void
    {
        Http::fake(['api.tomtom.com/routing/*' => Http::response(['routes' => [['summary' => ['lengthInMeters' => 131200]]]])]);

        $first = $this->estimate(['lat' => '-6.990312', 'lng' => '110.423104', 'point_label' => 'Jalan Pahlawan, Pleburan (Semarang, Jawa Tengah)']);
        $nearby = $this->estimate(['lat' => '-6.990330', 'lng' => '110.423120', 'point_label' => 'Jalan Pahlawan']);

        $this->assertSame(131, $first['distance_km']);
        $this->assertSame('map', $first['distance_source']);
        $this->assertSame('titik peta: Jalan Pahlawan, Pleburan (Semarang, Jawa Tengah)', $first['distance_to']);
        $this->assertSame(-6.990312, $first['lat']);
        $this->assertSame(131, $nearby['distance_km'], 'titik ±10 m memakai jarak yang sama');
        Http::assertSentCount(1);
        Http::assertSent(fn (HttpRequest $r) => str_contains(urldecode($r->url()), '-6.990312,110.423104/json'));
    }

    public function test_a_map_point_without_an_address_is_named_by_its_coordinates(): void
    {
        Http::fake(['api.tomtom.com/routing/*' => Http::response(['routes' => [['summary' => ['lengthInMeters' => 50000]]]])]);

        $data = $this->estimate(['lat' => '-7.5', 'lng' => '110.25']);

        $this->assertSame('titik peta: -7.50000, 110.25000', $data['distance_to']);
    }

    public function test_a_map_point_outside_indonesia_is_refused(): void
    {
        $this->expectException(ValidationException::class);

        $this->estimateResponse(['lat' => '35.6812', 'lng' => '139.7671']); // Tokyo
    }

    public function test_every_estimate_says_where_its_distance_came_from(): void
    {
        Regency::find(self::KENDAL)->update(['distance_km' => 98, 'lat' => -6.92, 'lng' => 110.2]);
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([
            ['osm_type' => 'node', 'osm_id' => 9, 'addresstype' => 'town', 'name' => 'Ungaran', 'display_name' => 'Ungaran, Indonesia', 'lat' => '-7.129', 'lon' => '110.404', 'address' => ['county' => 'Kabupaten Semarang', 'state' => 'Jawa Tengah']],
        ]), 'api.tomtom.com/*' => Http::response(['routes' => [['summary' => ['lengthInMeters' => 58000]]]])]);

        $this->assertSame('regency', $this->estimate(['city' => 'Kendal'])['distance_source']);
        $this->assertSame('text', $this->estimate(['city' => 'Ungaran'])['distance_source']);
        $this->assertSame('text', $this->estimate(['city' => 'Ungaran'])['distance_source'], 'dari simpanan pun sama');
        $token = $this->places('Ungaran')[0]['place_token'];
        $this->assertSame('place', $this->estimate(['place_token' => $token])['distance_source']);
    }

    #[DataProvider('pastedPoints')]
    public function test_coordinates_and_google_maps_links_are_read(string $text, float $lat, float $lng): void
    {
        $this->assertEquals(['lat' => $lat, 'lng' => $lng], MapPoint::fromText($text));
    }

    public static function pastedPoints(): array
    {
        return [
            'koordinat' => ['-6.9903, 110.4231', -6.9903, 110.4231],
            'koordinat tanpa koma' => ['-6.9903 110.4231', -6.9903, 110.4231],
            'link tempat: pin didahulukan dari pusat peta' => ['https://www.google.com/maps/place/Simpang+Lima/@-6.9900,110.4200,17z/data=!3m1!4b1!4m6!3m5!1s0x0:0x1!8m2!3d-6.9903!4d110.4231', -6.9903, 110.4231],
            'link pusat peta' => ['https://www.google.com/maps/@-7.7956,110.3695,15z', -7.7956, 110.3695],
            'link q' => ['https://maps.google.com/?q=-7.7956,110.3695', -7.7956, 110.3695],
            'link api query' => ['https://www.google.com/maps/search/?api=1&query=-7.7956%2C110.3695', -7.7956, 110.3695],
            'teks dengan link' => ['Lokasi klien: https://maps.google.com/?q=-7.7956,110.3695 ya', -7.7956, 110.3695],
        ];
    }

    public function test_a_short_share_link_is_followed_only_within_google(): void
    {
        Http::fake([
            'maps.app.goo.gl/abc' => Http::response('', 302, ['Location' => 'https://www.google.com/maps/place/X/@-6.9,110.4,17z/data=!3d-6.9903!4d110.4231']),
            'maps.app.goo.gl/evil' => Http::response('', 302, ['Location' => 'https://evil.example.com/@-6.9,110.4']),
        ]);

        $this->assertEquals(['lat' => -6.9903, 'lng' => 110.4231], MapPoint::fromText('https://maps.app.goo.gl/abc'));
        $this->assertNull(MapPoint::fromText('https://maps.app.goo.gl/evil'), 'pengalihan ke luar Google tidak diikuti');
        $this->assertNull(MapPoint::fromText('https://example.com/share/abc'), 'link pendek bukan Google tidak dibuka');
        Http::assertSentCount(2); // hanya dua link pendek Google; link lain tidak pernah diminta
    }

    public function test_the_point_endpoint_refuses_unknown_text_and_points_outside_indonesia(): void
    {
        Http::fake();

        $ok = (new PlaceController())->point(Request::create('/api/places/point', 'GET', ['text' => '-6.9903, 110.4231']));
        $unknown = (new PlaceController())->point(Request::create('/api/places/point', 'GET', ['text' => 'kantor klien']));
        $abroad = (new PlaceController())->point(Request::create('/api/places/point', 'GET', ['text' => '35.6812, 139.7671']));

        $this->assertSame(['lat' => -6.9903, 'lng' => 110.4231], $ok->getData(true)['data']);
        $this->assertSame(422, $unknown->getStatusCode());
        $this->assertSame('Link atau koordinat tidak dikenali.', $unknown->getData(true)['message']);
        $this->assertSame(422, $abroad->getStatusCode());
        $this->assertSame('Titik itu di luar Indonesia.', $abroad->getData(true)['message']);
    }

    public function test_the_pin_address_comes_from_tomtom_and_is_kept(): void
    {
        Http::fake(['api.tomtom.com/search/2/reverseGeocode/*' => Http::response(['addresses' => [['address' => [
            'streetName' => 'Jalan Pahlawan', 'municipalitySecondarySubdivision' => 'Pleburan', 'municipality' => 'Semarang',
            'countrySubdivisionName' => 'Jawa Tengah', 'freeformAddress' => 'Jalan Pahlawan, Kelurahan Pleburan, Semarang, Jawa Tengah 50241',
        ]]]])]);

        $reverse = fn () => (new PlaceController())->reverse(Request::create('/api/places/reverse', 'GET', ['lat' => '-6.99031', 'lng' => '110.42311']))->getData(true)['data'];
        $first = $reverse();
        $second = $reverse();

        $this->assertSame([
            'label' => 'Jalan Pahlawan, Pleburan',
            'address' => 'Jalan Pahlawan, Kelurahan Pleburan, Semarang, Jawa Tengah 50241',
            'where' => 'Semarang, Jawa Tengah',
        ], $first);
        $this->assertSame($first, $second);
        Http::assertSentCount(1);
    }

    public function test_map_routes_are_limited_per_user(): void
    {
        foreach ([['api/places/reverse', 'reverse', 'throttle:30,1'], ['api/places/point', 'point', 'throttle:20,1']] as [$uri, $action, $throttle]) {
            $route = Route::getRoutes()->match(Request::create('/'.$uri, 'GET'));

            $this->assertSame(PlaceController::class.'@'.$action, $route->getActionName());
            $this->assertContains($throttle, $route->gatherMiddleware());
            $this->assertContains('auth:sanctum', $route->gatherMiddleware());
        }
    }

    public function test_the_routes_exist_behind_sanctum(): void
    {
        foreach ([['api/regencies', RegencyController::class.'@index'], ['api/places', PlaceController::class.'@index'], ['api/travel/estimate-zone', TravelZoneController::class.'@estimateZone']] as [$uri, $action]) {
            $route = Route::getRoutes()->match(Request::create('/'.$uri, 'GET'));

            $this->assertSame($action, $route->getActionName());
            $this->assertContains('auth:sanctum', $route->gatherMiddleware());
        }
    }

    // ── bantuan ───────────────────────────────────────────────────────────────

    private function searchIds(string $query): array
    {
        return RegencyMatcher::search($query)->pluck('id')->all();
    }

    /** Satu hasil pencarian tempat dari Nominatim. */
    private function place(string $key, string $name, string $display, float $lat, float $lng, array $address = []): array
    {
        $types = ['N' => 'node', 'W' => 'way', 'R' => 'relation'];

        return [
            'osm_type' => $types[$key[0]], 'osm_id' => (int) substr($key, 1), 'addresstype' => 'retail',
            'name' => $name, 'display_name' => $display, 'lat' => (string) $lat, 'lon' => (string) $lng, 'address' => $address,
        ];
    }

    private function tomtomPoi(string $id, string $name, string $address, string $municipality, float $lat, float $lng): array
    {
        return [
            'type' => 'POI', 'id' => $id, 'poi' => ['name' => $name],
            'address' => ['municipality' => $municipality, 'countrySubdivisionName' => 'Jawa Tengah', 'freeformAddress' => $address],
            'position' => ['lat' => $lat, 'lon' => $lng],
        ];
    }

    private function suggest(string $query): array
    {
        $response = (new PlaceController())->suggest(Request::create('/api/places/suggest', 'GET', ['q' => $query]));
        $this->assertSame(200, $response->getStatusCode());

        return $response->getData(true)['data'];
    }

    private function places(string $query): array
    {
        $response = (new PlaceController())->index(Request::create('/api/places', 'GET', ['q' => $query]));
        $this->assertSame(200, $response->getStatusCode(), json_encode($response->getData(true)));

        return $response->getData(true)['data'];
    }

    /** Hasil Nominatim berupa tempat (kota/desa), seperti yang didapat dengan featureType=settlement. */
    private function town(float $lat, float $lng, array $address = []): array
    {
        return ['osm_type' => 'node', 'addresstype' => 'town', 'lat' => (string) $lat, 'lon' => (string) $lng, 'address' => $address];
    }

    /** Hasil Nominatim berupa seluruh wilayah kabupaten/kota (titik tengah batas wilayahnya). */
    private function area(float $lat, float $lng, array $address = []): array
    {
        return ['osm_type' => 'relation', 'addresstype' => 'county', 'lat' => (string) $lat, 'lon' => (string) $lng, 'address' => $address];
    }

    private function fakeMaps(array $nominatim, int $meters): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([$nominatim]),
            'api.tomtom.com/*' => Http::response(['routes' => [['summary' => ['lengthInMeters' => $meters]]]]),
        ]);
    }

    private function sentQuery(): string
    {
        $sent = Http::recorded(fn (HttpRequest $request) => str_contains($request->url(), 'nominatim'))->first();

        return urldecode($sent[0]->url());
    }

    private function estimateResponse(array $query)
    {
        return (new TravelZoneController())->estimateZone(Request::create('/api/travel/estimate-zone', 'GET', $query));
    }

    private function estimate(array $query): array
    {
        $response = $this->estimateResponse($query);
        $this->assertSame(200, $response->getStatusCode(), json_encode($response->getData(true)));

        return $response->getData(true)['data'];
    }
}
