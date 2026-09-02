<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoleController extends Controller
{
    /**
     * Display a listing of the roles.
     */
    public function index(): View
    {
        $roles = Role::withCount('users')->orderBy('id')->get();
        $systemMenus = Role::SYSTEM_MENUS;

        return view('roles.index', compact('roles', 'systemMenus'));
    }

    /**
     * Show the form for creating a new role.
     */
    public function create(): View
    {
        $systemMenus = Role::SYSTEM_MENUS;
        $role = new Role;

        return view('roles.form', compact('systemMenus', 'role'));
    }

    /**
     * Store a newly created role in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:roles,slug'],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ], [
            'name.required' => 'Nama role wajib diisi.',
            'slug.unique' => 'Kode slug role sudah digunakan.',
        ]);

        $slug = $validated['slug'] ? Str::slug($validated['slug'], '_') : Str::slug($validated['name'], '_');

        Role::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'permissions' => $validated['permissions'] ?? [],
        ]);

        return redirect()->route('roles.index')
            ->with('success', 'Role "'.$validated['name'].'" berhasil ditambahkan.');
    }

    /**
     * Show the form for editing the specified role.
     */
    public function edit(Role $role): View
    {
        $systemMenus = Role::SYSTEM_MENUS;

        return view('roles.form', compact('systemMenus', 'role'));
    }

    /**
     * Update the specified role in storage.
     */
    public function update(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('roles', 'slug')->ignore($role->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ], [
            'name.required' => 'Nama role wajib diisi.',
            'slug.unique' => 'Kode slug role sudah digunakan.',
        ]);

        // Protect super_admin slug from being accidentally changed
        $slug = ($role->slug === 'super_admin')
            ? 'super_admin'
            : ($validated['slug'] ? Str::slug($validated['slug'], '_') : Str::slug($validated['name'], '_'));

        $permissions = ($role->slug === 'super_admin')
            ? array_keys(array_merge(...array_values(array_map('array_keys', Role::SYSTEM_MENUS))))
            : ($validated['permissions'] ?? []);

        $role->update([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'permissions' => $permissions,
        ]);

        return redirect()->route('roles.index')
            ->with('success', 'Pengaturan hak akses role "'.$role->name.'" berhasil diperbarui.');
    }

    /**
     * Remove the specified role from storage.
     */
    public function destroy(Role $role): RedirectResponse
    {
        if ($role->slug === 'super_admin') {
            return back()->with('error', 'Role Super Admin adalah role utama sistem dan tidak boleh dihapus.');
        }

        if ($role->users()->count() > 0) {
            return back()->with('error', 'Role ini masih digunakan oleh '.$role->users()->count().' pengguna. Pindahkan pengguna ke role lain terlebih dahulu.');
        }

        $name = $role->name;
        $role->delete();

        return redirect()->route('roles.index')
            ->with('success', 'Role "'.$name.'" berhasil dihapus.');
    }
}
