<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('performances', function (Blueprint $table) {
            $table->boolean('has_audio_description')->default(false)->after('capacity_override');
            $table->boolean('has_sign_language')->default(false)->after('has_audio_description')->comment('ASL / sign language interpreter');
            $table->boolean('has_closed_captions')->default(false)->after('has_sign_language');
            $table->boolean('is_sensory_friendly')->default(false)->after('has_closed_captions')->comment('Reduced stimuli for neurodiverse audiences');
            $table->boolean('has_wheelchair_access')->default(false)->after('is_sensory_friendly');
            $table->boolean('has_hearing_loop')->default(false)->after('has_wheelchair_access')->comment('Induction loop for hearing aids');
            $table->boolean('has_braille_program')->default(false)->after('has_hearing_loop');
            $table->boolean('has_tactile_tour')->default(false)->after('has_braille_program')->comment('Pre-show tactile tour of set/costumes');
            $table->text('accessibility_notes')->nullable()->after('has_tactile_tour');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->json('accessibility_features')->nullable()->after('is_featured')->comment('Default accessibility features for all performances');
            $table->text('accessibility_info')->nullable()->after('accessibility_features')->comment('Public accessibility info text');
        });
    }

    public function down(): void
    {
        Schema::table('performances', function (Blueprint $table) {
            $table->dropColumn([
                'has_audio_description',
                'has_sign_language',
                'has_closed_captions',
                'is_sensory_friendly',
                'has_wheelchair_access',
                'has_hearing_loop',
                'has_braille_program',
                'has_tactile_tour',
                'accessibility_notes',
            ]);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['accessibility_features', 'accessibility_info']);
        });
    }
};
