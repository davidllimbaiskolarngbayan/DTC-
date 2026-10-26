<?php

namespace Tests\Feature;

use App\Models\Dtc;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DtcPlateEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private function api(string $uri, array $data)
    {
        return $this->withHeaders(['X-DTC-Key' => 'test-key', 'Accept' => 'application/json'])->postJson($uri, $data);
    }

    private function dtc(string $card, string $date): Dtc
    {
        return Dtc::create([
            'firstname' => 'Ada', 'lastname' => 'L', 'contact_no' => '0917123456', 'vehicle_reg_id' => 'ABC123',
            'tyre' => 'T', 'quantity' => 4, 'date_of_purchase' => $date, 'store_name' => 'S', 'dtc_card_no' => $card,
        ]);
    }

    public function test_latest_entry_today_returns_masked_plate_or_null(): void
    {
        $this->api('/api/fetchPlateNumLatestEntryToday', ['vehicle_reg_id' => 'abc-123'])->assertOk()->assertJson(['data' => null]);

        $this->dtc('c1', now()->toDateString());

        $response = $this->api('/api/fetchPlateNumLatestEntryToday', ['vehicle_reg_id' => 'abc-123'])->assertOk();
        $response->assertJsonPath('data.plate_no', 'AB****');
        $this->assertStringNotContainsString('ABC123', $response->getContent());
    }

    public function test_count_all_purchase_dates_counts_same_month_only(): void
    {
        $this->dtc('c1', '2026-03-05');
        $this->dtc('c2', '2026-03-28');
        $this->dtc('c3', '2026-04-01');

        $this->api('/api/countAllPurchaseDates', ['vehicle_reg_id' => 'ABC123', 'date_of_purchase' => '2026-03-15'])
            ->assertOk()->assertJson(['data' => 2]);
        $this->api('/api/countAllPurchaseDates', ['vehicle_reg_id' => 'ZZZ999', 'date_of_purchase' => '2026-03-15'])
            ->assertOk()->assertJson(['data' => 0]);
    }
}
