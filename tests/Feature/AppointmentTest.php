<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    protected $client;
    protected $professional;
    protected $profile;
    protected $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->create(['role' => 'client']);
        $this->professional = User::factory()->create(['role' => 'professional']);

        $this->profile = ProfessionalProfile::create([
            'user_id' => $this->professional->id,
            'profession' => 'Barberia',
            'has_physical_shop' => true,
            'shop_address' => 'Av. Sarmiento 1562',
            'open_time_1' => '08:00',
            'close_time_1' => '12:00',
            'has_second_range' => true,
            'open_time_2' => '16:00',
            'close_time_2' => '20:00',
            'working_days' => ['Lunes', 'Martes', 'Miércoles'],
        ]);

        $this->service = Service::create([
            'professional_profile_id' => $this->profile->id,
            'name' => 'Corte Masculino',
            'price' => 12000,
            'duration_minutes' => 60,
            'is_active' => true,
        ]);
    }

    public function test_client_can_book_valid_appointment()
    {
        $payload = [
            'professional_profile_id' => $this->profile->id,
            'service_id' => $this->service->id,
            'date' => '2026-07-06', // Lunes (Working day)
            'start_time' => '09:00', // Within open_time_1 range
            'notes' => 'Primer turno del día',
        ];

        $response = $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/appointments', $payload);

        $response->assertStatus(201);
        $response->assertJsonStructure(['message', 'appointment']);
        $this->assertDatabaseHas('appointments', [
            'professional_profile_id' => $this->profile->id,
            'client_id' => $this->client->id,
            'service_id' => $this->service->id,
            'date' => '2026-07-06',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'pending',
        ]);
    }

    public function test_cannot_book_appointment_on_non_working_day()
    {
        $payload = [
            'professional_profile_id' => $this->profile->id,
            'service_id' => $this->service->id,
            'date' => '2026-07-05', // Domingo (Non-working day)
            'start_time' => '09:00',
        ];

        $response = $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/appointments', $payload);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'El profesional no atiende en el día de la semana seleccionado.'
        ]);
    }

    public function test_cannot_book_appointment_outside_working_hours()
    {
        $payload = [
            'professional_profile_id' => $this->profile->id,
            'service_id' => $this->service->id,
            'date' => '2026-07-06', // Lunes
            'start_time' => '13:00', // Outside of 08:00-12:00 and 16:00-20:00 ranges
        ];

        $response = $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/appointments', $payload);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'El horario seleccionado está fuera del horario de atención del profesional.'
        ]);
    }

    public function test_cannot_book_overlapping_appointments()
    {
        // Book initial appointment: 09:00 - 10:00
        Appointment::create([
            'professional_profile_id' => $this->profile->id,
            'client_id' => $this->client->id,
            'service_id' => $this->service->id,
            'date' => '2026-07-06',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'pending',
        ]);

        // Attempt overlap case 1: Completely inside (09:15 - 10:15)
        $payload = [
            'professional_profile_id' => $this->profile->id,
            'service_id' => $this->service->id,
            'date' => '2026-07-06',
            'start_time' => '09:15',
        ];

        $response = $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/appointments', $payload);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'El horario seleccionado se solapa con un turno ya reservado o pendiente.'
        ]);
    }

    public function test_can_query_busy_slots()
    {
        // Create a couple of bookings
        Appointment::create([
            'professional_profile_id' => $this->profile->id,
            'client_id' => $this->client->id,
            'service_id' => $this->service->id,
            'date' => '2026-07-06',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'accepted',
        ]);

        Appointment::create([
            'professional_profile_id' => $this->profile->id,
            'client_id' => $this->client->id,
            'service_id' => $this->service->id,
            'date' => '2026-07-06',
            'start_time' => '17:00',
            'end_time' => '18:00',
            'status' => 'pending',
        ]);

        // Query busy slots
        $response = $this->actingAs($this->client, 'sanctum')
            ->getJson("/api/professionals/{$this->profile->id}/busy-slots?date=2026-07-06");

        $response->assertStatus(200);
        $response->assertJsonCount(2);
        $response->assertJsonFragment([
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'accepted',
        ]);
        $response->assertJsonFragment([
            'start_time' => '17:00',
            'end_time' => '18:00',
            'status' => 'pending',
        ]);
    }

    public function test_client_can_list_their_appointments()
    {
        Appointment::create([
            'professional_profile_id' => $this->profile->id,
            'client_id' => $this->client->id,
            'service_id' => $this->service->id,
            'date' => '2026-07-06',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'accepted',
        ]);

        $response = $this->actingAs($this->client, 'sanctum')
            ->getJson("/api/appointments");

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment([
            'start_time' => '09:00',
            'status' => 'accepted',
        ]);
    }

    public function test_professional_can_accept_appointment()
    {
        $appointment = Appointment::create([
            'professional_profile_id' => $this->profile->id,
            'client_id' => $this->client->id,
            'service_id' => $this->service->id,
            'date' => '2026-07-06',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->professional, 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}/status", [
                'status' => 'accepted',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('appointment.status', 'accepted');
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'accepted',
        ]);
    }

    public function test_professional_can_reject_appointment()
    {
        $appointment = Appointment::create([
            'professional_profile_id' => $this->profile->id,
            'client_id' => $this->client->id,
            'service_id' => $this->service->id,
            'date' => '2026-07-06',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->professional, 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}/status", [
                'status' => 'rejected',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('appointment.status', 'rejected');
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'rejected',
        ]);
    }

    public function test_unauthorized_user_cannot_update_appointment_status()
    {
        $appointment = Appointment::create([
            'professional_profile_id' => $this->profile->id,
            'client_id' => $this->client->id,
            'service_id' => $this->service->id,
            'date' => '2026-07-06',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'pending',
        ]);

        // A client cannot update status
        $response = $this->actingAs($this->client, 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}/status", [
                'status' => 'accepted',
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'pending',
        ]);
    }

    public function test_invalid_status_cannot_be_set()
    {
        $appointment = Appointment::create([
            'professional_profile_id' => $this->profile->id,
            'client_id' => $this->client->id,
            'service_id' => $this->service->id,
            'date' => '2026-07-06',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->professional, 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}/status", [
                'status' => 'invalid_status',
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'pending',
        ]);
    }

    public function test_client_can_view_appointment_details()
    {
        $appointment = Appointment::create([
            'professional_profile_id' => $this->profile->id,
            'client_id' => $this->client->id,
            'service_id' => $this->service->id,
            'date' => '2026-07-06',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->client, 'sanctum')
            ->getJson("/api/appointments/{$appointment->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure(['id', 'status', 'client', 'service', 'professional_profile']);
    }

    public function test_client_can_cancel_appointment_more_than_one_hour_before()
    {
        // Tomorrow at 10:00 (more than 1 hour away)
        $appointment = Appointment::create([
            'professional_profile_id' => $this->profile->id,
            'client_id' => $this->client->id,
            'service_id' => $this->service->id,
            'date' => date('Y-m-d', strtotime('+1 day')),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->client, 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}/cancel");

        $response->assertStatus(200);
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_client_cannot_cancel_appointment_less_than_one_hour_before()
    {
        // Today, 30 minutes from now (less than 1 hour away)
        $date = date('Y-m-d');
        $startTime = date('H:i', time() + 1800);
        $endTime = date('H:i', time() + 5400);

        $appointment = Appointment::create([
            'professional_profile_id' => $this->profile->id,
            'client_id' => $this->client->id,
            'service_id' => $this->service->id,
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->client, 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}/cancel");

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'No se puede cancelar el turno faltando menos de 1 hora.'
        ]);
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'pending',
        ]);
    }

    public function test_unauthorized_user_cannot_cancel_appointment()
    {
        $appointment = Appointment::create([
            'professional_profile_id' => $this->profile->id,
            'client_id' => $this->client->id,
            'service_id' => $this->service->id,
            'date' => date('Y-m-d', strtotime('+1 day')),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'pending',
        ]);

        $otherClient = User::factory()->create(['role' => 'client']);

        $response = $this->actingAs($otherClient, 'sanctum')
            ->patchJson("/api/appointments/{$appointment->id}/cancel");

        $response->assertStatus(403);
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'pending',
        ]);
    }

    public function test_booking_requires_address_when_professional_has_no_physical_shop()
    {
        $noShopProfile = ProfessionalProfile::create([
            'user_id' => User::factory()->create(['role' => 'professional'])->id,
            'profession' => 'Electricidad',
            'has_physical_shop' => false,
            'open_time_1' => '08:00',
            'close_time_1' => '12:00',
            'working_days' => ['Lunes', 'Martes', 'Miércoles'],
        ]);

        $service = Service::create([
            'professional_profile_id' => $noShopProfile->id,
            'name' => 'Reparación de enchufe',
            'price' => 5000,
            'duration_minutes' => 60,
            'is_active' => true,
        ]);

        // Attempting to book without address_id should fail
        $response = $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/appointments', [
                'professional_profile_id' => $noShopProfile->id,
                'service_id' => $service->id,
                'date' => '2026-07-06',
                'start_time' => '09:00',
            ]);

        $response->assertStatus(422);

        // Create an address for the client
        $address = \App\Models\Address::create([
            'user_id' => $this->client->id,
            'address_line' => 'Calle Falsa 123',
            'alias' => 'Casa',
        ]);

        // Booking with correct address_id should succeed
        $response = $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/appointments', [
                'professional_profile_id' => $noShopProfile->id,
                'service_id' => $service->id,
                'date' => '2026-07-06',
                'start_time' => '09:00',
                'address_id' => $address->id,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('appointments', [
            'professional_profile_id' => $noShopProfile->id,
            'address_id' => $address->id,
        ]);
    }
}
