<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sponsor;
use App\Models\SponsorReportDesign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class SponsorReportDesignController extends Controller
{
    /**
     * حفظ أو تحديث تصميم التقرير للجمعية
     */
    public function saveReportDesign(Request $request)
    {
        \Log::info('=== بدء حفظ تصميم التقرير ===', [
            'sponsor_id' => $request->sponsor_id,
            'background_type' => $request->background_type,
            'has_single_image' => $request->hasFile('single_image'),
            'has_header_image' => $request->hasFile('header_image'),
            'has_main_image' => $request->hasFile('main_image'),
            'has_footer_image' => $request->hasFile('footer_image'),
        ]);

        $validator = Validator::make($request->all(), [
            'sponsor_id' => 'required|exists:sponsors,id',
            'background_type' => 'required|in:single,triple',
            'single_image' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            'header_image' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            'main_image' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            'footer_image' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            'primary_color' => 'nullable|string',
            'secondary_color' => 'nullable|string',
            'accent_color' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            \Log::error('فشل التحقق من البيانات', [
                'errors' => $validator->errors()->toArray()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'بيانات غير صحيحة',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // البحث عن التصميم الموجود أو إنشاء جديد
            $design = SponsorReportDesign::updateOrCreate(
                ['sponsor_id' => $request->sponsor_id],
                ['background_type' => $request->background_type]
            );

            \Log::info('تم العثور على/إنشاء التصميم', [
                'design_id' => $design->id,
                'is_new' => $design->wasRecentlyCreated
            ]);

            // معالجة الصور
            if ($request->hasFile('single_image')) {
                // حذف الصورة القديمة
                if ($design->single_image) {
                    Storage::disk('public')->delete($design->single_image);
                    \Log::info('تم حذف الصورة الواحدة القديمة', ['path' => $design->single_image]);
                }

                $path = $request->file('single_image')->store('report_designs', 'public');
                $design->single_image = $path;

                \Log::info('تم حفظ الصورة الواحدة', [
                    'path' => $path,
                    'full_path' => storage_path('app/public/' . $path)
                ]);
            }

            if ($request->hasFile('header_image')) {
                if ($design->header_image) {
                    Storage::disk('public')->delete($design->header_image);
                    \Log::info('تم حذف صورة الرأس القديمة', ['path' => $design->header_image]);
                }

                $path = $request->file('header_image')->store('report_designs', 'public');
                $design->header_image = $path;

                \Log::info('تم حفظ صورة الرأس', [
                    'path' => $path,
                    'full_path' => storage_path('app/public/' . $path)
                ]);
            }

            if ($request->hasFile('main_image')) {
                if ($design->main_image) {
                    Storage::disk('public')->delete($design->main_image);
                    \Log::info('تم حذف الصورة الرئيسية القديمة', ['path' => $design->main_image]);
                }

                $path = $request->file('main_image')->store('report_designs', 'public');
                $design->main_image = $path;

                \Log::info('تم حفظ الصورة الرئيسية', [
                    'path' => $path,
                    'full_path' => storage_path('app/public/' . $path)
                ]);
            }

            if ($request->hasFile('footer_image')) {
                if ($design->footer_image) {
                    Storage::disk('public')->delete($design->footer_image);
                    \Log::info('تم حذف صورة التذييل القديمة', ['path' => $design->footer_image]);
                }

                $path = $request->file('footer_image')->store('report_designs', 'public');
                $design->footer_image = $path;

                \Log::info('تم حفظ صورة التذييل', [
                    'path' => $path,
                    'full_path' => storage_path('app/public/' . $path)
                ]);
            }

            // حفظ الألوان
            $themeColors = [
                'primary' => $request->primary_color ?? '#1a1a1a',
                'secondary' => $request->secondary_color ?? '#4a4a4a',
                'accent' => $request->accent_color ?? '#007bff',
            ];

            $design->theme_colors = $themeColors;
            $design->save();

            \Log::info('✓ تم حفظ التصميم بنجاح', [
                'design_id' => $design->id,
                'single_image' => $design->single_image,
                'header_image' => $design->header_image,
                'main_image' => $design->main_image,
                'footer_image' => $design->footer_image
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم حفظ تصميم التقرير بنجاح',
                'design' => $design
            ]);

        } catch (\Exception $e) {
            \Log::error('خطأ أثناء حفظ التصميم', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حفظ التصميم: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * جلب تصميم التقرير للجمعية
     */
    public function getReportDesign($sponsorId)
    {
        try {
            \Log::info('🔍 طلب جلب تصميم التقرير', ['sponsor_id' => $sponsorId]);

            $design = SponsorReportDesign::where('sponsor_id', $sponsorId)->first();

            if (!$design) {
                \Log::warning('⚠️ لا يوجد تصميم للكفيل', ['sponsor_id' => $sponsorId]);

                return response()->json([
                    'success' => true,
                    'design' => null,
                    'message' => 'لا يوجد تصميم محفوظ'
                ]);
            }

            \Log::info('✓ تم جلب التصميم', [
                'design_id' => $design->id,
                'background_type' => $design->background_type,
                'single_image' => $design->single_image,
                'header_image' => $design->header_image,
                'main_image' => $design->main_image,
                'footer_image' => $design->footer_image,
            ]);

            return response()->json([
                'success' => true,
                'design' => $design
            ]);

        } catch (\Exception $e) {
            \Log::error('❌ خطأ في جلب التصميم', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب التصميم: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * حذف تصميم التقرير
     */
    public function deleteReportDesign($sponsorId)
    {
        try {
            $design = SponsorReportDesign::where('sponsor_id', $sponsorId)->first();

            if (!$design) {
                return response()->json([
                    'success' => false,
                    'message' => 'التصميم غير موجود'
                ], 404);
            }

            // حذف الصور من التخزين
            if ($design->single_image) {
                Storage::disk('public')->delete($design->single_image);
            }
            if ($design->header_image) {
                Storage::disk('public')->delete($design->header_image);
            }
            if ($design->main_image) {
                Storage::disk('public')->delete($design->main_image);
            }
            if ($design->footer_image) {
                Storage::disk('public')->delete($design->footer_image);
            }

            $design->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف التصميم بنجاح'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حذف التصميم: ' . $e->getMessage()
            ], 500);
        }
    }
}
