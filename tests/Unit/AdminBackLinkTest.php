<?php

namespace Tests\Unit;

use App\Support\AdminBackLink;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Tombol Kembali di detail anggaran/LHP/LPJ: kembali ke halaman asal (lengkap dengan filter dan
 * halamannya) kalau dibuka dari sana, selain itu ke daftar bawaan. Alamat asal hanya diterima
 * kalau relatif di bawah /admin.
 */
class AdminBackLinkTest extends TestCase
{
    private function detail(array $query): Request
    {
        return Request::create('/admin/budget-requests/5', 'GET', $query);
    }

    public function test_detail_links_remember_the_page_they_were_opened_from(): void
    {
        $approvals = Request::create('/admin/approvals?tab=lpj&department_id=3&page=2', 'GET');

        $this->assertSame(['back' => '/admin/approvals?tab=lpj&department_id=3&page=2'], AdminBackLink::here($approvals));
        $this->assertSame(
            url('/admin/lpj/9?back=%2Fadmin%2Fapprovals%3Ftab%3Dlpj%26department_id%3D3%26page%3D2'),
            route('admin.lpj.show', ['id' => 9] + AdminBackLink::here($approvals))
        );
    }

    public function test_back_returns_to_the_exact_origin_page_with_its_filters(): void
    {
        foreach ([
            '/admin/approvals?tab=lpj&department_id=3' => 'Kembali ke Persetujuan',
            '/admin/budget-requests?status=approved&page=3' => 'Kembali ke Anggaran',
            '/admin/travel-reports?search=bogor' => 'Kembali ke LHP',
            '/admin/lpj?status=pending' => 'Kembali ke LPJ',
            '/admin/dashboard' => 'Kembali ke Dashboard',
            '/admin/assignment-letters/4' => 'Kembali ke Surat Tugas',
        ] as $origin => $label) {
            $request = $this->detail(['back' => $origin]);

            $this->assertSame($origin, AdminBackLink::url($request, 'admin.budget-requests.index'), $origin);
            $this->assertSame($label, AdminBackLink::label($request), $origin);
        }
    }

    public function test_back_defaults_to_own_list_without_origin(): void
    {
        $request = $this->detail([]);

        $this->assertSame(route('admin.budget-requests.index'), AdminBackLink::url($request, 'admin.budget-requests.index'));
        $this->assertSame('Kembali', AdminBackLink::label($request));
        $this->assertSame([], AdminBackLink::forward($request));
    }

    public function test_origin_outside_admin_is_ignored(): void
    {
        foreach ([
            'https://evil.test/admin/x',
            '//evil.test/admin/x',
            '/admin/../login',
            '/administrator/x',
            '/employee/dashboard',
            "/admin/x\r\nSet-Cookie: a=b",
            '/admin\\evil',
            '/admin/a b',
            '/admin//evil.test',
            '/admin/' . str_repeat('a', 700),
        ] as $evil) {
            $request = $this->detail(['back' => $evil]);

            $this->assertSame(route('admin.budget-requests.index'), AdminBackLink::url($request, 'admin.budget-requests.index'), $evil);
            $this->assertSame('Kembali', AdminBackLink::label($request), $evil);
        }

        $this->assertSame(route('admin.lpj.index'), AdminBackLink::url($this->detail(['back' => ['x']]), 'admin.lpj.index'));
    }

    public function test_sibling_pages_forward_the_same_origin_and_actions_read_it_from_referer(): void
    {
        $this->assertSame(['back' => '/admin/travel-reports?page=2'], AdminBackLink::forward($this->detail(['back' => '/admin/travel-reports?page=2'])));
        $this->assertSame([], AdminBackLink::forward($this->detail(['back' => 'https://evil.test'])));

        $post = Request::create('/admin/budget-requests/5/payments', 'POST');
        $post->headers->set('referer', url('/admin/budget-requests/5?back=%2Fadmin%2Fapprovals%3Ftab%3Dbudget'));
        $this->assertSame(['back' => '/admin/approvals?tab=budget'], AdminBackLink::fromReferer($post));

        $this->assertSame([], AdminBackLink::fromReferer(Request::create('/admin/budget-requests/5/payments', 'POST')));
    }
}
