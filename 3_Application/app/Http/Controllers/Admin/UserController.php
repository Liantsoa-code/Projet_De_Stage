<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Site;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->input('q'));
        $role = $request->input('role');

        $users = User::with('sites:id,nom')
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")))
            ->when($role, fn ($query) => $query->where('role', $role))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => ['q' => $q, 'role' => $role],
        ]);
    }

    public function edit(User $user)
    {
        $sites = Site::all();
        $user->load('sites');
        return Inertia::render('Admin/Users/Edit', ['userEdit' => $user, 'sites' => $sites]);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'role' => 'required|in:demandeur,validateur,admin',
            'sites' => 'nullable|array', // Identifiants des sites (si validateur)
        ]);

        $user->update(['role' => $validated['role']]);

        // Si l'utilisateur est défini comme validateur, on synchronise les sites dont il a la charge
        if ($validated['role'] === 'validateur' && isset($validated['sites'])) {
            $user->sites()->sync($validated['sites']);
        } else {
            // Sinon on retire toutes ses affectations de sites
            $user->sites()->detach();
        }

        return redirect()->route('admin.users.index')->with('message', 'Utilisateur mis à jour avec succès.');
    }
}
