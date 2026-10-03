<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'username' => $user->username,
                    'name' => $user->name,
                    'saldo' => $user->saldo,
                    'role' => $user->primaryRole(),
                    'is_super_admin' => $user->hasRole(\App\Enums\Role::SuperAdmin->value),
                    'superadmin_path' => $user->hasRole(\App\Enums\Role::SuperAdmin->value) ? config('ramela.superadmin_path') : null,
                ] : null,
            ],
            'cartCount' => fn() => $user ? $user->cartItems()->count() : 0,
            'features' => [
                'blog' => app(\App\Services\SettingsService::class)->bool('feature.blog', true),
                'faq' => app(\App\Services\SettingsService::class)->bool('feature.faq', true),
            ],
            'flash' => [
                'success' => fn() => $request->session()->get('success'),
            ],
        ];
    }
}
