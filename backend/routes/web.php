<?php

use App\Http\Controllers\AnggotaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HeaderController;
use App\Http\Controllers\KegiatanController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\PACController;
use App\Http\Controllers\PengajuanPACController;
use App\Http\Controllers\PengaturanController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AUTH
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| SPA SERVING HELPERS (shared hosting: Laravel menyajikan React build)
|--------------------------------------------------------------------------
| Prioritas: build yang sudah disalin ke public/spa.html (shared hosting),
| lalu fallback ke layout Docker/VPS.
*/
$findSpa = static function (): ?string {
    foreach ([
        public_path('spa.html'),
        '/var/www/frontend/index.html',
        base_path('../frontend/dist/index.html'),
        public_path('../../frontend/dist/index.html'),
    ] as $path) {
        if (is_file($path)) {
            return $path;
        }
    }

    return null;
};

$serveSpa = static function () use ($findSpa) {
    if ($path = $findSpa()) {
        return response()->file($path);
    }

    abort(404);
};

Route::get('/', function () use ($findSpa) {
    if (auth()->check()) {
        return redirect('/dashboard');
    }

    if ($path = $findSpa()) {
        return response()->file($path);
    }

    return view('login');
})->name('home');

Route::view('/login', 'login')
    ->name('login');

/*
|--------------------------------------------------------------------------
| HALAMAN PUBLIK SPA (React Router) — dilayani Laravel di satu domain
|--------------------------------------------------------------------------
*/
Route::get('/tentang', $serveSpa);
Route::get('/tentang/struktur-organisasi', $serveSpa);
Route::get('/struktur-organisasi', $serveSpa);
Route::get('/pengajuan-data-pac', $serveSpa);

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');

Route::get('/two-factor-challenge', [AuthController::class, 'showTwoFactorChallenge'])
    ->name('two-factor.challenge');

Route::post('/two-factor-challenge', [AuthController::class, 'verifyTwoFactor'])
    ->name('two-factor.verify')
    ->middleware('throttle:login');

Route::post('/two-factor-challenge/resend', [AuthController::class, 'resendTwoFactorCode'])
    ->name('two-factor.resend')
    ->middleware('throttle:login');

Route::post('/logout', [AuthController::class, 'logout'])
    ->name('logout');

Route::get('/csrf-token', fn () => response()->json([
    'token' => csrf_token(),
]))->name('csrf-token');

/*
|--------------------------------------------------------------------------
| KEGIATAN (PUBLIC SPA OR ADMIN VIEW)
|--------------------------------------------------------------------------
*/

Route::get('/kegiatan', function (Request $request) use ($findSpa) {
    if (auth()->check()) {
        $user = auth()->user();
        if ($user && $user->two_factor_enabled && ! $request->session()->get('two_factor_verified', false)) {
            return redirect('/two-factor-challenge');
        }

        return app(KegiatanController::class)->index($request);
    }

    if ($path = $findSpa()) {
        return response()->file($path);
    }

    return app(KegiatanController::class)->index($request);
})->name('kegiatan.index');

Route::get('/kegiatan/{id}', function (Request $request, $id) use ($findSpa) {
    if ($request->wantsJson() || $request->ajax()) {
        return app(KegiatanController::class)->show((int) $id);
    }

    if (auth()->check()) {
        return app(KegiatanController::class)->show((int) $id);
    }

    if ($path = $findSpa()) {
        return response()->file($path);
    }

    return app(KegiatanController::class)->show((int) $id);
})->name('kegiatan.show');

