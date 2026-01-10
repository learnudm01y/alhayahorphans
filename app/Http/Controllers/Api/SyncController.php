<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Data;
use App\Models\RePeople;
use App\Models\DeadPepole;
use App\Models\Sponsorship;
use App\Models\GuardianBankAccount;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Controller: SyncController
 *
 * Purpose: Handle all synchronization operations between mobile app and Laravel
 *
 * Features:
 * - Check person existence across all tables
 * - Generate unique file IDs for new persons
 * - Handle new person entry with handshake verification
 * - Update sponsorship relation_id_number
 * - Sync progress tracking
 */
class SyncController extends Controller
{
    /**
     * POST /api/sync/check-person-all-tables
     *
     * Check if a person exists in any of the central tables
     * (data, re_people, dead_people)
     */
    public function checkPersonAllTables(Request $request): JsonResponse
    {
        $request->validate([
            'identity_number' => 'required|string'
        ]);

        $identity = $request->identity_number;

        // Check in data table (guardians)
        $guardian = Data::where('data_id_number', $identity)->first();
        if ($guardian) {
            return response()->json([
                'exists' => true,
                'found_in' => 'data',
                'file_id' => $guardian->file_id_number,
                'person_type' => 'guardian',
                'person_data' => [
                    'file_id_number' => $guardian->file_id_number,
                    'first_name' => $guardian->data_first_name,
                    'father_name' => $guardian->data_father_name,
                    'grand_father_name' => $guardian->data_grand_father_name,
                    'family_name' => $guardian->data_family_name,
                    'identity_number' => $guardian->data_id_number,
                    'phone_number' => $guardian->data_phone_number
                ]
            ]);
        }

        // Check in re_people table (orphans/family members)
        $orphan = RePeople::where('person_id', $identity)->first();
        if ($orphan) {
            return response()->json([
                'exists' => true,
                'found_in' => 're_people',
                'file_id' => $orphan->registration_id,
                'person_type' => 'orphan',
                'person_data' => [
                    'registration_id' => $orphan->registration_id,
                    'first_name' => $orphan->first_name,
                    'second_name' => $orphan->second_name,
                    'third_name' => $orphan->third_name,
                    'last_name' => $orphan->last_name,
                    'identity_number' => $orphan->person_id,
                    'gender' => $orphan->person_gender,
                    'birth_date' => $orphan->person_birth_date
                ]
            ]);
        }

        // Check in dead_people table
        $deceased = DeadPepole::where('father_id', $identity)
            ->orWhere('mother_id', $identity)
            ->first();
        if ($deceased) {
            $isfather = $deceased->father_id === $identity;
            return response()->json([
                'exists' => true,
                'found_in' => 'dead_people',
                'file_id' => $deceased->re_file_id,
                'person_type' => $isfather ? 'deceased_father' : 'deceased_mother',
                'person_data' => [
                    're_file_id' => $deceased->re_file_id,
                    'type' => $isfather ? 'father' : 'mother',
                    'identity_number' => $identity
                ]
            ]);
        }

        // Not found in any table
        return response()->json([
            'exists' => false,
            'message' => 'الشخص غير موجود في قاعدة البيانات المركزية'
        ]);
    }

    /**
     * POST /api/sync/check-person-exists
     *
     * Check if a person exists by type and identity
     */
    public function checkPersonExists(Request $request): JsonResponse
    {
        $request->validate([
            'person_type' => 'required|in:guardian,orphan,deceased',
            'identity_number' => 'required|string'
        ]);

        $fileID = null;
        $exists = false;
        $personData = null;

        switch ($request->person_type) {
            case 'guardian':
                $record = Data::where('data_id_number', $request->identity_number)->first();
                if ($record) {
                    $fileID = $record->file_id_number;
                    $exists = true;
                    $personData = $record;
                }
                break;

            case 'orphan':
                $record = RePeople::where('person_id', $request->identity_number)->first();
                if ($record) {
                    $fileID = $record->registration_id;
                    $exists = true;
                    $personData = $record;
                }
                break;

            case 'deceased':
                $record = DeadPepole::where('father_id', $request->identity_number)
                    ->orWhere('mother_id', $request->identity_number)
                    ->first();
                if ($record) {
                    $fileID = $record->re_file_id;
                    $exists = true;
                    $personData = $record;
                }
                break;
        }

        return response()->json([
            'success' => true,
            'exists' => $exists,
            'file_id' => $fileID,
            'person_type' => $request->person_type,
            'person_data' => $personData
        ]);
    }

