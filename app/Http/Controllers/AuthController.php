<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Otp;
use App\Models\Role;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate(['phone_number' => 'required|string']);

        $user = User::firstOrCreate(
            ['phone_number' => $request->phone_number],
            ['role_id' => Role::where('name', 'user')->value('id')]
        );

        $otp = rand(1000, 9999);
        $expires = Carbon::now()->addMinutes(5);

        Otp::create([
            'phone_number' => $request->phone_number,
            'otp' => $otp,
            'expires_at' => $expires,
        ]);

        $this->sendWhatsappOtp($request->phone_number, $otp);

        return response()->json(['message' => 'OTP sent via WhatsApp']);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone_number' => 'required|string',
            'otp' => 'required|numeric|digits:4',
        ]);

        $otp = Otp::where('phone_number', $request->phone_number)
            ->where('otp', $request->otp)
            ->where('expires_at', '>=', now())
            ->latest()
            ->first();

        if (!$otp) {
            return response()->json(['message' => 'Invalid or expired OTP'], 422);
        }

        $user = User::where('phone_number', $request->phone_number)->first();

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
        ]);
    }

    public function logout()
    {
        auth()->logout();
        return response()->json(['message' => 'Logged out']);
    }

    public function refresh()
    {
        return response()->json([
            'access_token' => auth()->refresh(),
            'token_type' => 'Bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
        ]);
    }

    private function sendWhatsappOtp(string $phone, string $otp): bool
    {
        \Log::info("Send OTP {$otp} to WhatsApp {$phone}");
        $payload = [
            'number' => $this->formatPhoneForWhatsApp($phone),
            'message' => "SPORTVERSE: WASPADA PENIPUAN, JANGAN BERI KODE OTP INI KE SIAPAPUN BAHKAN PIHAK SPORTVERSE. KODE OTP {$otp}. Berlaku 5 menit.",
        ];

        try {
            $client = new \GuzzleHttp\Client();
            $client->post('http://localhost:3000/send-otp', [
                'json' => $payload,
            ]);
            return true;
        } catch (\Throwable $e) {
            \Log::error("Gagal kirim OTP via WA: " . $e->getMessage());
            return false;
        }
    }

    private function formatPhoneForWhatsApp(string $phone): string
    {
        // Ganti 08xxx → 628xxx
        $phone = preg_replace('/^0/', '62', $phone); // lock masih di Indonesia
        return $phone;
    }
}
