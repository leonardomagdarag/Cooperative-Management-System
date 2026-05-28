<div class="space-y-6">
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="bg-linear-to-r from-slate-900 via-cyan-800 to-emerald-700 px-6 py-7 text-white">
            <p class="text-sm font-medium text-cyan-100">{{ $greeting }}</p>
            <h2 class="mt-1 text-2xl font-semibold">Custom officer dashboard</h2>
            <p class="mt-2 max-w-2xl text-sm text-cyan-50/90">
                This role is being served by the shared permission-driven dashboard path. Use the navigation to access
                the sections granted to this account.
            </p>
        </div>

        <div class="grid gap-4 px-6 py-5 md:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Active role</p>
                <p class="mt-2 text-lg font-semibold text-slate-900">
                    {{ ucwords(str_replace(['-', '_'], ' ', $primaryRole)) }}</p>
            </div>

            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Granted permissions</p>
                <p class="mt-2 text-lg font-semibold text-emerald-900">{{ $permissionCount }}</p>
            </div>

            <div class="rounded-xl border border-cyan-200 bg-cyan-50 px-4 py-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-cyan-700">Account status</p>
                <p class="mt-2 text-lg font-semibold text-cyan-900">{{ ucfirst($accountStatus) }}</p>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-4">
            <h3 class="text-lg font-semibold text-slate-900">Permission summary</h3>
            <p class="mt-1 text-sm text-slate-500">Inherited and direct permissions currently available to this role.
            </p>
        </div>

        <div class="px-6 py-5">
            @if ($grantedPermissions === [])
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-800">
                    No permissions are currently assigned. An administrator can grant access from Role Permission
                    Management.
                </div>
            @else
                <div class="flex flex-wrap gap-2">
                    @foreach ($grantedPermissions as $permission)
                        <span
                            class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-700">
                            {{ \App\Support\PermissionCatalog::formatLabel($permission) }}
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
