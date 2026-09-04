<?php

namespace App\Providers;

use App\Services\GoogleDriveCredentialService;
use Google\Client;
use Google\Service\Drive;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use Masbug\Flysystem\GoogleDriveAdapter;

class GoogleDriveServiceProvider extends ServiceProvider
{
    /**
     * Register the custom "google" filesystem driver used by the cloud backup
     * tier. Credentials resolve at runtime through GoogleDriveCredentialService,
     * so values admins save from Settings → Backup & Restore (stored encrypted
     * in the app_credentials table) take effect immediately on the next disk
     * resolution, with GOOGLE_DRIVE_* .env values kept as a fallback.
     */
    public function register(): void
    {
        Storage::extend('google', function ($app, $config) {
            $credentials = $app->make(GoogleDriveCredentialService::class);

            $options = [];

            if ($credentials->teamDriveId()) {
                $options['teamDriveId'] = $credentials->teamDriveId();
            }

            if (! empty($config['sharedFolderId'] ?? null)) {
                $options['sharedFolderId'] = $config['sharedFolderId'];
            }

            $client = new Client;
            $client->setClientId($credentials->clientId());
            $client->setClientSecret($credentials->clientSecret());
            $client->refreshToken($credentials->refreshToken());

            $service = new Drive($client);
            $adapter = new GoogleDriveAdapter($service, $credentials->folder() ?? '/', $options);

            return new FilesystemAdapter(
                new Filesystem($adapter),
                $adapter,
            );
        });
    }
}
