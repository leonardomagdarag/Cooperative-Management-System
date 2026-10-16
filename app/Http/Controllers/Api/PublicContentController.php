<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SiteContent;
use App\Models\SystemSetting;
use App\Services\AuditLogService;
use App\Services\CooperativeMetricsService;
use App\Support\HtmlSanitizer;
use App\Support\SiteContentCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PublicContentController extends Controller
{
    public function index(): JsonResponse
    {
        $canManage = (bool) Auth::user()?->hasRole('admin');

        $payload = [
            'form' => SiteContentCatalog::editorContent(),
            'metrics' => app(CooperativeMetricsService::class)->publicMetrics(),
            'availability' => [
                'registration' => [
                    'global' => (bool) SystemSetting::get('service_registration_enabled', true),
                    'member' => (bool) SystemSetting::get('service_registration_member_enabled', true),
                ],
                'login' => [
                    'global' => (bool) SystemSetting::get('service_login_enabled', true),
                    'member' => (bool) SystemSetting::get('service_login_member_enabled', true),
                    'officer' => (bool) SystemSetting::get('service_login_officer_enabled', true),
                ],
            ],
            'can_manage' => $canManage,
            'turnstile_site_key' => config('services.turnstile.site_key'),
        ];

        // The editor schema (field definitions, groups, controls) is only needed
        // by the admin editor. Never ship it to unauthenticated visitors.
        if ($canManage) {
            $payload['sections'] = SiteContentCatalog::editorSections();
        }

        return response()->json($payload);
    }

    public function save(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->hasRole('admin'), 403);

        $validated = $request->validate($this->rules(), $this->messages(), $this->attributes());

        DB::transaction(function () use ($validated) {
            foreach (SiteContentCatalog::recordsFromForm($validated['form']) as $record) {
                $data = Arr::except($record, ['section', 'key']);

                if (($record['type'] ?? 'text') === 'html') {
                    $data['value'] = HtmlSanitizer::sanitize($data['value']);
                } elseif (is_string($data['value'] ?? null)) {
                    $data['value'] = trim(strip_tags($data['value']));
                } elseif (is_array($data['value'] ?? null)) {
                    $data['value'] = array_map(function ($item) {
                        if (is_string($item)) {
                            return trim(strip_tags($item));
                        }
                        if (is_array($item)) {
                            return array_map(fn ($val) => is_string($val) ? trim(strip_tags($val)) : $val, $item);
                        }

                        return $item;
                    }, $data['value']);
                }

                SiteContent::query()->updateOrCreate(
                    ['section' => $record['section'], 'key' => $record['key']],
                    $data
                );
            }
        });

        CooperativeMetricsService::forget();

        app(AuditLogService::class)->log(
            'public_content.updated',
            'Public website content updated.',
            'public_content',
            null,
            Auth::user(),
            ['sections' => array_keys($validated['form'] ?? [])],
        );

        return response()->json(['message' => 'Public content updated successfully.']);
    }

    private function rules(): array
    {
        $rules = [];

        foreach (SiteContentCatalog::fieldDefinitions() as $field) {
            $ruleKey = 'form.'.$field['section'].'.'.$field['path'];
            $storage = $field['storage'] ?? 'text';
            $control = $field['control'] ?? 'text';

            $rules[$ruleKey] = match (true) {
                $storage === 'boolean' => 'boolean',
                $storage === 'json' => 'nullable|array',
                $control === 'email' => 'nullable|email|max:255',
                default => 'nullable|string',
            };
        }

        foreach (['board', 'officers'] as $section) {
            $rules['form.'.$section.'.cards.*.name'] = 'nullable|string|max:255';
            $rules['form.'.$section.'.cards.*.position'] = 'nullable|string|max:255';
            $rules['form.'.$section.'.cards.*.image_url'] = 'nullable|string|max:2048';
        }

        $rules['form.home.founding_year'] = 'nullable|integer|min:1900|max:'.(int) date('Y');

        return $rules;
    }

    /**
     * Friendly messages for rules whose default wording exposes the raw key.
     *
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'form.home.founding_year.integer' => 'The founding year must be a whole number.',
            'form.home.founding_year.min' => 'The founding year must be at least :min.',
            'form.home.founding_year.max' => 'The founding year must not be greater than :max.',
        ];
    }

    /**
     * Map the generic `form.{section}.{path}` rule keys to human labels so
     * validation messages read naturally instead of leaking the internal key.
     *
     * @return array<string, string>
     */
    private function attributes(): array
    {
        $attributes = [];

        foreach (SiteContentCatalog::fieldDefinitions() as $field) {
            $attributes['form.'.$field['section'].'.'.$field['path']] = strtolower($field['label'] ?? $field['path']);
        }

        foreach (['board', 'officers'] as $section) {
            $attributes['form.'.$section.'.cards.*.name'] = 'name';
            $attributes['form.'.$section.'.cards.*.position'] = 'position';
            $attributes['form.'.$section.'.cards.*.image_url'] = 'image URL';
        }

        return $attributes;
    }
}
