<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use App\Support\HelpContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FaqController extends Controller
{
    public function index(): JsonResponse
    {
        $content = HelpContent::current();

        return response()->json([
            'data' => $content['categories'],
            'content' => $content['content'],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('settings.update'), 403);

        $validated = $request->validate($this->rules());

        $content = [
            'header_description' => HelpContent::cleanText($validated['content']['header_description'] ?? ''),
            'callout_title' => HelpContent::cleanText($validated['content']['callout_title'] ?? ''),
            'callout_description' => HelpContent::cleanText($validated['content']['callout_description'] ?? ''),
        ];

        $categories = HelpContent::sanitizeTree($validated['categories']);

        HelpContent::save($content, $categories);

        app(AuditLogService::class)->log(
            'knowledge_base.updated',
            'Help & Support knowledge base updated.',
            'settings',
            null,
            Auth::user()
        );

        return response()->json(['message' => 'Help & Support content updated successfully.']);
    }

    private function rules(): array
    {
        return [
            'content.header_description' => 'nullable|string|max:1000',
            'content.callout_title' => 'nullable|string|max:255',
            'content.callout_description' => 'nullable|string|max:1000',
            'categories' => 'required|array|max:25',
            'categories.*.id' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_-]+$/', 'distinct'],
            'categories.*.label' => 'required|string|max:120',
            'categories.*.icon' => 'nullable|string|max:64',
            'categories.*.description' => 'nullable|string|max:500',
            'categories.*.faqs' => 'array|max:100',
            'categories.*.faqs.*.q' => 'required|string|max:500',
            'categories.*.faqs.*.a' => 'required|string|max:5000',
            'categories.*.faqs.*.tags' => 'nullable|array|max:15',
            'categories.*.faqs.*.tags.*' => 'string|max:40',
        ];
    }
}
