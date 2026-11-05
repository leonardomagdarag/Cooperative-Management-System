<?php

use App\Support\SiteContentCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const OLD_COPY = "Founded with a vision to strengthen ottlers Credit Cooperative is dedicated to empowering our members through financial sur community, we've been serving our members with integrity, innovation, and unwavering commitment to their financial well-being.";

    private const NEW_COPY = "Founded with a vision to strengthen our community, Settlers Credit Cooperative is dedicated to empowering our members through financial services. We've been serving our members with integrity, innovation, and unwavering commitment to their financial well-being.";

    public function up(): void
    {
        $updated = DB::table('site_contents')
            ->where('section', 'about')
            ->where('key', 'about2')
            ->where('value', self::OLD_COPY)
            ->update(['value' => self::NEW_COPY]);
        if ($updated) {
            $this->forgetContentCache();
        }
    }

    public function down(): void
    {
        $updated = DB::table('site_contents')
            ->where('section', 'about')
            ->where('key', 'about2')
            ->where('value', self::NEW_COPY)
            ->update(['value' => self::OLD_COPY]);
        if ($updated) {
            $this->forgetContentCache();
        }
    }

    private function forgetContentCache(): void
    {
        Cache::forget('site_content.about.about2');
        Cache::forget('site_content.section.about');
        Cache::forget(SiteContentCatalog::CACHE_KEY);
    }
};
