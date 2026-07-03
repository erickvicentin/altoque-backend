<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ProfessionalProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfessionalTest extends TestCase
{
    use RefreshDatabase;

    protected User $client;
    protected User $barber;
    protected User $pilatesInstructor;

    protected function setUp(): void
    {
        parent::setUp();

        // Create client user
        $this->client = User::factory()->create([
            'role' => 'client'
        ]);

        // Create barber professional
        $this->barber = User::factory()->create([
            'name' => 'Juan',
            'last_name' => 'Pérez',
            'role' => 'professional'
        ]);
        ProfessionalProfile::create([
            'user_id' => $this->barber->id,
            'profession' => 'Barberia',
            'has_physical_shop' => true,
            'shop_address' => 'Av. San Martin 123',
            'working_days' => ['Lunes', 'Martes']
        ]);

        // Create pilates professional
        $this->pilatesInstructor = User::factory()->create([
            'name' => 'Ana',
            'last_name' => 'Silva',
            'role' => 'professional'
        ]);
        ProfessionalProfile::create([
            'user_id' => $this->pilatesInstructor->id,
            'profession' => 'Pilates',
            'has_physical_shop' => false,
            'working_days' => ['Miercoles', 'Jueves']
        ]);
    }

    public function test_can_list_all_professionals()
    {
        $response = $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/professionals');

        $response->assertStatus(200)
            ->assertJsonCount(2)
            ->assertJsonFragment([
                'name' => 'Juan Pérez',
                'category' => 'Barbería',
                'isShop' => true
            ])
            ->assertJsonFragment([
                'name' => 'Ana Silva',
                'category' => 'Pilates',
                'isShop' => false
            ]);
    }

    public function test_can_filter_professionals_by_profession_accented()
    {
        $response = $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/professionals?profession=' . urlencode('Barbería'));

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment([
                'name' => 'Juan Pérez',
                'category' => 'Barbería'
            ]);
    }

    public function test_can_filter_professionals_by_profession_unaccented()
    {
        $response = $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/professionals?profession=Barberia');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment([
                'name' => 'Juan Pérez',
                'category' => 'Barbería'
            ]);
    }

    public function test_can_search_professionals_by_name()
    {
        $response = $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/professionals?search=Ana');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment([
                'name' => 'Ana Silva'
            ]);
    }

    public function test_returns_empty_when_no_professionals_for_category()
    {
        $response = $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/professionals?profession=Electricidad');

        $response->assertStatus(200)
            ->assertJsonCount(0);
    }
}