    /**
     * POST /api/sync/generate-file-id
     *
     * Generate a unique file ID for a new person
     */
    public function generateFileID(Request $request): JsonResponse
    {
        $request->validate([
            'person_type' => 'required|in:guardian,orphan,deceased',
            'identity_number' => 'required|string',
            'person_name' => 'required|string',
            'handshake_token' => 'required|string',
            'device_id' => 'required|string'
        ]);

        try {
            DB::beginTransaction();

            // Check if person already exists
            $existingCheck = $this->checkPersonExists(new Request([
                'person_type' => $request->person_type,
                'identity_number' => $request->identity_number
            ]));

            if ($existingCheck->getData()->exists) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'الشخص موجود بالفعل في قاعدة البيانات',
                    'existing_file_id' => $existingCheck->getData()->file_id
                ], 409);
            }

            // Generate unique file ID
            $fileID = $this->generateUniqueFileNumber($request->person_type);

            // Determine target table
            $tableName = match($request->person_type) {
                'guardian' => 'data',
                'orphan' => 're_people',
                'deceased' => 'dead_people'
            };

            // Save to file_id_registry
            DB::table('file_id_registry')->insert([
                'file_id' => $fileID,
                'table_name' => $tableName,
                'person_type' => $request->person_type,
                'identity_number' => $request->identity_number,
                'person_name' => $request->person_name,
                'handshake_token' => $request->handshake_token,
                'device_id' => $request->device_id,
                'status' => 'reserved',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            DB::commit();

            Log::info('File ID generated', [
                'file_id' => $fileID,
                'person_type' => $request->person_type,
                'identity' => $request->identity_number
            ]);

            return response()->json([
                'success' => true,
                'file_id' => $fileID,
                'table_name' => $tableName,
                'handshake_token' => $request->handshake_token,
                'created_at' => now()->toISOString(),
                'message' => 'تم توليد رقم الملف بنجاح'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('File ID generation failed', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'فشل توليد رقم الملف: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/sync/new-person-entry
     *
     * Create a new person entry with auto-generated file ID
     */
    public function newPersonEntry(Request $request): JsonResponse
    {
        $request->validate([
            'is_new_person' => 'required|boolean',
            'sponsorship_id' => 'required|integer',
            'person_type' => 'required|in:guardian,orphan,deceased_father,deceased_mother',
            'person_data' => 'required|array',
            'person_data.first_name' => 'required|string',
            'person_data.identity_number' => 'required|string',
            'handshake_token' => 'required|string',
            'device_id' => 'required|string'
        ]);

        if (!$request->is_new_person) {
            return response()->json([
                'success' => false,
                'message' => 'This endpoint is only for new person entries'
            ], 400);
        }

        try {
            DB::beginTransaction();

            $personType = $request->person_type;
            $personData = $request->person_data;

            // Verify person doesn't exist
            $existingCheck = $this->checkPersonAllTables(new Request([
                'identity_number' => $personData['identity_number']
            ]));

            if ($existingCheck->getData()->exists) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'الشخص موجود بالفعل في قاعدة البيانات',
                    'existing_file_id' => $existingCheck->getData()->file_id,
                    'found_in' => $existingCheck->getData()->found_in
                ], 409);
            }

            // Map person type for file ID generation
            $mappedType = $this->mapPersonType($personType);

            // Generate unique file ID
            $fileID = $this->generateUniqueFileNumber($mappedType);

            // Determine target table and insert
            switch ($personType) {
                case 'guardian':
                    $this->insertGuardian($fileID, $personData);
                    $tableName = 'data';
                    break;

                case 'orphan':
                    $this->insertOrphan($fileID, $personData);
                    $tableName = 're_people';
                    break;

                case 'deceased_father':
                case 'deceased_mother':
                    $this->insertDeceased($fileID, $personData, $personType);
                    $tableName = 'dead_people';
                    break;
            }

            // Save to file_id_registry
            DB::table('file_id_registry')->insert([
                'file_id' => $fileID,
                'table_name' => $tableName,
                'person_type' => $mappedType,
                'identity_number' => $personData['identity_number'],
                'person_name' => $personData['first_name'] . ' ' . ($personData['last_name'] ?? ''),
                'handshake_token' => $request->handshake_token,
                'device_id' => $request->device_id,
                'status' => 'reserved',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            DB::commit();

            Log::info('New person entry created', [
                'file_id' => $fileID,
                'person_type' => $personType,
                'identity' => $personData['identity_number'],
                'sponsorship_id' => $request->sponsorship_id
            ]);

            return response()->json([
                'success' => true,
                'file_id' => $fileID,
                'table_name' => $tableName,
                'handshake_token' => $request->handshake_token,
                'message' => 'تم إدخال الشخص الجديد بنجاح'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('New person entry failed', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'فشل إدخال الشخص: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/sync/activate-file-id
     *
     * Activate a reserved file ID after successful data entry
     */
    public function activateFileID(Request $request): JsonResponse
    {
        $request->validate([
            'file_id' => 'required|string',
            'handshake_token' => 'required|string'
        ]);

        try {
            // Verify handshake token
            $registry = DB::table('file_id_registry')
                ->where('file_id', $request->file_id)
                ->where('handshake_token', $request->handshake_token)
                ->first();

            if (!$registry) {
                return response()->json([
                    'success' => false,
                    'message' => 'رقم الملف أو رمز التحقق غير صالح'
                ], 404);
            }

            // Update status to active
            DB::table('file_id_registry')
                ->where('file_id', $request->file_id)
                ->update([
                    'status' => 'active',
                    'activated_at' => now(),
                    'updated_at' => now()
                ]);

            Log::info('File ID activated', ['file_id' => $request->file_id]);

            return response()->json([
                'success' => true,
                'message' => 'تم تفعيل رقم الملف بنجاح'
            ]);

        } catch (\Exception $e) {
            Log::error('File ID activation failed', [
                'error' => $e->getMessage(),
                'file_id' => $request->file_id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'فشل تفعيل رقم الملف: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/sync/sponsorships/update-relation-id
     *
     * Update sponsorship with generated relation_id_number
     */
    public function updateSponsorshipRelationId(Request $request): JsonResponse
    {
        $request->validate([
            'sponsorship_id' => 'required|integer',
            'relation_id_number' => 'required|string',
            'handshake_token' => 'required|string'
        ]);

        try {
            // Verify handshake token
            $registry = DB::table('file_id_registry')
                ->where('file_id', $request->relation_id_number)
                ->where('handshake_token', $request->handshake_token)
                ->first();

            if (!$registry) {
                return response()->json([
                    'success' => false,
                    'message' => 'رمز التحقق غير صالح'
                ], 403);
            }

            // Update sponsorship
            $sponsorship = Sponsorship::findOrFail($request->sponsorship_id);
            $sponsorship->update([
                'relation_id_number' => $request->relation_id_number,
                'updated_at' => now()
            ]);

            Log::info('Sponsorship relation_id_number updated', [
                'sponsorship_id' => $request->sponsorship_id,
                'relation_id_number' => $request->relation_id_number
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث رقم الملف في الكفالة بنجاح'
            ]);

        } catch (\Exception $e) {
            Log::error('Sponsorship update failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'فشل تحديث رقم الملف: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/sync/eligible-sponsorships
     *
     * Get sponsorships eligible for sync (excluding disbursed)
     */
    public function getEligibleSponsorships(Request $request): JsonResponse
    {
        try {
            $excludeStatuses = ['ارسل للصرف', 'تم الصرف'];

            $sponsorships = Sponsorship::select([
                'sponsorships.id',
                'sponsorships.orphan_name',
                'sponsorships.guardian_name',
                'sponsorships.identity_number',
                'sponsorships.guardian_identity_number',
                'sponsorships.relation_id_number',
                'sponsorships.sponsorship_status_id',
                'sponsorship_statuses.description as status_description'
            ])
            ->leftJoin('sponsorship_statuses', 'sponsorships.sponsorship_status_id', '=', 'sponsorship_statuses.id')
            ->whereNotIn('sponsorship_statuses.description', $excludeStatuses)
            ->orderBy('sponsorships.created_at', 'desc')
            ->get();

            return response()->json([
                'success' => true,
                'count' => $sponsorships->count(),
                'sponsorships' => $sponsorships
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get eligible sponsorships', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'فشل جلب الكفالات'
            ], 500);
        }
    }

    /**
     * GET /api/sync/person-by-relation/{relationId}
     *
     * Get person data by relation ID
     */
    public function getPersonByRelation(string $relationId): JsonResponse
    {
        $data = [];

        // Guardian data
        $guardian = Data::where('file_id_number', $relationId)->first();
        if ($guardian) {
            $data['guardian'] = $guardian;

            // Bank accounts
            $data['bank_accounts'] = GuardianBankAccount::where('guardian_registration', $relationId)->get();
        }

        // Orphan data
        $orphan = RePeople::where('registration_id', $relationId)->first();
        if ($orphan) {
            $data['orphan'] = $orphan;
        }

        // Deceased data
        $deceased = DeadPepole::where('re_file_id', $relationId)->first();
        if ($deceased) {
            $data['deceased'] = $deceased;
        }

        return response()->json([
            'success' => true,
            'found' => !empty($data),
            'data' => $data
        ]);
    }

    // ==================== Private Methods ====================

    /**
     * Generate unique file number using central algorithm
     */
    private function generateUniqueFileNumber(string $personType): string
    {
        $prefix = match($personType) {
            'guardian' => 'G',
            'orphan' => 'O',
            'deceased' => 'D',
            default => 'X'
        };

        $year = date('Y');

        // Get last sequence number for current year
        $lastNumber = DB::table('file_id_registry')
            ->where('person_type', $personType)
            ->where('file_id', 'LIKE', $prefix . $year . '%')
            ->orderBy('file_id', 'desc')
            ->value('file_id');

        if ($lastNumber) {
            $sequence = intval(substr($lastNumber, -6)) + 1;
        } else {
            $sequence = 1;
        }

        // Format: PREFIX + YEAR + 6-digit sequence
        return $prefix . $year . str_pad($sequence, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Map person type for file ID generation
     */
    private function mapPersonType(string $type): string
    {
        return match($type) {
            'guardian' => 'guardian',
            'orphan' => 'orphan',
            'deceased_father', 'deceased_mother' => 'deceased',
            default => 'orphan'
        };
    }

    /**
     * Insert new guardian record
     */
    private function insertGuardian(string $fileID, array $data): void
    {
        Data::create([
            'file_id_number' => $fileID,
            'data_first_name' => $data['first_name'],
            'data_father_name' => $data['second_name'] ?? null,
            'data_grand_father_name' => $data['third_name'] ?? null,
            'data_family_name' => $data['last_name'] ?? null,
            'data_id_number' => $data['identity_number'],
            'data_phone_number' => $data['phone_number'] ?? null,
            'data_alt_phone_number' => $data['alt_phone_number'] ?? null,
            'data_province' => $data['province'] ?? null,
            'data_current_address' => $data['address'] ?? null
        ]);
    }

    /**
     * Insert new orphan record
     */
    private function insertOrphan(string $fileID, array $data): void
    {
        RePeople::create([
            'registration_id' => $fileID,
            'first_name' => $data['first_name'],
            'second_name' => $data['second_name'] ?? null,
            'third_name' => $data['third_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'person_id' => $data['identity_number'],
            'person_gender' => $data['gender'] ?? null,
            'person_birth_date' => $data['birth_date'] ?? null
        ]);
    }

    /**
     * Insert new deceased person record
     */
    private function insertDeceased(string $fileID, array $data, string $type): void
    {
        $isFather = $type === 'deceased_father';
        $prefix = $isFather ? 'father' : 'mother';

        DeadPepole::create([
            're_file_id' => $fileID,
            "{$prefix}_first_name" => $data['first_name'],
            "{$prefix}_second_name" => $data['second_name'] ?? null,
            "{$prefix}_third_name" => $data['third_name'] ?? null,
            "{$prefix}_last_name" => $data['last_name'] ?? null,
            "{$prefix}_id" => $data['identity_number']
        ]);
    }
}
