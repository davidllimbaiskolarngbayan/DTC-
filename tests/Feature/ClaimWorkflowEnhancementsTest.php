<?php

namespace Tests\Feature;

use App\Mail\ClaimNeedsStaffReview;
use App\Mail\ClaimFollowUpReminder;
use App\Mail\ClaimStatusUpdated;
use App\Mail\ClaimSubmitted;
use App\Mail\DtcThankYou;
use App\Models\Claim;
use App\Models\ClaimAttachment;
use App\Models\Dtc;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClaimWorkflowEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    private function registrant(array $overrides = []): Dtc
    {
        $registrant = Dtc::create(array_merge([
            'firstname' => 'Ada',
            'lastname' => 'Lovelace',
            'email' => 'ada@example.com',
            'contact_no' => '0917123456',
            'vehicle_reg_id' => 'ABC123',
            'tyre' => 'Eagle F1',
            'quantity' => 4,
            'date_of_purchase' => now()->subDay()->toDateString(),
            'store_name' => 'Main Store',
            'dtc_card_no' => 'DTC-0001',
        ], $overrides));

        return $registrant;
    }

    private function claimPayload(array $overrides = []): array
    {
        return array_merge([
            'idempotency_key' => (string) Str::uuid(),
            'dtc_card_no' => 'DTC-0001',
            'subject' => 'Tyre damage',
            'description' => 'A tread issue occurred during normal use.',
        ], $overrides);
    }

    private function selectStaffRecipient(string $email): User
    {
        $user = User::factory()->create(['email' => $email]);
        DB::table('claim_notification_recipients')->insert([
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    public function test_registration_sends_confirmation_without_requiring_email_verification(): void
    {
        Mail::fake();

        $this->withHeaders(['X-DTC-Key' => 'test-key', 'Accept' => 'application/json'])
            ->postJson('/api/register', [
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
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'success');

        $registrant = Dtc::firstOrFail();
        $this->assertNull($registrant->email_verified_at);
        Mail::assertSent(DtcThankYou::class, fn (DtcThankYou $mail) => $mail->hasTo('ada@example.com'));
    }

    public function test_claim_confirmation_is_sent_without_email_verification(): void
    {
        Mail::fake();
        $registrant = $this->registrant();
        $this->assertNull($registrant->email_verified_at);

        $this->withHeaders(['X-DTC-Key' => 'test-key', 'Accept' => 'application/json'])
            ->postJson('/api/claims', $this->claimPayload())
            ->assertCreated()
            ->assertJsonPath('email_notification', 'sent');

        Mail::assertSent(ClaimSubmitted::class, fn (ClaimSubmitted $mail) => $mail->hasTo('ada@example.com'));
    }

    public function test_staff_claim_alert_uses_branded_email_layout(): void
    {
        $mail = new ClaimNeedsStaffReview(
            'CLM-000006',
            'Tyre damage',
            'https://portal.example.com/claims/6'
        );

        $html = $mail->render();

        $this->assertStringContainsString('alt="David Tyre Company"', $html);
        $this->assertStringContainsString('background-color:#0b2a63', $html);
        $this->assertStringContainsString('border-bottom:4px solid #fbbd00', $html);
        $this->assertStringContainsString('CLM-000006', $html);
        $this->assertStringContainsString('Tyre damage', $html);
        $this->assertStringContainsString('Open claim in the staff portal', $html);
        $this->assertStringContainsString('https://portal.example.com/claims/6', $html);
    }

    public function test_claim_attachments_are_private_and_only_downloadable_by_staff(): void
    {
        Mail::fake();
        Storage::fake('local');
        $registrant = $this->registrant();
        $payload = $this->claimPayload(['idempotency_key' => '11111111-1111-4111-8111-111111111111']);
        $attachmentFile = UploadedFile::fake()->create('proof.pdf', 128, 'application/pdf');

        $this->call(
            'POST',
            '/api/claims',
            $payload,
            [],
            ['attachments' => [$attachmentFile]],
            ['HTTP_X_DTC_KEY' => 'test-key', 'HTTP_ACCEPT' => 'application/json'],
        )
            ->assertCreated();
        $this->call(
            'POST',
            '/api/claims',
            $payload,
            [],
            ['attachments' => [$attachmentFile]],
            ['HTTP_X_DTC_KEY' => 'test-key', 'HTTP_ACCEPT' => 'application/json'],
        )->assertOk()->assertJsonPath('idempotent_replay', true);

        $claim = Claim::firstOrFail();
        $attachment = $claim->attachments()->firstOrFail();
        $this->assertDatabaseCount('claim_attachments', 1);
        Storage::disk('local')->assertExists($attachment->path);

        $url = route('claims.attachments.download', [$claim, $attachment]);
        $this->get($url)->assertRedirect('/login');
        $staff = User::factory()->create();
        $this->actingAs($staff)->get($url)->assertDownload('proof.pdf');
        $this->get(route('claims.attachments.preview', [$claim, $attachment]))->assertNotFound();
        $this->get(route('claims.show', $claim))
            ->assertOk()
            ->assertSee('Customer attachments')
            ->assertSee('proof.pdf');
        $this->assertDatabaseHas('dtc_audit_logs', [
            'entity_type' => 'claim',
            'entity_id' => $claim->id,
            'action' => 'claim.attachment.downloaded',
        ]);

        $this->assertSame($registrant->id, $claim->dtc_id);
    }

    public function test_staff_can_preview_image_attachments_inline(): void
    {
        Mail::fake();
        Storage::fake('local');
        $this->registrant();
        $image = UploadedFile::fake()->image('tyre-damage.png');

        $this->call(
            'POST',
            '/api/claims',
            $this->claimPayload(),
            [],
            ['attachments' => [$image]],
            ['HTTP_X_DTC_KEY' => 'test-key', 'HTTP_ACCEPT' => 'application/json'],
        )->assertCreated();

        $claim = Claim::firstOrFail();
        $attachment = $claim->attachments()->firstOrFail();
        $staff = User::factory()->create();

        $this->get(route('claims.attachments.preview', [$claim, $attachment]))
            ->assertRedirect('/login');

        $this->actingAs($staff)
            ->get(route('claims.attachments.preview', [$claim, $attachment]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
        $this->get(route('claims.show', $claim))
            ->assertOk()
            ->assertSee('tyre-damage.png')
            ->assertSee('View image');

        $this->assertDatabaseHas('dtc_audit_logs', [
            'entity_type' => 'claim',
            'entity_id' => $claim->id,
            'action' => 'claim.attachment.previewed',
        ]);
    }

    public function test_claim_submission_rejects_more_than_one_attachment(): void
    {
        Mail::fake();
        $this->registrant();

        $this->call(
            'POST',
            '/api/claims',
            $this->claimPayload(),
            [],
            ['attachments' => [
                UploadedFile::fake()->create('proof-one.pdf', 128, 'application/pdf'),
                UploadedFile::fake()->create('proof-two.pdf', 128, 'application/pdf'),
            ]],
            ['HTTP_X_DTC_KEY' => 'test-key', 'HTTP_ACCEPT' => 'application/json'],
        )->assertUnprocessable()->assertJsonValidationErrors('attachments');

        $this->assertDatabaseCount('claims', 0);
        $this->assertDatabaseCount('claim_attachments', 0);
    }

    public function test_automatic_retry_resends_failed_claim_emails_and_records_the_attempt(): void
    {
        Mail::fake();
        $registrant = $this->registrant();
        $claim = Claim::create([
            'dtc_id' => $registrant->id,
            'claim_number' => 'CLM-000001',
            'subject' => 'Tyre damage',
            'description' => 'A tread issue occurred during normal use.',
            'eligible' => true,
            'eligibility_reason' => 'Within warranty.',
            'status' => 'submitted',
        ]);
        $delivery = $claim->emailDeliveries()->create([
            'type' => 'claim_submitted',
            'recipient_hash' => hash('sha256', $registrant->email),
            'status' => 'failed',
            'error' => 'First delivery failed.',
        ]);

        $this->artisan('claims:retry-email-notifications')->assertExitCode(0);

        $this->assertSame('sent', $delivery->fresh()->status);
        $this->assertSame(1, $delivery->fresh()->retry_attempts);
        $this->assertNotNull($delivery->fresh()->last_attempt_at);
        $this->assertSame('sent', $claim->fresh()->submission_notification_status);
        $this->assertDatabaseHas('dtc_audit_logs', [
            'entity_id' => $claim->id,
            'action' => 'claim.notification.auto_retry_sent',
        ]);
        Mail::assertSent(ClaimSubmitted::class, fn (ClaimSubmitted $mail) => $mail->hasTo('ada@example.com'));
    }

    public function test_automatic_email_retry_is_capped_at_three_attempts(): void
    {
        Mail::fake();
        $registrant = $this->registrant();
        $claim = Claim::create([
            'dtc_id' => $registrant->id,
            'claim_number' => 'CLM-000001',
            'subject' => 'Tyre damage',
            'description' => 'A tread issue occurred during normal use.',
            'eligible' => true,
            'eligibility_reason' => 'Within warranty.',
            'status' => 'submitted',
        ]);
        $delivery = $claim->emailDeliveries()->create([
            'type' => 'claim_submitted',
            'recipient_hash' => hash('sha256', $registrant->email),
            'status' => 'failed',
            'retry_attempts' => 3,
            'error' => 'All automatic retries used.',
        ]);

        $this->artisan('claims:retry-email-notifications')->assertExitCode(0);

        $this->assertSame('failed', $delivery->fresh()->status);
        $this->assertSame(3, $delivery->fresh()->retry_attempts);
        Mail::assertNothingSent();
    }

    public function test_open_claim_follow_up_reminders_are_sent_every_three_days_without_activity(): void
    {
        Mail::fake();
        $this->selectStaffRecipient('claims@example.com');
        $registrant = $this->registrant();
        $claim = Claim::create([
            'dtc_id' => $registrant->id,
            'claim_number' => 'CLM-000001',
            'subject' => 'Tyre damage',
            'description' => 'A tread issue occurred during normal use.',
            'eligible' => true,
            'eligibility_reason' => 'Within warranty.',
            'status' => 'submitted',
        ]);
        DB::table('claims')->where('id', $claim->id)->update([
            'updated_at' => now()->subDays(4),
        ]);

        $this->artisan('claims:send-follow-up-reminders')->assertExitCode(0);
        $this->artisan('claims:send-follow-up-reminders')->assertExitCode(0);
        Mail::assertSent(ClaimFollowUpReminder::class, 1);
        $this->assertNotNull($claim->fresh()->follow_up_reminded_at);
        $this->assertDatabaseHas('dtc_audit_logs', [
            'entity_id' => $claim->id,
            'action' => 'claim.follow_up_reminder_sent',
        ]);

        $this->travel(3)->days();
        $this->artisan('claims:send-follow-up-reminders')->assertExitCode(0);
        Mail::assertSent(ClaimFollowUpReminder::class, 2);
    }

    public function test_attachment_cleanup_removes_files_older_than_one_year_only(): void
    {
        Storage::fake('local');
        $registrant = $this->registrant();
        $claim = Claim::create([
            'dtc_id' => $registrant->id,
            'claim_number' => 'CLM-000001',
            'subject' => 'Tyre damage',
            'description' => 'A tread issue occurred during normal use.',
            'eligible' => true,
            'eligibility_reason' => 'Within warranty.',
            'status' => 'submitted',
        ]);
        Storage::disk('local')->put('private/claims/1/old.pdf', 'old');
        Storage::disk('local')->put('private/claims/1/recent.pdf', 'recent');
        $old = $claim->attachments()->create([
            'disk' => 'local',
            'path' => 'private/claims/1/old.pdf',
            'original_name' => 'old.pdf',
            'mime_type' => 'application/pdf',
            'size' => 3,
        ]);
        $recent = $claim->attachments()->create([
            'disk' => 'local',
            'path' => 'private/claims/1/recent.pdf',
            'original_name' => 'recent.pdf',
            'mime_type' => 'application/pdf',
            'size' => 6,
        ]);
        DB::table('claim_attachments')->where('id', $old->id)->update([
            'created_at' => now()->subYear()->subDay(),
        ]);

        $this->artisan('claims:prune-attachments')->assertExitCode(0);

        $this->assertDatabaseMissing('claim_attachments', ['id' => $old->id]);
        $this->assertDatabaseHas('claim_attachments', ['id' => $recent->id]);
        Storage::disk('local')->assertMissing('private/claims/1/old.pdf');
        Storage::disk('local')->assertExists('private/claims/1/recent.pdf');
    }

    public function test_failed_customer_confirmation_can_be_retried(): void
    {
        Mail::fake();
        $registrant = $this->registrant();
        $claim = Claim::create([
            'dtc_id' => $registrant->id,
            'claim_number' => 'CLM-000001',
            'subject' => 'Tyre damage',
            'description' => 'A tread issue occurred during normal use.',
            'eligible' => true,
            'eligibility_reason' => 'Within warranty.',
            'status' => 'submitted',
        ]);
        $claim->forceFill([
            'submission_notification_status' => 'failed',
            'submission_notification_attempts' => 1,
        ])->save();
        $failedDelivery = $claim->emailDeliveries()->create([
            'type' => 'claim_submitted',
            'recipient_hash' => hash('sha256', $registrant->email),
            'status' => 'failed',
            'error' => 'Previous delivery failed.',
        ]);

        $this->actingAs(User::factory()->create())
            ->post(route('claims.notification.retry', $claim))
            ->assertRedirect(route('claims.show', $claim))
            ->assertSessionHas('status', 'The customer confirmation email was sent.');

        $this->assertSame('sent', $claim->fresh()->submission_notification_status);
        $this->assertSame(2, $claim->fresh()->submission_notification_attempts);
        $this->assertSame('superseded', $failedDelivery->fresh()->status);
        $this->assertDatabaseCount('claim_email_deliveries', 2);
        Mail::assertSent(ClaimSubmitted::class, fn (ClaimSubmitted $mail) => $mail->hasTo('ada@example.com'));
    }

    public function test_failed_customer_status_notification_can_be_retried(): void
    {
        Mail::fake();
        $registrant = $this->registrant();
        $claim = Claim::create([
            'dtc_id' => $registrant->id,
            'claim_number' => 'CLM-000001',
            'subject' => 'Tyre damage',
            'description' => 'A tread issue occurred during normal use.',
            'eligible' => true,
            'eligibility_reason' => 'Within warranty.',
            'status' => 'under_review',
        ]);
        $history = $claim->histories()->create([
            'event_type' => 'status_changed',
            'from_status' => 'submitted',
            'to_status' => 'under_review',
            'customer_message' => 'Your claim is being reviewed.',
        ]);
        $delivery = $claim->emailDeliveries()->create([
            'claim_history_id' => $history->id,
            'type' => 'claim_status_update',
            'recipient_hash' => hash('sha256', $registrant->email),
            'status' => 'failed',
            'error' => 'Previous delivery failed.',
        ]);

        $this->actingAs(User::factory()->create())
            ->post(route('claims.email-deliveries.retry', [$claim, $delivery]))
            ->assertRedirect(route('claims.show', $claim))
            ->assertSessionHas('status', 'The customer status notification was resent.');

        $this->assertSame('sent', $delivery->fresh()->status);
        Mail::assertSent(ClaimStatusUpdated::class, fn (ClaimStatusUpdated $mail) => $mail->hasTo('ada@example.com')
            && $mail->customerMessage === 'Your claim is being reviewed.');
    }

    public function test_failed_staff_notification_can_be_retried_after_configuration_is_fixed(): void
    {
        Mail::fake();
        $this->registrant();

        $this->withHeaders(['X-DTC-Key' => 'test-key', 'Accept' => 'application/json'])
            ->postJson('/api/claims', $this->claimPayload())
            ->assertCreated();

        $claim = Claim::firstOrFail();
        $this->assertSame('failed', $claim->staff_notification_status);
        $this->selectStaffRecipient('claims@example.com');

        $this->actingAs(User::factory()->create())
            ->post(route('claims.staff-notification.retry', $claim))
            ->assertRedirect(route('claims.show', $claim))
            ->assertSessionHas('status', 'The staff notification was sent.');

        $claim->refresh();
        $this->assertSame('sent', $claim->staff_notification_status);
        $this->assertSame(2, $claim->staff_notification_attempts);
        $this->assertDatabaseHas('claim_email_deliveries', [
            'claim_id' => $claim->id,
            'type' => 'staff_new_claim',
            'status' => 'superseded',
        ]);
        $this->assertDatabaseHas('claim_email_deliveries', [
            'claim_id' => $claim->id,
            'type' => 'staff_new_claim',
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('dtc_audit_logs', [
            'entity_type' => 'claim',
            'entity_id' => $claim->id,
            'action' => 'claim.staff_retry',
        ]);
        $this->get(route('claims.show', $claim))
            ->assertOk()
            ->assertSee('Staff notification:')
            ->assertDontSee('Superseded')
            ->assertDontSee('A later manual notification retry succeeded.')
            ->assertDontSee('Email delivery history');
        Mail::assertSent(ClaimNeedsStaffReview::class, fn (ClaimNeedsStaffReview $mail) => $mail->hasTo('claims@example.com'));
    }

    public function test_admin_accounts_are_never_staff_notification_recipients(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['email' => 'admin@example.com', 'role' => 'admin']);
        DB::table('claim_notification_recipients')->insert([
            'user_id' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $registrant = $this->registrant();
        $claim = Claim::create([
            'dtc_id' => $registrant->id,
            'claim_number' => 'CLM-000999',
            'subject' => 'Tyre damage',
            'description' => 'A tread issue occurred during normal use.',
            'eligible' => true,
            'eligibility_reason' => 'Within warranty.',
            'status' => 'submitted',
        ]);

        $this->assertFalse(app(\App\Services\ClaimStaffNotifier::class)->send($claim));
        $this->assertSame('failed', $claim->fresh()->staff_notification_status);
        Mail::assertNothingSent();
    }
}