/*
|--------------------------------------------------------------------------
| DASHBOARD AREA
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', '2fa'])->group(function () {

    Route::get('/header/search', [HeaderController::class, 'search'])
        ->name('header.search');

    Route::get('/header/notifications', [HeaderController::class, 'notifications'])
        ->name('header.notifications');

    /*
    |--------------------------------------------------------------------------
    | DASHBOARD
    |--------------------------------------------------------------------------
    */

    Route::get('/dashboard', [DashboardController::class, 'index']);

    /*
    |--------------------------------------------------------------------------
    | ANGGOTA
    |--------------------------------------------------------------------------
    */

    Route::prefix('anggota')->group(function () {

        Route::get('/', [AnggotaController::class, 'index']);

        Route::post('/store', [AnggotaController::class, 'store']);

        Route::post('/import-csv', [AnggotaController::class, 'importCsv'])
            ->name('anggota.import-csv');

        Route::put('/update/{id}', [AnggotaController::class, 'update']);

        Route::delete('/delete/{id}', [AnggotaController::class, 'destroy'])
            ->middleware('role:super_admin,admin');

        Route::get('/{id}', [AnggotaController::class, 'show']);

    });

    /*
    |--------------------------------------------------------------------------
    | PAC
    |--------------------------------------------------------------------------
    */

    Route::prefix('data-pac')->group(function () {

        Route::get('/', [PACController::class, 'index'])
            ->name('pac.index');

        Route::post('/store', [PACController::class, 'store'])
            ->name('pac.store');

        Route::post('/import-csv', [PACController::class, 'importCsv'])
            ->name('pac.import-csv');

        Route::get('/export/excel', [PACController::class, 'exportExcel'])
            ->name('pac.export-excel');

        Route::put('/update/{id}', [PACController::class, 'update'])
            ->name('pac.update');

        Route::delete('/delete/{id}', [PACController::class, 'destroy'])
            ->middleware('role:super_admin,admin')
            ->name('pac.destroy');

        Route::get('/{id}', [PACController::class, 'show'])
            ->name('pac.show');

    });

    /*
    |--------------------------------------------------------------------------
    | PENGAJUAN PAC (APPROVAL & MANAGEMENT)
    |--------------------------------------------------------------------------
    */

    Route::prefix('pengajuan-pac')->group(function () {

        Route::get('/', [PengajuanPACController::class, 'index'])
            ->name('pengajuan-pac.index');

        Route::get('/{id}', [PengajuanPACController::class, 'show'])
            ->name('pengajuan-pac.show');

        Route::post('/{id}/approve', [PengajuanPACController::class, 'approve'])
            ->middleware('role:super_admin,admin')
            ->name('pengajuan-pac.approve');

        Route::post('/{id}/reject', [PengajuanPACController::class, 'reject'])
            ->middleware('role:super_admin,admin')
            ->name('pengajuan-pac.reject');

    });

    /*
    |--------------------------------------------------------------------------
    | KEGIATAN (ADMIN ACTIONS)
    |--------------------------------------------------------------------------
    */

    Route::prefix('kegiatan')->group(function () {

        Route::post('/store', [KegiatanController::class, 'store']);

        Route::put('/update/{id}', [KegiatanController::class, 'update']);

        Route::delete('/delete/{id}', [KegiatanController::class, 'destroy'])
            ->middleware('role:super_admin,admin');

    });

    /*
    |--------------------------------------------------------------------------
    | LAPORAN
    |--------------------------------------------------------------------------
    */

    Route::prefix('laporan')->group(function () {

        Route::get('/', [LaporanController::class, 'index']);

        Route::get('/generate/{type}', [LaporanController::class, 'generate'])
            ->whereIn('type', ['anggota', 'pac', 'kegiatan']);

        Route::get('/export/pdf', [LaporanController::class, 'exportPDF']);

        Route::get('/export/excel', [LaporanController::class, 'exportExcel']);

        Route::get('/export/csv', [LaporanController::class, 'exportCSV']);

        Route::get('/export/pac/pdf', [LaporanController::class, 'exportPacPDF']);

        Route::get('/export/kegiatan/pdf', [LaporanController::class, 'exportKegiatanPDF']);

    });

    /*
    |--------------------------------------------------------------------------
    | PENGATURAN
    |--------------------------------------------------------------------------
    */

    Route::get('/pengaturan', [PengaturanController::class, 'index']);

    Route::get('/pengaturan/profil', [PengaturanController::class, 'profil']);

    Route::post('/pengaturan/profil/update', [PengaturanController::class, 'updateProfil'])->name('pengaturan.profil.update');

    Route::delete('/pengaturan/profil/foto', [PengaturanController::class, 'hapusFoto'])->name('pengaturan.profil.foto.delete');

    Route::get('/pengaturan/keamanan', [PengaturanController::class, 'keamanan']);

    Route::post('/pengaturan/update-password', [PengaturanController::class, 'updatePassword'])->name('pengaturan.password.update');

    Route::post('/pengaturan/keamanan/two-factor', [PengaturanController::class, 'updateTwoFactor'])->name('pengaturan.two-factor.update');

    Route::post('/pengaturan/update', [PengaturanController::class, 'update'])->name('pengaturan.update');

    Route::get('/pengaturan/notifikasi', [PengaturanController::class, 'notifikasi']);

    Route::post('/pengaturan/notifikasi', [PengaturanController::class, 'updateNotifikasi'])->name('pengaturan.notifikasi.update');

    Route::get('/pengaturan/sistem', [PengaturanController::class, 'sistem']);

    Route::post('/backup/database', [PengaturanController::class, 'backupDatabase'])
        ->middleware('role:super_admin')
        ->name('backup.database');

    Route::post('/restore/database', [PengaturanController::class, 'restoreDatabase'])
        ->middleware('role:super_admin')
        ->name('restore.database');

});

/*
|--------------------------------------------------------------------------
| STORAGE FALLBACK (shared hosting tanpa symlink public/storage)
|--------------------------------------------------------------------------
| Jika symlink `public/storage` aktif, Apache/LiteSpeed melayani file
| langsung sebelum menyentuh Laravel. Route ini menjadi fallback aman bila
| fungsi symlink() dinonaktifkan di shared hosting.
*/
Route::get('/storage/{path}', function (string $path) {
    $baseDir = realpath(storage_path('app/public'));
    if ($baseDir === false) {
        abort(404);
    }

    $fullPath = realpath($baseDir.DIRECTORY_SEPARATOR.$path);
    if ($fullPath === false || ! str_starts_with($fullPath, $baseDir.DIRECTORY_SEPARATOR) || ! is_file($fullPath)) {
        abort(404);
    }

    return response()->file($fullPath);
})->where('path', '.*')->name('storage.fallback');

/*
|--------------------------------------------------------------------------
| SPA FALLBACK (deep-link / refresh pada halaman React yang tak terdaftar)
|--------------------------------------------------------------------------
| Semua GET yang tidak cocok route di atas dan bukan /api atau /storage
| dikembalikan ke index.html SPA supaya React Router menanganinya
| (termasuk halaman NotFound milik SPA).
*/
Route::fallback(function (Request $request) use ($findSpa) {
    if ($request->is('api/*') || $request->is('storage/*')) {
        abort(404);
    }

    if ($path = $findSpa()) {
        return response()->file($path);
    }

    abort(404);
});
