<?php

namespace App\Support;

use App\Models\SiteContent;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SiteContentCatalog
{
    public const CACHE_KEY = 'site_content.public_tree';

    public static function editorSections(): array
    {
        return [
            'home' => [
                'label' => 'Home',
                'description' => 'Update the landing page hero copy and the high-level cooperative stats.',
                'groups' => [
                    [
                        'title' => 'Hero',
                        'fields' => [
                            [
                                'path' => 'hero_image',
                                'label' => 'Hero image',
                                'description' => 'Background image shown on the public home page.',
                                'control' => 'image',
                                'upload_key' => 'home.hero_image',
                                'default' => '/images/hero_banner.jpg',
                            ],
                            [
                                'path' => 'hero_subtitle',
                                'label' => 'Hero subtitle',
                                'description' => 'Landing page subtitle shown below the cooperative name.',
                                'control' => 'textarea',
                                'rows' => 3,
                                'columnSpan' => 2,
                                'storage' => 'html',
                            ],
                        ],
                    ],
                    [
                        'title' => 'Stats',
                        'description' => 'Control the public metrics cards and switch them between live cooperative figures and the manual values below.',
                        'fields' => [
                            [
                                'path' => 'stats_visible',
                                'label' => 'Show metrics section',
                                'description' => 'Turn this off to hide the metrics cards on the public home page.',
                                'control' => 'toggle',
                                'storage' => 'boolean',
                                'default' => true,
                            ],
                            [
                                'path' => 'stats_use_cooperative',
                                'label' => 'Use cooperative metrics',
                                'description' => 'When on, the public page shows live figures from the cooperative\'s data. When off, the manual values below are used.',
                                'control' => 'toggle',
                                'storage' => 'boolean',
                                'default' => false,
                            ],
                            [
                                'path' => 'founding_year',
                                'label' => 'Founding year',
                                'description' => 'Used to compute years of service when live cooperative metrics are on.',
                                'control' => 'number',
                                'default' => '2016',
                            ],
                            [
                                'path' => 'active_members',
                                'label' => 'Active members',
                                'description' => 'Manual active member count. Used when live cooperative metrics are off.',
                            ],
                            [
                                'path' => 'total_assets',
                                'label' => 'Total assets',
                                'description' => 'Manual total assets callout. Used when live cooperative metrics are off.',
                            ],
                            [
                                'path' => 'years_service',
                                'label' => 'Years of service',
                                'description' => 'Manual years of service statistic. Used when live cooperative metrics are off.',
                            ],
                            [
                                'path' => 'service_done',
                                'label' => 'Services done',
                                'description' => 'Manual services delivered statistic. Used when live cooperative metrics are off.',
                            ],
                        ],
                    ],
                ],
            ],
            'about' => [
                'label' => 'About',
                'description' => 'Maintain the cooperative story, mission, vision, and values used across the public site.',
                'groups' => [
                    [
                        'title' => 'Overview',
                        'fields' => [
                            [
                                'path' => 'about_image',
                                'label' => 'About Us image',
                                'description' => 'Image displayed beside the cooperative history on the About Us page.',
                                'control' => 'image',
                                'upload_key' => 'about.about_image',
                                'default' => '/images/hero_banner.jpg',
                            ],
                            [
                                'path' => 'about1',
                                'label' => 'About paragraph 1',
                                'description' => 'Primary introductory description.',
                                'control' => 'textarea',
                                'rows' => 4,
                                'columnSpan' => 2,
                            ],
                            [
                                'path' => 'about2',
                                'label' => 'About paragraph 2',
                                'description' => 'Secondary description shown on home and about pages.',
                                'control' => 'textarea',
                                'rows' => 4,
                                'columnSpan' => 2,
                            ],
                            [
                                'path' => 'history',
                                'label' => 'History',
                                'description' => 'Detailed history block for the about page.',
                                'control' => 'textarea',
                                'rows' => 6,
                                'columnSpan' => 2,
                                'storage' => 'html',
                            ],
                        ],
                    ],
                    [
                        'title' => 'Mission and Vision',
                        'fields' => [
                            [
                                'path' => 'mission',
                                'label' => 'Mission',
                                'description' => 'Mission statement.',
                                'control' => 'textarea',
                                'rows' => 5,
                                'columnSpan' => 2,
                            ],
                            [
                                'path' => 'vision',
                                'label' => 'Vision',
                                'description' => 'Vision statement.',
                                'control' => 'textarea',
                                'rows' => 5,
                                'columnSpan' => 2,
                            ],
                        ],
                    ],
                    [
                        'title' => 'Values',
                        'description' => 'Each value renders as a bullet on the about page.',
                        'fields' => [
                            ['path' => 'values.0.title', 'label' => 'Value 1 title', 'description' => 'First value title.'],
                            ['path' => 'values.0.description', 'label' => 'Value 1 description', 'description' => 'First value supporting description.'],
                            ['path' => 'values.1.title', 'label' => 'Value 2 title', 'description' => 'Second value title.'],
                            ['path' => 'values.1.description', 'label' => 'Value 2 description', 'description' => 'Second value supporting description.'],
                            ['path' => 'values.2.title', 'label' => 'Value 3 title', 'description' => 'Third value title.'],
                            ['path' => 'values.2.description', 'label' => 'Value 3 description', 'description' => 'Third value supporting description.'],
                            ['path' => 'values.3.title', 'label' => 'Value 4 title', 'description' => 'Fourth value title.'],
                            ['path' => 'values.3.description', 'label' => 'Value 4 description', 'description' => 'Fourth value supporting description.'],
                            ['path' => 'values.4.title', 'label' => 'Value 5 title', 'description' => 'Fifth value title.'],
                            ['path' => 'values.4.description', 'label' => 'Value 5 description', 'description' => 'Fifth value supporting description.'],
                        ],
                    ],
                ],
            ],
            'login' => [
                'label' => 'Login',
                'description' => 'Choose the image shown behind the sign-in form.',
                'groups' => [
                    [
                        'title' => 'Background',
                        'fields' => [
                            [
                                'path' => 'login_image',
                                'label' => 'Login page image',
                                'description' => 'Background image displayed behind the login form.',
                                'control' => 'image',
                                'upload_key' => 'login.login_image',
                                'default' => '/images/hero_banner.jpg',
                            ],
                        ],
                    ],
                ],
            ],
            'board' => [
                'label' => 'Board of Directors',
                'description' => 'Manage board member cards and control whether the section appears on the public about page.',
                'editor' => 'cards',
                'toggle_label' => 'Show Board of Directors section',
                'toggle_description' => 'Turn this on to display the board section on the public about page.',
                'card_singular' => 'Board Member',
                'empty_state' => 'No board members have been added yet.',
                'groups' => [
                    [
                        'title' => 'Visibility',
                        'fields' => [
                            [
                                'path' => 'is_visible',
                                'label' => 'Show section',
                                'description' => 'Display the board of directors section on the public about page.',
                                'control' => 'toggle',
                                'storage' => 'boolean',
                                'default' => true,
                            ],
                            [
                                'path' => 'cards',
                                'label' => 'Board cards',
                                'storage' => 'json',
                                'default' => [],
                                'hidden' => true,
                            ],
                        ],
                    ],
                ],
            ],
            'officers' => [
                'label' => 'Officers',
                'description' => 'Manage officer cards and control whether the section appears on the public about page.',
                'editor' => 'cards',
                'toggle_label' => 'Show Officers section',
                'toggle_description' => 'Turn this on to display the officers section on the public about page.',
                'card_singular' => 'Officer',
                'empty_state' => 'No officers have been added yet.',
                'groups' => [
                    [
                        'title' => 'Visibility',
                        'fields' => [
                            [
                                'path' => 'is_visible',
                                'label' => 'Show section',
                                'description' => 'Display the officers section on the public about page.',
                                'control' => 'toggle',
                                'storage' => 'boolean',
                                'default' => true,
                            ],
                            [
                                'path' => 'cards',
                                'label' => 'Officer cards',
                                'storage' => 'json',
                                'default' => [],
                                'hidden' => true,
                            ],
                        ],
                    ],
                ],
            ],
            'member_statements' => [
                'label' => 'Member Statements',
                'description' => 'Maintain the testimonial cards featured on the about page.',
                'groups' => [
                    ['title' => 'Statement 1', 'fields' => [['path' => 'statement1.name', 'label' => 'Member name', 'description' => 'First testimonial member name.'], ['path' => 'statement1.member_since', 'label' => 'Member since', 'description' => 'First testimonial membership year.'], ['path' => 'statement1.statement', 'label' => 'Statement', 'description' => 'First testimonial message.', 'control' => 'textarea', 'rows' => 4, 'columnSpan' => 2]]],
                    ['title' => 'Statement 2', 'fields' => [['path' => 'statement2.name', 'label' => 'Member name', 'description' => 'Second testimonial member name.'], ['path' => 'statement2.member_since', 'label' => 'Member since', 'description' => 'Second testimonial membership year.'], ['path' => 'statement2.statement', 'label' => 'Statement', 'description' => 'Second testimonial message.', 'control' => 'textarea', 'rows' => 4, 'columnSpan' => 2]]],
                    ['title' => 'Statement 3', 'fields' => [['path' => 'statement3.name', 'label' => 'Member name', 'description' => 'Third testimonial member name.'], ['path' => 'statement3.member_since', 'label' => 'Member since', 'description' => 'Third testimonial membership year.'], ['path' => 'statement3.statement', 'label' => 'Statement', 'description' => 'Third testimonial message.', 'control' => 'textarea', 'rows' => 4, 'columnSpan' => 2]]],
                ],
            ],
            'news' => [
                'label' => 'News',
                'description' => 'Control the public news page hero and newsletter copy.',
                'groups' => [
                    ['title' => 'Hero', 'fields' => [['path' => 'hero_description', 'label' => 'Hero description', 'description' => 'Introductory text on the news page.', 'control' => 'textarea', 'rows' => 3, 'columnSpan' => 2]]],
                    ['title' => 'Newsletter', 'fields' => [['path' => 'newsletter.title', 'label' => 'Newsletter title', 'description' => 'Newsletter call-to-action title.'], ['path' => 'newsletter.privacy_note', 'label' => 'Privacy note', 'description' => 'Short privacy reassurance.'], ['path' => 'newsletter.description', 'label' => 'Newsletter description', 'description' => 'Newsletter subscription helper text.', 'control' => 'textarea', 'rows' => 4, 'columnSpan' => 2]]],
                ],
            ],
            'events' => [
                'label' => 'Events',
                'description' => 'Update the supporting copy used above the events listing.',
                'groups' => [
                    ['title' => 'Events introduction', 'fields' => [['path' => 'events', 'label' => 'Events description', 'description' => 'Introductory description above the events cards.', 'control' => 'textarea', 'rows' => 4, 'columnSpan' => 2]]],
                ],
            ],
            'contact' => [
                'label' => 'Contact',
                'description' => 'Manage the contact block, office hours, and map embed used on the public pages.',
                'groups' => [
                    [
                        'title' => 'Contact details',
                        'fields' => [
                            ['path' => 'address', 'label' => 'Address', 'description' => 'Public office address.', 'control' => 'textarea', 'rows' => 3, 'columnSpan' => 2],
                            ['path' => 'phone', 'label' => 'Phone numbers', 'description' => 'Public contact number block.'],
                            ['path' => 'email', 'label' => 'Email address', 'description' => 'Public email address.', 'control' => 'email'],
                            ['path' => 'office_hours', 'label' => 'Office hours', 'description' => 'Office schedule shown in the public contact section.', 'control' => 'textarea', 'rows' => 3, 'columnSpan' => 2, 'storage' => 'html'],
                            ['path' => 'map_embed', 'label' => 'Map embed URL', 'description' => 'Google Maps iframe source URL.', 'control' => 'textarea', 'rows' => 4, 'columnSpan' => 2],
                        ],
                    ],
                ],
            ],
            'policies' => [
                'label' => 'Policies & Agreements',
                'description' => 'Maintain the public Terms and Conditions and Data Privacy Policy used on the website and during office enrollment.',
                'groups' => [
                    [
                        'title' => 'Terms and Conditions',
                        'fields' => [
                            [
                                'path' => 'terms_and_conditions',
                                'label' => 'Terms and Conditions',
                                'description' => 'Public terms and agreement copy shown in the footer and office enrollment.',
                                'control' => 'textarea',
                                'rows' => 18,
                                'columnSpan' => 2,
                                'storage' => 'html',
                            ],
                        ],
                    ],
                    [
                        'title' => 'Privacy Policy',
                        'fields' => [
                            [
                                'path' => 'privacy_policy',
                                'label' => 'Privacy Policy',
                                'description' => 'Public privacy policy copy shown in the footer and office enrollment.',
                                'control' => 'textarea',
                                'rows' => 18,
                                'columnSpan' => 2,
                                'storage' => 'html',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function editorSectionsWithCounts(): array
    {
        $sections = self::editorSections();

        foreach ($sections as $sectionKey => $section) {
            $sections[$sectionKey]['field_count'] = count(self::fieldsForSection($section));
        }

        return $sections;
    }

    public static function fieldDefinitions(): array
    {
        $definitions = [];

        foreach (self::editorSections() as $sectionKey => $section) {
            $order = 1;

            foreach (self::fieldsForSection($section) as $field) {
                $definitions[] = [
                    'section' => $sectionKey,
                    'order' => $field['order'] ?? $order,
                    ...$field,
                ];

                $order++;
            }
        }

        return $definitions;
    }

    public static function publicContent(): array
    {
        if (! Schema::hasTable('site_contents')) {
            return [];
        }

        return Cache::remember(self::CACHE_KEY, 3600, function () {
            $tree = [];

            SiteContent::query()
                ->where('is_active', true)
                ->orderBy('section')
                ->orderBy('order')
                ->get()
                ->each(function (SiteContent $content) use (&$tree): void {
                    Arr::set(
                        $tree,
                        $content->section.'.'.$content->key,
                        self::castStoredValue($content->value, $content->type)
                    );
                });

            return self::normalizeDynamicSections($tree);
        });
    }

    public static function editorContent(): array
    {
        return self::normalizeDynamicSections(array_replace_recursive(self::blankEditorContent(), self::publicContent()));
    }

    public static function seedRecords(): array
    {
        return self::recordsFromTree(self::seedTree());
    }

    public static function recordsFromForm(array $form): array
    {
        return self::recordsFromTree($form);
    }

    private static function blankEditorContent(): array
    {
        $tree = [];

        foreach (self::fieldDefinitions() as $field) {
            Arr::set($tree, $field['section'].'.'.$field['path'], self::defaultForField($field['section'], $field));
        }

        return $tree;
    }

    private static function recordsFromTree(array $tree): array
    {
        $records = [];

        foreach (self::fieldDefinitions() as $field) {
            $records[] = [
                'section' => $field['section'],
                'key' => $field['path'],
                'value' => self::prepareForStorage(data_get($tree, $field['section'].'.'.$field['path'])),
                'type' => $field['storage'] ?? 'text',
                'description' => $field['description'] ?? $field['label'],
                'order' => (int) $field['order'],
                'is_active' => true,
            ];
        }

        return $records;
    }

    private static function prepareForStorage(mixed $value): string
    {
        if (is_array($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            return $encoded === false ? '[]' : $encoded;
        }

        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    private static function castStoredValue(mixed $value, string $type): mixed
    {
        if ($type === 'json') {
            $decoded = json_decode((string) $value, true);

            return is_array($decoded) ? $decoded : [];
        }

        if ($type === 'boolean') {
            return filter_var($value, FILTER_VALIDATE_BOOL);
        }

        return (string) $value;
    }

    private static function fieldsForSection(array $section): array
    {
        $fields = [];

        foreach ($section['groups'] ?? [] as $group) {
            foreach ($group['fields'] ?? [] as $field) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    private static function defaultForField(string $section, array $field): mixed
    {
        if (array_key_exists('default', $field)) {
            return $field['default'];
        }

        return data_get(self::seedTree(), $section.'.'.$field['path'], '');
    }

    private static function normalizeDynamicSections(array $tree): array
    {
        $tree['board'] = self::normalizeCardSection(
            $tree['board'] ?? [],
            ['chairperson', 'vice_chairperson', 'treasurer', 'secretary']
        );

        $tree['officers'] = self::normalizeCardSection(
            $tree['officers'] ?? [],
            ['officer1', 'officer2', 'officer3', 'officer4']
        );

        return $tree;
    }

    private static function normalizeCardSection(array $section, array $legacyKeys): array
    {
        $section['is_visible'] = array_key_exists('is_visible', $section)
            ? (bool) $section['is_visible']
            : true;

        if (! array_key_exists('cards', $section) || ! is_array($section['cards'])) {
            $section['cards'] = self::legacyCardsFromSection($section, $legacyKeys);
        } else {
            $section['cards'] = collect($section['cards'])
                ->map(fn ($card) => self::normalizeCard($card))
                ->values()
                ->all();
        }

        return $section;
    }

    public static function serializeForStorage(mixed $value, string $type): string
    {
        return self::prepareForStorage($value, $type);
    }

    private static function legacyCardsFromSection(array $section, array $legacyKeys): array
    {
        return collect($legacyKeys)
            ->map(function (string $legacyKey) use ($section) {
                return self::normalizeCard([
                    'name' => data_get($section, $legacyKey.'.name', ''),
                    'position' => data_get($section, $legacyKey.'.title', ''),
                    'image_url' => data_get($section, $legacyKey.'.image_url', ''),
                ]);
            })
            ->filter(fn (array $card) => filled($card['name']) || filled($card['position']) || filled($card['image_url']))
            ->values()
            ->all();
    }

    private static function normalizeCard(mixed $card): array
    {
        return [
            'name' => (string) data_get($card, 'name', ''),
            'position' => (string) data_get($card, 'position', data_get($card, 'title', '')),
            'image_url' => (string) data_get($card, 'image_url', ''),
        ];
    }

    private static function seedTree(): array
    {
        return [
            'home' => [
                'hero_subtitle' => 'Homing Strength in members through financial excellence and community partnership',
                'hero_image' => '/images/hero_banner.jpg',
                'stats_visible' => true,
                'stats_use_cooperative' => false,
                'founding_year' => '2016',
                'active_members' => '567',
                'total_assets' => '₱2M+',
                'years_service' => '10',
                'service_done' => '10k+',
            ],
            'about' => [
                'about_image' => '/images/hero_banner.jpg',
                'about1' => 'Services and community support. Our mission is to provide accessible and affordable financial solutions that help our members achieve their goals.',
                'about2' => "Founded with a vision to strengthen our community, Settlers Credit Cooperative is dedicated to empowering our members through financial services. We've been serving our members with integrity, innovation, and unwavering commitment to their financial well-being.",
                'history' => 'Founded in 2016, Settlers Credit Cooperative (previously known as Southville 7 Credit Cooperative) has grown from a small group of community members into a trusted financial organization serving hundreds of members. <br><br> Over the years, we have expanded our services and strengthened our commitment to member satisfaction and community development. Our journey reflects our dedication to financial excellence and community empowerment. ',
                'mission' => 'Ang Settlers Credit Cooperative ay nagbibigay ng pangunahing serbisyo sa mga kasapi sa pamamagitan ng pagbibigay ng pagkakataon na maka-ipon at makapagpahiram sa mababang interest',
                'vision' => "Kooperatibang naghuhubog sa kakayahan ng Settlers Credit Cooperative sa pamamahala ng iba't ibang proyekto",
                'values' => [
                    ['title' => 'Integrity', 'description' => 'Honesty in all dealings'],
                    ['title' => 'Member-Centric', 'description' => 'Members first, always'],
                    ['title' => 'Community Focus', 'description' => 'Building together'],
                    ['title' => 'Innovation', 'description' => 'Continuous improvement'],
                    ['title' => 'Collaboration', 'description' => 'Strength in unity'],
                ],
            ],
            'login' => [
                'login_image' => '/images/hero_banner.jpg',
            ],
            'board' => [
                'is_visible' => true,
                'cards' => [
                    ['name' => 'Ricardo M. Villanueva', 'position' => 'Chairperson', 'image_url' => ''],
                    ['name' => 'Elena S. Mercado', 'position' => 'Vice-Chairperson', 'image_url' => ''],
                    ['name' => 'Benjamin R. Pascual', 'position' => 'Treasurer', 'image_url' => ''],
                    ['name' => 'Marissa T. Domingo', 'position' => 'Secretary', 'image_url' => ''],
                ],
            ],
            'officers' => [
                'is_visible' => true,
                'cards' => [
                    ['name' => 'Juan Dela Cruz', 'position' => 'General Manager', 'image_url' => ''],
                    ['name' => 'Maria Santos', 'position' => 'Operations Manager', 'image_url' => ''],
                    ['name' => 'Roberto Gonzales', 'position' => 'Finance Officer', 'image_url' => ''],
                    ['name' => 'Ana Reyes', 'position' => 'Member Relations Officer', 'image_url' => ''],
                ],
            ],
            'member_statements' => [
                'statement1' => ['name' => 'Pedro Martinez', 'member_since' => '2018', 'statement' => 'The cooperative has been instrumental in helping me start my small business. The loan process was straightforward, and the interest rates were very reasonable. I am grateful for their support.'],
                'statement2' => ['name' => 'Luisa Fernandez', 'member_since' => '2019', 'statement' => 'Being part of this cooperative has given me financial security. The savings programs have helped me build my emergency fund, and the staff is always helpful and supportive.'],
                'statement3' => ['name' => 'Carlos Ramos', 'member_since' => '2020', 'statement' => 'I appreciate the transparency and integrity of this cooperative. They truly care about their members\' financial well-being and provide excellent services.'],
            ],
            'news' => [
                'hero_description' => 'Stay informed with the latest news, announcements, and upcoming events from Settlers Credit Cooperative.',
                'newsletter' => [
                    'title' => 'Subscribe to Our Newsletter',
                    'description' => 'Stay updated with the latest news, announcements, and upcoming events. Subscribe to our newsletter and never miss important updates from Settlers Credit Cooperative.',
                    'privacy_note' => 'We respect your privacy. Unsubscribe at any time.',
                ],
            ],
            'events' => [
                'events' => 'Stay updated with the latest events and activities organized by Settlers Credit Cooperative. We are committed to fostering community engagement and providing opportunities for our members to connect and grow.',
            ],
            'contact' => [
                'address' => 'Sunlife Learning Center, Site 3, Brgy Sto Tomas, Calauan, Laguna, Philippines',
                'phone' => '09876543210 / 09123456789',
                'email' => 'settlerscoop@email.com',
                'office_hours' => 'Mon - Fri: 8:00 AM - 5:00 PM <br> Sat: 8:00 AM - 12:00 PM',
                'map_embed' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d15473.5517532458!2d121.34772396353148!3d14.172041997611268!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x33bd5ecbd56244b1%3A0xd9004ec1d8317075!2sSite%203%20Southville%207%2C%20Calauan%2C%20Laguna!5e0!3m2!1sen!2sph!4v1768522137639!5m2!1sen!2sph',
            ],
            'policies' => [
                'terms_and_conditions' => <<<'HTML'
<h3>Terms and Conditions</h3>
<p>By accessing and using the Cooperative Management System for Settlers Credit Cooperative, users agree to comply with the following terms and conditions.</p>
<h4>1. Authorized Use</h4>
<p>The system is intended solely for authorized cooperative personnel and registered members. Unauthorized access, misuse, or tampering with the system is strictly prohibited.</p>
<h4>2. User Responsibilities</h4>
<p>Users are responsible for maintaining the confidentiality of their account credentials, providing accurate and updated information, using the system only for legitimate cooperative-related activities, and logging out properly after each session. Users must not share their accounts with others.</p>
<h4>3. Prohibited Activities</h4>
<p>Users shall not attempt unauthorized access to the system, modify, delete, or manipulate records without permission, upload malicious software or harmful content, or use the system for fraudulent or illegal activities. Violations may result in suspension of access and possible legal action.</p>
<h4>4. Accuracy of Information</h4>
<p>Users are responsible for ensuring the accuracy of the information they submit. The cooperative is not liable for issues caused by incorrect or incomplete data provided by users.</p>
<h4>5. System Availability</h4>
<p>The cooperative aims to maintain continuous system availability; however, temporary interruptions may occur due to maintenance, technical issues, or unforeseen circumstances.</p>
<h4>6. Intellectual Property</h4>
<p>The Cooperative Management System, including its design, database structure, and functionalities, is the intellectual property of the developers and the cooperative. Unauthorized reproduction or distribution is prohibited.</p>
<h4>7. Account Suspension and Termination</h4>
<p>The cooperative reserves the right to suspend or terminate user accounts that violate these terms or engage in suspicious activities.</p>
<h4>8. Limitation of Liability</h4>
<p>The cooperative and system developers shall not be held liable for loss of data due to technical failures, unauthorized access caused by user negligence, system downtime or interruptions, or damages resulting from improper use of the system.</p>
<h4>9. Acceptance of Terms</h4>
<p>By using the system, users acknowledge that they have read, understood, and agreed to these Terms and Conditions.</p>
HTML,
                'privacy_policy' => <<<'HTML'
<h3>Data Privacy Policy</h3>
<p>Settlers Credit Cooperative (the Cooperative) handles personal information under Republic Act No. 10173, the Data Privacy Act of 2012, its Implementing Rules and Regulations, and applicable National Privacy Commission issuances. The Cooperative is the personal information controller for information it collects and uses to process membership and provide cooperative services.</p>
<h4>1. Personal information we process</h4>
<p>Depending on the service and information you provide, this may include your name, date and place of birth, contact details, address, government identification numbers and ID images, employment and income information, membership and beneficiary details, account credentials, and records of savings, share capital, loans, payments, and other transactions. We may also process system, security, and audit logs. We ask for information relevant to the stated purpose and applicable requirements.</p>
<h4>2. Purposes and lawful criteria</h4>
<p>We use personal information to assess and administer membership applications; maintain member and beneficiary records; provide and administer cooperative savings, share capital, credit, and payment services; communicate with members; meet accounting, reporting, recordkeeping, and other legal obligations; protect accounts and prevent or investigate fraud and security incidents; and maintain reliable operations and audit trails.</p>
<p>Depending on the processing, we rely on your consent where required, steps taken at your request before entering a membership or service relationship and performance of that relationship, compliance with legal obligations, or legitimate interests such as account security and service administration where those interests are not overridden by your rights. We do not treat consent as the only basis for processing where another lawful criterion applies.</p>
<p>Information required to assess an application or provide a requested service must be supplied when indicated. If it is not supplied, we may be unable to assess the application or provide that service. Optional information will be identified where applicable.</p>
<h4>3. Sharing and service providers</h4>
<p>Access is limited to authorized Cooperative personnel who need the information for their duties. We may disclose relevant information to service providers that support system hosting, communications, security, payment processing, or other operations under appropriate confidentiality and security safeguards; to regulators, government agencies, courts, or law enforcement when required or authorized by law; and to other parties when necessary to provide a requested service or when you have authorized the disclosure. We do not sell personal information.</p>
<h4>4. Retention</h4>
<p>We retain information only for as long as needed for the purposes described above and for applicable statutory, regulatory, accounting, dispute-resolution, and audit requirements. Retention periods vary by record type. When information is no longer required, it will be securely deleted, anonymized, or archived with access restricted where deletion is not yet permitted.</p>
<h4>5. Security and incidents</h4>
<p>The Cooperative applies reasonable organizational, physical, and technical safeguards, including role-based access, account security controls, restricted administrative access, and protected records and backups. No system can guarantee absolute security. If a personal data breach occurs, the Cooperative will assess and notify affected individuals and the National Privacy Commission when notification is required by law.</p>
<h4>6. Your data privacy rights</h4>
<p>Subject to the conditions and exceptions in law, you may ask to be informed about processing, access your personal information, object to certain processing, request correction or erasure/blocking, request data portability where applicable, claim damages for proven violations, and file a complaint with the National Privacy Commission. You may also withdraw consent where processing is based on consent; withdrawal does not affect processing already lawfully carried out or processing supported by another lawful criterion.</p>
<p>To exercise a right or ask a question, <a href="/contact">contact Settlers Credit Cooperative</a> and ask for the person responsible for data privacy matters. We may need to verify your identity and clarify the request before acting. We will respond within the period required by applicable law.</p>
<h4>7. Updates</h4>
<p>We may update this notice as our services or legal requirements change. The current version is available through this website and during member registration. Material changes will be communicated through appropriate channels.</p>
HTML,
            ],
        ];
    }
}
