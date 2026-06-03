<?php

namespace Tests\Feature;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\User;
use Database\Seeders\RoleAndPositionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPositionSeeder::class);

        $this->admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);
        $this->admin->assignRole('superadmin');
    }

    public function test_can_create_inventory_item_with_special_fields(): void
    {
        $category = InventoryCategory::create([
            'name' => 'Electronic Tools',
            'slug' => 'electronic-tools',
        ]);

        $response = $this->actingAs($this->admin)->postJson(route('api.inventory-items.store'), [
            'name' => 'Thermal Printer Custom',
            'sku' => 'INV-PRN-001',
            'inventory_category_id' => $category->id,
            'price' => 500000,
            'qty_good' => 5,
            'qty_fair' => 2,
            'qty_damaged' => 1,
            'qty' => 8, // Total qty is good+fair+damaged
            'unit' => 'unit',
            'min_qty' => 10, // So status should be low_stock (8 <= 10)
            'purchase_date' => '2026-06-01',
            'storage_location' => 'Kasir Utama',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('status', 'low_stock');
        $response->assertJsonPath('qty', '8.00');

        $this->assertDatabaseHas('inventory_items', [
            'name' => 'Thermal Printer Custom',
            'qty_good' => 5.00,
            'qty_fair' => 2.00,
            'qty_damaged' => 1.00,
            'qty' => 8.00,
            'status' => 'low_stock',
            'storage_location' => 'Kasir Utama',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_auto_calculates_qty_on_saving(): void
    {
        $category = InventoryCategory::create([
            'name' => 'Electronic Tools',
            'slug' => 'electronic-tools',
        ]);

        $item = InventoryItem::create([
            'name' => 'Thermal Printer Custom',
            'sku' => 'INV-PRN-001',
            'inventory_category_id' => $category->id,
            'price' => 500000,
            'qty_good' => 10,
            'qty_fair' => 5,
            'qty_damaged' => 0,
            'unit' => 'unit',
            'min_qty' => 2,
            'slug' => 'thermal-printer-custom',
        ]);

        // Qty should be 15, and status should be in_stock
        $this->assertEquals(15.00, $item->qty);
        $this->assertEquals('in_stock', $item->status);
    }
}
