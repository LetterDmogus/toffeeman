<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\MenuItem;
use App\Models\Package;
use App\Models\User;
use Database\Seeders\RoleAndPositionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageTest extends TestCase
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

    public function test_can_sync_menu_items_and_inventory_items_in_package(): void
    {
        $package = Package::create([
            'name' => 'Super Bundle',
            'slug' => 'super-bundle',
            'price' => 100000,
            'status' => 'active',
        ]);

        $category = Category::create([
            'name' => 'Foods',
            'slug' => 'foods',
        ]);

        $menuItem = MenuItem::create([
            'name' => 'Burger',
            'slug' => 'burger',
            'category_id' => $category->id,
            'price' => 50000,
            'status' => 'available',
        ]);

        $invCategory = InventoryCategory::create([
            'name' => 'Merchandise',
            'slug' => 'merchandise',
        ]);

        $invItem = InventoryItem::create([
            'name' => 'T-Shirt Cool',
            'slug' => 't-shirt-cool',
            'inventory_category_id' => $invCategory->id,
            'price' => 75000,
            'qty_good' => 10,
            'unit' => 'pcs',
            'min_qty' => 2,
        ]);

        $response = $this->actingAs($this->admin)->postJson(route('api.packages.items.sync', $package->id), [
            'items' => [
                [
                    'menu_item_id' => $menuItem->id,
                    'inventory_item_id' => null,
                    'qty' => 2,
                    'notes' => 'Extra hot sauce',
                ],
                [
                    'menu_item_id' => null,
                    'inventory_item_id' => $invItem->id,
                    'qty' => 1,
                    'notes' => 'Size L',
                ],
            ],
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('package_items', [
            'package_id' => $package->id,
            'menu_item_id' => $menuItem->id,
            'inventory_item_id' => null,
            'qty' => 2,
        ]);

        $this->assertDatabaseHas('package_items', [
            'package_id' => $package->id,
            'menu_item_id' => null,
            'inventory_item_id' => $invItem->id,
            'qty' => 1,
        ]);
    }
}
