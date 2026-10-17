<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->owner(['name' => 'Owner One', 'username' => 'owner1']);
    }

    /**
     * @return array<string, mixed>
     */
    private function newUser(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Rahim Uddin',
            'username' => 'Rahim',
            'email' => 'rahim@example.com',
            'phone' => '01700000000',
            'locale' => 'bn',
            'role' => 'sr',
            'password' => 'temporary-pass-1',
            'password_confirmation' => 'temporary-pass-1',
        ];
    }

    public function test_owner_creates_a_user_who_must_change_the_password(): void
    {
        $this->actingAs($this->owner)->post('/users', $this->newUser())
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('status');

        $user = User::query()->where('username', 'rahim')->sole();
        $this->assertSame('sr', $user->roleName());
        $this->assertTrue($user->must_change_password);
        $this->assertSame('bn', $user->locale);
        $this->assertTrue(Hash::check('temporary-pass-1', $user->password));

        $created = AuditLog::query()->where('event', 'created')->where('auditable_id', $user->id)->sole();
        $this->assertSame($this->owner->id, $created->user_id);
        $this->assertSame('rahim', $created->new_values['username']);
        $this->assertSame('[hidden]', $created->new_values['password']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.role_changed', 'auditable_id' => $user->id]);
    }

    public function test_validation(): void
    {
        $this->userWithRole('viewer', ['username' => 'taken']);

        $this->actingAs($this->owner)->from('/users/create')->post('/users', $this->newUser([
            'username' => 'Taken',
            'email' => 'not-an-email',
            'role' => 'superuser',
            'password' => 'short',
            'password_confirmation' => 'other',
        ]))->assertSessionHasErrors(['username', 'email', 'role', 'password']);

        $this->actingAs($this->owner)->post('/users', $this->newUser(['username' => 'bad name!']))
            ->assertSessionHasErrors('username');
    }

    public function test_user_list_is_searchable_filterable_and_paginated(): void
    {
        User::factory()->count(55)->role('viewer')->create();
        $this->userWithRole('accountant', ['name' => 'Karim Accounts', 'username' => 'karim']);
        $this->userWithRole('viewer', ['name' => 'Vera Viewer', 'username' => 'vera']);

        $this->actingAs($this->owner)->get('/users')->assertOk()->assertSee('?page=2', false);

        $this->actingAs($this->owner)->get('/users?q=karim')
            ->assertSee('Karim Accounts')
            ->assertDontSee('?page=2', false);

        $this->actingAs($this->owner)->get('/users?role=accountant')
            ->assertSee('Karim Accounts')
            ->assertDontSee('Vera Viewer');
    }

    public function test_htmx_request_gets_only_the_table(): void
    {
        $this->actingAs($this->owner)->get('/users?q=owner', ['HX-Request' => 'true'])
            ->assertOk()
            ->assertSee('data-testid="users-table"', false)
            ->assertDontSee('<html', false);
    }

    public function test_owner_changes_a_role_and_it_is_audited(): void
    {
        $user = $this->userWithRole('viewer', ['username' => 'rahim']);

        $this->actingAs($this->owner)->put("/users/{$user->id}", [
            'name' => 'Rahim U.', 'username' => 'rahim', 'email' => null, 'phone' => null, 'locale' => null, 'role' => 'accountant',
        ])->assertRedirect(route('users.index'));

        $this->assertSame('accountant', $user->fresh()->roleName());
        $log = AuditLog::query()->where('event', 'user.role_changed')->where('auditable_id', $user->id)->sole();
        $this->assertSame(['role' => 'viewer'], $log->old_values);
        $this->assertSame(['role' => 'accountant'], $log->new_values);
    }

    public function test_the_last_owner_cannot_be_demoted_or_deactivated(): void
    {
        $this->actingAs($this->owner)->from("/users/{$this->owner->id}/edit")->put("/users/{$this->owner->id}", [
            'name' => 'Owner One', 'username' => 'owner1', 'role' => 'viewer',
        ])->assertSessionHasErrors('role');
        $this->assertTrue($this->owner->fresh()->isOwner());

        $second = $this->owner(['username' => 'owner2']);
        $this->actingAs($second)->delete("/users/{$this->owner->id}")->assertRedirect(route('users.index'));
        $this->assertSoftDeleted($this->owner);

        $this->actingAs($second)->delete("/users/{$second->id}")->assertSessionHasErrors('user');
    }

    public function test_nobody_can_deactivate_themselves(): void
    {
        $this->owner(['username' => 'owner2']);

        $this->actingAs($this->owner)->delete("/users/{$this->owner->id}")->assertSessionHasErrors('user');
        $this->assertNotSoftDeleted($this->owner);
    }

    public function test_deactivated_user_is_signed_out_listed_and_can_be_restored(): void
    {
        $user = $this->userWithRole('viewer', ['name' => 'Gone Person', 'username' => 'gone']);

        $this->actingAs($this->owner)->delete("/users/{$user->id}")->assertRedirect(route('users.index'));
        $this->assertSoftDeleted($user);
        $this->assertDatabaseHas('audit_logs', ['event' => 'deleted', 'auditable_id' => $user->id]);

        $this->actingAs($this->owner)->get('/users')->assertDontSee('Gone Person');
        $this->actingAs($this->owner)->get('/users?status=inactive')->assertSee('Gone Person');
        $this->actingAs($this->owner)->get("/users/{$user->id}/edit")->assertOk();

        $this->actingAs($this->owner)->post("/users/{$user->id}/restore")->assertRedirect(route('users.index'));
        $this->assertNotSoftDeleted($user);
        $this->assertDatabaseHas('audit_logs', ['event' => 'restored', 'auditable_id' => $user->id]);
    }

    public function test_owner_resets_a_password_and_two_factor(): void
    {
        $user = $this->userWithRole('viewer');
        $user->forceFill(['two_factor_secret' => 'ABCDEFGHIJKLMNOP', 'two_factor_confirmed_at' => now()])->save();

        $this->actingAs($this->owner)->post("/users/{$user->id}/reset-password", [
            'password' => 'given-by-owner-1', 'password_confirmation' => 'given-by-owner-1',
        ])->assertRedirect(route('users.edit', $user));

        $user->refresh();
        $this->assertTrue(Hash::check('given-by-owner-1', $user->password));
        $this->assertTrue($user->must_change_password);
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.password_reset_by_admin', 'auditable_id' => $user->id]);

        $this->actingAs($this->owner)->post("/users/{$user->id}/reset-two-factor");
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.two_factor_reset', 'auditable_id' => $user->id]);
    }

    public function test_a_non_owner_with_users_manage_cannot_touch_owners(): void
    {
        Role::findByName('accountant')->givePermissionTo('users.manage');
        $accountant = $this->userWithRole('accountant');

        $this->actingAs($accountant)->post('/users', $this->newUser())->assertRedirect(route('users.index'));
        $this->actingAs($accountant)->post('/users', $this->newUser(['username' => 'boss', 'email' => null, 'role' => 'owner']))->assertForbidden();
        $this->actingAs($accountant)->get("/users/{$this->owner->id}/edit")->assertForbidden();
        $this->actingAs($accountant)->delete("/users/{$this->owner->id}")->assertForbidden();
        $this->actingAs($accountant)->post("/users/{$this->owner->id}/reset-password", [
            'password' => 'given-by-owner-1', 'password_confirmation' => 'given-by-owner-1',
        ])->assertForbidden();
        $this->actingAs($accountant)->put("/users/{$accountant->id}", [
            'name' => 'Me', 'username' => $accountant->username, 'role' => 'owner',
        ])->assertForbidden();

        $this->assertSame('accountant', $accountant->fresh()->roleName());
    }

    public function test_accountant_can_list_users_but_not_change_them(): void
    {
        $accountant = $this->userWithRole('accountant');
        $viewer = $this->userWithRole('viewer');

        $this->actingAs($accountant)->get('/users')->assertOk()->assertDontSee(route('users.create'), false);
        $this->actingAs($accountant)->get("/users/{$viewer->id}/edit")->assertForbidden();
        $this->actingAs($accountant)->delete("/users/{$viewer->id}")->assertForbidden();
    }
}
