<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class FileAnalyticsController extends Controller
{
    /**
     * جلب إحصائيات الملفات من جداول attachments وenhanced_attachments وduplicate_files_temp
     */
    public function getAnalytics()
    {
        try {
            // إحصائيات من جدول attachments
            $attachmentsStats = $this->getAttachmentsStats();

            // إحصائيات من جدول enhanced_attachments
            $enhancedStats = $this->getEnhancedAttachmentsStats();

            // إحصائيات الملفات المكررة
            $duplicateStats = $this->getDuplicateFilesStats();

            // دمج الإحصائيات مع التفصيل
            $analytics = [
                // إجمالي الملفات
                'total_files' => [
                    'attachments' => $attachmentsStats['total'],
                    'enhanced_attachments' => $enhancedStats['total'],
                    'duplicate_files_temp' => $duplicateStats['total'],
                    'grand_total' => $attachmentsStats['total'] + $enhancedStats['total'] + $duplicateStats['total']
                ],

                // حجم التخزين
                'total_storage_size' => [
                    'attachments' => $attachmentsStats['size'],
                    'enhanced_attachments' => $enhancedStats['size'],
                    'duplicate_files_temp' => $duplicateStats['size'],
                    'grand_total' => $attachmentsStats['size'] + $enhancedStats['size'] + $duplicateStats['size']
                ],

                // ملفات اليوم
                'today_files' => [
                    'attachments' => $attachmentsStats['today'],
                    'enhanced_attachments' => $enhancedStats['today'],
                    'duplicate_files_temp' => $duplicateStats['today'],
                    'grand_total' => $attachmentsStats['today'] + $enhancedStats['today'] + $duplicateStats['today']
                ],

                // تفصيل أنواع الملفات في enhanced_attachments
                'file_types_breakdown' => $enhancedStats['file_types'],

                // إحصائيات الملفات المكررة
                'duplicate_files' => [
                    'total_duplicates' => $duplicateStats['total'],
                    'unique_hashes' => $duplicateStats['unique'],
                    'wasted_space' => $duplicateStats['size']
                ],

                // تفصيل الجداول الكامل
                'tables_breakdown' => [
                    'attachments' => $attachmentsStats,
                    'enhanced_attachments' => $enhancedStats,
                    'duplicate_files_temp' => $duplicateStats
                ],

                'last_updated' => Carbon::now()->format('Y-m-d H:i:s')
            ];

            return response()->json($analytics);

        } catch (\Exception $e) {
            Log::error('خطأ في جلب إحصائيات الملفات: ' . $e->getMessage());

            return response()->json([
                'total_files' => ['grand_total' => 0],
                'total_storage_size' => ['grand_total' => 0],
                'today_files' => ['grand_total' => 0],
                'duplicate_files' => ['total_duplicates' => 0],
                'error' => 'فشل في جلب الإحصائيات'
            ], 500);
        }
    }

    /**
     * إحصائيات جدول attachments
     */
    private function getAttachmentsStats()
    {
        try {
            $today = Carbon::today();

            $stats = DB::table('attachments')
                ->selectRaw('
                    COUNT(*) as total_count,
                    COALESCE(SUM(file_size), 0) as total_size,
                    COUNT(CASE WHEN DATE(created_at) = ? THEN 1 END) as today_count
                ', [$today->format('Y-m-d')])
                ->first();

            return [
                'total' => $stats->total_count ?? 0,
                'size' => $stats->total_size ?? 0,
                'today' => $stats->today_count ?? 0
            ];

        } catch (\Exception $e) {
            Log::warning('خطأ في جلب إحصائيات attachments: ' . $e->getMessage());
            return ['total' => 0, 'size' => 0, 'today' => 0];
        }
    }

    /**
     * إحصائيات جدول enhanced_attachments
     */
    private function getEnhancedAttachmentsStats()
    {
        try {
            $today = Carbon::today();

            // الإحصائيات العامة
            $stats = DB::table('enhanced_attachments')
                ->selectRaw('
                    COUNT(*) as total_count,
                    COALESCE(SUM(file_size), 0) as total_size,
                    COUNT(CASE WHEN DATE(created_at) = ? THEN 1 END) as today_count
                ', [$today->format('Y-m-d')])
                ->first();

            // تفصيل أنواع الملفات
            $fileTypes = DB::table('enhanced_attachments')
                ->selectRaw('
                    file_extension,
                    COUNT(*) as count,
                    COALESCE(SUM(file_size), 0) as size
                ')
                ->groupBy('file_extension')
                ->get()
                ->mapWithKeys(function ($item) {
                    $extension = strtolower($item->file_extension ?: 'unknown');
                    return [$extension => [
                        'count' => $item->count,
                        'size' => $item->size
                    ]];
                })
                ->toArray();

            // تجميع حسب الفئات الرئيسية
            $categorizedTypes = [
                'images' => [
                    'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp'],
                    'count' => 0,
                    'size' => 0
                ],
                'pdf' => [
                    'extensions' => ['pdf'],
                    'count' => 0,
                    'size' => 0
                ],
                'excel' => [
                    'extensions' => ['xlsx', 'xls', 'csv'],
                    'count' => 0,
                    'size' => 0
                ],
                'word' => [
                    'extensions' => ['docx', 'doc'],
                    'count' => 0,
                    'size' => 0
                ],
                'archive' => [
                    'extensions' => ['zip', 'rar', '7z', 'tar', 'gz'],
                    'count' => 0,
                    'size' => 0
                ],
                'other' => [
                    'extensions' => [],
                    'count' => 0,
                    'size' => 0
                ]
            ];

            // تصنيف الملفات
            foreach ($fileTypes as $ext => $data) {
                $categorized = false;
                foreach ($categorizedTypes as $category => &$categoryData) {
                    if (in_array($ext, $categoryData['extensions'])) {
                        $categoryData['count'] += $data['count'];
                        $categoryData['size'] += $data['size'];
                        $categorized = true;
                        break;
                    }
                }
                if (!$categorized) {
                    $categorizedTypes['other']['count'] += $data['count'];
                    $categorizedTypes['other']['size'] += $data['size'];
                }
            }

            return [
                'total' => $stats->total_count ?? 0,
                'size' => $stats->total_size ?? 0,
                'today' => $stats->today_count ?? 0,
                'file_types' => $categorizedTypes,
                'detailed_extensions' => $fileTypes
            ];

        } catch (\Exception $e) {
            Log::warning('خطأ في جلب إحصائيات enhanced_attachments: ' . $e->getMessage());
            return [
                'total' => 0,
                'size' => 0,
                'today' => 0,
                'file_types' => [],
                'detailed_extensions' => []
            ];
        }
    }

    /**
     * إحصائيات الملفات المكررة
     */
    private function getDuplicateFilesStats()
    {
        try {
            $today = Carbon::today();

            // التحقق من وجود الجدول والأعمدة أولاً
            if (!DB::getSchemaBuilder()->hasTable('duplicate_files_temp')) {
                Log::info('جدول duplicate_files_temp غير موجود');
                return ['total' => 0, 'unique' => 0, 'size' => 0, 'today' => 0];
            }

            // فحص الأعمدة المتاحة
            $columns = DB::getSchemaBuilder()->getColumnListing('duplicate_files_temp');

            // استعلام مبسط بدون file_hash إذا لم يكن موجوداً
            if (in_array('file_hash', $columns)) {
                $stats = DB::table('duplicate_files_temp')
                    ->selectRaw('
                        COUNT(DISTINCT file_hash) as unique_duplicates,
                        COUNT(*) as total_duplicates,
                        COALESCE(SUM(file_size), 0) as total_size,
                        COUNT(CASE WHEN DATE(created_at) = ? THEN 1 END) as today_count
                    ', [$today->format('Y-m-d')])
                    ->first();
            } else {
                // استعلام بديل بدون file_hash
                $stats = DB::table('duplicate_files_temp')
                    ->selectRaw('
                        COUNT(*) as total_duplicates,
                        COALESCE(SUM(CASE WHEN file_size IS NOT NULL THEN file_size ELSE 0 END), 0) as total_size,
                        COUNT(CASE WHEN DATE(created_at) = ? THEN 1 END) as today_count
                    ', [$today->format('Y-m-d')])
                    ->first();

                $stats->unique_duplicates = 0; // افتراضي إذا لم يكن file_hash موجوداً
            }

            return [
                'total' => $stats->total_duplicates ?? 0,
                'unique' => $stats->unique_duplicates ?? 0,
                'size' => $stats->total_size ?? 0,
                'today' => $stats->today_count ?? 0
            ];

        } catch (\Exception $e) {
            Log::warning('خطأ في جلب إحصائيات duplicate_files_temp: ' . $e->getMessage());
            return ['total' => 0, 'unique' => 0, 'size' => 0, 'today' => 0];
        }
    }

    /**
     * إحصائيات مفصلة حسب نوع الملف
     */
    public function getDetailedAnalytics()
    {
        try {
            $fileTypes = $this->getFileTypesBreakdown();
            $monthlyStats = $this->getMonthlyStats();
            $storageAnalysis = $this->getStorageAnalysis();

            return response()->json([
                'file_types' => $fileTypes,
                'monthly_stats' => $monthlyStats,
                'storage_analysis' => $storageAnalysis,
                'generated_at' => Carbon::now()->format('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            Log::error('خطأ في جلب الإحصائيات المفصلة: ' . $e->getMessage());
            return response()->json(['error' => 'فشل في جلب الإحصائيات المفصلة'], 500);
        }
    }

    /**
     * تحليل أنواع الملفات
     */
    private function getFileTypesBreakdown()
    {
        $attachmentTypes = DB::table('attachments')
            ->selectRaw('file_extension, COUNT(*) as count, SUM(file_size) as total_size')
            ->groupBy('file_extension')
            ->get();

        $enhancedTypes = DB::table('enhanced_attachments')
            ->selectRaw('file_extension, COUNT(*) as count, SUM(file_size) as total_size')
            ->groupBy('file_extension')
            ->get();

        // دمج النتائج
        $combined = [];
        foreach ($attachmentTypes as $type) {
            $ext = $type->file_extension ?: 'unknown';
            $combined[$ext] = [
                'count' => $type->count,
                'size' => $type->total_size
            ];
        }

        foreach ($enhancedTypes as $type) {
            $ext = $type->file_extension ?: 'unknown';
            if (isset($combined[$ext])) {
                $combined[$ext]['count'] += $type->count;
                $combined[$ext]['size'] += $type->total_size;
            } else {
                $combined[$ext] = [
                    'count' => $type->count,
                    'size' => $type->total_size
                ];
            }
        }

        return $combined;
    }

    /**
     * إحصائيات شهرية
     */
    private function getMonthlyStats()
    {
        $months = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $monthKey = $month->format('Y-m');

            $attachmentCount = DB::table('attachments')
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->count();

            $enhancedCount = DB::table('enhanced_attachments')
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->count();

            $months[$monthKey] = [
                'month' => $month->format('Y-m'),
                'month_name' => $month->format('F Y'),
                'attachments' => $attachmentCount,
                'enhanced' => $enhancedCount,
                'total' => $attachmentCount + $enhancedCount
            ];
        }

        return $months;
    }

    /**
     * تحليل التخزين
     */
    private function getStorageAnalysis()
    {
        $attachmentSize = DB::table('attachments')->sum('file_size') ?? 0;
        $enhancedSize = DB::table('enhanced_attachments')->sum('file_size') ?? 0;
        $duplicateSize = DB::table('duplicate_files_temp')->sum('file_size') ?? 0;

        return [
            'attachments_size' => $attachmentSize,
            'enhanced_size' => $enhancedSize,
            'duplicate_size' => $duplicateSize,
            'total_size' => $attachmentSize + $enhancedSize,
            'wasted_space' => $duplicateSize,
            'efficiency_percentage' => $duplicateSize > 0 ?
                round((($attachmentSize + $enhancedSize) / ($attachmentSize + $enhancedSize + $duplicateSize)) * 100, 2) : 100
        ];
    }
}
