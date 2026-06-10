<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AdminRtController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\SawController;
use App\Http\Controllers\Api\KriteriaController;
use App\Http\Controllers\Api\LogController;
use App\Http\Controllers\Api\WargaController;

/*
|--------------------------------------------------------------------------
| API Routes — disesuaikan persis dengan method yang ada di setiap controller
|--------------------------------------------------------------------------
*/

// ── PUBLIK ────────────────────────────────────────────────────────────────
Route::post('/login',    [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Dataset SAW publik (debug/export)
Route::get('/saw/dataset', [SawController::class, 'getDataset']);

// ── WAJIB LOGIN ───────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // --- Auth ---
    Route::post('/logout', [AuthController::class, 'logout']);

    // ── Warga: kirim & cek status pengajuan ──────────────────────────────
    // WargaController::kirimPengajuan()
    Route::post('/warga/pengajuan', [WargaController::class, 'kirimPengajuan']);
    // WargaController::getStatus() — dipakai WargaDashboard (/warga/status)
    Route::get('/warga/status',     [WargaController::class, 'getStatus']);
    // alias cekStatus → getStatus (method sama)
    Route::get('/warga/pengajuan/status', [WargaController::class, 'getStatus']);

    // ── RT: input kuesioner atas nama warga ──────────────────────────────
    // WargaController::store() — dipakai RtDashboard submit kuesioner
    Route::post('/warga/pengajuan-rt', [WargaController::class, 'store']);

    // ── Manajemen Akun (UserController — dipakai AdminDashboard) ─────────
    Route::get('/users',         [UserController::class, 'index']);
    Route::post('/users',        [UserController::class, 'store']);
    Route::put('/users/{id}',    [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);

    // ── Manajemen Pengajuan & Kuesioner (AdminRtController) ───────────────
    Route::get('/admin/pengajuan',                [AdminRtController::class, 'getDaftarPengajuan']);
    Route::put('/admin/pengajuan/{id}/status',    [AdminRtController::class, 'ubahStatusPengajuan']);
    Route::put('/admin/pengajuan/{id}/kuesioner', [AdminRtController::class, 'updateKuesioner']);
    Route::delete('/admin/pengajuan/{id}',        [AdminRtController::class, 'destroyPengajuan']);
    Route::post('/admin/pengajuan/import',        [AdminRtController::class, 'importCsv']);

    // ── Manajemen Akun via panel admin (AdminRtController) ────────────────
    // Dipakai RtDashboard untuk list & tambah warga
    Route::get('/admin/users',         [AdminRtController::class, 'getUsers']);
    Route::post('/admin/users',        [AdminRtController::class, 'storeUser']);
    Route::put('/admin/users/{id}',    [AdminRtController::class, 'updateUser']);
    Route::delete('/admin/users/{id}', [AdminRtController::class, 'deleteUser']);

    // ── Kriteria & Sub-Kriteria (KriteriaController) ──────────────────────
    Route::get('/kriteria',                    [KriteriaController::class, 'index']);
    Route::put('/kriteria/{id}',               [KriteriaController::class, 'update']);
    Route::get('/kriteria/{id}/subkriteria',   [KriteriaController::class, 'getSubKriteria']);
    Route::put('/admin/subkriteria/{id}',      [KriteriaController::class, 'updateSubKriteria']);

    // ── SPK SAW ──────────────────────────────────────────────────────────
    // Response: { success, source:"php", total, data:[{id_pengajuan, nama_warga, alamat, rt, skor_saw, ranking}] }
    Route::get('/saw/hitung', [SawController::class, 'hitungRanking']);

    // ── Perbaikan data mentah + generate matriks (Admin utility) ─────────
    Route::get('/admin/fix-data', [AdminRtController::class, 'fixDataMentah']);

    // ── Activity Log ─────────────────────────────────────────────────────
    // LogController::index() → tabel activity_logs { id, user_name, role, aksi, created_at }
    // PENTING: frontend fetch ke /logs, BUKAN /admin/activity-logs
    Route::get('/logs', [LogController::class, 'index']);

    // ── Chat (Warga ↔ RT) ─────────────────────────────────────────────────
    Route::get('/chat/{id}',  [ChatController::class, 'getPesan']);
    Route::post('/chat/{id}', [ChatController::class, 'storePesan']);
});