<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\User;
use Database\Seeders\RoleAndPositionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IngredientTest extends TestCase
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

    public function test_can_create_ingredient_and_manage_batches(): void
    {
        $category = IngredientCategory::create([
            'name' => 'Dairy Products',
            'slug' => 'dairy-products',
        ]);

        $response = $this->actingAs($this->admin)->postJson(route('api.ingredients.store'), [
            'name' => 'Milk 1L',
            'sku' => 'ING-DAI-MILK-01',
            'ingredient_category_id' => $category->id,
            'price' => 15000,
            'qty' => 0.00,
            'unit' => 'bottle',
            'min_qty' => 5.00,
            'storage_temperature' => 'Dingin (4°C)',
            'small_unit_qty' => 12000.00, // 12000 ml
            'description' => 'Susu segar 1 Liter.',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('qty', '0.00');

        $ingredientId = $response->json('id');

        $this->assertDatabaseHas('ingredients', [
            'id' => $ingredientId,
            'name' => 'Milk 1L',
            'created_by' => $this->admin->id,
        ]);

        // Add a batch via API
        $batchResponse = $this->actingAs($this->admin)->postJson(route('api.ingredient-batches.store'), [
            'ingredient_id' => $ingredientId,
            'batch_number' => 'BCH-MLK-001',
            'qty' => 12.00,
            'expiration_date' => '2026-07-01',
        ]);

        $batchResponse->assertStatus(201);
        $this->assertDatabaseHas('ingredient_batches', [
            'ingredient_id' => $ingredientId,
            'batch_number' => 'BCH-MLK-001',
            'qty' => 12.00,
            'expiration_date' => '2026-07-01 00:00:00',
            'created_by' => $this->admin->id,
        ]);

        // Verify ingredient qty was recalculated to 12.00
        $this->assertEquals(12.00, Ingredient::find($ingredientId)->qty);
    }
}
