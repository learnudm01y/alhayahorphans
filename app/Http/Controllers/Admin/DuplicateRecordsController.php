<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\Storage;

class DuplicateRecordsController extends Controller
{
    // Mapping table -> identity column(s)
    private array $mappings = [
        'data' => ['data_id_number'],
        'dead_people' => ['father_id', 'mother_id'],
        're_people' => ['person_id'],
    ];

    // Return duplicate id values that appear more than once in the DB
    public function findDbDuplicates(Request $request)
    {
        $table = $request->input('table');
        if (!isset($this->mappings[$table])) {
            return response()->json(['success' => false, 'message' => 'Unknown table'], 400);
        }

        try {
            $result = [];
            foreach ($this->mappings[$table] as $column) {
                if (!DB::getSchemaBuilder()->hasColumn($table, $column)) {
                    continue;
                }

                $duplicates = DB::table($table)
                    ->select($column, DB::raw('COUNT(*) as cnt'))
                    ->whereNotNull($column)
                    ->where($column, '!=', '')
                    ->groupBy($column)
                    ->having('cnt', '>', 1)
                    ->get()
                    ->map(function ($r) use ($column) {
                        return ['column' => $column, 'id' => $r->$column, 'count' => $r->cnt];
                    })->toArray();

                $result = array_merge($result, $duplicates);
            }

            return response()->json(['success' => true, 'duplicates' => $result]);
        } catch (\Exception $e) {
            Log::error('findDbDuplicates error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Check a list of identity numbers against the DB and return which already exist
    public function checkExisting(Request $request)
    {
        $table = $request->input('table');
        $ids = $request->input('ids', []);

        if (!isset($this->mappings[$table]) || empty($ids)) {
            return response()->json(['success' => false, 'message' => 'Invalid input'], 400);
        }

        try {
            $existing = [];
            foreach ($this->mappings[$table] as $column) {
                if (!DB::getSchemaBuilder()->hasColumn($table, $column)) {
                    continue;
                }

                $rows = DB::table($table)
                    ->whereIn($column, $ids)
                    ->whereNotNull($column)
                    ->where($column, '!=', '')
                    ->pluck($column)
                    ->unique()
                    ->values()
                    ->toArray();

                $existing = array_merge($existing, $rows);
            }

            $existing = array_values(array_unique($existing));

            return response()->json(['success' => true, 'existing' => $existing]);
        } catch (\Exception $e) {
            Log::error('checkExisting error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Process insertion of multiple records: skip duplicates, return counts and cached skipped ids for export
    public function processInsert(Request $request)
    {
        $table = $request->input('table');
        $rows = $request->input('rows', []);
    $cacheIds = $request->input('cache_ids', []);

        if (!isset($this->mappings[$table]) || !is_array($rows)) {
            return response()->json(['success' => false, 'message' => 'Invalid input'], 400);
        }

        // If caller only wants to cache duplicate ids for export without inserting rows
        if (empty($rows) && !empty($cacheIds) && is_array($cacheIds)) {
            $cacheKey = 'duplicate_export_' . uniqid();
            Cache::put($cacheKey, array_values(array_unique($cacheIds)), 3600);
            return response()->json([
                'success' => true,
                'inserted' => 0,
                'skipped' => count($cacheIds),
                'skipped_ids' => array_values(array_unique($cacheIds)),
                'cache_key' => $cacheKey,
            ]);
        }

        $identityColumns = $this->mappings[$table];
        $inserted = 0;
        $skipped = 0;
        $skippedIds = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $row) {
                // Determine identity values for this row
                $identityValues = [];
                foreach ($identityColumns as $col) {
                    if (isset($row[$col]) && $row[$col] !== '') {
                        $identityValues[$col] = $row[$col];
                    }
                }

                // If no identity values present, insert as normal
                if (empty($identityValues)) {
                    DB::table($table)->insert($row);
                    $inserted++;
                    continue;
                }

                // Check if any of the identity values exist already
                $exists = false;
                foreach ($identityValues as $col => $val) {
                    $count = DB::table($table)->where($col, $val)->count();
                    if ($count > 0) {
                        $exists = true;
                        $skippedIds[] = $val;
                        break;
                    }
                }

                if ($exists) {
                    $skipped++;
                    continue; // skip inserting this duplicated record
                }

                // Insert non-duplicate record
                DB::table($table)->insert($row);
                $inserted++;
            }

            DB::commit();

            // Cache skipped ids for possible export (key returned to client)
            $cacheKey = 'duplicate_export_' . uniqid();
            Cache::put($cacheKey, array_values(array_unique($skippedIds)), 3600);

            return response()->json([
                'success' => true,
                'inserted' => $inserted,
                'skipped' => $skipped,
                'skipped_ids' => array_values(array_unique($skippedIds)),
                'cache_key' => $cacheKey,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('processInsert error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Export cached duplicate ids to Excel and return download
    public function exportDuplicates(Request $request, $cacheKey)
    {
        try {
            if (!Cache::has($cacheKey)) {
                return response()->json(['success' => false, 'message' => 'No data to export or expired'], 404);
            }

            $ids = Cache::get($cacheKey, []);
            if (empty($ids)) {
                return response()->json(['success' => false, 'message' => 'No ids to export'], 400);
            }

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setCellValue('A1', 'id_number');
            $rowNum = 2;
            foreach ($ids as $id) {
                $sheet->setCellValue('A' . $rowNum, $id);
                $rowNum++;
            }

            $filename = 'duplicate_ids_' . date('Ymd_His') . '.xlsx';
            $path = 'exports/duplicates/' . $filename;
            // Ensure directory exists
            Storage::makeDirectory('exports/duplicates');

            $writer = new Xlsx($spreadsheet);
            $tmpFile = sys_get_temp_dir() . '/' . $filename;
            $writer->save($tmpFile);
            Storage::put($path, file_get_contents($tmpFile));

            // Return the stored file as download
            return response()->download(Storage::path($path))->deleteFileAfterSend(false);
        } catch (\Exception $e) {
            Log::error('exportDuplicates error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
