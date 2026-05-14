<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\PengajuanController;
use App\Http\Controllers\Api\AdminRtController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\SawController;
use App\Http\Controllers\Api\KriteriaController;
use App\Http\Controllers\Api\LogController;
use App\Http\Controllers\Api\WargaController;
use App\Http\Controllers\Api\RtController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ================= AUTENTIKASI PENGGUNA =================
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// ================= JALUR MESIN PYTHON =================
Route::get('/saw/dataset', [SawController::class, 'siapkanDataUntukPython']);

// ================= ZONA WAJIB LOGIN (SANCTUM PROTECTED) =================
Route::middleware('auth:sanctum')->group(function () {


    // ==========================================
    // RUTE WARGA
    // ==========================================
    Route::post('/warga/pengajuan', [WargaController::class, 'storePengajuan']);
    Route::get('/warga/status', [WargaController::class, 'getStatus']);

    // ==========================================
    // RUTE ADMIN
    // ==========================================
    Route::get('/admin/users', [AdminController::class, 'getUsers']);
    Route::post('/admin/users', [AdminController::class, 'storeUser']);
    Route::put('/admin/users/{id}', [AdminController::class, 'updateUser']);
    Route::delete('/admin/users/{id}', [AdminController::class, 'deleteUser']);
    
    Route::get('/admin/pengajuan', [AdminController::class, 'getPengajuan']);
    Route::put('/admin/pengajuan/{id}/status', [AdminController::class, 'updateStatus']);
    Route::put('/admin/pengajuan/{id}/kuesioner', [AdminController::class, 'updateKuesioner']);
    Route::delete('/admin/pengajuan/{id}', [AdminController::class, 'deletePengajuan']);
    Route::post('/admin/pengajuan/import', [AdminController::class, 'importCSV']);
    Route::get('/admin/users', [AdminRtController::class, 'getUsers']);
    Route::post('/admin/users', [AdminRtController::class, 'storeUser']);     
    Route::put('/admin/users/{id}', [AdminRtController::class, 'updateUser']); 
    Route::delete('/admin/users/{id}', [AdminRtController::class, 'deleteUser']);
    // --- MANAJEMEN AKUN STAFF & WILAYAH (ADMIN) ---
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);

    // ==========================================
    // RUTE RT (Verifikasi & Chat)
    // ==========================================
    Route::get('/rt/pengajuan/{id}/pesan', [RtController::class, 'getPesan']);
    Route::post('/rt/pengajuan/{id}/pesan', [RtController::class, 'storePesan']);

    // ==========================================
    // RUTE SPK SAW (Perhitungan)
    // ==========================================
    Route::get('/saw/hitung', [SawController::class, 'hitungRanking']);
    
    // --- LOGOUT ---
    Route::post('/logout', [AuthController::class, 'logout']);

    // --- MANAJEMEN DATA WARGA MENTAH (ADMIN / RT) ---
    Route::get('/warga', [WargaController::class, 'index']);
    Route::post('/warga', [WargaController::class, 'store']);
    Route::put('/warga/{id}', [WargaController::class, 'update']);
    Route::delete('/warga/{id}', [WargaController::class, 'destroy']);
    Route::post('/warga/pengajuan', [WargaController::class, 'store']);
    Route::get('/warga/status', [WargaController::class, 'getStatus']);
    Route::get('/warga/pengajuan/status', [WargaController::class, 'cekStatus']);
    Route::post('/warga/pengajuan', [WargaController::class, 'kirimPengajuan']);

    // --- MANAJEMEN KRITERIA SAW (ADMIN) ---
    Route::get('/kriteria', [KriteriaController::class, 'index']);
    Route::put('/kriteria/{id}', [KriteriaController::class, 'update']);

    // --- EKSEKUSI PERHITUNGAN SAW ---
    Route::get('/saw/hitung', [SawController::class, 'hitungRanking']);

    // --- MANAJEMEN PENGAJUAN / KUESIONER (ADMIN / RT / RW) ---
    Route::get('/admin/pengajuan', [AdminRtController::class, 'getDaftarPengajuan']);
    Route::put('/admin/pengajuan/{id}/status', [AdminRtController::class, 'ubahStatusPengajuan']);
    Route::put('/admin/pengajuan/{id}/kuesioner', [AdminRtController::class, 'updateKuesioner']);
    Route::delete('/admin/pengajuan/{id}', [AdminRtController::class, 'destroyPengajuan']);
    Route::post('/admin/pengajuan/import', [AdminRtController::class, 'importCsv']);
    Route::get('/admin/fix-data', [AdminRtController::class, 'fixDataMentah']);

    // --- RIWAYAT AKTIVITAS (LOGS) ---
    Route::get('/logs', [LogController::class, 'index']);

    // Rute Global untuk Chat (Bisa diakses Warga maupun RT)
    Route::get('/chat/{id}', [ChatController::class, 'getPesan']);
    Route::post('/chat/{id}', [ChatController::class, 'storePesan']);
});

// Rute untuk testing perbaikan data mentah (Bisa diakses tanpa login, tapi sebaiknya hanya untuk admin)
Route::get('/admin/fix-data', [AdminRtController::class, 'fixDataMentah']);
