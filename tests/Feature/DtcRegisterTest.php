<?php

namespace Tests\Feature;

use App\Mail\DtcThankYou;
use App\Mail\ClaimSubmitted;
use App\Models\Dtc;
use App\Models\DtcAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DtcRegisterTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.com',
            'contact_no' => '+1 555 0100',
            'vehicle_reg_id' => 'ab-123 cd',
            'tyre' => 'Eagle F1',
            'quantity' => 4,
            'date_of_purchase' => now()->subDay()->toDateString(),
            'find_retailer' => 'Main Store',
            'dtc_card_no' => 'dtc-0001',
            'brand' => 'Toyota',
            'model' => 'Vios',
            'tyre_size' => '205/55',
            'rim_size' => 'R16',
            'state' => 'Metro',
            'mileage' => 45000,
            'tnc' => 1,
            'subscribe' => 1,
        ], $overrides);
    }

    private function api(string $uri, array $data = [], string $key = 'test-key')
    {
        if ($uri === '/api/claims' && ! isset($data['idempotency_key'])) {
            $data['idempotency_key'] = (string) \Illuminate\Support\Str::uuid();
        }

        return $this->withHeaders(['X-DTC-Key' => $key, 'Accept' => 'application/json'])->postJson($uri, $data);
    }

    public function test_delete_soft_deletes_registration_and_requires_admin_authentication(): void
    {
        $dtc = Dtc::create([
            'firstname' => 'Ada', 'lastname' => 'Lovelace', 'contact_no' => '0917123456',
            'vehicle_reg_id' => 'ABC123', 'tyre' => 'Eagle F1', 'quantity' => 4,
            'date_of_purchase' => now()->subDay()->toDateString(), 'store_name' => 'Main Store',
            'dtc_card_no' => 'DTC-0001',
        ]);

        $this->withHeaders(['X-DTC-Key' => 'test-key', 'Accept' => 'application/json'])
            ->deleteJson("/api/dtc/{$dtc->id}")
            ->assertUnauthorized();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->withHeaders(['X-DTC-Key' => 'test-key', 'Accept' => 'application/json'])
            ->deleteJson("/api/dtc/{$dtc->id}")
            ->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertSoftDeleted('dtc_users', ['id' => $dtc->id]);
        $this->assertNull(Dtc::find($dtc->id));
        $this->assertDatabaseHas('dtc_audit_logs', [
            'dtc_id' => $dtc->id,
            'action' => 'registrant.deleted',
            'summary' => 'API deleted registrant record',
        ]);
    }

    public function test_wrong_api_key_returns_401(): void
    {
        $this->api('/api/register', $this->payload(), 'wrong')->assertStatus(401);
    }

    public function test_register_creates_encrypted_record_and_sends_thank_you_email(): void
    {
        Mail::fake();

        $response = $this->api('/api/register', $this->payload())->assertCreated()->assertJson(['dtc_card_no' => 'DTC-0001']);

        $this->assertNotSame('Ada', \DB::table('dtc_users')->value('firstname'));
        $dtc = Dtc::first();
        $this->assertSame('Ada', $dtc->firstname);
        $this->assertSame('Main Store', $dtc->store_name);
        $this->assertTrue($dtc->subscribe);

        Mail::assertSent(DtcThankYou::class, function (DtcThankYou $mail) {
            $html = $mail->render();

            return $mail->hasTo('ada@example.com')
                && str_contains($html, '<!DOCTYPE html>')
                && preg_match('/src="(?:cid:|data:image\/svg\+xml;base64,)/', $html) === 1
                && str_contains($html, 'alt="David Tyre Company"')
                && str_contains($html, 'background-color:#f4f4f4')
                && str_contains($html, '<strong>Brand</strong>')
                && str_contains($html, 'width="155" valign="top"')
                && str_contains($html, 'font-size:16px')
                && str_contains($html, 'DTC Code')
                && str_contains($html, 'Main Store');
        });
        $this->assertStringContainsString('/register/thankyou/', $response->json('thankyou_url'));
    }

    public function test_register_saves_promo_data_and_invoice_and_shows_promo_on_registrant_view(): void
    {
        Mail::fake();
        Storage::fake('local');

        $this->withHeaders(['X-DTC-Key' => 'test-key', 'Accept' => 'application/json'])
            ->post('/api/register', $this->payload([
                'validpromo' => 1,
                'promo' => [
                    'promo' => 'eFuel Voucher',
                    'assisted_person' => 'David',
                    'confirm' => 'Yes',
                    'terms' => 'Yes',
                ],
                'invoice' => UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf'),
            ]))
            ->assertCreated();

        $registrant = Dtc::firstOrFail();
        $this->assertDatabaseHas('dtc_promo', [
            'user_id' => $registrant->id,
            'promo_key' => 'promo',
            'promo_value' => 'eFuel Voucher',
        ]);
        $this->assertDatabaseHas('dtc_promo', [
            'user_id' => $registrant->id,
            'promo_key' => 'assisted_person',
            'promo_value' => 'David',
        ]);
        $invoicePath = $registrant->promos()->where('promo_key', 'invoice')->value('promo_value');
        $this->assertNotNull($invoicePath);
        Storage::disk('local')->assertExists($invoicePath);
        $this->assertFalse(Schema::hasColumn('dtc_promo', 'locale'));

        $showResponse = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('dtc.show', $registrant))
            ->assertOk()
            ->assertSee('View Invoice')
            ->assertSee('Promo')
            ->assertSee('eFuel Voucher')
            ->assertSee('Assisted Person')
            ->assertSee('David')
            ->assertDontSee('<td>Term</td>', false)
            ->assertDontSee('Personnel')
            ->assertSee('Confirm')
            ->assertSee('Terms')
            ->assertSee('data-toggle="modal"', false)
            ->assertSee('data-target="#dtcPromoInvoiceModal"', false)
            ->assertSee('id="dtcPromoInvoiceFrame"', false)
            ->assertDontSee('target="_blank"', false);

        $content = $showResponse->getContent();
        $termsPosition = strpos($content, '<td>Terms</td>');
        $invoicePosition = strpos($content, '<td>Invoice</td>');
        $this->assertNotFalse($termsPosition);
        $this->assertNotFalse($invoicePosition);
        $this->assertGreaterThan($termsPosition, $invoicePosition);

        $this->get(route('dtc.promo-invoice', $registrant))->assertOk();
    }

    public function test_register_rejects_invalid_input_and_duplicates(): void
    {
        $this->api('/api/register', $this->payload())->assertCreated();

        $this->api('/api/register', $this->payload(['dtc_card_no' => 'dtc-0001', 'vehicle_reg_id' => 'zz9']))
            ->assertStatus(422)->assertJsonPath('status', 'error')->assertJsonPath('message.dtc_card_no.0', 'The DTC Code has already been taken.');
        $this->api('/api/register', $this->payload(['dtc_card_no' => 'DTC-0002', 'vehicle_reg_id' => 'AB123CD']))
            ->assertStatus(422)->assertJsonPath('status', 'error');
        $this->api('/api/register', $this->payload(['dtc_card_no' => 'DTC-0003', 'vehicle_reg_id' => 'x1', 'tnc' => null]))
            ->assertStatus(422);
        $this->api('/api/register', $this->payload([
            'dtc_card_no' => 'DTC-0004',
            'vehicle_reg_id' => 'x2',
            'date_of_purchase' => now(config('dtc.timezone'))->addDay()->toDateString(),
        ]))
            ->assertStatus(422);

        $this->assertSame(1, Dtc::count());
    }

    public function test_purchase_date_today_in_business_timezone_is_accepted_before_utc_midnight(): void
    {
        config(['dtc.timezone' => 'Asia/Manila']);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-09 16:30:00', 'UTC'));

        $this->api('/api/register', $this->payload([
            'date_of_purchase' => '2026-10-10',
        ]))->assertCreated();

        $this->assertSame('2026-10-10', Dtc::firstOrFail()->date_of_purchase->toDateString());
    }

    public function test_registration_requires_a_valid_email(): void
    {
        $this->api('/api/register', $this->payload(['email' => null]))
            ->assertStatus(422)
            ->assertJsonPath('message.email.0', 'The email field is required.');

        $this->api('/api/register', $this->payload(['email' => 'not-an-email']))
            ->assertStatus(422)
            ->assertJsonPath('message.email.0', 'The email field must be a valid email address.');
    }

    public function test_check_existing_record_detects_duplicate(): void
    {
        $this->api('/api/register', $this->payload())->assertCreated();

        $this->api('/api/checkExistingRecord', ['vehicle_reg_id' => 'AB123CD', 'quantity' => 4, 'date_of_purchase' => now()->subDay()->toDateString()])
            ->assertOk()->assertJson(['data' => true]);
        $this->api('/api/checkExistingRecord', ['vehicle_reg_id' => 'AB123CD', 'quantity' => 2, 'date_of_purchase' => now()->subDay()->toDateString()])
            ->assertOk()->assertJson(['data' => false]);
    }

    public function test_lookup_finds_by_plate_and_exposes_no_pii(): void
    {
        $this->api('/api/register', $this->payload())->assertCreated();

        $response = $this->api('/api/checkRecordByDtcCodeOrPlateNum', ['dtc_code' => 'AB123CD'])->assertOk();

        $response->assertJsonPath('data.0.dtc_card_no', 'DTC-0001')
            ->assertJsonPath('data.0.vehicle_reg_id', 'AB*****')
            ->assertJsonPath('data.0.claims', []);
        $this->assertStringNotContainsString('Lovelace', $response->getContent());
        $this->api('/api/checkRecordByDtcCodeOrPlateNum', ['dtc_code' => 'nope'])->assertOk()->assertJson(['data' => []]);
    }

    public function test_warranty_lookup_includes_claim_status_history_without_private_claim_details(): void
    {
        $this->api('/api/register', $this->payload())->assertCreated();
        $this->api('/api/register', $this->payload([
            'dtc_card_no' => 'DTC-0002',
            'date_of_purchase' => now()->subMonths(2)->toDateString(),
            'quantity' => 2,
        ]))->assertCreated();
        Dtc::query()->update(['email_verified_at' => now()]);

        $this->api('/api/claims', [
            'dtc_card_no' => 'DTC-0001',
            'subject' => 'Internal subject must not be public',
            'description' => 'Internal claim description must not be public',
        ])->assertCreated();

        $claim = \App\Models\Claim::firstOrFail();
        $claim->update(['status' => 'under_review']);
        $claim->histories()->create([
            'event_type' => 'status_changed',
            'from_status' => 'submitted',
            'to_status' => 'under_review',
            'note' => 'Internal staff note must not be public',
            'customer_message' => 'Your claim is being reviewed.',
        ]);

        $this->api('/api/claims', [
            'dtc_card_no' => 'DTC-0001',
            'subject' => 'A second claim on the same card',
            'description' => 'A separate issue for the same registration.',
            'different_issue_confirmed' => 1,
        ])->assertCreated();
        $this->api('/api/claims', [
            'dtc_card_no' => 'DTC-0002',
            'subject' => 'Claim for the other purchase',
            'description' => 'A separate issue for the other registration.',
        ])->assertCreated();

        $plateLookup = $this->api('/api/checkRecordByDtcCodeOrPlateNum', ['dtc_code' => 'AB123CD'])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.dtc_card_no', 'DTC-0001')
            ->assertJsonPath('data.0.warranty_eligible', true)
            ->assertJsonPath('data.0.warranty_status', 'Eligible')
            ->assertJsonPath('data.0.claims.0.claim_number', 'CLM-000001')
            ->assertJsonPath('data.0.claims.0.status', 'Under Review')
            ->assertJsonPath('data.0.claims.0.is_open', true)
            ->assertJsonPath('data.0.claims.0.history.0.event', 'Submitted')
            ->assertJsonPath('data.0.claims.0.history.1.status', 'Under Review')
            ->assertJsonPath('data.0.claims.0.history.1.customer_message', 'Your claim is being reviewed.')
            ->assertJsonCount(2, 'data.0.claims')
            ->assertJsonPath('data.1.dtc_card_no', 'DTC-0002')
            ->assertJsonPath('data.1.warranty_eligible', true)
            ->assertJsonCount(1, 'data.1.claims');

        $this->assertStringNotContainsString('Internal subject must not be public', $plateLookup->getContent());
        $this->assertStringNotContainsString('Internal claim description must not be public', $plateLookup->getContent());
        $this->assertStringNotContainsString('Internal staff note must not be public', $plateLookup->getContent());

        $claimLookup = $this->api('/api/claims/lookup', [
            'dtc_card_no' => 'DTC-0001',
            'vehicle_reg_id' => 'AB-123 CD',
        ])->assertOk()
            ->assertJsonPath('claims.0.claim_number', 'CLM-000002')
            ->assertJsonPath('claims.1.claim_number', 'CLM-000001')
            ->assertJsonPath('claims.1.status', 'Under Review')
            ->assertJsonPath('claims.1.history.0.event', 'Submitted')
            ->assertJsonPath('claims.1.history.1.to_status', 'Under Review')
            ->assertJsonPath('claims.1.history.1.customer_message', 'Your claim is being reviewed.')
            ->assertJsonCount(2, 'claims');
        $this->assertStringNotContainsString('Internal staff note must not be public', $claimLookup->getContent());

        $this->api('/api/claims/lookup', [
            'dtc_card_no' => 'DTC-0001',
            'vehicle_reg_id' => 'WRONG',
        ])->assertUnprocessable();

        $cardLookup = $this->api('/api/checkRecordByDtcCodeOrPlateNum', ['dtc_code' => 'DTC-0001'])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.dtc_card_no', 'DTC-0001')
            ->assertJsonCount(2, 'data.0.claims');
        $this->assertStringNotContainsString('Internal staff note must not be public', $cardLookup->getContent());

        $claim->update(['status' => 'approved']);
        $this->api('/api/claims/lookup', ['dtc_card_no' => 'DTC-0001', 'vehicle_reg_id' => 'AB123CD'])
            ->assertOk()
            ->assertJsonPath('claims.1.status', 'Approved');
    }

    public function test_customer_can_send_additional_information_for_a_verified_claim(): void
    {
        Mail::fake();
        $staff = User::factory()->create(['role' => 'staff']);
        $this->api('/api/register', $this->payload())->assertCreated();
        $this->api('/api/claims', [
            'dtc_card_no' => 'DTC-0001',
            'subject' => 'Tyre damage',
            'description' => 'The tyre is damaged.',
        ])->assertCreated();

        $claim = \App\Models\Claim::firstOrFail();
        $claim->update(['status' => 'need_more_info']);
        $claim->histories()->create([
            'event_type' => 'status_changed',
            'from_status' => 'submitted',
            'to_status' => 'need_more_info',
            'note' => 'Internal note must stay private.',
            'customer_message' => 'Please send a photo of the receipt.',
        ]);

        $this->api('/api/claims/respond', [
            'dtc_card_no' => 'DTC-0001',
            'vehicle_reg_id' => 'WRONG',
            'claim_number' => $claim->claim_number,
            'message' => 'Here are the receipt details.',
        ])->assertUnprocessable();

        $this->api('/api/claims/respond', [
            'dtc_card_no' => 'DTC-0001',
            'vehicle_reg_id' => 'AB-123 CD',
            'claim_number' => $claim->claim_number,
            'message' => 'Here are the receipt details.',
        ])->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Your additional information was sent to our team.');

        $this->assertDatabaseHas('claim_histories', [
            'claim_id' => $claim->id,
            'event_type' => 'customer_response',
            'customer_message' => 'Here are the receipt details.',
            'note' => null,
        ]);
        $this->assertDatabaseHas('claim_staff_notifications', [
            'claim_id' => $claim->id,
            'user_id' => $staff->id,
            'message' => 'Customer sent additional information',
        ]);
        $lookup = $this->api('/api/claims/lookup', [
            'dtc_card_no' => 'DTC-0001',
            'vehicle_reg_id' => 'AB123CD',
        ])->assertOk()
            ->assertJsonPath('claims.0.history.2.event', 'Additional information received')
            ->assertJsonPath('claims.0.history.2.customer_message', 'Here are the receipt details.');
        $this->assertStringNotContainsString('Internal note must stay private', $lookup->getContent());
    }

    public function test_plate_lookup_returns_all_matching_registrations_when_plate_equals_a_dtc_card_number(): void
    {
        $this->api('/api/register', $this->payload([
            'dtc_card_no' => 'X5Z5K5TH',
            'vehicle_reg_id' => 'OTHER123',
        ]))->assertCreated();
        $this->api('/api/register', $this->payload([
            'dtc_card_no' => 'DTC-0002',
            'vehicle_reg_id' => 'X5Z5K5TH',
            'date_of_purchase' => now()->subMonths(2)->toDateString(),
        ]))->assertCreated();
        $this->api('/api/register', $this->payload([
            'dtc_card_no' => 'DTC-0003',
            'vehicle_reg_id' => 'X5Z5K5TH',
            'date_of_purchase' => now()->subMonths(3)->toDateString(),
            'quantity' => 2,
        ]))->assertCreated();

        $this->api('/api/checkRecordByDtcCodeOrPlateNum', ['dtc_code' => 'X5Z5K5TH'])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.dtc_card_no', 'DTC-0002')
            ->assertJsonPath('data.1.dtc_card_no', 'DTC-0003');
    }

    public function test_warranty_lookup_shows_when_purchase_is_outside_warranty_period(): void
    {
        $this->api('/api/register', $this->payload([
            'date_of_purchase' => now()->subMonths(13)->toDateString(),
        ]))->assertCreated();

        $this->api('/api/checkRecordByDtcCodeOrPlateNum', ['dtc_code' => 'AB123CD'])
            ->assertOk()
            ->assertJsonPath('data.0.warranty_eligible', false)
            ->assertJsonPath('data.0.warranty_status', 'Expired')
            ->assertJsonPath('data.0.warranty_expiry_date', now()->subMonths(13)->addYear()->toDateString())
            ->assertJsonPath('data.0.warranty_reason', 'The 12-month warranty from the purchase date has expired.');
    }

    public function test_wordpress_claim_requires_a_registration_email(): void
    {
        Mail::fake();
        Dtc::create([
            'firstname' => 'Ada', 'lastname' => 'Lovelace', 'contact_no' => '0917123456',
            'vehicle_reg_id' => 'ABC123', 'tyre' => 'Eagle F1', 'quantity' => 4,
            'date_of_purchase' => now()->subDay()->toDateString(), 'store_name' => 'Main Store',
            'dtc_card_no' => 'DTC-0001',
        ]);

        $this->api('/api/claims', [
            'dtc_card_no' => 'dtc-0001',
            'subject' => 'Tyre damage',
            'description' => 'A tread issue occurred during normal use.',
        ])->assertUnprocessable()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'A valid customer email is required before a claim can be submitted. Update the registration email and try again.');
        Mail::assertNothingSent();

        $this->assertDatabaseCount('claims', 0);
    }

    public function test_wordpress_claim_is_saved_once_when_confirmation_email_fails(): void
    {
        \Illuminate\Support\Facades\Mail::shouldReceive('to')
            ->once()
            ->with('ada@example.com')
            ->andThrow(new \RuntimeException('Mail server unavailable.'));
        Dtc::create([
            'firstname' => 'Ada', 'lastname' => 'Lovelace', 'email' => 'ada@example.com', 'contact_no' => '0917123456',
            'vehicle_reg_id' => 'ABC123', 'tyre' => 'Eagle F1', 'quantity' => 4,
            'date_of_purchase' => now()->subDay()->toDateString(), 'store_name' => 'Main Store',
            'dtc_card_no' => 'DTC-0001', 'email_verified_at' => now(),
        ]);
        Dtc::query()->update(['email_verified_at' => now()]);

        $this->api('/api/claims', [
            'dtc_card_no' => 'dtc-0001',
            'subject' => 'Tyre damage',
            'description' => 'A tread issue occurred during normal use.',
        ])->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('claim_number', 'CLM-000001')
            ->assertJsonPath('email_notification', 'failed');

        $this->assertDatabaseCount('claims', 1);
        $this->assertDatabaseCount('claim_histories', 1);
        $this->assertDatabaseCount('dtc_audit_logs', 1);
        $this->assertDatabaseHas('claims', [
            'claim_number' => 'CLM-000001',
            'submission_notification_status' => 'failed',
        ]);
    }

    public function test_wordpress_claim_submission_emails_confirmation_without_claim_details(): void
    {
        Mail::fake();
        $this->api('/api/register', $this->payload())->assertCreated();
        Dtc::query()->update(['email_verified_at' => now()]);

        $this->api('/api/claims', [
            'dtc_card_no' => 'dtc-0001',
            'subject' => 'Tyre damage',
            'description' => 'Private description should not be emailed.',
        ])->assertCreated()
            ->assertJsonPath('claim_number', 'CLM-000001')
            ->assertJsonPath('email_notification', 'sent');
        $this->assertDatabaseHas('dtc_audit_logs', [
            'action' => 'claim.submitted',
            'entity_type' => 'claim',
            'entity_id' => 1,
            'summary' => 'Customer submitted claim CLM-000001',
        ]);

        Mail::assertSent(ClaimSubmitted::class, function (ClaimSubmitted $mail) {
            $rendered = $mail->render();

            return $mail->hasTo('ada@example.com')
                && str_contains($rendered, 'CLM-000001')
                && str_contains($rendered, 'alt="David Tyre Company"')
                && str_contains($rendered, 'Claim number')
                && str_contains($rendered, 'awaiting review')
                && str_contains($rendered, 'does not mean the claim has been approved')
                && ! str_contains($rendered, 'Private description should not be emailed.');
        });
    }

    public function test_wordpress_claim_submission_is_idempotent_and_rejects_key_reuse_with_different_payload(): void
    {
        Mail::fake();
        Dtc::create([
            'firstname' => 'Ada', 'lastname' => 'Lovelace', 'email' => 'ada@example.com', 'contact_no' => '0917123456',
            'vehicle_reg_id' => 'ABC123', 'tyre' => 'Eagle F1', 'quantity' => 4,
            'date_of_purchase' => now()->subDay()->toDateString(), 'store_name' => 'Main Store',
            'dtc_card_no' => 'DTC-0001', 'email_verified_at' => now(),
        ]);
        Dtc::query()->update(['email_verified_at' => now()]);
        $payload = [
            'idempotency_key' => '11111111-1111-4111-8111-111111111111',
            'dtc_card_no' => 'DTC-0001',
            'subject' => 'Tyre damage',
            'description' => 'A tread issue occurred during normal use.',
        ];

        $this->api('/api/claims', $payload)
            ->assertCreated()
            ->assertJsonPath('claim_number', 'CLM-000001')
            ->assertJsonMissingPath('idempotent_replay');

        $this->api('/api/claims', $payload)
            ->assertOk()
            ->assertJsonPath('claim_number', 'CLM-000001')
            ->assertJsonPath('idempotent_replay', true);

        $this->api('/api/claims', array_merge($payload, [
            'description' => 'A different issue.',
        ]))
            ->assertStatus(409)
            ->assertJsonPath('status', 'error');

        $this->assertDatabaseCount('claims', 1);
        $this->assertDatabaseCount('claim_histories', 1);
        $this->assertDatabaseCount('dtc_audit_logs', 1);
        Mail::assertSent(ClaimSubmitted::class, 1);
    }

    public function test_wordpress_claim_submission_rejects_unknown_card_number(): void
    {
        $this->api('/api/claims', [
            'dtc_card_no' => 'unknown-card',
            'subject' => 'Tyre damage',
            'description' => 'A tread issue occurred during normal use.',
        ])->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'We could not find a registration with that DTC Code. Check the code and try again.');

        $this->assertDatabaseCount('claims', 0);
    }

    public function test_wordpress_can_submit_multiple_claims_while_another_is_open(): void
    {
        Dtc::create([
            'firstname' => 'Ada', 'lastname' => 'Lovelace', 'email' => 'ada@example.com', 'contact_no' => '0917123456',
            'vehicle_reg_id' => 'ABC123', 'tyre' => 'Eagle F1', 'quantity' => 4,
            'date_of_purchase' => now()->subDay()->toDateString(), 'store_name' => 'Main Store',
            'dtc_card_no' => 'DTC-0001', 'email_verified_at' => now(),
        ]);
        Dtc::query()->update(['email_verified_at' => now()]);
        $claimPayload = [
            'dtc_card_no' => 'dtc-0001',
            'subject' => 'Tyre damage',
            'description' => 'A tread issue occurred during normal use.',
            'incident_date' => now()->subDay()->toDateString(),
            'affected_tyres' => 'Front left',
        ];

        $this->api('/api/claims', $claimPayload)->assertCreated();
        $claim = \App\Models\Claim::firstOrFail();
        $this->assertSame(now()->subDay()->toDateString(), $claim->incident_date->toDateString());
        $this->assertSame('Front left', $claim->affected_tyres);
        $this->api('/api/claims', $claimPayload)
            ->assertStatus(422)
            ->assertJsonPath('message', 'An open claim already exists. Confirm that this is a different issue before submitting another claim.');
        $this->assertDatabaseCount('claims', 1);

        $claimPayload['different_issue_confirmed'] = 1;
        $this->api('/api/claims', $claimPayload)
            ->assertCreated()
            ->assertJsonPath('claim_number', 'CLM-000002');

        $this->assertDatabaseCount('claims', 2);

        \App\Models\Claim::firstOrFail()->update(['status' => 'approved']);
        $this->api('/api/claims', $claimPayload)->assertCreated();
        $this->assertDatabaseCount('claims', 3);

        \App\Models\Claim::latest('id')->firstOrFail()->update(['status' => 'under_review']);
        $this->api('/api/claims', $claimPayload)->assertCreated();
        $this->assertDatabaseCount('claims', 4);

        \App\Models\Claim::latest('id')->firstOrFail()->update(['status' => 'resolved']);
        $this->api('/api/claims', $claimPayload)->assertCreated();
        $this->assertDatabaseCount('claims', 5);
    }

    public function test_wordpress_cannot_submit_an_issue_date_before_purchase_date(): void
    {
        $purchaseDate = now()->subDay()->toDateString();
        $this->api('/api/register', $this->payload(['date_of_purchase' => $purchaseDate]))->assertCreated();
        Dtc::query()->update(['email_verified_at' => now()]);

        $this->api('/api/claims', [
            'dtc_card_no' => 'dtc-0001',
            'subject' => 'Tyre damage',
            'description' => 'A tread issue occurred during normal use.',
            'incident_date' => now()->subDays(2)->toDateString(),
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['incident_date'])
            ->assertJsonPath('errors.incident_date.0', 'The issue date cannot be before the purchase date.');

        $this->assertDatabaseCount('claims', 0);
    }

    public function test_thank_you_page_and_unsubscribe_flow(): void
    {
        $dtc = Dtc::create([
            'firstname' => 'Ada', 'lastname' => 'L', 'email' => 'ada@example.com', 'contact_no' => '0917123456',
            'vehicle_reg_id' => 'ABC123', 'tyre' => 'Eagle F1', 'quantity' => 4, 'date_of_purchase' => now()->toDateString(),
            'store_name' => 'S', 'dtc_card_no' => 'x1', 'subscribe' => true,
        ]);
        $enc = Crypt::encryptString((string) $dtc->id);

        $this->get("/register/thankyou/{$enc}")
            ->assertOk()
            ->assertSee('Dear Ada')
            ->assertSee('Unsubscribe from marketing emails')
            ->assertSee(asset('images/dtc-logo.svg'), false)
            ->assertSee('David Tyre Company');
        $this->get('/register/thankyou/not-valid')->assertNotFound();
        $this->get("/unsubscribe/{$enc}")->assertOk()->assertSee('Confirm unsubscribe');
        $this->put("/unsubscribe/{$enc}")->assertRedirect('/unsubscribed/thankyou');
        $this->assertFalse($dtc->fresh()->subscribe);
    }
}
