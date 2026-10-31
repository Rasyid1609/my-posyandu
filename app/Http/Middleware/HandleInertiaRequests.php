<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => $user ? [
                'user' => $user->only(['id', 'name', 'email']),
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ] : null,
            'flash' => ['success' => fn () => $request->session()->get('success')],
            'configuration' => [
                'name' => config('posyandu.name'),
                'real_data_approved' => config('posyandu.real_data_approved'),
                'clinical_rules' => false,
                'ai_enabled' => config('analysis.enabled') && config('analysis.processing_approved') && (bool) config('analysis.api_key') && (bool) config('analysis.model'),
            ],
        ];
    }
}
