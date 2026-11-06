<?php

namespace Database\Seeders;

use App\Models\Loan;
use App\Models\LoanDocument;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Scanned paperwork attached to the loan files.
 *
 * The upload path is the same one LoanController uses (`loan-documents/{id}`
 * on the private disk), and the file is written alongside the row so the
 * document actually downloads instead of 404ing. A small placeholder image
 * stands in for the scanned page.
 *
 * Each document is matched on its loan and original name, so re-seeding
 * refreshes the file without adding a second row.
 */
class LoanDocumentSeeder extends Seeder
{
    /** 1x1 PNG used as the stand-in for a scanned page. */
    private const PLACEHOLDER_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    /**
     * The paperwork a couple of loans would carry.
     *
     * @var list<string>
     */
    private const DOCUMENTS = [
        'Signed-Loan-Agreement.png',
        'Valid-Government-ID.png',
    ];

    public function run(): void
    {
        $uploader = User::query()->where('user_number', 'USR000004')->first();

        $loans = Loan::query()
            ->whereIn('loan_status', ['active', 'paid_off'])
            ->orderBy('id')
            ->limit(4)
            ->get();

        $created = 0;

        foreach ($loans as $loan) {
            foreach (self::DOCUMENTS as $original) {
                $exists = LoanDocument::query()
                    ->where('loan_id', $loan->id)
                    ->where('original_name', $original)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $bytes = base64_decode(self::PLACEHOLDER_PNG);
                $path = 'loan-documents/'.$loan->id.'/'.strtolower(str_replace(' ', '-', $original));

                Storage::disk('local')->put($path, $bytes);

                LoanDocument::create([
                    'loan_id' => $loan->id,
                    'original_name' => $original,
                    'storage_path' => $path,
                    'mime_type' => 'image/png',
                    'file_size' => strlen($bytes),
                    'uploaded_by' => $uploader?->id,
                ]);

                $created++;
            }
        }

        $this->command?->info('Attached '.$created.' loan documents.');
    }
}
