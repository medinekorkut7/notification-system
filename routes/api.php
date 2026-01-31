<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\MetricsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\DeadLetterNotificationController;
use App\Http\Controllers\NotificationTemplateController;
use App\Http\Controllers\TemplatePreviewController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::middleware(['api.key', 'client.rate'])->group(function () {
        Route::get('health', [HealthController::class, 'index']);
        Route::get('metrics', [MetricsController::class, 'index']);
        Route::get('metrics/prometheus', [MetricsController::class, 'prometheus']);

        Route::post('notifications', [NotificationController::class, 'store']);
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::get('notifications/{notificationId}', [NotificationController::class, 'show']);
        Route::post('notifications/{notificationId}/cancel', [NotificationController::class, 'cancel']);

        Route::get('batches/{batchId}', [NotificationController::class, 'showBatch']);
        Route::post('batches/{batchId}/cancel', [NotificationController::class, 'cancelBatch']);

        Route::get('dead-letter', [DeadLetterNotificationController::class, 'index']);
        Route::post('dead-letter/requeue', [DeadLetterNotificationController::class, 'requeueAll']);
        Route::get('dead-letter/{deadLetterId}', [DeadLetterNotificationController::class, 'show']);
        Route::post('dead-letter/{deadLetterId}/requeue', [DeadLetterNotificationController::class, 'requeue']);

        Route::post('templates', [NotificationTemplateController::class, 'store']);
        Route::get('templates', [NotificationTemplateController::class, 'index']);
        Route::post('templates/preview', TemplatePreviewController::class);
        Route::get('templates/{templateId}', [NotificationTemplateController::class, 'show']);
        Route::patch('templates/{templateId}', [NotificationTemplateController::class, 'update']);
        Route::delete('templates/{templateId}', [NotificationTemplateController::class, 'destroy']);
    });
});
