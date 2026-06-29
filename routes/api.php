<?php

use App\Http\Controllers\Api\V1\AssetController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\IncidentController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\PlanningProposalController;
use App\Http\Controllers\Api\V1\ReportsController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\StatsController;
use App\Http\Controllers\Api\V1\WorkOrderController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

        Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [MeController::class, 'show']);

        Route::get('/assets', [AssetController::class, 'index']);
        Route::post('/assets', [AssetController::class, 'store']);

        Route::get('/customers', [CustomerController::class, 'index']);
        Route::post('/customers', [CustomerController::class, 'store']);
        Route::get('/customers/{id}', [CustomerController::class, 'show']);
        Route::patch('/customers/{id}', [CustomerController::class, 'update']);

        Route::get('/incidents', [IncidentController::class, 'index']);
        Route::post('/incidents', [IncidentController::class, 'store']);
        Route::get('/incidents/{id}', [IncidentController::class, 'show']);
        Route::patch('/incidents/{id}', [IncidentController::class, 'update']);

        Route::get('/work-orders', [WorkOrderController::class, 'index']);
        Route::post('/work-orders', [WorkOrderController::class, 'store']);
        Route::get('/work-orders/{id}', [WorkOrderController::class, 'show']);
        Route::patch('/work-orders/{id}', [WorkOrderController::class, 'update']);

        Route::get('/planning/proposals', [PlanningProposalController::class, 'index']);
        Route::post('/planning/proposals', [PlanningProposalController::class, 'store']);
        Route::get('/planning/proposals/{id}', [PlanningProposalController::class, 'show']);
        Route::patch('/planning/proposals/{id}', [PlanningProposalController::class, 'update']);

        Route::get('/stats/usage', [StatsController::class, 'usage']);

        Route::get('/reports/summary', [ReportsController::class, 'summary']);
        Route::get('/reports/incidents/analytics', [ReportsController::class, 'incidentAnalytics']);
        Route::get('/reports/uptime', [ReportsController::class, 'uptime']);
        Route::get('/reports/history', [ReportsController::class, 'history']);
        Route::post('/reports/generate', [ReportsController::class, 'generate']);
        Route::get('/reports/{id}/download', [ReportsController::class, 'download']);

        Route::get('/settings/organization', [SettingsController::class, 'getOrganization']);
        Route::patch('/settings/organization', [SettingsController::class, 'updateOrganization']);
        Route::get('/settings/integrations', [SettingsController::class, 'getIntegrations']);
        Route::patch('/settings/integrations/webhook', [SettingsController::class, 'updateWebhook']);
        Route::patch('/settings/integrations/{providerId}', [SettingsController::class, 'updateIntegration']);
        Route::post('/settings/integrations/mikrotik/test', [SettingsController::class, 'testMikrotikConnection']);
        Route::get('/settings/billing', [SettingsController::class, 'getBilling']);
        Route::patch('/settings/billing', [SettingsController::class, 'updateBilling']);
        Route::post('/settings/billing/sync', [SettingsController::class, 'syncBilling']);
        Route::get('/settings/team', [SettingsController::class, 'getTeam']);
        Route::post('/settings/team/invites', [SettingsController::class, 'inviteTeamMember']);
        Route::patch('/settings/team/members/{memberId}', [SettingsController::class, 'updateTeamMember']);
        Route::delete('/settings/team/invites/{inviteId}', [SettingsController::class, 'revokeInvite']);
    });
});
