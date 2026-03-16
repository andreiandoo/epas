<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Microservice;

return new class extends Migration
{
    public function up(): void
    {
        $netopia = Microservice::where('slug', 'payment-netopia')->first();

        if (!$netopia) {
            return;
        }

        $metadata = $netopia->metadata ?? [];
        $schema = $metadata['settings_schema'] ?? [];
        $sections = $metadata['settings_sections'] ?? [];

        // Check if already added
        $existingKeys = array_column($schema, 'key');
        if (in_array('cultural_card_enabled', $existingKeys)) {
            return;
        }

        // Add cultural card fields to schema
        $schema[] = ['key' => 'cultural_card_enabled', 'label' => 'Activează plata prin Carduri Culturale', 'type' => 'boolean', 'default' => false, 'section' => 'cultural_card'];
        $schema[] = ['key' => 'cultural_card_surcharge_percent', 'label' => 'Procent suplimentar card cultural (%)', 'type' => 'number', 'required' => false, 'placeholder' => '4', 'section' => 'cultural_card'];

        // Add cultural card section
        $sections['cultural_card'] = [
            'label' => 'Card Cultural',
            'description' => 'Setări pentru plata prin carduri culturale (Edenred, Sodexo, Up România). Procesarea se face tot prin Netopia.',
        ];

        $metadata['settings_schema'] = $schema;
        $metadata['settings_sections'] = $sections;

        $netopia->metadata = $metadata;
        $netopia->save();
    }

    public function down(): void
    {
        $netopia = Microservice::where('slug', 'payment-netopia')->first();

        if (!$netopia) {
            return;
        }

        $metadata = $netopia->metadata ?? [];
        $schema = $metadata['settings_schema'] ?? [];
        $sections = $metadata['settings_sections'] ?? [];

        $schema = array_values(array_filter($schema, fn($f) => !in_array($f['key'] ?? '', ['cultural_card_enabled', 'cultural_card_surcharge_percent'])));
        unset($sections['cultural_card']);

        $metadata['settings_schema'] = $schema;
        $metadata['settings_sections'] = $sections;

        $netopia->metadata = $metadata;
        $netopia->save();
    }
};
