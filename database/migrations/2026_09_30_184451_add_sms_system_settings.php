<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $settings = [
            [
                'key' => 'sms_service_enabled',
                'value' => '0',
                'type' => 'boolean',
                'group' => 'sms',
                'label' => 'SMS Service Enabled',
                'description' => 'Master toggle for all SMS delivery via TextBee.',
            ],
            [
                'key' => 'textbee_api_key',
                'value' => '',
                'type' => 'string',
                'group' => 'sms',
                'label' => 'TextBee API Key',
                'description' => 'API key from app.textbee.dev dashboard.',
            ],
            [
                'key' => 'textbee_device_id',
                'value' => '',
                'type' => 'string',
                'group' => 'sms',
                'label' => 'TextBee Device ID',
                'description' => 'Optional: specific Android device to use for sending.',
            ],
        ];

        foreach ($settings as $s) {
            \App\Models\SystemSetting::firstOrCreate(
                ['key' => $s['key']],
                array_merge($s, ['is_editable' => true, 'requires_restart' => false])
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \App\Models\SystemSetting::whereIn('key', [
            'sms_service_enabled',
            'textbee_api_key',
            'textbee_device_id',
        ])->delete();
    }
};
