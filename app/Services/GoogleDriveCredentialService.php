<?php

namespace App\Services;

use App\Models\AppCredential;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class GoogleDriveCredentialService
{
    public const KEY_CLIENT_ID = 'gdrive_client_id';

    public const KEY_CLIENT_SECRET = 'gdrive_client_secret';

    public const KEY_REFRESH_TOKEN = 'gdrive_refresh_token';

    public const KEY_FOLDER = 'gdrive_folder';

    public const KEY_TEAM_DRIVE_ID = 'gdrive_team_drive_id';

    private const VALID_INPUT_KEYS = [
        'client_id' => self::KEY_CLIENT_ID,
        'client_secret' => self::KEY_CLIENT_SECRET,
        'refresh_token' => self::KEY_REFRESH_TOKEN,
        'folder' => self::KEY_FOLDER,
        'team_drive_id' => self::KEY_TEAM_DRIVE_ID,
    ];

    /**
     * The stored (decrypted) value for a key, or null when it has not been
     * saved in the database yet. Failures to reach the database (e.g. during
     * early bootstrap) degrade gracefully to "not stored".
     */
    private function stored(string $key): ?string
    {
        try {
            $credential = AppCredential::query()->where('key', $key)->first();

            return $credential?->value !== null ? (string) $credential->value : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Resolve a credential: the database value (if saved) wins over the
     * .env fallback, preserving backward compatibility with GOOGLE_DRIVE_*.
     */
    public function get(string $key, mixed $envFallback = null): ?string
    {
        $stored = $this->stored($key);

        if ($stored !== null) {
            return $stored;
        }

        if (is_string($envFallback) && $envFallback !== '') {
            return $envFallback;
        }

        return null;
    }

    public function clientId(): ?string
    {
        return $this->get(self::KEY_CLIENT_ID, config('filesystems.disks.google.clientId'));
    }

    public function clientSecret(): ?string
    {
        return $this->get(self::KEY_CLIENT_SECRET, config('filesystems.disks.google.clientSecret'));
    }

    public function refreshToken(): ?string
    {
        return $this->get(self::KEY_REFRESH_TOKEN, config('filesystems.disks.google.refreshToken'));
    }

    public function folder(): ?string
    {
        $folder = $this->get(self::KEY_FOLDER, config('filesystems.disks.google.folder'));

        return $folder !== null && $folder !== '' ? $folder : null;
    }

    public function teamDriveId(): ?string
    {
        return $this->get(self::KEY_TEAM_DRIVE_ID, config('filesystems.disks.google.teamDriveId'));
    }

    public function isConfigured(): bool
    {
        return (bool) $this->clientId()
            && (bool) $this->clientSecret()
            && (bool) $this->refreshToken();
    }

    /**
     * Persist credentials. Blank values are left untouched so an admin never
     * has to re-type (and re-expose) a secret just to change the folder.
     */
    public function store(array $values): void
    {
        foreach (self::VALID_INPUT_KEYS as $inputKey => $credentialKey) {
            $value = $values[$inputKey] ?? null;

            if ($value === null || trim((string) $value) === '') {
                continue;
            }

            AppCredential::query()->updateOrCreate(
                ['key' => $credentialKey],
                ['value' => trim((string) $value), 'label' => $this->labelFor($credentialKey)],
            );
        }
    }

    /**
     * Remove all stored Google Drive credentials, falling back to any
     * GOOGLE_DRIVE_* values still present in .env.
     */
    public function clear(): void
    {
        AppCredential::query()->whereIn('key', array_values(self::VALID_INPUT_KEYS))->delete();
    }

    /**
     * Non-secret overview for the UI. Secret values are never returned.
     */
    public function summary(): array
    {
        return [
            'configured' => $this->isConfigured(),
            'folder' => $this->folder(),
            'team_drive_id' => $this->teamDriveId(),
            'stored' => [
                'client_id' => $this->stored(self::KEY_CLIENT_ID) !== null,
                'client_secret' => $this->stored(self::KEY_CLIENT_SECRET) !== null,
                'refresh_token' => $this->stored(self::KEY_REFRESH_TOKEN) !== null,
                'folder' => $this->stored(self::KEY_FOLDER) !== null,
                'team_drive_id' => $this->stored(self::KEY_TEAM_DRIVE_ID) !== null,
            ],
        ];
    }

    /**
     * Verify the current credentials by listing the top level of the Drive.
     */
    public function testConnection(): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Google Drive is not configured. Provide the client ID, secret and refresh token first.');
        }

        try {
            // A stored disk instance would use stale credentials after a save;
            // forcing a fresh resolution picks up the latest database values.
            Storage::forgetDisk('google');
            Storage::disk('google')->files('/');
        } catch (Throwable $e) {
            throw new RuntimeException('Could not reach Google Drive: '.$e->getMessage(), 0, $e);
        }
    }

    private function labelFor(string $key): string
    {
        return match ($key) {
            self::KEY_CLIENT_ID => 'Google Drive client ID',
            self::KEY_CLIENT_SECRET => 'Google Drive client secret',
            self::KEY_REFRESH_TOKEN => 'Google Drive refresh token',
            self::KEY_FOLDER => 'Google Drive folder',
            self::KEY_TEAM_DRIVE_ID => 'Google Drive team drive ID',
            default => $key,
        };
    }
}
