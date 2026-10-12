<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'flash' => [
                'message' => fn () => $request->session()->get('message'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'notifications' => function () use ($request) {
                if ($request->user()) {
                    // Partagé à chaque page : seuls les champs affichés dans la cloche sont chargés.
                    return \App\Models\Notification::where('user_id', $request->user()->id)
                        ->where('lu', false)
                        ->with(['demande:id,site_id', 'demande.site:id,nom'])
                        ->orderBy('date_envoi', 'desc')
                        ->orderBy('created_at', 'desc')
                        ->get(['id', 'demande_id', 'contenu', 'date_envoi']);
                }
                return [];
            },
        ];
    }
}
