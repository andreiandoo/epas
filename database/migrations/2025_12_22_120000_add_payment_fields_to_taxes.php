<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('general_taxes', function (Blueprint $table) {
            // Payment information
            $table->string('beneficiary')->nullable()->after('explanation');
            $table->string('iban', 34)->nullable()->after('beneficiary');
            $table->text('beneficiary_address')->nullable()->after('iban');
            $table->text('where_to_pay')->nullable()->after('beneficiary_address');

            // Payment terms
            $table->string('payment_term')->nullable()->after('where_to_pay');
            $table->integer('payment_term_day')->nullable()->after('payment_term'); // Day of month (e.g., 10, 25)
            $table->integer('payment_term_days_after')->nullable()->after('payment_term_day'); // Days after event
            $table->enum('payment_term_type', ['day_of_month', 'days_after_event', 'at_sale', 'quarterly', 'custom'])->nullable()->after('payment_term_days_after');

            // Legal and documentation
            $table->string('legal_basis')->nullable()->after('payment_term_type');
            $table->text('declaration')->nullable()->after('legal_basis');
            $table->text('before_event_instructions')->nullable()->after('declaration');
            $table->text('after_event_instructions')->nullable()->after('before_event_instructions');

            // Tax application rules
            $table->boolean('is_added_to_price')->default(false)->after('after_event_instructions'); // Stamps are added, VAT is included
            $table->enum('applied_to_base', ['gross_with_vat', 'gross_excl_vat', 'ticket_price', 'net_revenue'])->default('gross_excl_vat')->after('is_added_to_price');

            // Tiered rates (for UCMR-ADA style systems)
            $table->boolean('has_tiered_rates')->default(false)->after('applied_to_base');
            $table->json('tiered_rates')->nullable()->after('has_tiered_rates'); // [{"min": 0, "max": 500000, "rate": 7}, ...]

            // Thresholds
            $table->decimal('min_revenue_threshold', 15, 2)->nullable()->after('tiered_rates'); // Min revenue to apply
            $table->decimal('max_revenue_threshold', 15, 2)->nullable()->after('min_revenue_threshold'); // Max revenue to apply
            $table->decimal('min_guaranteed_amount', 15, 2)->nullable()->after('max_revenue_threshold'); // Minimum payment
        });

        Schema::table('local_taxes', function (Blueprint $table) {
            // Payment information
            $table->string('beneficiary')->nullable()->after('explanation');
            $table->string('iban', 34)->nullable()->after('beneficiary');
            $table->text('beneficiary_address')->nullable()->after('iban');
            $table->text('where_to_pay')->nullable()->after('beneficiary_address');

            // Payment terms
            $table->string('payment_term')->nullable()->after('where_to_pay');
            $table->integer('payment_term_day')->nullable()->after('payment_term');
            $table->enum('payment_term_type', ['day_of_month', 'days_after_event', 'quarterly', 'custom'])->nullable()->after('payment_term_day');

            // Legal and documentation
            $table->string('legal_basis')->nullable()->after('payment_term_type');
            $table->text('declaration')->nullable()->after('legal_basis');
            $table->text('before_event_instructions')->nullable()->after('declaration');
            $table->text('after_event_instructions')->nullable()->after('before_event_instructions');

            // Tax application rules
            $table->enum('applied_to_base', ['gross_with_vat', 'gross_excl_vat', 'ticket_price'])->default('gross_excl_vat')->after('after_event_instructions');
            $table->decimal('max_rate', 5, 2)->nullable()->after('applied_to_base'); // Maximum rate allowed by law (for HCL)
        });
    }

    public function down(): void
    {
        Schema::table('general_taxes', function (Blueprint $table) {
            $table->dropColumn([
                'beneficiary',
                'iban',
                'beneficiary_address',
                'where_to_pay',
                'payment_term',
                'payment_term_day',
                'payment_term_days_after',
                'payment_term_type',
                'legal_basis',
                'declaration',
                'before_event_instructions',
                'after_event_instructions',
                'is_added_to_price',
                'applied_to_base',
                'has_tiered_rates',
                'tiered_rates',
                'min_revenue_threshold',
                'max_revenue_threshold',
                'min_guaranteed_amount',
            ]);
        });

        Schema::table('local_taxes', function (Blueprint $table) {
            $table->dropColumn([
                'beneficiary',
                'iban',
                'beneficiary_address',
                'where_to_pay',
                'payment_term',
                'payment_term_day',
                'payment_term_type',
                'legal_basis',
                'declaration',
                'before_event_instructions',
                'after_event_instructions',
                'applied_to_base',
                'max_rate',
            ]);
        });
    }
};
