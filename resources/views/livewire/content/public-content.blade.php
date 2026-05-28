<div class="space-y-5">

    {{-- ── Page header ──────────────────────────────────────────────────────── --}}
    <div class="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
        <div class="bg-gradient-to-r from-emerald-600 to-cyan-600 px-6 py-5">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-white/80">Public Content</p>
            <h2 class="mt-1 text-2xl font-bold text-white">Public website information</h2>
        </div>

        <div class="flex flex-col gap-5 px-6 py-5 xl:flex-row xl:items-center xl:justify-between">
            <p class="max-w-2xl text-sm text-gray-600">
                The public pages read directly from the database. Pick a section, update the fields, then save — changes
                appear on the site immediately.
            </p>

            <div class="flex w-full shrink-0 flex-col gap-3 xl:w-auto xl:min-w-[360px] xl:flex-row xl:items-center">
                <div class="flex-1">
                    <label class="sr-only" for="section-select">Section</label>
                    <select id="section-select" wire:model.live="activeSection"
                        class="w-full rounded-xl border-gray-300 py-2.5 text-sm text-gray-700 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        @foreach ($sections as $sectionKey => $section)
                            <option value="{{ $sectionKey }}">{{ $section['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    @if ($canManage)
                        <button type="button" wire:click="save" wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700 focus:outline-none focus:ring-4 focus:ring-emerald-200 disabled:opacity-60">
                            <svg wire:loading wire:target="save" class="h-4 w-4 animate-spin text-white"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8v4l3-3-3-3v4a8 8 0 00-8 8h4z"></path>
                            </svg>
                            <span wire:loading.remove wire:target="save">Save changes</span>
                            <span wire:loading wire:target="save">Saving…</span>
                        </button>
                    @endif

                    <button type="button" wire:click="goToSettings"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-600 shadow-sm transition-colors hover:bg-gray-50 focus:outline-none focus:ring-4 focus:ring-gray-100">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                        Settings
                    </button>
                </div>
            </div>
        </div>
    </div>

    @if (!$canManage)
        <div class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4" role="alert">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-500" fill="none" stroke="currentColor" stroke-width="2"
                viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M12 9v3m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
            </svg>
            <p class="text-sm text-amber-800">Only admins can update public website content. You currently have
                view-only access.</p>
        </div>
    @endif

    {{-- ── Active section heading ───────────────────────────────────────────── --}}
    <div class="flex items-center gap-4 px-1">
        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100">
            <svg class="h-5 w-5 text-emerald-700" fill="none" stroke="currentColor" stroke-width="2"
                viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h10" />
            </svg>
        </div>
        <div>
            <h3 class="text-lg font-bold text-gray-900">{{ $activeSectionMeta['label'] ?? 'Section' }}</h3>
            <p class="text-sm text-gray-500">
                {{ $activeSectionMeta['description'] ?? 'Edit the fields below and save.' }}</p>
        </div>
    </div>

    @if ($activeSectionMeta)

        {{-- ══ CARD-EDITOR MODE (Board / Officers) ═══════════════════════════ --}}
        @if (($activeSectionMeta['editor'] ?? null) === 'cards')
            @php $cards = data_get($form, $activeSection . '.cards', []); @endphp

            {{-- Visibility toggle --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-4">
                        <div @class([
                            'flex h-12 w-12 shrink-0 items-center justify-center rounded-xl transition-colors',
                            'bg-emerald-100' => (bool) data_get($form, $activeSection . '.is_visible'),
                            'bg-gray-100' => !(bool) data_get($form, $activeSection . '.is_visible'),
                        ])>
                            <svg @class([
                                'h-6 w-6 transition-colors',
                                'text-emerald-600' => (bool) data_get(
                                    $form,
                                    $activeSection . '.is_visible'),
                                'text-gray-400' => !(bool) data_get($form, $activeSection . '.is_visible'),
                            ]) fill="none" stroke="currentColor" stroke-width="2"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </div>
                        <div>
                            <h4 class="text-base font-semibold text-gray-900">Section visibility</h4>
                            <p class="mt-0.5 text-sm text-gray-500">
                                {{ $activeSectionMeta['toggle_description'] ?? 'Control whether this section is shown on the public page.' }}
                            </p>
                        </div>
                    </div>

                    {{-- Toggle switch --}}
                    <label class="inline-flex cursor-pointer items-center gap-3 @disabled(!$canManage)">
                        <span class="text-sm font-medium @class([
                            'text-emerald-700' => (bool) data_get(
                                $form,
                                $activeSection . '.is_visible'),
                            'text-gray-500' => !(bool) data_get($form, $activeSection . '.is_visible'),
                        ])">
                            {{ (bool) data_get($form, $activeSection . '.is_visible') ? 'Visible' : 'Hidden' }}
                        </span>
                        <div class="relative">
                            <input type="checkbox" wire:model.live="form.{{ $activeSection }}.is_visible"
                                @disabled(!$canManage) class="peer sr-only">
                            <div
                                class="h-6 w-11 rounded-full border-2 border-transparent bg-gray-200 transition-colors peer-checked:bg-emerald-500 peer-focus:ring-4 peer-focus:ring-emerald-200 peer-disabled:opacity-50">
                            </div>
                            <div
                                class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform peer-checked:translate-x-5">
                            </div>
                        </div>
                    </label>
                </div>

                @error('form.' . $activeSection . '.is_visible')
                    <p class="mt-3 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Card list --}}
            <div class="rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="flex items-center justify-between gap-4 border-b border-gray-100 px-6 py-4">
                    <div>
                        <h4 class="text-base font-semibold text-gray-900">
                            {{ $activeSectionMeta['card_singular'] ?? 'Card' }} cards
                            <span
                                class="ml-2 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600">{{ count($cards) }}</span>
                        </h4>
                        <p class="mt-0.5 text-sm text-gray-500">
                            Use an absolute URL or a relative public path — e.g. <code
                                class="rounded bg-gray-100 px-1 text-xs">images/board/name.jpg</code>
                        </p>
                    </div>

                    @if ($canManage)
                        <button type="button" wire:click="addCard('{{ $activeSection }}')"
                            class="inline-flex shrink-0 items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700 focus:outline-none focus:ring-4 focus:ring-emerald-200">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            Add {{ $activeSectionMeta['card_singular'] ?? 'Card' }}
                        </button>
                    @endif
                </div>

                <div class="divide-y divide-gray-100">
                    @forelse ($cards as $index => $card)
                        @php
                            $image = $card['image_url'] ?? '';
                            $imageSrc = filled($image)
                                ? (filter_var($image, FILTER_VALIDATE_URL)
                                    ? $image
                                    : asset(ltrim($image, '/')))
                                : null;
                            $displayName =
                                $card['name'] ?: ($activeSectionMeta['card_singular'] ?? 'Card') . ' ' . ($index + 1);
                        @endphp

                        <article wire:key="public-content-card-{{ $activeSection }}-{{ $index }}"
                            class="p-6">
                            <div class="mb-5 flex items-start justify-between gap-4">
                                <div class="flex items-center gap-3">
                                    <span
                                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-700">{{ $index + 1 }}</span>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">{{ $displayName }}</p>
                                        <p class="text-xs text-gray-500">
                                            {{ $card['position'] ?: 'No position set yet' }}</p>
                                    </div>
                                </div>

                                @if ($canManage)
                                    <button type="button"
                                        wire:click="removeCard('{{ $activeSection }}', {{ $index }})"
                                        class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-600 transition-colors hover:bg-red-100 focus:outline-none focus:ring-4 focus:ring-red-100">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor"
                                            stroke-width="2.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        Remove
                                    </button>
                                @endif
                            </div>

                            <div class="grid gap-5 xl:grid-cols-[200px_minmax(0,1fr)]">
                                {{-- Preview card --}}
                                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                                    @if ($imageSrc)
                                        <img src="{{ $imageSrc }}" alt="{{ $displayName }}"
                                            class="h-52 w-full object-cover" loading="lazy" decoding="async">
                                    @else
                                        <div
                                            class="flex h-52 items-center justify-center bg-gradient-to-br from-slate-700 to-emerald-700">
                                            <i class="fas fa-user text-5xl text-white/60"></i>
                                        </div>
                                    @endif
                                    <div class="border-t border-gray-100 px-4 py-3">
                                        <p class="truncate text-sm font-semibold text-gray-900">{{ $displayName }}
                                        </p>
                                        <p class="truncate text-xs text-emerald-600">{{ $card['position'] ?: '—' }}
                                        </p>
                                    </div>
                                </div>

                                {{-- Fields --}}
                                <div class="space-y-4">
                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <div>
                                            <label
                                                class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Name</label>
                                            <input type="text"
                                                wire:model.defer="form.{{ $activeSection }}.cards.{{ $index }}.name"
                                                @disabled(!$canManage) placeholder="Full name"
                                                class="w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 disabled:bg-gray-100 disabled:text-gray-400">
                                            @error('form.' . $activeSection . '.cards.' . $index . '.name')
                                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div>
                                            <label
                                                class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Position
                                                / Title</label>
                                            <input type="text"
                                                wire:model.defer="form.{{ $activeSection }}.cards.{{ $index }}.position"
                                                @disabled(!$canManage) placeholder="e.g. Chairperson"
                                                class="w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 disabled:bg-gray-100 disabled:text-gray-400">
                                            @error('form.' . $activeSection . '.cards.' . $index . '.position')
                                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>

                                    <div>
                                        <label
                                            class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Image
                                            path or URL</label>
                                        <input type="text"
                                            wire:model.defer="form.{{ $activeSection }}.cards.{{ $index }}.image_url"
                                            @disabled(!$canManage)
                                            placeholder="images/board/member.jpg or https://…"
                                            class="w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 disabled:bg-gray-100 disabled:text-gray-400">
                                        <p class="mt-1.5 text-xs text-gray-400">Accepts a relative public path or any
                                            absolute URL. Leave blank to show the default avatar.</p>
                                        @error('form.' . $activeSection . '.cards.' . $index . '.image_url')
                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="flex flex-col items-center justify-center gap-3 px-6 py-16 text-center">
                            <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gray-100">
                                <svg class="h-8 w-8 text-gray-400" fill="none" stroke="currentColor"
                                    stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <p class="text-base font-semibold text-gray-700">
                                {{ $activeSectionMeta['empty_state'] ?? 'No cards added yet.' }}</p>
                            <p class="text-sm text-gray-400">Add a card using the button above to start building this
                                public section.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- ══ FIELD-EDITOR MODE (all other sections) ══════════════════════════ --}}
        @else
            @foreach ($activeSectionMeta['groups'] as $groupIndex => $group)
                <section wire:key="public-content-group-{{ $activeSection }}-{{ $groupIndex }}"
                    class="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                    @if (!empty($group['title']))
                        <div class="border-b border-gray-100 bg-gray-50 px-6 py-4">
                            <h4 class="text-sm font-semibold text-gray-800">{{ $group['title'] }}</h4>
                            @if (!empty($group['description']))
                                <p class="mt-0.5 text-xs text-gray-500">{{ $group['description'] }}</p>
                            @endif
                        </div>
                    @endif

                    <div class="grid grid-cols-1 gap-5 p-6 lg:grid-cols-2">
                        @foreach ($group['fields'] as $field)
                            <div @class([
                                'hidden' => !empty($field['hidden']),
                                'lg:col-span-2' => ($field['columnSpan'] ?? 1) === 2,
                            ])>
                                <label
                                    class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $field['label'] }}</label>

                                @if (($field['control'] ?? 'text') === 'toggle')
                                    <label class="inline-flex cursor-pointer items-center gap-3 @disabled(!$canManage)">
                                        <span class="text-sm font-medium @class([
                                            'text-emerald-700' => (bool) data_get($form, $activeSection . '.' . $field['path']),
                                            'text-gray-500' => !(bool) data_get($form, $activeSection . '.' . $field['path']),
                                        ])">
                                            {{ (bool) data_get($form, $activeSection . '.' . $field['path']) ? 'Enabled' : 'Disabled' }}
                                        </span>
                                        <div class="relative">
                                            <input type="checkbox"
                                                wire:model.live="form.{{ $activeSection }}.{{ $field['path'] }}"
                                                @disabled(!$canManage) class="peer sr-only">
                                            <div
                                                class="h-6 w-11 rounded-full border-2 border-transparent bg-gray-200 transition-colors peer-checked:bg-emerald-500 peer-focus:ring-4 peer-focus:ring-emerald-200 peer-disabled:opacity-50">
                                            </div>
                                            <div
                                                class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform peer-checked:translate-x-5">
                                            </div>
                                        </div>
                                    </label>
                                @elseif (($field['control'] ?? 'text') === 'textarea')
                                    <textarea rows="{{ $field['rows'] ?? 4 }}" wire:model.defer="form.{{ $activeSection }}.{{ $field['path'] }}"
                                        @disabled(!$canManage)
                                        class="w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 disabled:bg-gray-100 disabled:text-gray-400"></textarea>
                                @else
                                    <input type="{{ $field['control'] ?? 'text' }}"
                                        wire:model.defer="form.{{ $activeSection }}.{{ $field['path'] }}"
                                        @disabled(!$canManage)
                                        class="w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 disabled:bg-gray-100 disabled:text-gray-400">
                                @endif

                                @if (!empty($field['description']))
                                    <p class="mt-1.5 text-xs text-gray-400">{{ $field['description'] }}</p>
                                @endif

                                @error('form.' . $activeSection . '.' . $field['path'])
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        @endif
    @endif

    {{-- ── Bottom save bar ──────────────────────────────────────────────────── --}}
    @if ($canManage)
        <div class="flex justify-end rounded-2xl border border-gray-200 bg-white px-6 py-4 shadow-sm">
            <button type="button" wire:click="save" wire:loading.attr="disabled"
                class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700 focus:outline-none focus:ring-4 focus:ring-emerald-200 disabled:opacity-60">
                <svg wire:loading wire:target="save" class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg"
                    fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                        stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a8 8 0 00-8 8h4z">
                    </path>
                </svg>
                <svg wire:loading.remove wire:target="save" class="h-4 w-4" fill="none" stroke="currentColor"
                    stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <span wire:loading.remove wire:target="save">Save changes</span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
        </div>
    @endif
</div>
