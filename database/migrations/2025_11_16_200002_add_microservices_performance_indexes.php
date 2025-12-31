<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds performance indexes for frequently queried microservices tables
     */
    public function up(): void
    {
        // Tenant microservices - optimize subscription queries
        Schema::table('tenant_microservices', function (Blueprint $table) {
            // Index for active subscriptions query
            if (!$this->indexExists('tenant_microservices', 'idx_tenant_microservices_status_expires')) {
                $table->index(['status', 'expires_at'], 'idx_tenant_microservices_status_expires');
            }

            // Index for tenant lookup
            if (!$this->indexExists('tenant_microservices', 'idx_tenant_microservices_tenant_status')) {
                $table->index(['tenant_id', 'status'], 'idx_tenant_microservices_tenant_status');
            }

            // Index for microservice analytics
            if (!$this->indexExists('tenant_microservices', 'idx_tenant_microservices_microservice_status')) {
                $table->index(['microservice_id', 'status'], 'idx_tenant_microservices_microservice_status');
            }
        });

        // Metrics - optimize time-series queries
        if (Schema::hasTable('microservice_metrics')) {
            Schema::table('microservice_metrics', function (Blueprint $table) {
                // Composite index for tenant + service + time range queries
                if (!$this->indexExists('microservice_metrics', 'idx_metrics_tenant_service_time')) {
                    $table->index(['tenant_id', 'microservice_id', 'created_at'], 'idx_metrics_tenant_service_time');
                }

                // Index for metrics aggregation by type
                if (!$this->indexExists('microservice_metrics', 'idx_metrics_type_time')) {
                    $table->index(['metric_type', 'created_at'], 'idx_metrics_type_time');
                }
            });
        }

        // Webhook deliveries - optimize retry and status queries
        if (Schema::hasTable('tenant_webhook_deliveries')) {
            Schema::table('tenant_webhook_deliveries', function (Blueprint $table) {
                // Index for retry processing
                if (!$this->indexExists('tenant_webhook_deliveries', 'idx_webhooks_retry')) {
                    $table->index(['status', 'next_retry_at'], 'idx_webhooks_retry');
                }

                // Index for tenant webhook history
                if (!$this->indexExists('tenant_webhook_deliveries', 'idx_webhooks_tenant_time')) {
                    $table->index(['tenant_id', 'created_at'], 'idx_webhooks_tenant_time');
                }

                // Index for event type analytics
                if (!$this->indexExists('tenant_webhook_deliveries', 'idx_webhooks_event_status')) {
                    $table->index(['event_type', 'status'], 'idx_webhooks_event_status');
                }
            });
        }

        // Feature flags - optimize lookup queries
        if (Schema::hasTable('feature_flags')) {
            Schema::table('feature_flags', function (Blueprint $table) {
                // Index for tenant-specific flags
                if (!$this->indexExists('feature_flags', 'idx_flags_tenant_enabled')) {
                    $table->index(['tenant_id', 'enabled'], 'idx_flags_tenant_enabled');
                }

                // Index for global flags
                if (!$this->indexExists('feature_flags', 'idx_flags_key_tenant')) {
                    $table->index(['flag_key', 'tenant_id'], 'idx_flags_key_tenant');
                }
            });
        }

        // Notification queue - optimize processing
        if (Schema::hasTable('tenant_notifications')) {
            Schema::table('tenant_notifications', function (Blueprint $table) {
                // Index for pending notifications
                if (!$this->indexExists('tenant_notifications', 'idx_notifications_status_scheduled')) {
                    $table->index(['status', 'scheduled_at'], 'idx_notifications_status_scheduled');
                }

                // Index for tenant history
                if (!$this->indexExists('tenant_notifications', 'idx_notifications_tenant_time')) {
                    $table->index(['tenant_id', 'created_at'], 'idx_notifications_tenant_time');
                }
            });
        }

        // ANAF queue - optimize processing
        if (Schema::hasTable('anaf_queue')) {
            Schema::table('anaf_queue', function (Blueprint $table) {
                // Index for pending submissions (only if priority column exists)
                if (Schema::hasColumn('anaf_queue', 'priority') && !$this->indexExists('anaf_queue', 'idx_anaf_status_priority')) {
                    $table->index(['status', 'priority', 'created_at'], 'idx_anaf_status_priority');
                }

                // Index for tenant lookup
                if (!$this->indexExists('anaf_queue', 'idx_anaf_tenant_status')) {
                    $table->index(['tenant_id', 'status'], 'idx_anaf_tenant_status');
                }
            });
        }

        // WhatsApp message queue
        if (Schema::hasTable('whatsapp_messages')) {
            Schema::table('whatsapp_messages', function (Blueprint $table) {
                // Index for pending messages
                if (!$this->indexExists('whatsapp_messages', 'idx_whatsapp_status_scheduled')) {
                    $table->index(['status', 'scheduled_at'], 'idx_whatsapp_status_scheduled');
                }

                // Index for tenant analytics
                if (!$this->indexExists('whatsapp_messages', 'idx_whatsapp_tenant_time')) {
                    $table->index(['tenant_id', 'created_at'], 'idx_whatsapp_tenant_time');
                }
            });
        }

        // Invitations batch processing
        if (Schema::hasTable('inv_batches')) {
            Schema::table('inv_batches', function (Blueprint $table) {
                // Index for active batches
                if (!$this->indexExists('inv_batches', 'idx_batches_tenant_status')) {
                    $table->index(['tenant_id', 'status'], 'idx_batches_tenant_status');
                }

                // Index for event lookup
                if (!$this->indexExists('inv_batches', 'idx_batches_event')) {
                    $table->index('event_ref', 'idx_batches_event');
                }
            });
        }

        if (Schema::hasTable('inv_invites')) {
            Schema::table('inv_invites', function (Blueprint $table) {
                // Index for batch processing
                if (!$this->indexExists('inv_invites', 'idx_invites_batch_status')) {
                    $table->index(['batch_id', 'status'], 'idx_invites_batch_status');
                }

                // Index for pending emails (only if email_status column exists)
                if (Schema::hasColumn('inv_invites', 'email_status') && !$this->indexExists('inv_invites', 'idx_invites_email_status')) {
                    $table->index(['email_status', 'emailed_at'], 'idx_invites_email_status');
                }

                // Index for code lookup
                if (!$this->indexExists('inv_invites', 'idx_invites_code')) {
                    $table->index('invite_code', 'idx_invites_code');
                }
            });
        }

        // Accounting connectors
        if (Schema::hasTable('acc_connectors')) {
            Schema::table('acc_connectors', function (Blueprint $table) {
                // Index for active connectors (only if is_active column exists)
                if (Schema::hasColumn('acc_connectors', 'is_active') && !$this->indexExists('acc_connectors', 'idx_acc_tenant_active')) {
                    $table->index(['tenant_id', 'is_active'], 'idx_acc_tenant_active');
                }
            });
        }

        if (Schema::hasTable('acc_jobs')) {
            Schema::table('acc_jobs', function (Blueprint $table) {
                // Index for pending jobs
                if (!$this->indexExists('acc_jobs', 'idx_acc_jobs_status')) {
                    $table->index(['status', 'created_at'], 'idx_acc_jobs_status');
                }

                // Index for connector lookup (only if connector_id column exists)
                if (Schema::hasColumn('acc_jobs', 'connector_id') && !$this->indexExists('acc_jobs', 'idx_acc_jobs_connector')) {
                    $table->index('connector_id', 'idx_acc_jobs_connector');
                }
            });
        }

        // Insurance policies
        if (Schema::hasTable('ti_policies')) {
            Schema::table('ti_policies', function (Blueprint $table) {
                // Index for active policies
                if (!$this->indexExists('ti_policies', 'idx_policies_tenant_status')) {
                    $table->index(['tenant_id', 'status'], 'idx_policies_tenant_status');
                }

                // Index for expiration monitoring (only if expires_at column exists)
                if (Schema::hasColumn('ti_policies', 'expires_at') && !$this->indexExists('ti_policies', 'idx_policies_expires')) {
                    $table->index('expires_at', 'idx_policies_expires');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes in reverse order
        Schema::table('ti_policies', function (Blueprint $table) {
            $table->dropIndex('idx_policies_expires');
            $table->dropIndex('idx_policies_tenant_status');
        });

        Schema::table('acc_jobs', function (Blueprint $table) {
            $table->dropIndex('idx_acc_jobs_connector');
            $table->dropIndex('idx_acc_jobs_status');
        });

        Schema::table('acc_connectors', function (Blueprint $table) {
            $table->dropIndex('idx_acc_tenant_active');
        });

        Schema::table('inv_invites', function (Blueprint $table) {
            $table->dropIndex('idx_invites_code');
            $table->dropIndex('idx_invites_email_status');
            $table->dropIndex('idx_invites_batch_status');
        });

        Schema::table('inv_batches', function (Blueprint $table) {
            $table->dropIndex('idx_batches_event');
            $table->dropIndex('idx_batches_tenant_status');
        });

        if (Schema::hasTable('whatsapp_messages')) {
            Schema::table('whatsapp_messages', function (Blueprint $table) {
                $table->dropIndex('idx_whatsapp_tenant_time');
                $table->dropIndex('idx_whatsapp_status_scheduled');
            });
        }

        if (Schema::hasTable('anaf_queue')) {
            Schema::table('anaf_queue', function (Blueprint $table) {
                $table->dropIndex('idx_anaf_tenant_status');
                $table->dropIndex('idx_anaf_status_priority');
            });
        }

        if (Schema::hasTable('tenant_notifications')) {
            Schema::table('tenant_notifications', function (Blueprint $table) {
                $table->dropIndex('idx_notifications_tenant_time');
                $table->dropIndex('idx_notifications_status_scheduled');
            });
        }

        if (Schema::hasTable('feature_flags')) {
            Schema::table('feature_flags', function (Blueprint $table) {
                $table->dropIndex('idx_flags_key_tenant');
                $table->dropIndex('idx_flags_tenant_enabled');
            });
        }

        if (Schema::hasTable('tenant_webhook_deliveries')) {
            Schema::table('tenant_webhook_deliveries', function (Blueprint $table) {
                $table->dropIndex('idx_webhooks_event_status');
                $table->dropIndex('idx_webhooks_tenant_time');
                $table->dropIndex('idx_webhooks_retry');
            });
        }

        if (Schema::hasTable('microservice_metrics')) {
            Schema::table('microservice_metrics', function (Blueprint $table) {
                $table->dropIndex('idx_metrics_type_time');
                $table->dropIndex('idx_metrics_tenant_service_time');
            });
        }

        Schema::table('tenant_microservices', function (Blueprint $table) {
            $table->dropIndex('idx_tenant_microservices_microservice_status');
            $table->dropIndex('idx_tenant_microservices_tenant_status');
            $table->dropIndex('idx_tenant_microservices_status_expires');
        });
    }

    /**
     * Check if an index exists on a table
     *
     * @param string $table
     * @param string $index
     * @return bool
     */
    protected function indexExists(string $table, string $index): bool
    {
        $result = DB::select(
            "SHOW INDEX FROM `{$table}` WHERE Key_name = ?",
            [$index]
        );

        return count($result) > 0;
    }
};
