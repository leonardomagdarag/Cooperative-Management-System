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
        $sections = SiteContentCatalog::editorSections();
        $form = SiteContentCatalog::editorContent();

        return response()->json([
            'sections' => $sections,
            'form' => $form,
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
            'can_manage' => (bool) Auth::user()?->hasRole('admin'),
            'turnstile_site_key' => config('services.turnstile.site_key'),
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->hasRole('admin'), 403);

        $validated = $request->validate($this->rules());

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
}
