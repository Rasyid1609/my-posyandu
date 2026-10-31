<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AnalysisController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClinicalCalculationController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\HomeVisitController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\PeriodController;
use App\Http\Controllers\RegistryController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ResidentController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TargetController;
use App\Http\Controllers\VisitController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/masyarakat');
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');
});
Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::resource('masyarakat', ResidentController::class)->parameters(['masyarakat' => 'resident'])
        ->only(['index', 'create', 'store', 'edit', 'update'])->names([
            'index' => 'residents.index',
            'create' => 'residents.create',
            'store' => 'residents.store',
            'edit' => 'residents.edit',
            'update' => 'residents.update',
        ]);
    Route::get('/sasaran', [PeriodController::class, 'index'])->name('periods.index');
    Route::get('/masyarakat/keluarga/daftar', [RegistryController::class, 'index'])->name('households.index');
    Route::get('/masyarakat/keluarga/{household}', [RegistryController::class, 'show'])->name('households.show');
    Route::post('/masyarakat/keluarga', [RegistryController::class, 'saveHousehold'])->name('households.store');
    Route::put('/masyarakat/keluarga/{household}', [RegistryController::class, 'saveHousehold'])->name('households.update');
    Route::post('/masyarakat/{resident}/episode', [RegistryController::class, 'episode'])->name('episodes.store');
    Route::patch(
        '/masyarakat/{resident}/episode/{episode}',
        [RegistryController::class, 'closeEpisode']
    )->name('episodes.close');
    Route::get('/sasaran/{period}/rumah', [HomeVisitController::class, 'index'])->name('home-visits.index');
    Route::post('/sasaran/{period}/rumah', [HomeVisitController::class, 'store'])->name('home-visits.store');
    Route::put('/sasaran/{period}/rumah/{homeVisit}', [HomeVisitController::class, 'update'])->name('home-visits.update');
    Route::post(
        '/sasaran/{period}/rumah/{homeVisit}/finalisasi',
        [HomeVisitController::class, 'finalize']
    )->name('home-visits.finalize');
    Route::post('/sasaran/{period}/keluarga/generate', [HomeVisitController::class, 'targets'])->name('household-targets.generate');
    Route::post('/sasaran', [PeriodController::class, 'store'])->name('periods.store');
    Route::get('/sasaran/{period}', [PeriodController::class, 'show'])->name('periods.show');
    Route::post('/sasaran/{period}/kegiatan', [ActivityController::class, 'store'])->name('activities.store');
    Route::post('/sasaran/{period}/rumah/{homeVisit}/koreksi', [HomeVisitController::class, 'correct'])->name('home-visits.correct');
    Route::post('/sasaran/{period}/anggota', [TargetController::class, 'store'])->name('targets.store');
    Route::post('/sasaran/{period}/generate', [TargetController::class, 'generate'])->name('targets.generate');
    Route::post('/sasaran/{period}/kunjungan/{visit}/koreksi', [VisitController::class, 'correct'])->name('visits.correct');
    Route::get('/pengaturan/aturan', [SettingsController::class, 'index'])->name('settings.rules');
    Route::post('/pengaturan/aturan', [SettingsController::class, 'store'])->name('settings.rules.store');
    Route::patch('/sasaran/{period}/anggota/{target}', [TargetController::class, 'update'])->name('targets.update');
    Route::post('/sasaran/{period}/anggota/{target}/snapshot', [TargetController::class, 'refresh'])->name('targets.refresh');
    Route::delete('/sasaran/{period}/anggota/{target}', [TargetController::class, 'destroy'])->name('targets.destroy');
    Route::get('/sasaran/{period}/anggota/{target}/kunjungan', [VisitController::class, 'index'])->name('visits.index');
    Route::post('/sasaran/{period}/anggota/{target}/perhitungan', ClinicalCalculationController::class)
        ->middleware('throttle:120,1')
        ->name('visits.calculate');
    Route::post('/sasaran/{period}/anggota/{target}/kunjungan', [VisitController::class, 'store'])->name('visits.store');
    Route::put(
        '/sasaran/{period}/anggota/{target}/kunjungan/{visit}',
        [VisitController::class, 'update']
    )->name('visits.update');
    Route::post('/sasaran/{period}/kunjungan/{visit}/finalisasi', [VisitController::class, 'finalize'])->name('visits.finalize');
    Route::get('/rekapitulasi', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/rekapitulasi/{period}/analisis', [AnalysisController::class, 'index'])->name('analysis.index');
    Route::post('/rekapitulasi/{period}/analisis', [AnalysisController::class, 'store'])
        ->middleware('throttle:analysis')
        ->name('analysis.store');
    Route::patch('/rekapitulasi/{period}/analisis/{run}', [AnalysisController::class, 'review'])->name('analysis.review');
    Route::get('/rekapitulasi/{period}/import', [ImportController::class, 'index'])->name('imports.index');
    Route::get('/rekapitulasi/{period}/import/template', [ImportController::class, 'template'])->name('imports.template');
    Route::post('/rekapitulasi/{period}/import', [ImportController::class, 'store'])->name('imports.store');
    Route::patch('/rekapitulasi/{period}/import/{batch}', [ImportController::class, 'update'])->name('imports.update');
    Route::post('/rekapitulasi/{period}/import/{batch}/commit', [ImportController::class, 'commit'])->name('imports.commit');
    Route::post('/rekapitulasi/{period}/import/{batch}/ocr', [ImportController::class, 'ocr'])
        ->middleware('throttle:analysis')
        ->name('imports.ocr');
    Route::get('/rekapitulasi/{period}/import/{batch}/sumber', [ImportController::class, 'source'])
        ->middleware('signed')
        ->name('imports.source');
    Route::post('/rekapitulasi/{period}/sumber', [ImportController::class, 'selectSource'])->name('imports.select-source');
    Route::get('/rekapitulasi/{period}/ekspor', [ExportController::class, 'show'])->name('reports.export');
    Route::patch('/rekapitulasi/{period}', [ReportController::class, 'update'])->name('reports.update');
    Route::get('/pengaturan/akun', [AccountController::class, 'index'])->name('accounts.index');
    Route::post('/pengaturan/akun', [AccountController::class, 'store'])->name('accounts.store');
    Route::put('/pengaturan/akun/{user}', [AccountController::class, 'update'])->name('accounts.update');
});
