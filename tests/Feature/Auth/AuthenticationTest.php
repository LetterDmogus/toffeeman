<?php

namespace Tests\Feature\Auth;

use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered()
    {
        $response = $this->get(route('login'));

        $response->assertOk();
    }

    public function test_users_can_authenticate_using_the_login_screen()
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_cashier_redirects_to_pos_upon_login()
    {
        $position = Position::create([
            'name' => 'Cashier',
            'slug' => 'cashier',
            'starting_page' => '/pos',
        ]);
        $user = User::factory()->create([
            'position_id' => $position->id,
        ]);
        Employee::create([
            'user_id' => $user->id,
            'salary' => 3000000,
            'status' => 'active',
            'hired_at' => now(),
        ]);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('pos', absolute: false));
    }

    public function test_manager_redirects_to_reports_upon_login()
    {
        $position = Position::create([
            'name' => 'Manager',
            'slug' => 'manager',
            'starting_page' => '/reports',
        ]);
        $user = User::factory()->create([
            'position_id' => $position->id,
        ]);
        Employee::create([
            'user_id' => $user->id,
            'salary' => 5000000,
            'status' => 'active',
            'hired_at' => now(),
        ]);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('reports', absolute: false));
    }

    public function test_user_direct_position_redirects_upon_login()
    {
        $position = Position::create([
            'name' => 'Chef Direct',
            'slug' => 'chef-direct',
            'starting_page' => '/kitchen',
        ]);

        $user = User::factory()->create([
            'position_id' => $position->id,
        ]);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('kitchen', absolute: false));
    }

    public function test_user_inherits_position_permissions()
    {
        $position = Position::create([
            'name' => 'Cashier Position',
            'slug' => 'cashier-pos',
            'guard_name' => 'web',
        ]);

        // Create a permission
        $permission = Permission::firstOrCreate(['name' => 'pos-access', 'guard_name' => 'web']);
        $position->givePermissionTo($permission);

        $user = User::factory()->create([
            'position_id' => $position->id,
        ]);

        $this->assertTrue($user->hasPermissionTo('pos-access'));
    }

    public function test_users_with_two_factor_enabled_are_redirected_to_two_factor_challenge()
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $user = User::factory()->withTwoFactor()->create();

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.login'));
        $response->assertSessionHas('login.id', $user->id);
        $this->assertGuest();
    }

    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_users_are_rate_limited()
    {
        $user = User::factory()->create();

        RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertTooManyRequests();
    }
}
