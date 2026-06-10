<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class KioskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure roles exist in the test database
        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
    }

    private function createTable(string $status = 'available'): Table
    {
        return Table::create([
            'number' => 'T-'.fake()->unique()->numberBetween(1, 99),
            'capacity' => 4,
            'location' => 'indoor',
            'status' => $status,
            'qr_code' => fake()->uuid(),
        ]);
    }

    private function createMenuItem(): MenuItem
    {
        $category = Category::create([
            'name' => fake()->unique()->word(),
            'slug' => fake()->unique()->slug(),
        ]);

        return MenuItem::create([
            'name' => fake()->unique()->word(),
            'slug' => fake()->unique()->slug(),
            'price' => 25000,
            'category_id' => $category->id,
            'status' => 'available',
        ]);
    }

    private function createCustomer(): User
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        return $user;
    }

    // ── Page Access ──────────────────────────────────────────────────────

    public function test_kiosk_page_loads_with_valid_token(): void
    {
        $table = $this->createTable();
        $this->createMenuItem();

        $response = $this->get("/kiosk/{$table->qr_code}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('kiosk/Show')
            ->has('table')
            ->has('categories')
            ->has('menuItems')
        );
    }

    public function test_kiosk_page_returns_404_for_invalid_token(): void
    {
        $this->get('/kiosk/invalid-token-xyz')->assertNotFound();
    }

    public function test_kiosk_page_returns_503_for_maintenance_table(): void
    {
        $table = $this->createTable(status: 'maintenance');

        $this->get("/kiosk/{$table->qr_code}")->assertStatus(503);
    }

    // ── Order Placement ─────────────────────────────────────────────────

    public function test_guest_can_place_kiosk_order(): void
    {
        $table = $this->createTable();
        $item = $this->createMenuItem();

        $response = $this->postJson("/kiosk/{$table->qr_code}/api/orders", [
            'guest_name' => 'Budi',
            'items' => [
                [
                    'menu_item_id' => $item->id,
                    'name' => $item->name,
                    'qty' => 2,
                    'price' => 25000,
                ],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonFragment(['source' => 'kiosk', 'guest_name' => 'Budi']);
        $this->assertDatabaseHas('orders', ['source' => 'kiosk', 'table_id' => $table->id]);
    }

    public function test_logged_in_customer_order_links_to_customer_id(): void
    {
        $table = $this->createTable();
        $item = $this->createMenuItem();
        $customer = $this->createCustomer();

        $response = $this->actingAs($customer)->postJson("/kiosk/{$table->qr_code}/api/orders", [
            'items' => [[
                'menu_item_id' => $item->id,
                'name' => $item->name,
                'qty' => 1,
                'price' => 25000,
            ]],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('orders', ['source' => 'kiosk', 'customer_id' => $customer->id]);
    }

    public function test_kiosk_order_rejects_missing_items(): void
    {
        $table = $this->createTable();

        // No items key at all should be rejected
        $this->postJson("/kiosk/{$table->qr_code}/api/orders", [])
            ->assertUnprocessable();
    }

    // ── Auth ─────────────────────────────────────────────────────────────

    public function test_customer_can_register_via_kiosk(): void
    {
        $table = $this->createTable();

        $response = $this->postJson("/kiosk/{$table->qr_code}/api/auth/register", [
            'name' => 'Siti',
            'email' => 'siti@example.com',
            'password' => 'password123',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', ['email' => 'siti@example.com']);
        $user = User::where('email', 'siti@example.com')->first();
        $this->assertTrue($user->hasRole('customer'));
    }

    public function test_customer_can_login_via_kiosk_with_email(): void
    {
        $table = $this->createTable();
        $user = User::factory()->create([
            'email' => 'budi@example.com',
            'password' => Hash::make('secret'),
        ]);
        $user->assignRole('customer');

        $response = $this->postJson("/kiosk/{$table->qr_code}/api/auth/login", [
            'login' => 'budi@example.com',
            'password' => 'secret',
        ]);

        $response->assertOk();
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $table = $this->createTable();
        $user = User::factory()->create([
            'email' => 'budi@example.com',
            'password' => Hash::make('correct'),
        ]);
        $user->assignRole('customer');

        $this->postJson("/kiosk/{$table->qr_code}/api/auth/login", [
            'login' => 'budi@example.com',
            'password' => 'wrong',
        ])->assertUnauthorized();
    }

    public function test_staff_cannot_login_as_kiosk_customer(): void
    {
        $table = $this->createTable();
        $staff = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);
        $staff->assignRole('employee');

        $this->postJson("/kiosk/{$table->qr_code}/api/auth/login", [
            'login' => 'admin@example.com',
            'password' => 'password',
        ])->assertUnauthorized();
    }

    // ── Order History ────────────────────────────────────────────────────

    public function test_logged_in_customer_can_view_order_history(): void
    {
        $table = $this->createTable();
        $customer = $this->createCustomer();

        $this->actingAs($customer)
            ->get("/kiosk/{$table->qr_code}/orders")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('kiosk/Orders')->has('orders'));
    }

    public function test_guest_cannot_access_order_history(): void
    {
        $table = $this->createTable();

        $this->get("/kiosk/{$table->qr_code}/orders")->assertStatus(401);
    }
}
