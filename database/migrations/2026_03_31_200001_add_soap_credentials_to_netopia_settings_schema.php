<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $netopia = DB::table('microservices')->where('slug', 'payment-netopia')->first();
        if (!$netopia) return;

        $metadata = json_decode($netopia->metadata, true) ?? [];
        $schema = $metadata['settings_schema'] ?? [];

        // Add SOAP credential fields for refund capability
        $soapFields = [
            ['key' => 'test_soap_username', 'label' => 'Sandbox SOAP Username', 'type' => 'text', 'required' => false, 'section' => 'test', 'placeholder' => 'Netopia admin panel username'],
            ['key' => 'test_soap_password', 'label' => 'Sandbox SOAP Password', 'type' => 'password', 'required' => false, 'section' => 'test', 'placeholder' => 'Netopia admin panel password'],
            ['key' => 'live_soap_username', 'label' => 'Live SOAP Username', 'type' => 'text', 'required' => false, 'section' => 'live', 'placeholder' => 'Netopia admin panel username'],
            ['key' => 'live_soap_password', 'label' => 'Live SOAP Password', 'type' => 'password', 'required' => false, 'section' => 'live', 'placeholder' => 'Netopia admin panel password'],
        ];

        // Check if already added
        $existingKeys = array_column($schema, 'key');
        foreach ($soapFields as $field) {
            if (!in_array($field['key'], $existingKeys)) {
                $schema[] = $field;
            }
        }

        // Add refund section
        $sections = $metadata['settings_sections'] ?? [];
        // SOAP fields go in existing test/live sections, no new section needed

        $metadata['settings_schema'] = $schema;
        $metadata['settings_sections'] = $sections;

        DB::table('microservices')->where('id', $netopia->id)->update([
            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function down(): void
    {
        $netopia = DB::table('microservices')->where('slug', 'payment-netopia')->first();
        if (!$netopia) return;

        $metadata = json_decode($netopia->metadata, true) ?? [];
        $schema = $metadata['settings_schema'] ?? [];

        $soapKeys = ['test_soap_username', 'test_soap_password', 'live_soap_username', 'live_soap_password'];
        $schema = array_values(array_filter($schema, fn($f) => !in_array($f['key'], $soapKeys)));

        $metadata['settings_schema'] = $schema;

        DB::table('microservices')->where('id', $netopia->id)->update([
            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }
};
