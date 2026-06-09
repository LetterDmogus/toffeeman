<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmployeeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create the admin role since EmployeeController assigns it
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_can_create_employee_with_new_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->postJson('/api/employees', [
            'name' => 'John Doe',
            'email' => 'johndoe@example.com',
            'phone' => '081234567890',
            'password' => 'password123',
            'salary' => 5000000,
            'status' => 'active',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'johndoe@example.com',
        ]);
        $this->assertDatabaseHas('employees', [
            'salary' => 5000000,
            'status' => 'active',
        ]);
    }

    public function test_can_create_employee_by_linking_existing_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $existingUser = User::factory()->create([
            'name' => 'Existing User',
            'email' => 'existing@example.com',
            'phone' => '0899999999',
        ]);

        $response = $this->actingAs($admin)->postJson('/api/employees', [
            'user_id' => $existingUser->id,
            'name' => 'Updated User Name', // User can change the name
            'email' => 'existing@example.com', // Keeping same email is allowed now
            'phone' => '0899999999',
            'salary' => 7500000,
            'status' => 'active',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'id' => $existingUser->id,
            'name' => 'Updated User Name',
            'email' => 'existing@example.com',
        ]);
        $this->assertDatabaseHas('employees', [
            'user_id' => $existingUser->id,
            'salary' => 7500000,
            'status' => 'active',
        ]);
    }
}
