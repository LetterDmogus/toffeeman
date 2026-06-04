<?php

namespace Tests\Feature;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\User;
use Database\Seeders\RoleAndPositionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InventoryMovementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected InventoryItem $item;

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

        $category = InventoryCategory::create([
            'name' => 'Bahan Makanan',
            'slug' => 'bahan-makanan',
        ]);

        $this->item = InventoryItem::create([
            'name' => 'Beras Pandan Wangi',
            'slug' => 'beras-pandan-wangi',
            'sku' => 'BRS-001',
            'inventory_category_id' => $category->id,
            'qty_good' => 10.00,
            'qty_fair' => 5.00,
            'qty_damaged' => 1.00,
            'unit' => 'kg',
            'price' => 15000,
        ]);
    }

    public function test_can_create_incoming_inventory_entry(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->admin)->postJson(route('api.inventory-ins.store'), [
            'reference_number' => 'PO-001',
            'date' => '2026-06-04',
            'notes' => 'Restock beras',
            'details' => [
                [
                    'inventory_item_id' => $this->item->id,
                    'qty_good' => 20,
                    'qty_fair' => 0,
                    'qty_damaged' => 0,
                    'price' => 14500,
                ],
            ],
            'photos' => [
                UploadedFile::fake()->image('invoice.jpg'),
            ],
        ]);

        $response->assertStatus(201);

        // Check stock updated: qty_good: 10 + 20 = 30
        $this->item->refresh();
        $this->assertEquals(30.00, $this->item->qty_good);
        $this->assertEquals(5.00, $this->item->qty_fair);
        $this->assertEquals(1.00, $this->item->qty_damaged);

        // Check Database has record
        $this->assertDatabaseHas('inventory_ins', [
            'reference_number' => 'PO-001',
            'created_by' => $this->admin->id,
        ]);

        $this->assertDatabaseHas('inventory_in_details', [
            'inventory_item_id' => $this->item->id,
            'qty_good' => 20.00,
        ]);

        $this->assertDatabaseHas('inventory_attachments', [
            'attachable_type' => 'App\Models\InventoryIn',
        ]);

        $this->assertDatabaseHas('transactions', [
            'type' => 'expense',
            'category' => 'inventory_purchase',
            'amount' => 290000.00,
            'description' => 'Pembelian inventaris (PO-001)',
        ]);
    }

    public function test_can_create_outgoing_inventory_entry(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->admin)->postJson(route('api.inventory-outs.store'), [
            'reference_number' => 'OUT-001',
            'date' => '2026-06-04',
            'notes' => 'Beras rusak dibuang',
            'details' => [
                [
                    'inventory_item_id' => $this->item->id,
                    'qty_good' => 0,
                    'qty_fair' => 2,
                    'qty_damaged' => 1,
                ],
            ],
            'photos' => [
                UploadedFile::fake()->image('proof.jpg'),
            ],
        ]);

        $response->assertStatus(201);

        // Check stock updated:
        // qty_fair: 5 - 2 = 3
        // qty_damaged: 1 - 1 = 0
        $this->item->refresh();
        $this->assertEquals(10.00, $this->item->qty_good);
        $this->assertEquals(3.00, $this->item->qty_fair);
        $this->assertEquals(0.00, $this->item->qty_damaged);

        // Check Database has record
        $this->assertDatabaseHas('inventory_outs', [
            'reference_number' => 'OUT-001',
            'created_by' => $this->admin->id,
        ]);

        $this->assertDatabaseHas('inventory_out_details', [
            'inventory_item_id' => $this->item->id,
            'qty_fair' => 2.00,
            'qty_damaged' => 1.00,
        ]);

        $this->assertDatabaseHas('inventory_attachments', [
            'attachable_type' => 'App\Models\InventoryOut',
        ]);
    }

    public function test_can_create_inventory_opname_entry(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->admin)->postJson(route('api.inventory-opnames.store'), [
            'date' => '2026-06-04',
            'notes' => 'Opname beras rutin',
            'details' => [
                [
                    'inventory_item_id' => $this->item->id,
                    'qty_good_physical' => 8.00,
                    'qty_fair_physical' => 4.00,
                    'qty_damaged_physical' => 0.00,
                ],
            ],
            'photos' => [
                UploadedFile::fake()->image('opname.jpg'),
            ],
        ]);

        $response->assertStatus(201);

        // Check stock overridden:
        $this->item->refresh();
        $this->assertEquals(8.00, $this->item->qty_good);
        $this->assertEquals(4.00, $this->item->qty_fair);
        $this->assertEquals(0.00, $this->item->qty_damaged);

        // Check details record system (original) value vs physical value
        $this->assertDatabaseHas('inventory_opname_details', [
            'inventory_item_id' => $this->item->id,
            'qty_good_system' => 10.00, // system had 10 before
            'qty_good_physical' => 8.00, // physical count is 8
            'qty_fair_system' => 5.00,
            'qty_fair_physical' => 4.00,
            'qty_damaged_system' => 1.00,
            'qty_damaged_physical' => 0.00,
        ]);
    }
}
