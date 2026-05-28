@php
    $state = $state ?? 'activePolicyModal';
    $termsContent = trim((string) ($termsContent ?? data_get($publicContent ?? [], 'policies.terms_and_conditions', '')));
    $privacyContent = trim((string) ($privacyContent ?? data_get($publicContent ?? [], 'policies.privacy_policy', '')));
@endphp

<div x-show="{{ $state }} !== null" x-transition.opacity class="fixed inset-0" style="display: none; z-index: 9999;">
    <div class="absolute inset-0 bg-gray-950/60" @click="{{ $state }} = null"></div>

    <div class="relative flex min-h-full items-center justify-center p-4">
        <div class="w-full max-w-3xl overflow-hidden rounded-3xl bg-white shadow-2xl">
            <div class="flex items-start justify-between border-b border-gray-200 px-6 py-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Settlers Credit Cooperative</p>
                    <h3 class="mt-1 text-xl font-semibold text-gray-900" x-text="{{ $state }} === 'privacy' ? 'Privacy Policy' : 'Terms and Conditions'"></h3>
                </div>
                <button type="button" @click="{{ $state }} = null" class="rounded-full p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="max-h-[70vh] overflow-y-auto px-6 py-5 text-sm leading-6 text-gray-700">
                <div x-show="{{ $state }} === 'terms'" class="space-y-3 [&_h3]:text-lg [&_h3]:font-semibold [&_h3]:text-gray-900 [&_h4]:mt-5 [&_h4]:text-sm [&_h4]:font-semibold [&_h4]:uppercase [&_h4]:tracking-wide [&_h4]:text-gray-900 [&_p]:text-gray-700">
                    {!! $termsContent !== '' ? $termsContent : '<p>Terms and conditions are not available yet.</p>' !!}
                </div>

                <div x-show="{{ $state }} === 'privacy'" class="space-y-3 [&_h3]:text-lg [&_h3]:font-semibold [&_h3]:text-gray-900 [&_h4]:mt-5 [&_h4]:text-sm [&_h4]:font-semibold [&_h4]:uppercase [&_h4]:tracking-wide [&_h4]:text-gray-900 [&_p]:text-gray-700">
                    {!! $privacyContent !== '' ? $privacyContent : '<p>Privacy policy content is not available yet.</p>' !!}
                </div>
            </div>

            <div class="flex justify-end border-t border-gray-200 px-6 py-4">
                <button type="button" @click="{{ $state }} = null" class="inline-flex items-center rounded-full bg-emerald-700 px-5 py-2 text-sm font-semibold text-white transition hover:bg-emerald-800">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>