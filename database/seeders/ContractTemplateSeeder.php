<?php

namespace Database\Seeders;

use App\Models\ContractTemplate;
use Illuminate\Database\Seeder;

class ContractTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Default contract template
        ContractTemplate::create([
            'name' => 'Default Contract',
            'slug' => 'default-contract',
            'description' => 'Standard contract template for all tenants',
            'is_default' => true,
            'is_active' => true,
            'content' => $this->getDefaultContractContent(),
        ]);

        // Exclusive (1%) contract template
        ContractTemplate::create([
            'name' => 'Exclusive Partnership Contract',
            'slug' => 'exclusive-contract',
            'description' => 'Contract for exclusive (1% commission) partnerships',
            'work_method' => 'exclusive',
            'plan' => '1percent',
            'is_default' => false,
            'is_active' => true,
            'content' => $this->getExclusiveContractContent(),
        ]);

        // Mixed (2%) contract template
        ContractTemplate::create([
            'name' => 'Mixed Partnership Contract',
            'slug' => 'mixed-contract',
            'description' => 'Contract for mixed (2% commission) partnerships',
            'work_method' => 'mixed',
            'plan' => '2percent',
            'is_default' => false,
            'is_active' => true,
            'content' => $this->getMixedContractContent(),
        ]);

        // Reseller (3%) contract template
        ContractTemplate::create([
            'name' => 'Reseller Contract',
            'slug' => 'reseller-contract',
            'description' => 'Contract for reseller (3% commission) arrangements',
            'work_method' => 'reseller',
            'plan' => '3percent',
            'is_default' => false,
            'is_active' => true,
            'content' => $this->getResellerContractContent(),
        ]);
    }

    /**
     * Get default contract content
     */
    protected function getDefaultContractContent(): string
    {
        return <<<'HTML'
<h2>SERVICE AGREEMENT</h2>

<p>This Service Agreement ("Agreement") is entered into as of {{contract_date}} ("Effective Date") by and between:</p>

<h3>PARTIES</h3>

<p><strong>Service Provider:</strong><br>
{{platform_company_name}}<br>
Tax ID: {{platform_cui}}<br>
Trade Register: {{platform_reg_com}}<br>
Address: {{platform_address}}<br>
Bank: {{platform_bank_name}}<br>
IBAN: {{platform_bank_account}}</p>

<p><strong>Client:</strong><br>
{{tenant_company_name}}<br>
Tax ID: {{tenant_cui}}<br>
Trade Register: {{tenant_reg_com}}<br>
Address: {{tenant_address}}, {{tenant_city}}, {{tenant_state}}, {{tenant_country}}<br>
VAT Payer: {{tenant_vat_payer}}<br>
Represented by: {{tenant_contact_name}}, {{tenant_contact_position}}<br>
Email: {{tenant_contact_email}}<br>
Phone: {{tenant_contact_phone}}</p>

<h3>1. SCOPE OF SERVICES</h3>

<p>The Service Provider agrees to provide the Client with access to the ticketing platform for event management and ticket sales, including but not limited to:</p>
<ul>
    <li>Event creation and management tools</li>
    <li>Ticket sales processing</li>
    <li>Customer management features</li>
    <li>Reporting and analytics</li>
    <li>Payment processing integration</li>
</ul>

<h3>2. FEES AND PAYMENT</h3>

<p>The Client agrees to pay the Service Provider a commission of <strong>{{tenant_commission_rate}}%</strong> on all ticket sales processed through the platform.</p>

<p>Work Method: <strong>{{tenant_work_method}}</strong></p>

<p>Payment terms: Net 30 days from invoice date.</p>

<h3>3. TERM AND TERMINATION</h3>

<p>This Agreement shall commence on the Effective Date and continue until terminated by either party with 30 days written notice.</p>

<h3>4. CONFIDENTIALITY</h3>

<p>Both parties agree to maintain the confidentiality of any proprietary information shared during the course of this Agreement.</p>

<h3>5. LIABILITY</h3>

<p>Each party shall be liable for damages caused by its own negligence or willful misconduct. Neither party shall be liable for indirect, incidental, or consequential damages.</p>

<h3>6. GOVERNING LAW</h3>

<p>This Agreement shall be governed by and construed in accordance with the laws of Romania.</p>

<h3>7. ENTIRE AGREEMENT</h3>

<p>This Agreement constitutes the entire agreement between the parties and supersedes all prior negotiations, representations, or agreements relating to this subject matter.</p>
HTML;
    }

    /**
     * Get exclusive contract content (1%)
     */
    protected function getExclusiveContractContent(): string
    {
        return <<<'HTML'
<h2>EXCLUSIVE PARTNERSHIP AGREEMENT</h2>

<p>This Exclusive Partnership Agreement ("Agreement") is entered into as of {{contract_date}} by and between:</p>

<h3>PARTIES</h3>

<p><strong>Service Provider:</strong><br>
{{platform_company_name}}<br>
Tax ID: {{platform_cui}}<br>
Address: {{platform_address}}</p>

<p><strong>Exclusive Partner:</strong><br>
{{tenant_company_name}}<br>
Tax ID: {{tenant_cui}}<br>
Address: {{tenant_address}}, {{tenant_city}}, {{tenant_country}}<br>
Contact: {{tenant_contact_name}} ({{tenant_contact_email}})</p>

<h3>1. EXCLUSIVE PARTNERSHIP TERMS</h3>

<p>As an Exclusive Partner, the Client is entitled to the following benefits:</p>
<ul>
    <li><strong>Lowest commission rate:</strong> 1% on all ticket sales</li>
    <li><strong>Priority support:</strong> Dedicated account manager</li>
    <li><strong>Custom branding:</strong> White-label options available</li>
    <li><strong>Advanced analytics:</strong> Comprehensive reporting dashboard</li>
</ul>

<h3>2. EXCLUSIVITY REQUIREMENTS</h3>

<p>To maintain Exclusive Partner status, the Client agrees to:</p>
<ul>
    <li>Use the platform as the primary ticketing solution for all events</li>
    <li>Meet minimum monthly ticket volume requirements</li>
    <li>Maintain an active partnership for a minimum of 12 months</li>
</ul>

<h3>3. COMMISSION STRUCTURE</h3>

<p>Commission Rate: <strong>{{tenant_commission_rate}}%</strong></p>
<p>Work Method: <strong>{{tenant_work_method}}</strong></p>

<p>The 1% commission is the most favorable rate offered by the platform and is exclusively available to partners meeting the exclusivity criteria.</p>

<h3>4. PAYMENT TERMS</h3>

<p>Client Bank Details:<br>
Bank: {{tenant_bank_name}}<br>
IBAN: {{tenant_bank_account}}</p>

<p>Settlements are processed weekly for the previous week's sales.</p>

<h3>5. TERM</h3>

<p>This Agreement is effective from {{contract_date}} and shall remain in force for a minimum period of 12 months, automatically renewing thereafter unless terminated with 60 days written notice.</p>

<h3>6. GOVERNING LAW</h3>

<p>This Agreement shall be governed by the laws of Romania.</p>
HTML;
    }

    /**
     * Get mixed contract content (2%)
     */
    protected function getMixedContractContent(): string
    {
        return <<<'HTML'
<h2>MIXED PARTNERSHIP AGREEMENT</h2>

<p>This Mixed Partnership Agreement ("Agreement") is entered into as of {{contract_date}} by and between:</p>

<h3>PARTIES</h3>

<p><strong>Service Provider:</strong><br>
{{platform_company_name}}<br>
Tax ID: {{platform_cui}}<br>
Address: {{platform_address}}</p>

<p><strong>Partner:</strong><br>
{{tenant_company_name}}<br>
Tax ID: {{tenant_cui}}<br>
Address: {{tenant_address}}, {{tenant_city}}, {{tenant_country}}<br>
Contact: {{tenant_contact_name}} ({{tenant_contact_email}})</p>

<h3>1. MIXED PARTNERSHIP TERMS</h3>

<p>As a Mixed Partner, the Client receives:</p>
<ul>
    <li><strong>Competitive commission:</strong> 2% on all ticket sales</li>
    <li><strong>Flexible usage:</strong> Freedom to use multiple ticketing platforms</li>
    <li><strong>Standard support:</strong> Business hours support</li>
    <li><strong>Full platform access:</strong> All standard features included</li>
</ul>

<h3>2. COMMISSION STRUCTURE</h3>

<p>Commission Rate: <strong>{{tenant_commission_rate}}%</strong></p>
<p>Work Method: <strong>{{tenant_work_method}}</strong></p>

<p>The 2% commission applies to all ticket sales processed through the platform.</p>

<h3>3. PLATFORM FEATURES</h3>

<p>The Client has access to:</p>
<ul>
    <li>Event creation and management</li>
    <li>Multi-payment processor support</li>
    <li>Customer database management</li>
    <li>Sales reporting and analytics</li>
    <li>Mobile ticket delivery</li>
</ul>

<h3>4. PAYMENT TERMS</h3>

<p>Client Bank Details:<br>
Bank: {{tenant_bank_name}}<br>
IBAN: {{tenant_bank_account}}</p>

<p>Settlements are processed bi-weekly.</p>

<h3>5. TERM</h3>

<p>This Agreement commences on {{contract_date}} and continues on a month-to-month basis until terminated with 30 days written notice.</p>

<h3>6. GOVERNING LAW</h3>

<p>This Agreement shall be governed by the laws of Romania.</p>
HTML;
    }

    /**
     * Get reseller contract content (3%)
     */
    protected function getResellerContractContent(): string
    {
        return <<<'HTML'
<h2>RESELLER AGREEMENT</h2>

<p>This Reseller Agreement ("Agreement") is entered into as of {{contract_date}} by and between:</p>

<h3>PARTIES</h3>

<p><strong>Platform Provider:</strong><br>
{{platform_company_name}}<br>
Tax ID: {{platform_cui}}<br>
Address: {{platform_address}}</p>

<p><strong>Reseller:</strong><br>
{{tenant_company_name}}<br>
Tax ID: {{tenant_cui}}<br>
Address: {{tenant_address}}, {{tenant_city}}, {{tenant_country}}<br>
Contact: {{tenant_contact_name}} ({{tenant_contact_email}})</p>

<h3>1. RESELLER ARRANGEMENT</h3>

<p>The Reseller is authorized to sell tickets on behalf of event organizers using the platform. This arrangement includes:</p>
<ul>
    <li><strong>Reseller commission:</strong> 3% on all ticket sales</li>
    <li><strong>Markup rights:</strong> Ability to add service fees</li>
    <li><strong>Multi-client support:</strong> Manage multiple event organizers</li>
    <li><strong>API access:</strong> Integration capabilities</li>
</ul>

<h3>2. RESELLER RESPONSIBILITIES</h3>

<p>The Reseller agrees to:</p>
<ul>
    <li>Maintain accurate records of all sales</li>
    <li>Provide customer support for end-users</li>
    <li>Comply with all applicable laws and regulations</li>
    <li>Protect customer data and privacy</li>
</ul>

<h3>3. COMMISSION STRUCTURE</h3>

<p>Commission Rate: <strong>{{tenant_commission_rate}}%</strong></p>
<p>Work Method: <strong>{{tenant_work_method}}</strong></p>

<p>The Reseller retains all additional service fees charged to customers.</p>

<h3>4. PAYMENT TERMS</h3>

<p>Reseller Bank Details:<br>
Bank: {{tenant_bank_name}}<br>
IBAN: {{tenant_bank_account}}</p>

<p>Settlements are processed monthly for the previous month's sales.</p>

<h3>5. TERM</h3>

<p>This Agreement is effective from {{contract_date}} and continues until terminated with 30 days written notice by either party.</p>

<h3>6. GOVERNING LAW</h3>

<p>This Agreement shall be governed by the laws of Romania.</p>
HTML;
    }
}
