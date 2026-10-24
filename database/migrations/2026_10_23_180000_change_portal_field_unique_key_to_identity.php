<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('portal_general_registration_field_values')
            ->select('identity_number', 'field_key')
            ->whereNotNull('identity_number')
            ->groupBy('identity_number', 'field_key')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $rows = DB::table('portal_general_registration_field_values')
                ->where('identity_number', $duplicate->identity_number)
                ->where('field_key', $duplicate->field_key)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get([
                    'id',
                    'file_id_number',
                    'sponsorship_id',
                    'updated_by_user_id',
                ]);

            $survivor = $rows->first();
            $updates = [];
            foreach (['file_id_number', 'sponsorship_id', 'updated_by_user_id'] as $column) {
                if ($survivor->{$column} === null) {
                    foreach ($rows->skip(1) as $row) {
                        if ($row->{$column} !== null) {
                            $updates[$column] = $row->{$column};
                            break;
                        }
                    }
                }
            }

            if ($updates !== []) {
                DB::table('portal_general_registration_field_values')
                    ->where('id', $survivor->id)
                    ->update($updates);
            }

            DB::table('portal_general_registration_field_values')
                ->whereIn('id', $rows->skip(1)->pluck('id'))
                ->delete();
        }

        Schema::table('portal_general_registration_field_values', function (Blueprint $table) {
            $table->dropUnique('pgrfv_file_field_uq');
            $table->unique(['identity_number', 'field_key'], 'pgrfv_identity_field_uq');
        });
    }

    public function down(): void
    {
        $duplicate = DB::table('portal_general_registration_field_values')
            ->select('file_id_number', 'field_key')
            ->whereNotNull('file_id_number')
            ->groupBy('file_id_number', 'field_key')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicate) {
            throw new RuntimeException(
                'Cannot restore the portal file/field unique key: duplicate file_id_number and field_key values now exist.'
            );
        }

        Schema::table('portal_general_registration_field_values', function (Blueprint $table) {
            $table->dropUnique('pgrfv_identity_field_uq');
            $table->unique(['file_id_number', 'field_key'], 'pgrfv_file_field_uq');
        });
    }
};
