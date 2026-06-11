<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Database Backup Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for automated database backups, retention policies,
    | and disaster recovery settings.
    |
    */

    'enabled' => env('BACKUP_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Backup Schedule
    |--------------------------------------------------------------------------
    */

    'schedule' => [
        // Full backup frequency
        'full_backup' => env('BACKUP_FULL_CRON', '0 2 * * *'), // Daily at 2 AM

        // Incremental backup frequency (WAL shipping for PostgreSQL)
        'incremental' => env('BACKUP_INCREMENTAL_CRON', '*/15 * * * *'), // Every 15 minutes

        // Transaction log backup (for PITR)
        'transaction_log' => env('BACKUP_TRANSACTION_LOG', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention Policy
    |--------------------------------------------------------------------------
    */

    'retention' => [
        // How many daily backups to keep
        'daily' => env('BACKUP_RETENTION_DAILY', 7),

        // How many weekly backups to keep
        'weekly' => env('BACKUP_RETENTION_WEEKLY', 4),

        // How many monthly backups to keep
        'monthly' => env('BACKUP_RETENTION_MONTHLY', 12),

        // How long to keep transaction logs (for PITR)
        'transaction_logs_days' => env('BACKUP_TRANSACTION_LOG_DAYS', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    */

    'storage' => [
        // Primary backup storage
        'disk' => env('BACKUP_DISK', 'backup'),

        // Remote storage for offsite backups
        'remote' => [
            'enabled' => env('BACKUP_REMOTE_ENABLED', false),
            'driver' => env('BACKUP_REMOTE_DRIVER', 's3'), // s3, gcs, azure
            'bucket' => env('BACKUP_REMOTE_BUCKET', ''),
            'path' => env('BACKUP_REMOTE_PATH', 'database-backups'),
            'region' => env('BACKUP_REMOTE_REGION', 'eu-central-1'),
        ],

        // Local backup path (for initial dump before transfer)
        'local_path' => env('BACKUP_LOCAL_PATH', storage_path('backups')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Encryption
    |--------------------------------------------------------------------------
    */

    'encryption' => [
        'enabled' => env('BACKUP_ENCRYPTION_ENABLED', true),
        'algorithm' => env('BACKUP_ENCRYPTION_ALGORITHM', 'aes-256-cbc'),
        'key' => env('BACKUP_ENCRYPTION_KEY'), // Must be set in production
    ],

    /*
    |--------------------------------------------------------------------------
    | Compression
    |--------------------------------------------------------------------------
    */

    'compression' => [
        'enabled' => env('BACKUP_COMPRESSION_ENABLED', true),
        'algorithm' => env('BACKUP_COMPRESSION_ALGORITHM', 'gzip'), // gzip, bzip2, xz
        'level' => env('BACKUP_COMPRESSION_LEVEL', 6), // 1-9
    ],

    /*
    |--------------------------------------------------------------------------
    | Verification
    |--------------------------------------------------------------------------
    */

    'verification' => [
        // Verify backup integrity after creation
        'enabled' => env('BACKUP_VERIFICATION_ENABLED', true),

        // Test restore to temporary database periodically
        'test_restore' => env('BACKUP_TEST_RESTORE', false),
        'test_restore_cron' => env('BACKUP_TEST_RESTORE_CRON', '0 4 * * 0'), // Weekly on Sunday
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */

    'notifications' => [
        'enabled' => env('BACKUP_NOTIFICATIONS_ENABLED', true),

        // Notify on successful backup
        'on_success' => env('BACKUP_NOTIFY_SUCCESS', false),

        // Notify on failed backup
        'on_failure' => env('BACKUP_NOTIFY_FAILURE', true),

        // Notify on verification failure
        'on_verification_failure' => env('BACKUP_NOTIFY_VERIFICATION_FAILURE', true),

        // Notification channels
        'channels' => ['mail', 'slack'],

        // Recipients
        'recipients' => [
            'mail' => env('BACKUP_NOTIFICATION_EMAIL', 'admin@example.com'),
            'slack' => env('BACKUP_NOTIFICATION_SLACK_WEBHOOK', ''),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Recovery Objectives
    |--------------------------------------------------------------------------
    */

    'recovery_objectives' => [
        // Recovery Time Objective (target time to restore service)
        'rto_minutes' => env('BACKUP_RTO_MINUTES', 240), // 4 hours

        // Recovery Point Objective (maximum acceptable data loss)
        'rpo_minutes' => env('BACKUP_RPO_MINUTES', 15), // 15 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Database-Specific Settings
    |--------------------------------------------------------------------------
    */

    'postgresql' => [
        // Use pg_dump custom format for faster restore
        'format' => env('BACKUP_PG_FORMAT', 'custom'), // custom, plain, directory, tar

        // Number of parallel jobs for dump/restore
        'jobs' => env('BACKUP_PG_JOBS', 4),

        // Include/exclude specific schemas
        'schemas' => env('BACKUP_PG_SCHEMAS', 'public'),

        // Exclude tables (comma-separated)
        'exclude_tables' => env('BACKUP_PG_EXCLUDE_TABLES', 'sessions,cache,jobs,failed_jobs'),

        // WAL archiving for PITR
        'wal_archiving' => [
            'enabled' => env('BACKUP_PG_WAL_ENABLED', false),
            'archive_command' => env('BACKUP_PG_WAL_ARCHIVE_COMMAND', ''),
        ],
    ],

    'mysql' => [
        // Use mysqldump with single-transaction for consistent backups
        'single_transaction' => true,

        // Quick mode (retrieve rows one at a time)
        'quick' => true,

        // Lock tables
        'lock_tables' => false,

        // Exclude tables
        'exclude_tables' => env('BACKUP_MYSQL_EXCLUDE_TABLES', 'sessions,cache,jobs,failed_jobs'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring
    |--------------------------------------------------------------------------
    */

    'monitoring' => [
        // Health check endpoint
        'health_check' => env('BACKUP_HEALTH_CHECK_ENABLED', true),

        // Maximum age of last successful backup before alerting (in hours)
        'max_backup_age_hours' => env('BACKUP_MAX_AGE_HOURS', 25),

        // Minimum backup size (to detect empty/failed backups)
        'min_backup_size_mb' => env('BACKUP_MIN_SIZE_MB', 1),

        // External monitoring service (e.g., healthchecks.io)
        'ping_url' => env('BACKUP_PING_URL', ''),
    ],
];
