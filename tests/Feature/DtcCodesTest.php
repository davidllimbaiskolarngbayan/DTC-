<?php

namespace Tests\Feature;

use App\Models\DtcCode;
use App\Models\User;
use App\Models\Dtc;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DtcCodesTest extends TestCase
{
    use RefreshDatabase;

    private function api(string $method, string $uri, array $data = [])
    {
        return $this->withHeaders(['X-DTC-Key' => 'test-key', 'Accept' => 'application/json'])->json($method, $uri, $data);
    }

    private function registration(string $card): array
    {
        return [
            'first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@example.com', 'contact_no' => '0917123456', 'vehicle_reg_id' => 'ABC123',
            'tyre' => 'Eagle F1', 'quantity' => 4, 'date_of_purchase' => now()->subDay()->toDateString(),
            'find_retailer' => 'Main Store', 'dtc_card_no' => $card, 'tnc' => 1,
            'brand' => 'Toyota', 'model' => 'Vios', 'tyre_size' => '205/55', 'rim_size' => 'R16', 'state' => 'Metro', 'mileage' => 45000,
        ];
    }

    public function test_pages_require_login(): void
    {
        $this->get('/dtc_codes')->assertRedirect('/login');
        $this->post('/dtc_codes', ['counter' => 5])->assertRedirect('/login');
        $this->get('/dtc_codes/download')->assertRedirect('/login');
    }

    public function test_admin_generates_distinct_unambiguous_codes(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->post('/dtc_codes', ['counter' => 50])->assertRedirect('/dtc_codes');

        $codes = DtcCode::pluck('code');
        $this->assertCount(50, $codes);
        $this->assertCount(50, $codes->unique());
        $codes->each(fn ($code) => $this->assertMatchesRegularExpression('/^[2-9A-HJKMNP-Z]{8}$/', $code));

        $this->get('/dtc_codes')->assertOk()
            ->assertSee('DTC Codes')
            ->assertSee('Generate DTC Codes')
            ->assertSee('DTC Code')
            ->assertSee($codes->first());
        $this->get('/dtc_codes?search='.strtolower($codes->first()))->assertOk()->assertSee($codes->first());
    }

    public function test_counter_is_validated(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->post('/dtc_codes', ['counter' => 0])->assertSessionHasErrors('counter');
        $this->post('/dtc_codes', ['counter' => 5001])->assertSessionHasErrors('counter');
        $this->assertSame(0, DtcCode::count());
    }

    public function test_download_streams_csv(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        DtcCode::create(['code' => 'ABCD2345']);

        $csv = $this->get('/dtc_codes/download')->assertOk()->streamedContent();

        $this->assertStringContainsString('"DTC Code",Used,"Created At"', $csv);
        $this->assertStringContainsString('ABCD2345,0', $csv);
    }

    public function test_api_checks_codes_and_requires_key(): void
    {
        DtcCode::create(['code' => 'ABCD2345']);
        DtcCode::create(['code' => 'USED2345', 'is_use' => true]);

        $this->getJson('/api/dtc_codes/is_exist/ABCD2345')->assertStatus(401);
        $this->api('GET', '/api/dtc_codes/is_exist/abcd2345')->assertJson(['success' => true, 'data' => ['code' => 'ABCD2345']]);
        $this->api('GET', '/api/dtc_codes/is_exist/USED2345')->assertJson(['success' => false]);
        $this->api('GET', '/api/dtc_codes_new/is_exist/USED2345')->assertJson(['success' => true, 'is_use' => true]);
        $this->api('GET', '/api/dtc_codes_new/is_exist/NOPE0000')->assertJson(['success' => false]);
    }

    public function test_registration_marks_code_used(): void
    {
        DtcCode::create(['code' => 'ABCD2345']);

        $this->api('POST', '/api/register', $this->registration('abcd2345'))->assertCreated();

        $this->assertTrue(DtcCode::where('code', 'ABCD2345')->first()->is_use);
    }

    public function test_registration_requires_unused_code_when_enabled(): void
    {
        config(['dtc.require_dtc_code' => true]);
        DtcCode::create(['code' => 'GOOD2345']);
        DtcCode::create(['code' => 'USED2345', 'is_use' => true]);

        $this->api('POST', '/api/register', $this->registration('MADEUP99'))->assertStatus(422);
        $this->api('POST', '/api/register', $this->registration('USED2345'))->assertStatus(422);
        $this->assertSame(0, Dtc::count());

        $this->api('POST', '/api/register', $this->registration('GOOD2345'))->assertCreated();
        $this->assertSame(1, Dtc::count());
    }
}
