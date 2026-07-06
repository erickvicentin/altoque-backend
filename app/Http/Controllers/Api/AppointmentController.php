<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    /**
     * Display a listing of appointments for the authenticated user.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Lazy update: Mark past accepted appointments as completed
        $now = now();
        $todayDate = $now->toDateString();
        $currentTime = $now->toTimeString();

        Appointment::where('status', 'accepted')
            ->where(function ($query) use ($todayDate, $currentTime) {
                $query->where('date', '<', $todayDate)
                      ->orWhere(function ($q) use ($todayDate, $currentTime) {
                          $q->where('date', $todayDate)
                            ->where('end_time', '<', $currentTime);
                      });
            })
            ->update(['status' => 'completed']);

        if ($user->role === 'client') {
            $appointments = Appointment::where('client_id', $user->id)
                ->with(['professionalProfile.user', 'service', 'address'])
                ->orderBy('date', 'desc')
                ->orderBy('start_time', 'desc')
                ->get();
        } else {
            $profile = $user->professionalProfile;
            if (!$profile) {
                return response()->json([]);
            }
            $appointments = Appointment::where('professional_profile_id', $profile->id)
                ->with(['client', 'service', 'address'])
                ->orderBy('date', 'desc')
                ->orderBy('start_time', 'desc')
                ->get();
        }

        return response()->json($appointments);
    }

    /**
     * Get busy slots for a professional on a given date.
     */
    public function getBusySlots(Request $request, $profileId)
    {
        $request->validate([
            'date' => 'required|date_format:Y-m-d',
        ]);

        $date = $request->query('date');

        $appointments = Appointment::where('professional_profile_id', $profileId)
            ->where('date', $date)
            ->whereIn('status', ['pending', 'accepted', 'blocked'])
            ->orderBy('start_time', 'asc')
            ->get(['start_time', 'end_time', 'status']);

        // Format times to HH:MM for easier consumption
        $formatted = $appointments->map(function ($app) {
            return [
                'start_time' => substr($app->start_time, 0, 5),
                'end_time' => substr($app->end_time, 0, 5),
                'status' => $app->status,
            ];
        });

        return response()->json($formatted);
    }

    /**
     * Store a newly created appointment.
     */
    public function store(Request $request)
    {
        $request->validate([
            'professional_profile_id' => 'required|exists:professional_profiles,id',
            'service_id' => 'required|exists:services,id',
            'date' => 'required|date_format:Y-m-d',
            'start_time' => 'required|date_format:H:i',
            'notes' => 'nullable|string',
            'address_id' => 'nullable|exists:addresses,id',
        ]);

        $profile = ProfessionalProfile::findOrFail($request->professional_profile_id);
        $user = $request->user();
        $isProfessional = $user->role === 'professional';

        if (!$isProfessional && !$profile->has_physical_shop) {
            $request->validate([
                'address_id' => 'required|exists:addresses,id',
            ]);

            $addressExists = $request->user()->addresses()->where('id', $request->address_id)->exists();
            if (!$addressExists) {
                return response()->json([
                    'message' => 'La dirección seleccionada no es válida para este usuario.'
                ], 422);
            }
        }

        $service = Service::findOrFail($request->service_id);

        $startTime = $request->start_time;
        $duration = $service->duration_minutes;
        $endTime = date('H:i', strtotime($startTime . " +{$duration} minutes"));

        // Determine weekday in Spanish
        $dayOfWeekMap = [
            0 => 'Domingo',
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
        ];
        $timestamp = strtotime($request->date);
        $dayNum = date('w', $timestamp);
        $dayName = $dayOfWeekMap[$dayNum];

        // Verify if it is a working day
        $workingDays = $profile->working_days ?: [];
        $isWorking = false;

        $normalize = function ($str) {
            $str = strtolower(trim($str));
            $accents = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','ü'=>'u','Ü'=>'U','ñ'=>'n','Ñ'=>'N'];
            return strtr($str, $accents);
        };
        $normDayName = $normalize($dayName);

        if (is_array($workingDays)) {
            if (isset($workingDays[$dayName])) {
                $isWorking = isset($workingDays[$dayName]['is_active']) ? (bool)$workingDays[$dayName]['is_active'] : false;
            } else {
                foreach ($workingDays as $workDay) {
                    if (is_string($workDay) && $normalize($workDay) === $normDayName) {
                        $isWorking = true;
                        break;
                    }
                }
            }
        }

        if (!$isWorking) {
            return response()->json([
                'message' => 'El profesional no atiende en el día de la semana seleccionado.'
            ], 422);
        }

        // Verify working hours
        $openTime1 = $profile->open_time_1 ?: '08:00';
        $closeTime1 = $profile->close_time_1 ?: '12:00';
        $hasSecond = (bool)($profile->has_second_range ?: false);
        $openTime2 = $profile->open_time_2 ?: '15:30';
        $closeTime2 = $profile->close_time_2 ?: '21:00';

        if (is_array($workingDays) && isset($workingDays[$dayName]) && isset($workingDays[$dayName]['is_active'])) {
            $daySchedule = $workingDays[$dayName];
            $openTime1 = $daySchedule['open_time_1'] ?? $openTime1;
            $closeTime1 = $daySchedule['close_time_1'] ?? $closeTime1;
            $hasSecond = (bool)($daySchedule['has_second_range'] ?? $hasSecond);
            $openTime2 = $daySchedule['open_time_2'] ?? $openTime2;
            $closeTime2 = $daySchedule['close_time_2'] ?? $closeTime2;
        }

        $timeToMin = function ($timeStr) {
            $parts = explode(':', $timeStr);
            return (int)$parts[0] * 60 + (int)$parts[1];
        };

        $startMin = $timeToMin($startTime);
        $endMin = $timeToMin($endTime);

        $openMin1 = $timeToMin($openTime1);
        $closeMin1 = $timeToMin($closeTime1);
        $openMin2 = $timeToMin($openTime2);
        $closeMin2 = $timeToMin($closeTime2);

        $inRange1 = ($startMin >= $openMin1 && $endMin <= $closeMin1);
        $inRange2 = $hasSecond && ($startMin >= $openMin2 && $endMin <= $closeMin2);

        if (!$inRange1 && !$inRange2) {
            return response()->json([
                'message' => 'El horario seleccionado está fuera del horario de atención del profesional.'
            ], 422);
        }

        // Verify solapamiento / overlap
        $overlap = Appointment::where('professional_profile_id', $profile->id)
            ->where('date', $request->date)
            ->whereIn('status', ['pending', 'accepted', 'blocked'])
            ->where(function ($query) use ($startTime, $endTime) {
                $query->where('start_time', '<', $endTime)
                      ->where('end_time', '>', $startTime);
            })
            ->exists();

        if ($overlap) {
            return response()->json([
                'message' => 'El horario seleccionado se solapa con un turno ya reservado o pendiente.'
            ], 422);
        }

        // Store
        $appointment = Appointment::create([
            'professional_profile_id' => $profile->id,
            'client_id' => $isProfessional ? null : $user->id,
            'service_id' => $service->id,
            'address_id' => (!$isProfessional && !$profile->has_physical_shop) ? $request->address_id : null,
            'date' => $request->date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => $isProfessional ? 'blocked' : 'pending',
            'notes' => $request->notes,
        ]);

        return response()->json([
            'message' => $isProfessional ? 'Slot bloqueado exitosamente.' : 'Turno reservado exitosamente y en espera de confirmación.',
            'appointment' => $appointment
        ], 201);
    }

    /**
     * Update the status of an appointment.
     */
    public function updateStatus(Request $request, Appointment $appointment)
    {
        $user = $request->user();

        // Check if the user is the professional associated with the appointment
        $profile = $user->professionalProfile;
        if (!$profile || $appointment->professional_profile_id !== $profile->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        // Validate the request status
        $request->validate([
            'status' => 'required|string|in:accepted,rejected,cancelled',
        ]);

        // business rule: only allow cancel if status is currently accepted or blocked
        if ($request->status === 'cancelled' && !in_array($appointment->status, ['accepted', 'blocked'])) {
            return response()->json([
                'message' => 'Solo se pueden cancelar turnos que ya hayan sido confirmados o bloqueados.'
            ], 422);
        }

        // business rule: only allow accept/reject if status is currently pending
        if (in_array($request->status, ['accepted', 'rejected']) && $appointment->status !== 'pending') {
            return response()->json([
                'message' => 'Solo se pueden aprobar o rechazar turnos en estado pendiente.'
            ], 422);
        }

        $appointment->update([
            'status' => $request->status,
        ]);

        return response()->json([
            'message' => 'Estado del turno actualizado con éxito.',
            'appointment' => $appointment->load(['client', 'service']),
        ]);
    }

    /**
     * Display the specified appointment.
     */
    public function show(Appointment $appointment)
    {
        // Lazy update: check if this individual appointment is accepted and in the past
        if ($appointment->status === 'accepted') {
            $now = now();
            $todayDate = $now->toDateString();
            $currentTime = $now->toTimeString();

            $isPastDate = $appointment->date < $todayDate;
            $isPastTimeToday = ($appointment->date === $todayDate && $appointment->end_time < $currentTime);
            
            if ($isPastDate || $isPastTimeToday) {
                $appointment->update(['status' => 'completed']);
            }
        }

        $appointment->load(['client', 'service', 'professionalProfile.user', 'address']);
        return response()->json($appointment);
    }

    /**
     * Cancel an appointment (Client).
     */
    public function cancel(Request $request, Appointment $appointment)
    {
        $user = $request->user();

        // Check if the user is the client who booked the appointment
        if ($appointment->client_id !== $user->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        // Check if the appointment is already cancelled
        if ($appointment->status === 'cancelled') {
            return response()->json(['message' => 'El turno ya está cancelado.'], 400);
        }

        // Verify if there is at least 1 hour remaining before the appointment start time
        $appointmentDateTime = strtotime("{$appointment->date} {$appointment->start_time}");
        $currentDateTime = time();

        $differenceInSeconds = $appointmentDateTime - $currentDateTime;
        $differenceInHours = $differenceInSeconds / 3600;

        if ($differenceInHours < 1) {
            return response()->json([
                'message' => 'No se puede cancelar el turno faltando menos de 1 hora.'
            ], 422);
        }

        $appointment->update([
            'status' => 'cancelled',
        ]);

        return response()->json([
            'message' => 'Turno cancelado exitosamente.',
            'appointment' => $appointment->load(['client', 'service', 'professionalProfile.user']),
        ]);
    }
}
