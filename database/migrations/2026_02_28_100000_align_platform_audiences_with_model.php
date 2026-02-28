<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rename existing columns to match model $fillable
        Schema::table('platform_audiences', function (Blueprint $table) {
            if (Schema::hasColumn('platform_audiences', 'ad_account_id') && !Schema::hasColumn('platform_audiences', 'platform_ad_account_id')) {
                $table->renameColumn('ad_account_id', 'platform_ad_account_id');
            }
        });

        Schema::table('platform_audiences', function (Blueprint $table) {
            if (Schema::hasColumn('platform_audiences', 'audience_id') && !Schema::hasColumn('platform_audiences', 'platform_audience_id')) {
                $table->renameColumn('audience_id', 'platform_audience_id');
            }
        });

        Schema::table('platform_audiences', function (Blueprint $table) {
            if (Schema::hasColumn('platform_audiences', 'filters') && !Schema::hasColumn('platform_audiences', 'segment_rules')) {
                $table->renameColumn('filters', 'segment_rules');
            }
        });

        Schema::table('platform_audiences', function (Blueprint $table) {
            if (Schema::hasColumn('platform_audiences', 'auto_sync') && !Schema::hasColumn('platform_audiences', 'is_auto_sync')) {
                $table->renameColumn('auto_sync', 'is_auto_sync');
            }
        });

        // Add missing columns
        Schema::table('platform_audiences', function (Blueprint $table) {
            if (!Schema::hasColumn('platform_audiences', 'status')) {
                $table->string('status', 50)->default('draft')->after('audience_type');
            }
        });

        Schema::table('platform_audiences', function (Blueprint $table) {
            if (!Schema::hasColumn('platform_audiences', 'platform_status')) {
                $table->string('platform_status', 50)->nullable()->after('status');
            }
        });

        Schema::table('platform_audiences', function (Blueprint $table) {
            if (!Schema::hasColumn('platform_audiences', 'error_message')) {
                $table->text('error_message')->nullable()->after('platform_status');
            }
        });

        Schema::table('platform_audiences', function (Blueprint $table) {
            if (!Schema::hasColumn('platform_audiences', 'matched_count')) {
                $table->integer('matched_count')->default(0)->after('member_count');
            }
        });

        // Make platform_ad_account_id nullable (audiences can exist without an ad account)
        // Drop FK first, then modify, then re-add FK as nullable
        try {
            Schema::table('platform_audiences', function (Blueprint $table) {
                // Try dropping FK with old name
                $table->dropForeign(['ad_account_id']);
            });
        } catch (\Exception $e) {
            try {
                Schema::table('platform_audiences', function (Blueprint $table) {
                    $table->dropForeign(['platform_ad_account_id']);
                });
            } catch (\Exception $e2) {
                // FK might not exist or has different name, continue
            }
        }

        Schema::table('platform_audiences', function (Blueprint $table) {
            if (Schema::hasColumn('platform_audiences', 'platform_ad_account_id')) {
                $table->unsignedBigInteger('platform_ad_account_id')->nullable()->change();
            }
        });

        // Re-add FK as nullable
        try {
            Schema::table('platform_audiences', function (Blueprint $table) {
                $table->foreign('platform_ad_account_id')
                    ->references('id')
                    ->on('platform_ad_accounts')
                    ->nullOnDelete();
            });
        } catch (\Exception $e) {
            // FK might already exist
        }

        // Make platform_audience_id nullable
        Schema::table('platform_audiences', function (Blueprint $table) {
            if (Schema::hasColumn('platform_audiences', 'platform_audience_id')) {
                $table->string('platform_audience_id')->nullable()->change();
            }
        });

        // Drop unique constraint that references old column names
        try {
            Schema::table('platform_audiences', function (Blueprint $table) {
                $table->dropUnique(['ad_account_id', 'audience_id']);
            });
        } catch (\Exception $e) {
            // Unique might not exist or already dropped
        }

        // Also align platform_audience_members FK column names
        if (Schema::hasTable('platform_audience_members')) {
            // Drop FKs before renaming
            try {
                Schema::table('platform_audience_members', function (Blueprint $table) {
                    $table->dropForeign(['audience_id']);
                });
            } catch (\Exception $e) {}
            try {
                Schema::table('platform_audience_members', function (Blueprint $table) {
                    $table->dropForeign(['customer_id']);
                });
            } catch (\Exception $e) {}
            try {
                Schema::table('platform_audience_members', function (Blueprint $table) {
                    $table->dropUnique(['audience_id', 'customer_id']);
                });
            } catch (\Exception $e) {}

            Schema::table('platform_audience_members', function (Blueprint $table) {
                if (Schema::hasColumn('platform_audience_members', 'audience_id') && !Schema::hasColumn('platform_audience_members', 'platform_audience_id')) {
                    $table->renameColumn('audience_id', 'platform_audience_id');
                }
            });

            Schema::table('platform_audience_members', function (Blueprint $table) {
                if (Schema::hasColumn('platform_audience_members', 'customer_id') && !Schema::hasColumn('platform_audience_members', 'core_customer_id')) {
                    $table->renameColumn('customer_id', 'core_customer_id');
                }
            });

            // Re-add FKs with new column names
            try {
                Schema::table('platform_audience_members', function (Blueprint $table) {
                    $table->foreign('platform_audience_id')->references('id')->on('platform_audiences')->cascadeOnDelete();
                    $table->foreign('core_customer_id')->references('id')->on('core_customers')->cascadeOnDelete();
                });
            } catch (\Exception $e) {}

            // Add missing columns on members table
            Schema::table('platform_audience_members', function (Blueprint $table) {
                if (!Schema::hasColumn('platform_audience_members', 'hashed_email')) {
                    $table->string('hashed_email')->nullable()->after('core_customer_id');
                }
            });
            Schema::table('platform_audience_members', function (Blueprint $table) {
                if (!Schema::hasColumn('platform_audience_members', 'hashed_phone')) {
                    $table->string('hashed_phone')->nullable()->after('hashed_email');
                }
            });
            Schema::table('platform_audience_members', function (Blueprint $table) {
                if (!Schema::hasColumn('platform_audience_members', 'hashed_first_name')) {
                    $table->string('hashed_first_name')->nullable()->after('hashed_phone');
                }
            });
            Schema::table('platform_audience_members', function (Blueprint $table) {
                if (!Schema::hasColumn('platform_audience_members', 'hashed_last_name')) {
                    $table->string('hashed_last_name')->nullable()->after('hashed_first_name');
                }
            });
            Schema::table('platform_audience_members', function (Blueprint $table) {
                if (!Schema::hasColumn('platform_audience_members', 'is_matched')) {
                    $table->boolean('is_matched')->default(false)->after('hashed_last_name');
                }
            });
            Schema::table('platform_audience_members', function (Blueprint $table) {
                if (!Schema::hasColumn('platform_audience_members', 'removed_at')) {
                    $table->timestamp('removed_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // Reverse column renames
        Schema::table('platform_audiences', function (Blueprint $table) {
            if (Schema::hasColumn('platform_audiences', 'platform_ad_account_id')) {
                $table->renameColumn('platform_ad_account_id', 'ad_account_id');
            }
            if (Schema::hasColumn('platform_audiences', 'platform_audience_id')) {
                $table->renameColumn('platform_audience_id', 'audience_id');
            }
            if (Schema::hasColumn('platform_audiences', 'segment_rules')) {
                $table->renameColumn('segment_rules', 'filters');
            }
            if (Schema::hasColumn('platform_audiences', 'is_auto_sync')) {
                $table->renameColumn('is_auto_sync', 'auto_sync');
            }
        });

        Schema::table('platform_audiences', function (Blueprint $table) {
            $drop = [];
            foreach (['status', 'platform_status', 'error_message', 'matched_count'] as $col) {
                if (Schema::hasColumn('platform_audiences', $col)) {
                    $drop[] = $col;
                }
            }
            if ($drop) $table->dropColumn($drop);
        });

        if (Schema::hasTable('platform_audience_members')) {
            Schema::table('platform_audience_members', function (Blueprint $table) {
                if (Schema::hasColumn('platform_audience_members', 'platform_audience_id')) {
                    $table->renameColumn('platform_audience_id', 'audience_id');
                }
                if (Schema::hasColumn('platform_audience_members', 'core_customer_id')) {
                    $table->renameColumn('core_customer_id', 'customer_id');
                }
            });

            Schema::table('platform_audience_members', function (Blueprint $table) {
                $drop = [];
                foreach (['hashed_email', 'hashed_phone', 'hashed_first_name', 'hashed_last_name', 'is_matched', 'removed_at'] as $col) {
                    if (Schema::hasColumn('platform_audience_members', $col)) {
                        $drop[] = $col;
                    }
                }
                if ($drop) $table->dropColumn($drop);
            });
        }
    }
};
