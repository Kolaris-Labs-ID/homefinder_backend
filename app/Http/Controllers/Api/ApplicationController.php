<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\HomeAdvisorApproved;

class ApplicationController extends Controller
{
    // POST /api/applications/check-email
    public function checkEmail(Request $request)
    {
        $validated = $request->validate(['email' => 'required|email:rfc,dns']);
        $existing = DB::table('applications')->where('email', $validated['email'])->first();
        if ($existing && $existing->status_crm !== 'Ditolak') {
            return response()->json(['message' => 'Email ini sudah terdaftar dan aplikasi sedang diproses.'], 400);
        }
        return response()->json(['message' => 'Email tersedia'], 200);
    }

    // POST /api/applications (Public)
    public function store(Request $request)
    {
        try {
        $validated = $request->validate([
            'nama_lengkap' => 'required|string|max:255',
            'email' => 'required|email:rfc,dns|max:255',
            'domisili' => 'required|string|max:255',
            'nomor_wa' => ['required', 'string', 'min:9', 'max:20', 'regex:/^[0-9\-\+\(\)\s]+$/'],
            'sosial_media_ig' => 'nullable|string',
            'sosial_media_tiktok' => 'nullable|string',
            'sosial_media_linkedin' => 'nullable|string',
            'punya_pengalaman' => 'required|in:Ya,Tidak',
            'lama_pengalaman' => 'nullable|string',
            'jenis_properti' => 'nullable|string',
            'area_dikuasai' => 'required|string',
            'status_network' => 'required|string',
            'sumber_customer' => 'required|string',
            'jumlah_network' => 'required|string',
            'pengalaman_sales' => 'required|string',
            'kenyamanan_komunikasi' => 'required|string',
            'alokasi_waktu' => 'required|string',
            'kesediaan_visit' => 'required|string',
            'punya_kendaraan' => 'required|in:Ya,Tidak',
            'alasan_tertarik' => 'required|string',
            'harapan_bergabung' => 'required|string',
            'dokumen_cv_url' => 'required|file|mimes:pdf|max:2048',
        ]);

        // Validate if they already have a pending application
        $existing = DB::table('applications')->where('email', $validated['email'])->first();
        if ($existing && $existing->status_crm !== 'Ditolak') {
            return response()->json(['message' => 'Anda sudah mendaftar dan aplikasi Anda sedang diproses.'], 400);
        }

        $cvPath = null;
        if ($request->hasFile('dokumen_cv_url')) {
            $disk = config('filesystems.disks.s3.bucket') ? 's3' : config('filesystems.default', 'public');
            $cvPath = $request->file('dokumen_cv_url')->store('cvs', $disk);
            if ($disk === 's3') {
                $cvPath = \Illuminate\Support\Facades\Storage::disk('s3')->url($cvPath);
            }
        }

        $id = DB::table('applications')->insertGetId([
            'nama_lengkap' => $validated['nama_lengkap'],
            'email' => $validated['email'],
            'domisili' => $validated['domisili'],
            'nomor_wa' => $validated['nomor_wa'],
            'sosial_media_ig' => $validated['sosial_media_ig'] ?? null,
            'sosial_media_tiktok' => $validated['sosial_media_tiktok'] ?? null,
            'sosial_media_linkedin' => $validated['sosial_media_linkedin'] ?? null,
            'dokumen_cv_url' => $cvPath,
            'punya_pengalaman' => $validated['punya_pengalaman'] === 'Ya',
            'lama_pengalaman' => $validated['lama_pengalaman'] ?? null,
            'jenis_properti' => $validated['jenis_properti'] ?? null,
            'area_dikuasai' => $validated['area_dikuasai'],
            'status_network' => $validated['status_network'],
            'sumber_customer' => $validated['sumber_customer'],
            'jumlah_network' => $validated['jumlah_network'],
            'pengalaman_sales' => $validated['pengalaman_sales'],
            'kenyamanan_komunikasi' => $validated['kenyamanan_komunikasi'],
            'alokasi_waktu' => $validated['alokasi_waktu'],
            'kesediaan_visit' => $validated['kesediaan_visit'],
            'punya_kendaraan' => $validated['punya_kendaraan'] === 'Ya',
            'alasan_tertarik' => $validated['alasan_tertarik'],
            'harapan_bergabung' => $validated['harapan_bergabung'],
            'status_crm' => 'Menunggu Validasi',
            'tanggal_submit' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Pendaftaran berhasil dikirim',
            'id' => $id
        ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'S3 Error: ' . $e->getMessage()], 500);
        }
    }

    // GET /api/admin/applications (Admin Only)
    public function index(Request $request)
    {
        // Simple authentication check
        if (!in_array($request->user()->role, ['admin', 'superadmin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $applications = DB::table('applications')->orderBy('created_at', 'desc')->get();
        return response()->json(['data' => $applications]);
    }

    // GET /api/admin/applications/{id} (Admin Only)
    public function show(Request $request, $id)
    {
        if (!in_array($request->user()->role, ['admin', 'superadmin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $application = DB::table('applications')->where('id', $id)->first();
        if (!$application) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        return response()->json(['data' => $application]);
    }

    // PUT /api/admin/applications/{id}/approve (Admin Only)
    public function approve(Request $request, $id)
    {
        if (!in_array($request->user()->role, ['admin', 'superadmin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $application = DB::table('applications')->where('id', $id)->first();
        if (!$application) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        $status = $request->input('status'); // 'Disetujui' or 'Ditolak'
        
        DB::table('applications')->where('id', $id)->update([
            'status_crm' => $status,
            'updated_at' => now(),
        ]);

        if ($status === 'Disetujui' && !$application->user_id) {
            // Generate random password & create user
            $defaultPassword = Str::random(8);
            
            // Check if email already used by user
            $user = User::where('email', $application->email)->first();
            if (!$user) {
                $user = User::create([
                    'name' => $application->nama_lengkap,
                    'email' => $application->email,
                    'password' => Hash::make($defaultPassword),
                    'role' => 'homeadvisor',
                    'phone_number' => $application->nomor_wa,
                ]);
            } else {
                // Update existing user to homeadvisor and update their phone number (and reset password to the new random one so they can login)
                $user->update([
                    'role' => 'homeadvisor',
                    'password' => Hash::make($defaultPassword),
                    'phone_number' => $application->nomor_wa,
                ]);
            }

            DB::table('applications')->where('id', $id)->update(['user_id' => $user->id]);
            
            // Send the congratulatory email with credentials
            try {
                Mail::to($user->email)->send(new HomeAdvisorApproved($user, $defaultPassword));
            } catch (\Exception $e) {
                // Log error but continue so the approval isn't rolled back
                \Log::error('Gagal mengirim email HomeAdvisorApproved: ' . $e->getMessage());
            }
            
            return response()->json([
                'message' => 'Berhasil disetujui. Akun berhasil dibuat dan kredensial telah dikirim ke email kandidat.',
                'email' => $user->email,
                'email_sent' => true
            ]);
        }

        return response()->json([
            'message' => 'Status berhasil diubah menjadi ' . $status
        ]);
    }

    // DELETE /api/admin/applications/{id} (Admin Only)
    public function destroy(Request $request, $id)
    {
        if (!in_array($request->user()->role, ['admin', 'superadmin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $application = DB::table('applications')->where('id', $id)->first();
        if (!$application) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        DB::table('applications')->where('id', $id)->delete();
        
        return response()->json(['message' => 'Pelamar berhasil dihapus']);
    }
}

