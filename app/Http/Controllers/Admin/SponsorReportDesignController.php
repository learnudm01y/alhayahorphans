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

            // معالجة الصور
            if ($request->hasFile('single_image')) {
                // حذف الصورة القديمة
                if ($design->single_image) {
                    Storage::disk('public')->delete($design->single_image);
                }

                $path = $request->file('single_image')->store('report_designs', 'public');
                $design->single_image = $path;
            }

            if ($request->hasFile('header_image')) {
                if ($design->header_image) {
                    Storage::disk('public')->delete($design->header_image);
                }

                $path = $request->file('header_image')->store('report_designs', 'public');
                $design->header_image = $path;
            }

            if ($request->hasFile('main_image')) {
                if ($design->main_image) {
                    Storage::disk('public')->delete($design->main_image);
                }

                $path = $request->file('main_image')->store('report_designs', 'public');
                $design->main_image = $path;
            }

            if ($request->hasFile('footer_image')) {
                if ($design->footer_image) {
                    Storage::disk('public')->delete($design->footer_image);
                }

                $path = $request->file('footer_image')->store('report_designs', 'public');
                $design->footer_image = $path;
            }

            // حفظ الألوان
            $themeColors = [
                'primary' => $request->primary_color ?? '#1a1a1a',
                'secondary' => $request->secondary_color ?? '#4a4a4a',
                'accent' => $request->accent_color ?? '#007bff',
            ];

            $design->theme_colors = $themeColors;
            $design->save();

            return response()->json([
                'success' => true,
                'message' => 'تم حفظ تصميم التقرير بنجاح',
                'design' => $design
            ]);

        } catch (\Exception $e) {
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
            $design = SponsorReportDesign::where('sponsor_id', $sponsorId)->first();

            if (!$design) {
                return response()->json([
                    'success' => true,
                    'design' => null,
                    'message' => 'لا يوجد تصميم محفوظ'
                ]);
            }

            return response()->json([
                'success' => true,
                'design' => $design
            ]);

        } catch (\Exception $e) {
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
