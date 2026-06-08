<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Promo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PromoControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    private function createMenuItem(): MenuItem
    {
        $category = Category::firstOrCreate(
            ['slug' => 'test-cat'],
            ['name' => 'Test Category', 'description' => 'Test'],
        );

        return MenuItem::create([
            'category_id' => $category->id,
            'name' => 'Test Item '.fake()->unique()->word(),
            'price' => 20000,
            'is_available' => true,
        ]);
    }

    private function validPromoPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Diskon Test 10%',
            'code' => 'TEST10',
            'description' => 'Promo test',
            'condition_type' => 'min_amount',
            'condition_value' => 50000,
            'reward_type' => 'discount_percent',
            'reward_value' => 10,
            'reward_scope' => 'all',
            'is_active' => true,
        ], $overrides);
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function test_index_returns_all_promos(): void
    {
        Promo::create($this->validPromoPayload());
        Promo::create($this->validPromoPayload(['name' => 'Promo 2', 'code' => 'PROMO2']));

        $response = $this->actingAs($this->admin)
            ->getJson('/api/promos?all=true');

        $response->assertOk()->assertJsonCount(2);
    }

    public function test_index_returns_paginated_promos(): void
    {
        Promo::create($this->validPromoPayload());

        $response = $this->actingAs($this->admin)
            ->getJson('/api/promos');

        $response->assertOk()->assertJsonStructure(['data', 'total']);
    }

    public function test_index_supports_search(): void
    {
        Promo::create($this->validPromoPayload(['name' => 'Diskon Spesial', 'code' => 'DISKON']));
        Promo::create($this->validPromoPayload(['name' => 'BOGO Deal', 'code' => 'BOGO']));

        $response = $this->actingAs($this->admin)
            ->getJson('/api/promos?all=true&search=Spesial');

        $response->assertOk()->assertJsonCount(1);
    }

    public function test_index_returns_trashed_promos(): void
    {
        $promo = Promo::create($this->validPromoPayload());
        $promo->delete();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/promos?all=true&trash=true');

        $response->assertOk()->assertJsonCount(1);
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function test_store_creates_promo_with_min_amount_condition(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/promos', $this->validPromoPayload());

        $response->assertCreated()->assertJsonPath('name', 'Diskon Test 10%');
        $this->assertDatabaseHas('promos', ['code' => 'TEST10', 'condition_type' => 'min_amount']);
    }

    public function test_store_creates_promo_with_specific_item_condition_and_free_item_reward(): void
    {
        $item = $this->createMenuItem();

        $payload = $this->validPromoPayload([
            'name' => 'BOGO Test',
            'code' => 'BOGO',
            'condition_type' => 'specific_item_qty',
            'condition_value' => 1,
            'condition_menu_item_id' => $item->id,
            'reward_type' => 'free_item',
            'reward_menu_item_id' => $item->id,
            'reward_scope' => 'specific',
            'menu_item_ids' => [$item->id],
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/promos', $payload);

        $response->assertCreated();
        $this->assertDatabaseHas('promo_items', ['menu_item_id' => $item->id]);
    }

    public function test_store_fails_with_missing_name(): void
    {
        $payload = $this->validPromoPayload(['name' => '']);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/promos', $payload);

        $response->assertUnprocessable()->assertJsonValidationErrors(['name']);
    }

    public function test_store_fails_with_invalid_condition_type(): void
    {
        $payload = $this->validPromoPayload(['condition_type' => 'invalid_type']);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/promos', $payload);

        $response->assertUnprocessable()->assertJsonValidationErrors(['condition_type']);
    }

    public function test_store_fails_with_duplicate_code(): void
    {
        Promo::create($this->validPromoPayload());

        $response = $this->actingAs($this->admin)
            ->postJson('/api/promos', $this->validPromoPayload());

        $response->assertUnprocessable()->assertJsonValidationErrors(['code']);
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function test_show_returns_promo_with_relationships(): void
    {
        $item = $this->createMenuItem();
        $promo = Promo::create($this->validPromoPayload([
            'condition_type' => 'specific_item_qty',
            'condition_menu_item_id' => $item->id,
        ]));

        $response = $this->actingAs($this->admin)
            ->getJson("/api/promos/{$promo->id}");

        $response->assertOk()
            ->assertJsonPath('id', $promo->id)
            ->assertJsonStructure(['condition_menu_item', 'reward_menu_item', 'menu_items']);
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function test_update_modifies_promo(): void
    {
        $promo = Promo::create($this->validPromoPayload());

        $response = $this->actingAs($this->admin)
            ->putJson("/api/promos/{$promo->id}", ['name' => 'Updated Name']);

        $response->assertOk()->assertJsonPath('name', 'Updated Name');
        $this->assertDatabaseHas('promos', ['id' => $promo->id, 'name' => 'Updated Name']);
    }

    public function test_update_syncs_menu_items_for_specific_scope(): void
    {
        $item1 = $this->createMenuItem();
        $item2 = $this->createMenuItem();
        $promo = Promo::create($this->validPromoPayload());

        $this->actingAs($this->admin)
            ->putJson("/api/promos/{$promo->id}", [
                'reward_scope' => 'specific',
                'menu_item_ids' => [$item1->id, $item2->id],
            ]);

        $this->assertDatabaseHas('promo_items', ['promo_id' => $promo->id, 'menu_item_id' => $item1->id]);
        $this->assertDatabaseHas('promo_items', ['promo_id' => $promo->id, 'menu_item_id' => $item2->id]);
    }

    public function test_update_detaches_menu_items_when_scope_changes_to_all(): void
    {
        $item = $this->createMenuItem();
        $promo = Promo::create($this->validPromoPayload(['reward_scope' => 'specific']));
        $promo->menuItems()->attach($item->id);

        $this->actingAs($this->admin)
            ->putJson("/api/promos/{$promo->id}", ['reward_scope' => 'all']);

        $this->assertDatabaseMissing('promo_items', ['promo_id' => $promo->id]);
    }

    // ─── Delete / Restore ─────────────────────────────────────────────────────

    public function test_destroy_soft_deletes_promo(): void
    {
        $promo = Promo::create($this->validPromoPayload());

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/promos/{$promo->id}");

        $response->assertOk();
        $this->assertSoftDeleted('promos', ['id' => $promo->id]);
    }

    public function test_restore_recovers_soft_deleted_promo(): void
    {
        $promo = Promo::create($this->validPromoPayload());
        $promo->delete();

        $response = $this->actingAs($this->admin)
            ->patchJson("/api/promos/{$promo->id}/restore");

        $response->assertOk();
        $this->assertDatabaseHas('promos', ['id' => $promo->id, 'deleted_at' => null]);
    }

    public function test_force_delete_permanently_removes_promo(): void
    {
        $promo = Promo::create($this->validPromoPayload());
        $promo->delete();

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/promos/{$promo->id}/force-delete");

        $response->assertOk();
        $this->assertDatabaseMissing('promos', ['id' => $promo->id]);
    }
}
