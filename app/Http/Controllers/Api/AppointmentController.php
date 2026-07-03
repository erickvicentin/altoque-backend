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

        if ($user->role === 'client') {
            $appointments = Appointment::where('client_id', $user->id)
                ->with(['professionalProfile.user', 'service'])
                ->orderBy('date', 'desc')
                ->orderBy('start_time', 'desc')
                ->get();
        } else {
            $profile = $user->professionalProfile;
            if (!$profile) {
                return response()->json([]);
            }
            $appointments = Appointment::where('professional_profile_id', $profile->id)
                ->with(['client', 'service'])
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
        ]);

        $profile = ProfessionalProfile::findOrFail($request->professional_profile_id);
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
            'client_id' => $request->user()->id,
            'service_id' => $service->id,
            'date' => $request->date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => 'pending',
            'notes' => $request->notes,
        ]);

        return response()->json([
            'message' => 'Turno reservado exitosamente y en espera de confirmación.',
            'appointment' => $appointment
        ], 201);
    }
}
