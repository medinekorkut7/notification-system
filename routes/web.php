<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DeadLetterUiController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AdminJobController;
use App\Http\Controllers\AdminAuditLogController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dead-letter', [DeadLetterUiController::class, 'index']);

Route::get('/admin/login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
Route::post('/admin/login', [AdminAuthController::class, 'login']);
Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->middleware('admin.session');

Route::get('/admin', [AdminDashboardController::class, 'index'])->middleware('admin.session');
Route::get('/admin/users', [AdminUserController::class, 'index'])->middleware('admin.session');
Route::get('/admin/users/create', [AdminUserController::class, 'create'])->middleware(['admin.session', 'admin.role']);
Route::post('/admin/users', [AdminUserController::class, 'store'])->middleware(['admin.session', 'admin.role']);
Route::get('/admin/users/{adminUser}/edit', [AdminUserController::class, 'edit'])->middleware(['admin.session', 'admin.role']);
Route::put('/admin/users/{adminUser}', [AdminUserController::class, 'update'])->middleware(['admin.session', 'admin.role']);
Route::delete('/admin/users/{adminUser}', [AdminUserController::class, 'destroy'])->middleware(['admin.session', 'admin.role']);
Route::get('/admin/audit', [AdminAuditLogController::class, 'index'])->middleware('admin.session');

Route::get('/admin/jobs/status', [AdminJobController::class, 'status'])->middleware('admin.session');
Route::post('/admin/jobs/pause', [AdminJobController::class, 'pause'])->middleware(['admin.session', 'admin.role']);
Route::post('/admin/jobs/resume', [AdminJobController::class, 'resume'])->middleware(['admin.session', 'admin.role']);
Route::post('/admin/jobs/restart', [AdminJobController::class, 'restart'])->middleware(['admin.session', 'admin.role']);
Route::get('/admin/workers/status', [AdminJobController::class, 'workersStatus'])->middleware('admin.session');
Route::post('/admin/workers/start', [AdminJobController::class, 'workersStart'])->middleware(['admin.session', 'admin.role']);
Route::post('/admin/workers/stop', [AdminJobController::class, 'workersStop'])->middleware(['admin.session', 'admin.role']);
Route::post('/admin/jobs/stress', [AdminJobController::class, 'stress'])->middleware(['admin.session', 'admin.role']);
Route::get('/admin/jobs/stress/status', [AdminJobController::class, 'stressStatus'])->middleware('admin.session');
Route::post('/admin/provider/settings', [AdminJobController::class, 'providerSettings'])->middleware(['admin.session', 'admin.role']);
Route::post('/admin/dead-letter/requeue', [AdminJobController::class, 'requeueDeadLetters'])->middleware(['admin.session', 'admin.role']);
Route::post('/admin/dead-letter/{deadLetterId}/requeue', [AdminJobController::class, 'requeueDeadLetter'])->middleware(['admin.session', 'admin.role']);

Route::get('/ws-demo', function () {
    return view('ws-demo');
});

Route::get('/swagger', function () {
    return view('swagger');
});

Route::get('/docs/openapi.yaml', function () {
    return response()->file(base_path('docs/openapi.yaml'), [
        'Content-Type' => 'application/yaml',
    ]);
});
