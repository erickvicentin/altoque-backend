<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $professional;
    protected ProfessionalProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();

        // Create professional user
        $this->professional = User::factory()->create([
            'role' => 'professional'
        ]);

        // Create profile
        $this->profile = ProfessionalProfile::create([
            'user_id' => $this->professional->id,
            'profession' => 'Barberia',
            'has_physical_shop' => true,
            'shop_address' => 'Av. Siempreviva 742',
            'working_days' => ['Lunes', 'Martes']
        ]);
    }

    public function test_can_list_own_services()
    {
        // Create services
        $service1 = Service::create([
            'professional_profile_id' => $this->profile->id,
            'name' => 'Corte',
            'duration_minutes' => 30,
            'price' => 5000,
            'is_active' => true
        ]);

        $service2 = Service::create([
            'professional_profile_id' => $this->profile->id,
            'name' => 'Afeitado',
            'duration_minutes' => 15,
            'price' => 3000,
            'is_active' => false
        ]);

        $response = $this->actingAs($this->professional, 'sanctum')
            ->getJson('/api/services');

        $response->assertStatus(200)
            ->assertJsonCount(2)
            ->assertJsonFragment(['name' => 'Corte'])
            ->assertJsonFragment(['name' => 'Afeitado']);
    }

    public function test_can_create_service_with_valid_duration()
    {
        $response = $this->actingAs($this->professional, 'sanctum')
            ->postJson('/api/services', [
                'name' => 'Spa de cejas',
                'duration_minutes' => 25, // Multiple of 5
                'price' => 4500,
                'is_active' => true
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('service.name', 'Spa de cejas');

        $this->assertDatabaseHas('services', [
            'name' => 'Spa de cejas',
            'duration_minutes' => 25
        ]);
    }

    public function test_cannot_create_service_with_invalid_duration()
    {
        $response = $this->actingAs($this->professional, 'sanctum')
            ->postJson('/api/services', [
                'name' => 'Spa de cejas',
                'duration_minutes' => 23, // NOT a multiple of 5
                'price' => 4500,
                'is_active' => true
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['duration_minutes']);
    }

    public function test_can_update_service_is_active_toggle()
    {
        $service = Service::create([
            'professional_profile_id' => $this->profile->id,
            'name' => 'Corte',
            'duration_minutes' => 30,
            'price' => 5000,
            'is_active' => true
        ]);

        $response = $this->actingAs($this->professional, 'sanctum')
            ->putJson("/api/services/{$service->id}", [
                'is_active' => false
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('service.is_active', false);

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'is_active' => false
        ]);
    }

    public function test_can_delete_own_service()
    {
        $service = Service::create([
            'professional_profile_id' => $this->profile->id,
            'name' => 'Corte',
            'duration_minutes' => 30,
            'price' => 5000,
            'is_active' => true
        ]);

        $response = $this->actingAs($this->professional, 'sanctum')
            ->deleteJson("/api/services/{$service->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('services', ['id' => $service->id]);
    }

    public function test_client_can_get_only_active_services()
    {
        $client = User::factory()->create(['role' => 'client']);

        // Service 1: Active
        Service::create([
            'professional_profile_id' => $this->profile->id,
            'name' => 'Active Service',
            'duration_minutes' => 30,
            'price' => 5000,
            'is_active' => true
        ]);

        // Service 2: Inactive
        Service::create([
            'professional_profile_id' => $this->profile->id,
            'name' => 'Inactive Service',
            'duration_minutes' => 45,
            'price' => 8000,
            'is_active' => false
        ]);

        $response = $this->actingAs($client, 'sanctum')
            ->getJson("/api/professionals/{$this->profile->id}/services");

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['name' => 'Active Service'])
            ->assertJsonMissing(['name' => 'Inactive Service']);
    }
}
