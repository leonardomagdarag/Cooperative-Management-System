<?php

use App\Support\SiteContentCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function legacyPolicy(): string
    {
        return <<<'HTML'
<h3>Data Privacy Policy</h3>
<p>The Cooperative Management System for Settlers Credit Cooperative is committed to protecting the privacy, confidentiality, and security of all personal and financial information collected from members, employees, and authorized users of the system. This policy is established in accordance with the National Privacy Commission and Republic Act No. 10173, also known as the Data Privacy Act of 2012.</p>
<h4>1. Collection of Information</h4>
<p>The system collects necessary personal and transaction-related information including but not limited to full name, address, contact information, membership details, loan records, savings and deposit records, payment transactions, and user account credentials. The collected data is limited only to information necessary for cooperative operations and management.</p>
<h4>2. Purpose of Data Collection</h4>
<p>The collected information shall only be used for legitimate purposes such as member registration and management, loan processing and monitoring, savings and financial transaction management, report generation, authentication and system security, and audit and administrative purposes.</p>
<h4>3. Data Protection and Security</h4>
<p>The system implements reasonable security measures to protect user data from unauthorized access, disclosure, alteration, or destruction. These measures include secure login authentication, role-based access control, password encryption, database backup procedures, and restricted administrative access.</p>
<p>Only authorized personnel are permitted to access confidential information based on their assigned responsibilities.</p>
<h4>4. Data Confidentiality</h4>
<p>All personal and financial records stored in the system shall remain confidential. Information will not be shared with third parties unless authorized by the data owner, required by law or government authorities, or necessary for legitimate cooperative operations.</p>
<h4>5. Data Retention</h4>
<p>User information and transaction records shall be retained only for as long as necessary for operational, legal, and auditing purposes. Obsolete or inactive records may be archived or securely disposed of following cooperative policies.</p>
<h4>6. Rights of Users</h4>
<p>Users of the system have the right to access their personal information, request correction of inaccurate records, request updates to their information, and be informed regarding how their data is used.</p>
<h4>7. Limitation of Liability</h4>
<p>While the system implements security measures to protect information, the cooperative cannot guarantee absolute protection against cyberattacks, unauthorized access, or technical failures beyond reasonable control.</p>
<h4>8. Amendments</h4>
<p>The cooperative reserves the right to modify or update this Data Privacy Policy whenever necessary to comply with legal requirements or improve system operations.</p>
HTML;
    }

    private function currentDefaultPolicy(): string
    {
        foreach (SiteContentCatalog::seedRecords() as $record) {
            if ($record['section'] === 'policies' && $record['key'] === 'privacy_policy') {
                return $record['value'];
            }
        }

        throw new RuntimeException('Default privacy policy content is missing.');
    }

    public function up(): void
    {
        $row = DB::table('site_contents')->where(['section' => 'policies', 'key' => 'privacy_policy'])->first();

        if ($row && $row->value === $this->legacyPolicy()) {
            DB::table('site_contents')->where('id', $row->id)->update([
                'value' => $this->currentDefaultPolicy(),
                'updated_at' => now(),
            ]);
        }

        Cache::forget(SiteContentCatalog::CACHE_KEY);
    }

    public function down(): void
    {
        $row = DB::table('site_contents')->where(['section' => 'policies', 'key' => 'privacy_policy'])->first();

        if ($row && $row->value === $this->currentDefaultPolicy()) {
            DB::table('site_contents')->where('id', $row->id)->update([
                'value' => $this->legacyPolicy(),
                'updated_at' => now(),
            ]);
        }

        Cache::forget(SiteContentCatalog::CACHE_KEY);
    }
};
