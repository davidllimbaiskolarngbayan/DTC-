<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use App\Models\User;
use App\Models\Dtc;
use App\Models\Claim;
use App\Models\DtcAuditLog;
use App\Models\DtcCode;
use App\Mail\ClaimStatusUpdated;
use App\Mail\ClaimSubmitted;
use App\Mail\ClaimAssignedToStaff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DtcAdminTest extends TestCase
{
    use RefreshDatabase;

    private function dtc(array $overrides = []): Dtc
    {
        $dtc = Dtc::create(array_merge([
            'firstname' => 'Ada', 'lastname' => 'Lovelace', 'email' => 'ada@example.com', 'contact_no' => '0917123456',
            'vehicle_reg_id' => 'ABC123', 'brand' => 'Toyota', 'tyre' => 'Eagle F1', 'quantity' => 4,
            'date_of_purchase' => now()->subDay()->toDateString(), 'store_name' => 'Main Store',
            'dtc_card_no' => 'dtc-1', 'subscribe' => true,
        ], $overrides));
        $dtc->forceFill(['email_verified_at' => now()])->save();

        return $dtc;
    }

    public function test_pages_require_login(): void
    {
        $dtc = $this->dtc();

        foreach (['/', '/dtc', "/dtc/{$dtc->id}", "/dtc/{$dtc->id}/edit", '/claims/export'] as $uri) {
            $this->get($uri)->assertRedirect('/login');
        }
        $this->post('/dtc/export/user')->assertRedirect('/login');
        $this->get('/settings/claim-notifications')->assertRedirect('/login');
    }

    public function test_staff_can_process_claims_but_cannot_access_admin_features(): void
    {
        $dtc = $this->dtc();
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('DTC Codes')
            ->assertSee('Recent activity')
            ->assertSee('href="'.route('activity.index').'"', false);
        $this->get('/claims')->assertOk();
        $this->get("/dtc/{$dtc->id}")->assertOk()->assertDontSee('Edit</a>', false);
        $this->get('/users')->assertForbidden();
        $this->get('/users/create')->assertForbidden();
        $this->get(route('users.show', $staff))->assertForbidden();
        $this->get(route('users.edit', $staff))->assertForbidden();
        $this->put(route('users.update', $staff), ['name' => 'Changed', 'email' => 'changed@example.com'])->assertForbidden();
        $this->delete(route('users.destroy', $staff))->assertForbidden();
        $this->get('/activity')->assertOk()->assertSee('Activity log');
        $this->get('/settings/claim-notifications')->assertForbidden();
        $this->get('/dtc_codes')->assertForbidden();
        $this->get('/claims/export')->assertForbidden();
        $this->get("/dtc/{$dtc->id}/edit")->assertForbidden();
        $this->delete("/dtc/{$dtc->id}")->assertForbidden();
        $response = $this->post('/dtc/export/user', [
            'date_from' => today()->toDateString(),
            'date_to' => today()->toDateString(),
        ])->assertOk();
        $this->assertStringContainsString('ada@example.com', $response->streamedContent());
    }

    public function test_admin_can_create_staff_and_admin_accounts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)
            ->get('/users/create')
            ->assertOk()
            ->assertSee('Staff — process claims and view registrants')
            ->assertSee('Admin — full portal and account access');

        $this->post('/users', [
            'name' => 'Claims Staff',
            'email' => 'claims.staff@example.com',
            'role' => 'staff',
            'password' => 'a-strong-test-password',
            'password_confirmation' => 'a-strong-test-password',
        ])->assertRedirect('/users')
            ->assertSessionHas('status', 'Account created successfully.');

        $created = User::where('email', 'claims.staff@example.com')->firstOrFail();
        $this->assertSame('staff', $created->role);
        $this->assertTrue(password_verify('a-strong-test-password', $created->password));
        $this->assertDatabaseHas('dtc_audit_logs', [
            'user_id' => $admin->id,
            'entity_type' => 'user',
            'entity_id' => $created->id,
            'action' => 'user.account_created',
            'dtc_id' => null,
        ]);

        $this->post('/users', [
            'name' => 'Another Admin',
            'email' => 'new.admin@example.com',
            'role' => 'admin',
            'password' => 'another-strong-test-password',
            'password_confirmation' => 'another-strong-test-password',
        ])->assertRedirect('/users');
        $this->assertDatabaseHas('users', ['email' => 'new.admin@example.com', 'role' => 'admin']);
    }

    public function test_admin_can_view_edit_and_soft_delete_staff_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create([
            'role' => 'staff',
            'name' => 'Profile Staff',
            'email' => 'profile.staff@example.com',
        ]);
        $dtc = $this->dtc();
        $claim = Claim::create([
            'dtc_id' => $dtc->id,
            'claim_number' => 'CLM-STAFF-001',
            'subject' => 'Staff assigned claim',
            'description' => 'Review this claim.',
            'eligible' => true,
            'eligibility_reason' => 'Within warranty.',
            'status' => 'submitted',
        ]);
        $claim->forceFill(['assigned_to' => $staff->id, 'assigned_at' => now()])->save();
        DB::table('claim_notification_recipients')->insert([
            'user_id' => $staff->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('users.show', $staff))
            ->assertOk()
            ->assertSee('Profile Staff')
            ->assertSee('profile.staff@example.com')
            ->assertSee('Staff')
            ->assertSee(route('users.edit', $staff), false);

        $this->get(route('users.edit', $staff))
            ->assertOk()
            ->assertSee('Edit account')
            ->assertSee('value="Profile Staff"', false);

        $this->put(route('users.update', $staff), [
            'name' => 'Updated Staff',
            'email' => 'updated.staff@example.com',
            'password' => 'updated-strong-password',
            'password_confirmation' => 'updated-strong-password',
        ])->assertRedirect(route('users.show', $staff))
            ->assertSessionHas('status', 'Account updated successfully.');

        $this->assertSame('Updated Staff', $staff->fresh()->name);
        $this->assertSame('updated.staff@example.com', $staff->fresh()->email);
        $this->assertTrue(password_verify('updated-strong-password', $staff->fresh()->password));
        $this->assertDatabaseHas('dtc_audit_logs', [
            'user_id' => $admin->id,
            'entity_type' => 'user',
            'entity_id' => $staff->id,
            'action' => 'user.account_updated',
        ]);

        $this->delete(route('users.destroy', $staff))
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('status', 'Staff account deleted successfully.');

        $this->assertSoftDeleted('users', ['id' => $staff->id]);
        $this->assertDatabaseHas('claims', [
            'id' => $claim->id,
            'assigned_to' => null,
            'assigned_at' => null,
        ]);
        $this->assertDatabaseMissing('claim_notification_recipients', ['user_id' => $staff->id]);
        $this->assertDatabaseHas('dtc_audit_logs', [
            'user_id' => $admin->id,
            'entity_type' => 'user',
            'entity_id' => $staff->id,
            'action' => 'user.account_deleted',
        ]);
        $this->get(route('users.index'))->assertOk()->assertDontSee('updated.staff@example.com');
        $this->get(route('users.show', $staff))->assertNotFound();
    }

    public function test_admin_account_cannot_be_soft_deleted_through_staff_route(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $anotherAdmin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->delete(route('users.destroy', $anotherAdmin))->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $anotherAdmin->id,
            'deleted_at' => null,
        ]);
    }

    public function test_staff_can_view_and_edit_only_their_own_profile(): void
    {
        $staff = User::factory()->create([
            'role' => 'staff',
            'name' => 'Profile Staff',
            'email' => 'profile.staff@example.com',
        ]);
        $anotherStaff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('My profile')
            ->assertSee('Profile Staff')
            ->assertSee('profile.staff@example.com')
            ->assertSee(route('profile.edit'), false);

        $this->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Edit my profile')
            ->assertSee('action="'.route('profile.update').'"', false);

        $this->put(route('profile.update'), [
            'name' => 'Staff Updated Own Name',
            'email' => 'staff.own.updated@example.com',
        ])->assertRedirect(route('profile.show'))
            ->assertSessionHas('status', 'Account updated successfully.');

        $this->assertSame('Staff Updated Own Name', $staff->fresh()->name);
        $this->assertSame('staff.own.updated@example.com', $staff->fresh()->email);
        $this->assertSame($anotherStaff->name, $anotherStaff->fresh()->name);
        $this->assertDatabaseHas('dtc_audit_logs', [
            'user_id' => $staff->id,
            'entity_type' => 'user',
            'entity_id' => $staff->id,
            'action' => 'user.account_updated',
        ]);
    }

    public function test_staff_cannot_create_accounts_or_change_notification_recipients(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        $this->post('/users', [
            'name' => 'Unauthorized Admin',
            'email' => 'unauthorized@example.com',
            'role' => 'admin',
            'password' => 'a-strong-test-password',
            'password_confirmation' => 'a-strong-test-password',
        ])->assertForbidden();
        $this->put('/settings/claim-notifications', ['recipients' => [$staff->id]])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'unauthorized@example.com']);
    }

    public function test_staff_can_select_multiple_claim_notification_recipients(): void
    {
        $firstRecipient = User::factory()->create(['name' => 'First Staff', 'email' => 'first@example.com']);
        $secondRecipient = User::factory()->create(['name' => 'Second Staff', 'email' => 'second@example.com']);
        $notSelected = User::factory()->create(['name' => 'Other Staff', 'email' => 'other@example.com']);
        $actor = User::factory()->create(['role' => 'admin']);
        $adminRecipient = User::factory()->create(['name' => 'Admin Account', 'email' => 'admin@example.com', 'role' => 'admin']);

        $this->actingAs($actor)
            ->get('/settings/claim-notifications')
            ->assertOk()
            ->assertSee('Staff alert recipients')
            ->assertSee('first@example.com')
            ->assertSee('second@example.com')
            ->assertSee('other@example.com')
            ->assertDontSee('admin@example.com');

        $this->put('/settings/claim-notifications', [
            'recipients' => [$firstRecipient->id, $secondRecipient->id, $adminRecipient->id],
        ])->assertSessionHasErrors('recipients.2');

        $this->put('/settings/claim-notifications', [
            'recipients' => [$firstRecipient->id, $secondRecipient->id],
        ])->assertRedirect('/settings/claim-notifications')
            ->assertSessionHas('status', 'Staff claim notification recipients updated.');

        $this->assertDatabaseCount('claim_notification_recipients', 2);
        $this->assertDatabaseHas('claim_notification_recipients', ['user_id' => $firstRecipient->id]);
        $this->assertDatabaseHas('claim_notification_recipients', ['user_id' => $secondRecipient->id]);
        $this->assertDatabaseMissing('claim_notification_recipients', ['user_id' => $notSelected->id]);
        $this->assertDatabaseMissing('claim_notification_recipients', ['user_id' => $adminRecipient->id]);
        $this->assertDatabaseHas('dtc_audit_logs', [
            'user_id' => $actor->id,
            'action' => 'claim.alert_recipients_updated',
        ]);
    }

    public function test_dashboard_list_show_and_edit_render(): void
    {
        $dtc = $this->dtc();
        Claim::create([
            'dtc_id' => $dtc->id,
            'claim_number' => 'CLM-000001',
            'subject' => 'Tyre issue',
            'description' => 'Review the tyre issue.',
            'eligible' => true,
            'eligibility_reason' => 'Within warranty.',
            'status' => 'under_review',
        ]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->get('/')
            ->assertOk()
            ->assertSee('Purchases per month')
            ->assertSee('Eagle F1')
            ->assertSee('Claim status breakdown')
            ->assertSee('Under Review')
            ->assertDontSee('Top vehicle brands')
            ->assertDontSee('>Dashboard</a>', false);
        $this->get('/dtc?search=ABC-123')
            ->assertOk()
            ->assertSee('First name')
            ->assertSee('Last name')
            ->assertSee('Lovelace')
            ->assertSee('Ada')
            ->assertDontSee('Subscribed')
            ->assertSee('Export DTC Users')
            ->assertSee('>View</a>', false)
            ->assertSee('Delete');
        $this->get('/dtc?search=nobody')->assertOk()->assertSee('No registrants found');
        $this->get("/dtc/{$dtc->id}")->assertOk()->assertSee('DTC-1');
        $this->get("/dtc/{$dtc->id}/edit")
            ->assertOk()
            ->assertSee('Edit user')
            ->assertDontSee('Subscribe to emails')
            ->assertDontSee('name="subscribe"', false);
    }

    public function test_staff_gets_persistent_individual_claim_alerts(): void
    {
        $dtc = $this->dtc();
        $firstStaff = User::factory()->create(['role' => 'staff']);
        $secondStaff = User::factory()->create(['role' => 'staff']);
        $submittedClaim = Claim::create([
            'dtc_id' => $dtc->id,
            'claim_number' => 'CLM-000001',
            'subject' => 'New tyre issue',
            'description' => 'Review the tyre issue.',
            'eligible' => true,
            'eligibility_reason' => 'Within warranty.',
            'status' => 'submitted',
        ]);
        Claim::create([
            'dtc_id' => $dtc->id,
            'claim_number' => 'CLM-000002',
            'subject' => 'Already being reviewed',
            'description' => 'This claim is already in progress.',
            'eligible' => true,
            'eligibility_reason' => 'Within warranty.',
            'status' => 'under_review',
        ]);
        DB::table('claim_staff_notifications')->insert([
            [
                'claim_id' => $submittedClaim->id,
                'user_id' => $firstStaff->id,
                'event_key' => 'claim_submitted',
                'message' => 'New claim submitted',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'claim_id' => $submittedClaim->id,
                'user_id' => $secondStaff->id,
                'event_key' => 'claim_submitted',
                'message' => 'New claim submitted',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        $submittedClaim->update(['status' => 'under_review']);

        $this->actingAs($firstStaff)
            ->get('/')
            ->assertOk()
            ->assertSee('Claim alerts')
            ->assertSee('Unread claim alerts')
            ->assertSee('CLM-000001')
            ->assertSee('New tyre issue')
            ->assertDontSee('CLM-000002')
            ->assertSee('Open and mark read');
        $this->post(route('claims.alerts.open', $submittedClaim))->assertRedirect(route('claims.show', $submittedClaim));
        $this->assertDatabaseHas('claim_staff_notifications', [
            'claim_id' => $submittedClaim->id,
            'user_id' => $firstStaff->id,
        ]);
        $this->assertNotNull(DB::table('claim_staff_notifications')
            ->where('claim_id', $submittedClaim->id)
            ->where('user_id', $firstStaff->id)
            ->value('read_at'));
        $this->actingAs($secondStaff)->get('/')->assertSee('CLM-000001');

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/')
            ->assertOk()
            ->assertDontSee('Claim alerts')
            ->assertDontSee('CLM-000001');
    }

    public function test_admin_can_assign_claim_and_staff_can_claim_unassigned_work(): void
    {
        Mail::fake();
        $dtc = $this->dtc();
        $claim = Claim::create([
            'dtc_id' => $dtc->id,
            'claim_number' => 'CLM-000201',
            'subject' => 'Tyre issue',
            'description' => 'Review the tyre issue.',
            'eligible' => true,
            'eligibility_reason' => 'Within warranty.',
            'status' => 'submitted',
        ]);
        $staff = User::factory()->create(['role' => 'staff', 'name' => 'Claim Staff']);
        $otherStaff = User::factory()->create(['role' => 'staff']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('claims.assignment', $claim), ['assigned_to' => $staff->id])
            ->assertRedirect(route('claims.show', $claim));
        $this->assertDatabaseHas('claims', ['id' => $claim->id, 'assigned_to' => $staff->id]);
        $this->assertDatabaseHas('dtc_audit_logs', ['action' => 'claim.assigned', 'entity_id' => $claim->id]);
        Mail::assertSent(ClaimAssignedToStaff::class, fn (ClaimAssignedToStaff $mail) => $mail->hasTo($staff->email)
            && $mail->claimNumber === $claim->claim_number
            && $mail->staffName === 'Claim Staff');

        $this->actingAs($otherStaff)
            ->post(route('claims.assignment', $claim))
            ->assertSessionHasErrors('assignment');

        $unassigned = Claim::create([
            'dtc_id' => $dtc->id,
            'claim_number' => 'CLM-000202',
            'subject' => 'Second tyre issue',
            'description' => 'Review another issue.',
            'eligible' => true,
            'eligibility_reason' => 'Within warranty.',
            'status' => 'submitted',
        ]);
        $this->actingAs($otherStaff)
            ->post(route('claims.assignment', $unassigned))
            ->assertRedirect(route('claims.show', $unassigned));
        $this->assertDatabaseHas('claims', ['id' => $unassigned->id, 'assigned_to' => $otherStaff->id]);
        Mail::assertSent(ClaimAssignedToStaff::class, fn (ClaimAssignedToStaff $mail) => $mail->hasTo($otherStaff->email)
            && $mail->claimNumber === $unassigned->claim_number);
    }

    public function test_staff_can_only_update_claims_assigned_to_them(): void
    {
        Mail::fake();
        $dtc = $this->dtc();
        $staff = User::factory()->create(['role' => 'staff']);
        $otherStaff = User::factory()->create(['role' => 'staff']);
        $unassigned = Claim::create([
            'dtc_id' => $dtc->id,
            'claim_number' => 'CLM-000203',
            'subject' => 'Unassigned tyre issue',
            'description' => 'Review the unassigned issue.',
            'eligible' => true,
            'eligibility_reason' => 'Within warranty.',
            'status' => 'submitted',
        ]);
        $assignedToOther = Claim::create([
            'dtc_id' => $dtc->id,
            'claim_number' => 'CLM-000204',
            'subject' => 'Assigned tyre issue',
            'description' => 'Review the assigned issue.',
            'eligible' => true,
            'eligibility_reason' => 'Within warranty.',
            'status' => 'submitted',
        ]);
        $assignedToOther->forceFill(['assigned_to' => $otherStaff->id])->save();

        $this->actingAs($staff)
            ->get(route('claims.show', $unassigned))
            ->assertOk()
            ->assertSee('Assign this claim to me')
            ->assertDontSee('Update status')
            ->assertDontSee('Add internal note');
        $this->put(route('claims.status', $unassigned), [
            'status' => 'under_review',
            'note' => 'Attempted unauthorized status change.',
        ])->assertForbidden();
        $this->post(route('claims.notes', $unassigned), [
            'note' => 'Attempted unauthorized note.',
        ])->assertForbidden();

        $this->get(route('claims.show', $assignedToOther))
            ->assertOk()
            ->assertSee('only the assigned staff member can update it')
            ->assertDontSee('Update status')
            ->assertDontSee('Add internal note');
        $this->put(route('claims.status', $assignedToOther), [
            'status' => 'under_review',
            'note' => 'Attempted unauthorized status change.',
        ])->assertForbidden();
        $this->post(route('claims.notes', $assignedToOther), [
            'note' => 'Attempted unauthorized note.',
        ])->assertForbidden();

        $this->actingAs($otherStaff)
            ->get(route('claims.show', $assignedToOther))
            ->assertOk()
            ->assertSee('Update status')
            ->assertSee('Add internal note');
        $this->put(route('claims.status', $assignedToOther), [
            'status' => 'under_review',
            'note' => 'Review started by the assigned staff member.',
        ])->assertRedirect(route('claims.show', $assignedToOther));
        $this->post(route('claims.notes', $assignedToOther), [
            'note' => 'Internal note by the assigned staff member.',
        ])->assertRedirect(route('claims.show', $assignedToOther));

        $this->assertSame('submitted', $unassigned->fresh()->status);
        $this->assertSame('under_review', $assignedToOther->fresh()->status);
        $this->assertDatabaseMissing('claim_histories', [
            'claim_id' => $unassigned->id,
            'event_type' => 'note_added',
        ]);
        $this->assertDatabaseMissing('claim_histories', [
            'claim_id' => $assignedToOther->id,
            'event_type' => 'note_added',
            'note' => 'Attempted unauthorized note.',
        ]);
        $this->assertDatabaseHas('claim_histories', [
            'claim_id' => $assignedToOther->id,
            'event_type' => 'note_added',
            'note' => 'Internal note by the assigned staff member.',
        ]);
    }

    public function test_claims_list_shows_assignee_but_assignment_is_done_in_claim_details(): void
    {
        $dtc = $this->dtc();
        $staff = User::factory()->create(['role' => 'staff', 'name' => 'List Staff']);
        $admin = User::factory()->create(['role' => 'admin']);
        $claim = Claim::create([
            'dtc_id' => $dtc->id,
            'claim_number' => 'CLM-000211',
            'subject' => 'List assignment issue',
            'description' => 'Review the tyre issue.',
            'eligible' => true,
            'eligibility_reason' => 'Within warranty.',
            'status' => 'submitted',
        ]);
        $claim->forceFill(['assigned_to' => $staff->id])->save();

        $this->actingAs($admin)
            ->get('/claims?quick_view=submitted&page=1')
            ->assertOk()
            ->assertSee('CLM-000211')
            ->assertSee('List Staff')
            ->assertDontSee('claim-assignee-'.$claim->id, false)
            ->assertDontSee('Assign to me')
            ->assertDontSee('Save</button>', false);

        $this->get(route('claims.show', $claim))
            ->assertOk()
            ->assertSee('Assign claim to staff')
            ->assertSee('List Staff');
    }

    public function test_claim_response_deadline_and_date_filtered_reports_render(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-23 12:00:00'));
        try {
            $dtc = $this->dtc();
            $overdue = Claim::create([
                'dtc_id' => $dtc->id,
                'claim_number' => 'CLM-000301',
                'subject' => 'Overdue issue',
                'description' => 'Awaiting review.',
                'eligible' => true,
                'eligibility_reason' => 'Within warranty.',
                'status' => 'submitted',
            ]);
            $overdue->forceFill(['created_at' => now()->subDays(4), 'updated_at' => now()->subDays(4)])->save();

            $staff = User::factory()->create(['role' => 'staff']);
            $this->actingAs($staff)
                ->get('/claims?quick_view=overdue')
                ->assertOk()
                ->assertSee('CLM-000301')
                ->assertSee('Overdue');
            $this->get(route('claims.show', $overdue))
                ->assertOk()
                ->assertSee('Response overdue');
            $this->get('/claims/reports?date_from=2026-10-01&date_to=2026-10-23')
                ->assertOk()
                ->assertSee('Claims report')
            ->assertSee('Submitted')
                ->assertSee('October 2026');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_registrant_search_matches_only_card_number_and_registration_id(): void
    {
        $this->dtc([
            'firstname' => 'Plate Match',
            'dtc_card_no' => 'card-match',
            'vehicle_reg_id' => 'PLATE123',
            'email' => 'plate@example.com',
        ]);
        $this->dtc([
            'firstname' => 'Email Match',
            'dtc_card_no' => 'other-card',
            'vehicle_reg_id' => 'OTHER123',
            'email' => 'search@example.com',
        ]);
        $this->dtc([
            'firstname' => 'Store Match',
            'dtc_card_no' => 'store-card',
            'vehicle_reg_id' => 'STORE123',
            'email' => 'store@example.com',
            'store_name' => 'Search Depot',
        ]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->get('/dtc?search=CARD-MATCH')
            ->assertOk()
            ->assertSee('Plate Match')
            ->assertDontSee('Email Match');
        $this->get('/dtc?search=PLATE123')
            ->assertOk()
            ->assertSee('Plate Match')
            ->assertDontSee('Email Match');
        $this->get('/dtc?search=search%40example.com')
            ->assertOk()
            ->assertSee('No registrants found');
        $this->get('/dtc?search=Search%20Depot')
            ->assertOk()
            ->assertSee('No registrants found');
        $this->get('/dtc')
            ->assertOk()
            ->assertSee('Export DTC Users')
            ->assertSee('dtcUserReport');
    }

    public function test_registrants_are_paginated_ten_per_page(): void
    {
        for ($index = 1; $index <= 11; $index++) {
            $this->dtc([
                'firstname' => 'Pagination '.$index,
                'dtc_card_no' => 'page-card-'.$index,
                'vehicle_reg_id' => 'PAGE'.$index,
            ]);
        }

        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $firstPage = $this->get('/dtc');
        $firstPage->assertOk();
        $this->assertSame(10, $firstPage->viewData('users')->count());
        $this->assertSame(11, $firstPage->viewData('users')->total());
        $this->assertSame(10, $firstPage->viewData('users')->perPage());
        $firstPage->assertSee('Pagination 11')->assertDontSee('Pagination 1</td>');

        $secondPage = $this->get('/dtc?page=2');
        $secondPage->assertOk()
            ->assertSee('Pagination 1')
            ->assertDontSee('Pagination 2</td>');
        $this->assertSame(1, $secondPage->viewData('users')->count());
    }

    public function test_claims_can_be_created_reviewed_and_tracked(): void
    {
        $dtc = $this->dtc();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $this->get("/dtc/{$dtc->id}/claims/create")
            ->assertOk()
            ->assertSee('Within warranty')
            ->assertSee('12-month warranty period is checked against the issue date');

        $this->post("/dtc/{$dtc->id}/claims", [
            'subject' => 'Tyre damage',
            'description' => 'Sidewall damage during normal use.',
            'incident_date' => now()->subDay()->toDateString(),
            'affected_tyres' => 'Front left',
        ])->assertRedirect();

        $claim = Claim::firstOrFail();
        $this->assertSame('CLM-'.str_pad((string) $claim->id, 6, '0', STR_PAD_LEFT), $claim->claim_number);
        $this->assertTrue($claim->eligible);
        $this->assertSame('submitted', $claim->status);
        $this->assertSame('Front left', $claim->affected_tyres);
        $this->assertSame(now()->subDay()->toDateString(), $claim->incident_date->toDateString());
        $this->assertSame($admin->id, $claim->created_by);
        $this->get('/dtc')
            ->assertOk()
            ->assertSee('Claims')
            ->assertSee('1 submitted claim awaiting review');

        Mail::fake();
        $this->put(route('claims.status', $claim), [
            'status' => 'under_review',
            'note' => 'Reviewing proof of purchase.',
            'customer_message' => 'Your claim is being reviewed.',
        ])->assertRedirect(route('claims.show', $claim));
        Mail::assertSent(ClaimStatusUpdated::class, fn ($mail) => $mail->hasTo('ada@example.com')
            && $mail->customerMessage === 'Your claim is being reviewed.');
        $this->post(route('claims.notes', $claim), [
            'note' => 'Contacted the customer for more details.',
        ])->assertRedirect(route('claims.show', $claim));

        $this->assertSame('under_review', $claim->fresh()->status);
        $this->assertDatabaseHas('claim_histories', [
            'claim_id' => $claim->id,
            'event_type' => 'status_changed',
            'from_status' => 'submitted',
            'to_status' => 'under_review',
            'note' => 'Reviewing proof of purchase.',
            'customer_message' => 'Your claim is being reviewed.',
        ]);
        $this->assertDatabaseHas('claim_histories', [
            'claim_id' => $claim->id,
            'event_type' => 'note_added',
            'note' => 'Contacted the customer for more details.',
        ]);

        $this->get('/claims?status=under_review&search='.$claim->claim_number)
            ->assertOk()
            ->assertSee($claim->claim_number)
            ->assertSee('Tyre damage')
            ->assertSee('Submitted — awaiting review')
            ->assertSee('Active claims')
            ->assertSee('Total claims')
            ->assertSee('Download Claims')
            ->assertSee('/claims/export?status=under_review&amp;search='.$claim->claim_number, false);
        $this->get(route('claims.show', $claim))
            ->assertOk()
            ->assertSee('Claim history')
            ->assertSee('Reviewing proof of purchase.')
            ->assertSee('Your claim is being reviewed.')
            ->assertSee('Contacted the customer for more details.');
    }

    public function test_purchase_on_current_manila_date_is_not_future_while_utc_is_previous_day(): void
    {
        config(['dtc.timezone' => 'Asia/Manila']);
        Carbon::setTestNow(Carbon::parse('2026-10-10 17:30:00', 'UTC'));

        try {
            $dtc = $this->dtc(['date_of_purchase' => '2026-10-11']);
            $this->assertSame([true, 'Within 12 months of the purchase date.'], Claim::eligibilityFor($dtc));

            $this->actingAs(User::factory()->create(['role' => 'admin']))
                ->get("/dtc/{$dtc->id}/claims/create")
                ->assertOk()
                ->assertSee('max="2026-10-11"', false)
                ->assertSee('Within warranty');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_claim_is_recorded_for_manual_review_when_warranty_has_expired(): void
    {
        $purchaseDate = now()->subMonths(13)->startOfDay();
        $dtc = $this->dtc(['date_of_purchase' => $purchaseDate->toDateString()]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->get("/dtc/{$dtc->id}/claims/create")
            ->assertOk()
            ->assertSee('Current warranty status')
            ->assertSee('12-month warranty');
        $this->post("/dtc/{$dtc->id}/claims", [
            'subject' => 'Out of warranty claim',
            'description' => 'Please review this case.',
            'incident_date' => $purchaseDate->copy()->addYear()->addDay()->toDateString(),
        ])->assertRedirect();

        $claim = Claim::firstOrFail();
        $this->assertFalse($claim->eligible);
        $this->assertSame('submitted', $claim->status);
    }

    public function test_issue_during_warranty_remains_eligible_when_claim_is_submitted_after_expiry(): void
    {
        $purchaseDate = now()->subMonths(13)->startOfDay();
        $incidentDate = $purchaseDate->copy()->addMonths(6)->toDateString();
        $dtc = $this->dtc(['date_of_purchase' => $purchaseDate->toDateString()]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->post("/dtc/{$dtc->id}/claims", [
            'subject' => 'Issue during coverage',
            'description' => 'The issue happened while the warranty was active.',
            'incident_date' => $incidentDate,
        ])->assertRedirect();

        $claim = Claim::firstOrFail();
        $this->assertTrue($claim->eligible);
        $this->assertSame('The issue occurred within the 12-month warranty period.', $claim->eligibility_reason);
    }

    public function test_staff_created_claim_sends_customer_submission_confirmation(): void
    {
        Mail::fake();
        $dtc = $this->dtc();
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        $this->post("/dtc/{$dtc->id}/claims", [
            'subject' => 'Tyre damage',
            'description' => 'Please review this case.',
        ])->assertRedirect()
            ->assertSessionHas('status', 'Claim CLM-000001 submitted and confirmation email accepted by the mail server.');

        $claim = Claim::firstOrFail();
        $this->assertSame($staff->id, $claim->created_by);
        $this->assertSame($staff->id, $claim->assigned_to);
        $this->assertNotNull($claim->assigned_at);
        $this->get(route('claims.show', $claim))
            ->assertOk()
            ->assertSee('Update status')
            ->assertSee('Add internal note');

        Mail::assertSent(ClaimSubmitted::class, fn (ClaimSubmitted $mail) => $mail->hasTo('ada@example.com')
            && $mail->claimNumber === 'CLM-000001');
    }

    public function test_staff_claim_is_not_created_when_customer_has_no_email(): void
    {
        Mail::fake();
        $dtc = $this->dtc(['email' => null]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->post("/dtc/{$dtc->id}/claims", [
            'subject' => 'Tyre damage',
            'description' => 'Please review this case.',
        ])->assertRedirect("/dtc/{$dtc->id}/claims/create")
            ->assertSessionHasErrors([
                'email_notification' => 'A valid customer email is required before a claim can be submitted. Update the registration email and try again.',
            ]);

        Mail::assertNothingSent();
        $this->assertDatabaseCount('claims', 0);
        $this->assertDatabaseCount('claim_histories', 0);
    }

    public function test_staff_cannot_create_claim_with_issue_date_before_purchase_date(): void
    {
        $dtc = $this->dtc(['date_of_purchase' => now()->subDay()->toDateString()]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->get("/dtc/{$dtc->id}/claims/create")
            ->assertOk()
            ->assertSee('min="'.now()->subDay()->toDateString().'"', false);

        $this->post("/dtc/{$dtc->id}/claims", [
            'subject' => 'Tyre damage',
            'description' => 'Issue date predates purchase.',
            'incident_date' => now()->subDays(2)->toDateString(),
        ])->assertSessionHasErrors('incident_date');

        $this->assertDatabaseCount('claims', 0);
    }

    public function test_need_more_info_requires_a_customer_message_separate_from_internal_notes(): void
    {
        $dtc = $this->dtc(['email' => null]);
        $claim = Claim::create([
            'dtc_id' => $dtc->id,
            'subject' => 'Warranty details',
            'description' => 'Please review the tyre issue.',
            'eligible' => true,
            'eligibility_reason' => 'Within 12 months of the purchase date.',
            'status' => 'submitted',
            'claim_number' => 'CLM-000001',
        ]);
        $claim->histories()->create([
            'event_type' => 'submitted',
            'to_status' => 'submitted',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $this->put(route('claims.status', $claim), [
            'status' => 'need_more_info',
            'note' => 'Need the purchase receipt.',
        ])->assertSessionHasErrors('customer_message');

        $this->put(route('claims.status', $claim), [
            'status' => 'need_more_info',
            'note' => 'Internal note: receipt required.',
            'customer_message' => 'Please provide a copy of your purchase receipt.',
        ])->assertRedirect(route('claims.show', $claim))
            ->assertSessionHas('status', 'Claim status updated. No registered email is available; the customer can see the update in Check Warranty.');

        $this->assertDatabaseHas('claim_histories', [
            'claim_id' => $claim->id,
            'customer_message' => 'Please provide a copy of your purchase receipt.',
            'note' => 'Internal note: receipt required.',
        ]);
    }

    public function test_open_claims_without_activity_for_three_days_are_marked_for_follow_up(): void
    {
        $dtc = $this->dtc();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $this->post("/dtc/{$dtc->id}/claims", [
            'subject' => 'Old claim',
            'description' => 'Waiting for staff review.',
        ])->assertRedirect();

        $claim = Claim::firstOrFail();
        DB::table('claims')->where('id', $claim->id)->update([
            'updated_at' => now()->copy()->subDays(4)->toDateTimeString(),
        ]);
        $claim = $claim->fresh();
        $this->assertSame('submitted', $claim->fresh()->status);
        $this->assertTrue($claim->fresh()->updated_at->lte(now()->copy()->subDays(3)));
        $cutoff = now()->copy()->subDays(3)->toDateTimeString();
        $this->assertSame(1, Claim::whereIn('status', Claim::OPEN_STATUSES)
            ->where('updated_at', '<=', $cutoff)
            ->count());

        $this->get('/claims?quick_view=follow_up')
            ->assertOk()
            ->assertSee('Needs follow-up')
            ->assertSee($claim->claim_number)
            ->assertSee('Needs follow-up');

        $this->post(route('claims.notes', $claim), [
            'note' => 'Staff checked the record.',
        ])->assertRedirect(route('claims.show', $claim));
        $this->get('/claims?quick_view=follow_up')
            ->assertOk()
            ->assertSee('No claims found.');
    }

    public function test_admin_can_create_multiple_claims_while_another_is_open(): void
    {
        $dtc = $this->dtc();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $this->post("/dtc/{$dtc->id}/claims", [
            'subject' => 'First issue',
            'description' => 'Existing customer claim.',
        ])->assertRedirect();

        $existingClaim = Claim::firstOrFail();
        $this->get("/dtc/{$dtc->id}/claims/create")
            ->assertOk()
            ->assertSee('Existing open claims')
            ->assertSee($existingClaim->claim_number)
            ->assertSee('First issue')
            ->assertSee('different issue or incident')
            ->assertSee('name="different_issue_confirmed"', false)
            ->assertSee('name="subject"', false);

        $this->get("/dtc/{$dtc->id}")
            ->assertOk()
            ->assertSee('Create claim')
            ->assertSee($existingClaim->claim_number);

        $this->post("/dtc/{$dtc->id}/claims", [
                'subject' => 'Second issue',
                'description' => 'Another customer issue.',
            ])
            ->assertSessionHasErrors('different_issue_confirmed');
        $this->assertDatabaseCount('claims', 1);

        $this->post("/dtc/{$dtc->id}/claims", [
                'subject' => 'Second issue',
                'description' => 'Another customer issue.',
                'different_issue_confirmed' => '1',
            ])
            ->assertRedirect();
        $this->assertDatabaseCount('claims', 2);

        $this->put(route('claims.status', $existingClaim), [
            'status' => 'under_review',
            'note' => 'Review started.',
        ])->assertRedirect(route('claims.show', $existingClaim));
        $this->put(route('claims.status', $existingClaim), [
            'status' => 'approved',
            'note' => 'Claim approved.',
        ])->assertRedirect(route('claims.show', $existingClaim));
        $this->get("/dtc/{$dtc->id}")
            ->assertOk()
            ->assertSee('Create claim')
            ->assertDontSee('Open claim: '.$existingClaim->claim_number);

        $this->post("/dtc/{$dtc->id}/claims", [
            'subject' => 'Third issue',
            'description' => 'A separate issue filed after approval.',
            'different_issue_confirmed' => '1',
        ])->assertRedirect();

        $this->assertDatabaseCount('claims', 3);
    }

    public function test_delete_button_soft_deletes_registration_and_requires_login(): void
    {
        $dtc = $this->dtc();

        $this->delete("/dtc/{$dtc->id}")->assertRedirect('/login');

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->delete("/dtc/{$dtc->id}")
            ->assertRedirect('/dtc')
            ->assertSessionHas('status', 'Registrant deleted.');

        $this->assertSoftDeleted('dtc_users', ['id' => $dtc->id]);
        $this->assertNull(Dtc::find($dtc->id));
    }

    public function test_update_changes_editable_fields_and_keeps_registration_data(): void
    {
        $dtc = $this->dtc();
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->put("/dtc/{$dtc->id}", [
            'dtc_card_no' => 'dtc-1', 'first_name' => 'Grace', 'last_name' => 'Hopper', 'email' => 'ada@example.com', 'contact_no' => '0917123456',
            'vehicle_reg_id' => 'XYZ999', 'tyre' => 'Eagle F1', 'quantity' => 2,
            'date_of_purchase' => now()->subDay()->toDateString(), 'find_retailer' => 'Other Store', 'subscribe' => false,
        ])->assertRedirect("/dtc/{$dtc->id}");

        $fresh = $dtc->fresh();
        $this->assertSame('Grace', $fresh->firstname);
        $this->assertSame('Other Store', $fresh->store_name);
        $this->assertTrue($fresh->subscribe);
        $this->assertSame(Dtc::hashVehicleRegId('XYZ999'), $fresh->vehicle_reg_hash);
    }

    public function test_activity_log_tracks_registrant_and_claim_changes_without_private_values(): void
    {
        Mail::fake();
        $dtc = $this->dtc();
        $staff = User::factory()->create(['role' => 'admin']);
        $this->actingAs($staff);

        $this->put("/dtc/{$dtc->id}", [
            'dtc_card_no' => 'dtc-1', 'first_name' => 'Grace', 'last_name' => 'Lovelace',
            'email' => 'ada@example.com', 'contact_no' => '0917123456', 'vehicle_reg_id' => 'ABC123',
            'brand' => 'Toyota', 'model' => 'Corolla', 'mileage' => 100, 'tyre' => 'Eagle F1',
            'tyre_size' => '205/55R16', 'rim_size' => '16', 'quantity' => 4,
            'date_of_purchase' => now()->subDay()->toDateString(), 'find_retailer' => 'Main Store', 'state' => 'NSW',
        ])->assertRedirect("/dtc/{$dtc->id}");

        $registrantActivity = DtcAuditLog::where('action', 'registrant.updated')->firstOrFail();
        $this->assertContains('firstname', $registrantActivity->old_values['changed_fields']);
        $this->assertStringNotContainsString('ada@example.com', json_encode($registrantActivity->getAttributes()));

        $claim = Claim::create([
            'dtc_id' => $dtc->id,
            'claim_number' => 'CLM-000123',
            'subject' => 'Tyre issue',
            'description' => 'Customer description.',
            'eligible' => true,
            'eligibility_reason' => 'Within warranty.',
            'status' => 'submitted',
        ]);

        $this->put("/claims/{$claim->id}/status", [
            'status' => 'under_review',
            'note' => 'Private staff note.',
        ])->assertRedirect("/claims/{$claim->id}");

        $statusActivity = DtcAuditLog::where('action', 'claim.status_changed')->firstOrFail();
        $this->assertSame(['status' => 'submitted'], $statusActivity->old_values);
        $this->assertSame(['status' => 'under_review'], $statusActivity->new_values);
        $this->assertStringNotContainsString('Private staff note.', json_encode($statusActivity->getAttributes()));

        $this->post("/claims/{$claim->id}/notes", ['note' => 'Another internal note.'])
            ->assertRedirect("/claims/{$claim->id}");
        $noteActivity = DtcAuditLog::where('action', 'claim.note_added')->firstOrFail();
        $this->assertStringNotContainsString('Another internal note.', json_encode($noteActivity->getAttributes()));

        $this->delete("/dtc/{$dtc->id}")->assertRedirect('/dtc');
        $this->assertDatabaseHas('dtc_audit_logs', [
            'dtc_id' => $dtc->id,
            'action' => 'registrant.deleted',
            'user_id' => $staff->id,
        ]);

        $this->get('/activity?search=CLM-000123&type=claim')
            ->assertOk()
            ->assertSee('Activity log')
            ->assertSee('Updated claim CLM-000123 status')
            ->assertSee('CLM-000123');
        $this->get('/')
            ->assertOk()
            ->assertSee('Recent activity')
            ->assertSee('Updated claim CLM-000123 status');
    }

    public function test_old_activity_logs_are_archived_before_removal_from_active_table(): void
    {
        Storage::fake('local');
        Carbon::setTestNow(Carbon::parse('2026-10-11 12:00:00'));

        try {
            $old = DtcAuditLog::forceCreate([
                'entity_type' => 'registrant',
                'entity_id' => 123,
                'action' => 'registrant.updated',
                'summary' => 'Old registrant update',
                'created_at' => Carbon::parse('2024-10-10 12:00:00'),
                'updated_at' => Carbon::parse('2024-10-10 12:00:00'),
            ]);
            $recent = DtcAuditLog::forceCreate([
                'entity_type' => 'registrant',
                'entity_id' => 124,
                'action' => 'registrant.updated',
                'summary' => 'Recent registrant update',
                'created_at' => Carbon::parse('2024-10-12 12:00:00'),
                'updated_at' => Carbon::parse('2024-10-12 12:00:00'),
            ]);

            $this->artisan('activity:archive --dry-run')
                ->expectsOutputToContain('1 activity log(s)')
                ->assertSuccessful();
            $this->assertDatabaseHas('dtc_audit_logs', ['id' => $old->id]);

            $this->artisan('activity:archive')
                ->expectsOutputToContain('Archived 1 activity log(s)')
                ->assertSuccessful();

            $this->assertDatabaseMissing('dtc_audit_logs', ['id' => $old->id]);
            $this->assertDatabaseHas('dtc_audit_logs', ['id' => $recent->id]);

            $archives = Storage::disk('local')->allFiles('activity-archives');
            $this->assertCount(1, $archives);
            $contents = gzdecode(Storage::disk('local')->get($archives[0]));
            $this->assertIsString($contents);
            $this->assertStringContainsString('Old registrant update', $contents);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_update_rejects_card_number_used_by_another_record(): void
    {
        $this->dtc(['dtc_card_no' => 'taken']);
        $dtc = $this->dtc(['dtc_card_no' => 'mine', 'vehicle_reg_id' => 'QQQ111']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->put("/dtc/{$dtc->id}", [
            'dtc_card_no' => 'TAKEN', 'first_name' => 'A', 'last_name' => 'B', 'contact_no' => '0917123456',
            'vehicle_reg_id' => 'QQQ111', 'tyre' => 'T', 'quantity' => 1,
            'date_of_purchase' => now()->subDay()->toDateString(), 'find_retailer' => 'S',
        ])->assertSessionHasErrors('dtc_card_no');
    }

    public function test_update_requires_an_unused_dtc_code_when_enabled(): void
    {
        config(['dtc.require_dtc_code' => true]);
        DtcCode::create(['code' => 'CURRENT1', 'is_use' => true]);
        DtcCode::create(['code' => 'USED0001', 'is_use' => true]);
        DtcCode::create(['code' => 'AVAILABLE']);
        $dtc = $this->dtc(['dtc_card_no' => 'CURRENT1']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->put("/dtc/{$dtc->id}", [
            'dtc_card_no' => 'CURRENT1', 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@example.com', 'contact_no' => '0917123456',
            'vehicle_reg_id' => 'ABC123', 'tyre' => 'T', 'quantity' => 1,
            'date_of_purchase' => now()->subDay()->toDateString(), 'find_retailer' => 'S',
        ])->assertRedirect("/dtc/{$dtc->id}");

        $this->put("/dtc/{$dtc->id}", [
            'dtc_card_no' => 'MADEUP01', 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@example.com', 'contact_no' => '0917123456',
            'vehicle_reg_id' => 'ABC123', 'tyre' => 'T', 'quantity' => 1,
            'date_of_purchase' => now()->subDay()->toDateString(), 'find_retailer' => 'S',
        ])->assertSessionHasErrors('dtc_card_no');

        $this->put("/dtc/{$dtc->id}", [
            'dtc_card_no' => 'USED0001', 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@example.com', 'contact_no' => '0917123456',
            'vehicle_reg_id' => 'ABC123', 'tyre' => 'T', 'quantity' => 1,
            'date_of_purchase' => now()->subDay()->toDateString(), 'find_retailer' => 'S',
        ])->assertSessionHasErrors('dtc_card_no');

        $this->put("/dtc/{$dtc->id}", [
            'dtc_card_no' => 'AVAILABLE', 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@example.com', 'contact_no' => '0917123456',
            'vehicle_reg_id' => 'ABC123', 'tyre' => 'T', 'quantity' => 1,
            'date_of_purchase' => now()->subDay()->toDateString(), 'find_retailer' => 'S',
        ])->assertRedirect("/dtc/{$dtc->id}");

        $this->assertTrue(DtcCode::where('code', 'AVAILABLE')->value('is_use'));
    }

    public function test_export_streams_csv_within_date_range_and_guards_formulas(): void
    {
        $this->dtc(['firstname' => '=HYPERLINK("x")']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $response = $this->post('/dtc/export/user', ['date_from' => now()->subDay()->toDateString(), 'date_to' => now()->toDateString()]);
        $response->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString('First Name', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString('DTC-1', $csv);
    }

    public function test_export_allows_date_ranges_over_three_months(): void
    {
        $record = $this->dtc([
            'dtc_card_no' => 'old-record',
            'created_at' => now()->subMonths(4),
        ]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $response = $this->post('/dtc/export/user', [
            'date_from' => now()->subMonths(6)->toDateString(),
            'date_to' => now()->toDateString(),
        ]);
        $response->assertOk();
        $this->assertStringContainsString('OLD-RECORD', $response->streamedContent());
    }

    public function test_export_rejects_future_dates(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $tomorrow = now()->addDay()->toDateString();

        $this->from('/dtc')->post('/dtc/export/user', [
            'date_from' => $tomorrow,
            'date_to' => $tomorrow,
        ])->assertRedirect('/dtc')
            ->assertSessionHasErrors(['date_from', 'date_to']);
    }

    public function test_claim_export_streams_filtered_csv_with_history_notes_and_formula_protection(): void
    {
        $firstRegistrant = $this->dtc(['dtc_card_no' => 'report-card-1']);
        $secondRegistrant = $this->dtc([
            'dtc_card_no' => 'report-card-2',
            'vehicle_reg_id' => 'XYZ999',
            'firstname' => 'Other',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $this->post("/dtc/{$firstRegistrant->id}/claims", [
            'subject' => '=HYPERLINK("x")',
            'description' => '+SUM(1,1)',
        ])->assertRedirect();
        $firstClaim = Claim::firstOrFail();
        $this->post(route('claims.notes', $firstClaim), ['note' => 'Internal follow-up note'])->assertRedirect();

        $this->post("/dtc/{$secondRegistrant->id}/claims", [
            'subject' => 'Other registration claim',
            'description' => 'Should not be in filtered report.',
        ])->assertRedirect();

        $response = $this->get('/claims/export?status=submitted&search=report-card-1&date_from='.today()->toDateString().'&date_to='.today()->toDateString());
        $response->assertOk();
        $this->assertStringContainsString('attachment; filename=claims_', $response->headers->get('content-disposition'));

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Claim No.', $csv);
        $this->assertStringContainsString($firstClaim->claim_number, $csv);
        $this->assertStringContainsString('REPORT-CARD-1', $csv);
        $this->assertStringContainsString('Internal follow-up note', $csv);
        $this->assertStringContainsString('\'=HYPERLINK', $csv);
        $this->assertStringContainsString('\'+SUM', $csv);
        $this->assertStringNotContainsString('Other registration claim', $csv);
    }
}
