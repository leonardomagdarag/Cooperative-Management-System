<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SiteContent;
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
            'can_manage' => Auth::user()?->can('settings.update'),
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->can('settings.update'), 403);

        $form = $request->input('form', []);

        DB::transaction(function () use ($form) {
            foreach (SiteContentCatalog::recordsFromForm($form) as $record) {
                SiteContent::query()->updateOrCreate(
                    ['section' => $record['section'], 'key' => $record['key']],
                    Arr::except($record, ['section', 'key'])
                );
            }
        });

        return response()->json(['message' => 'Public content updated successfully.']);
    }
}
