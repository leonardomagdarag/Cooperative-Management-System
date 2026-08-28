<?php

namespace Database\Seeders;

use App\Models\SiteContent;
use App\Support\SiteContentCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class SiteContentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $existingContent = SiteContentCatalog::publicContent();

        foreach (SiteContentCatalog::seedRecords() as $content) {
            $record = SiteContent::query()->firstOrNew([
                'section' => $content['section'],
                'key' => $content['key'],
            ]);

            $record->fill(Arr::except($content, ['section', 'key', 'value']));

            if (! $record->exists) {
                $resolvedValue = data_get($existingContent, $content['section'].'.'.$content['key']);

                $record->value = SiteContentCatalog::serializeForStorage(
                    $resolvedValue !== null ? $resolvedValue : $content['value'],
                    $content['type']
                );
            }

            $record->save();
        }

        $this->command->info('Site content seeded successfully!');
    }
}
