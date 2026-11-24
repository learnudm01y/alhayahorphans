<?php

use App\Services\DesktopSync\ChangeLogWriter;
use App\Services\DesktopSync\SchemaGuard;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private array $capturedLaravelTables = [
        'academic_degrees',
        'additional_dead_people',
        'additional_deceased',
        'additional_deceased_people',
        'admin_dashboard_snapshot_v4',
        'admin_reports_source_v4',
        'aid_management',
        'aid_status',
        'association_employees',
        'attachment_face_embeddings',
        'attachment_face_results',
        'attachment_face_scan_events',
        'attachment_face_scan_groups',
        'attachment_face_scans',
        'attachment_trash',
        'attachments',
        'backup_runs',
        'bank_names',
        'category_of_relations',
        'chunked_uploads',
        'ci_birth_cd',
        'ci_birth_tb_cd',
        'ci_personal_cd',
        'city',
        'conflict_review_queue_v4',
        'creativity_aspects',
        'currency_types',
        'data',
        'dead_people',
        'death_reasons',
        'deaths_list',
        'device_registry_v4',
        'displacement_statuses',
        'document_types',
        'duplicate_files_temp',
        'employment',
        'enhanced_attachments',
        'failed_jobs',
        'file_id_registry',
        'file_index_v4',
        'files',
        'general_category',
        'google_drive_uploads',
        'guardian_bank_accounts',
        'health_statuses',
        'housing_status',
        'jobs',
        'marital_status',
        'member_transfer_requests',
        'model_has_permissions',
        'model_has_roles',
        'offline_upload_statuses',
        'orphan_needs',
        'password_reset_tokens',
        'permissions',
        'permissions_manifest_v4',
        'personal_access_tokens',
        'persons',
        'portal_general_registration_field_values',
        'provinces',
        're_people',
        'record_audit_log_v4',
        'refresh_tokens',
        'relations',
        'reports_designs',
        'representative_of_associations',
        'request_status',
        'reserved_codes',
        'role_has_permissions',
        'roles',
        'server_sync_actions',
        'sms_groups',
        'sms_logs',
        'sms_settings',
        'sms_templates',
        'sponsor_document_types',
        'sponsor_field_settings',
        'sponsor_report_designs',
        'sponsors',
        'sponsorship_sponsor',
        'sponsorship_statuses',
        'sponsorships',
        'sync_idempotency_log_v4',
        'sync_outbox_v4',
        'sync_progress',
        'type_of_accommodation',
        'type_of_guarantee',
        'users',
        'whatsapp_faq',
        'whatsapp_logs',
        'whatsapp_settings',
        'whatsapp_unanswered_questions',
    ];

    public function up(): void
    {
        $writer = new ChangeLogWriter(new SchemaGuard());

        foreach ($this->capturedLaravelTables as $table) {
            $writer->installTriggersFor($table);
        }
    }

    public function down(): void
    {
        $writer = new ChangeLogWriter(new SchemaGuard());

        foreach ($this->capturedLaravelTables as $table) {
            $writer->removeTriggersFor($table);
        }
    }
};
