<?php

namespace App\Livewire\Content;

use App\Models\SiteContent;
use App\Support\SiteContentCatalog;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class PublicContent extends Component
{
    public array $form = [];

    public string $activeSection = 'home';

    public function mount(): void
    {
        abort_unless(Auth::check(), 403);

        $sections = SiteContentCatalog::editorSections();

        if ($sections !== []) {
            $this->activeSection = array_key_first($sections);
        }

        $this->form = SiteContentCatalog::editorContent();

        $this->ensureCardSections();
    }

    public function setActiveSection(string $section): void
    {
        if (array_key_exists($section, SiteContentCatalog::editorSections())) {
            $this->activeSection = $section;
        }
    }

    public function updatedActiveSection(string $section): void
    {
        if (! array_key_exists($section, SiteContentCatalog::editorSections())) {
            $this->activeSection = array_key_first(SiteContentCatalog::editorSections()) ?? 'home';
        }
    }

    public function addCard(string $section): void
    {
        abort_unless(Auth::user()?->can('settings.update'), 403);

        if (! $this->sectionUsesCards($section)) {
            return;
        }

        $cards = data_get($this->form, $section . '.cards', []);
        $cards[] = $this->emptyCard();

        data_set($this->form, $section . '.cards', array_values($cards));
    }

    public function removeCard(string $section, int $index): void
    {
        abort_unless(Auth::user()?->can('settings.update'), 403);

        if (! $this->sectionUsesCards($section)) {
            return;
        }

        $cards = data_get($this->form, $section . '.cards', []);

        if (! array_key_exists($index, $cards)) {
            return;
        }

        unset($cards[$index]);

        data_set($this->form, $section . '.cards', array_values($cards));
    }

    public function save(): void
    {
        abort_unless(Auth::user()?->can('settings.update'), 403);

        $validated = $this->validate($this->rules());

        DB::transaction(function () use ($validated): void {
            foreach (SiteContentCatalog::recordsFromForm($validated['form']) as $record) {
                SiteContent::query()->updateOrCreate(
                    ['section' => $record['section'], 'key' => $record['key']],
                    Arr::except($record, ['section', 'key'])
                );
            }
        });

        $this->form = SiteContentCatalog::editorContent();

        $this->ensureCardSections();

        $this->dispatch('toast', [
            'message' => 'Public content updated successfully.',
            'type' => 'success',
        ]);
    }

    public function goToSettings(): void
    {
        $this->dispatch('sectionChanged', 'settings');
    }

    public function render()
    {
        $sections = SiteContentCatalog::editorSections();

        return view('livewire.content.public-content', [
            'sections' => $sections,
            'activeSectionMeta' => $sections[$this->activeSection] ?? reset($sections),
            'canManage' => (bool) Auth::user()?->can('settings.update'),
        ]);
    }

    private function rules(): array
    {
        $rules = [];

        foreach (SiteContentCatalog::fieldDefinitions() as $field) {
            $ruleKey = 'form.' . $field['section'] . '.' . $field['path'];
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
            $rules['form.' . $section . '.cards.*.name'] = 'nullable|string|max:255';
            $rules['form.' . $section . '.cards.*.position'] = 'nullable|string|max:255';
            $rules['form.' . $section . '.cards.*.image_url'] = 'nullable|string|max:2048';
        }

        return $rules;
    }

    private function ensureCardSections(): void
    {
        foreach (['board', 'officers'] as $section) {
            if (! $this->sectionUsesCards($section)) {
                continue;
            }

            $cards = data_get($this->form, $section . '.cards', []);

            if (! is_array($cards)) {
                $cards = [];
            }

            $cards = array_map(fn($card) => [
                'name' => (string) data_get($card, 'name', ''),
                'position' => (string) data_get($card, 'position', ''),
                'image_url' => (string) data_get($card, 'image_url', ''),
            ], $cards);

            data_set($this->form, $section . '.cards', array_values($cards));
            data_set($this->form, $section . '.is_visible', (bool) data_get($this->form, $section . '.is_visible', true));
        }
    }

    private function sectionUsesCards(string $section): bool
    {
        return (SiteContentCatalog::editorSections()[$section]['editor'] ?? null) === 'cards';
    }

    private function emptyCard(): array
    {
        return [
            'name' => '',
            'position' => '',
            'image_url' => '',
        ];
    }
}
