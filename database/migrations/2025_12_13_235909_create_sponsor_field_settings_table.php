<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sponsor_field_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sponsor_id')->comment('معرف الجمعية');

            // معلومات المكفول الأساسية
            $table->boolean('field_sponsor_name')->default(1)->comment('إسم المكفول');
            $table->boolean('field_phone')->default(1)->comment('رقم الهاتف');
            $table->boolean('field_mother_name')->default(1)->comment('إسم الأم ثلاثي');
            $table->boolean('field_father_death_date')->default(1)->comment('تاريخ وفاة الأب');

            // معلومات السكن
            $table->boolean('field_housing_status')->default(1)->comment('حالة السكن');
            $table->boolean('field_housing_type')->default(1)->comment('نوع السكن');
            $table->boolean('field_housing_address')->default(1)->comment('عنوان السكن');
            $table->boolean('field_housing_address_detail')->default(1)->comment('عنوان السكن التفصيلي');
            $table->boolean('field_house_demolition')->default(1)->comment('هل المنزل هدم كلي او جزئي');
            $table->boolean('field_house_repair_need')->default(1)->comment('هل المنزل بحاجة الى ترميم او بناء');

            // المعلومات الدراسية
            $table->boolean('field_school_name')->default(1)->comment('اسم المدرسة');
            $table->boolean('field_school_address')->default(1)->comment('عنوان المدرسة');
            $table->boolean('field_grade')->default(1)->comment('الصف');
            $table->boolean('field_stage')->default(1)->comment('المرحلة');
            $table->boolean('field_education_stage')->default(1)->comment('المرحلة الدراسية');
            $table->boolean('field_student_level')->default(1)->comment('مستوى الطالب');
            $table->boolean('field_weakness_reason')->default(1)->comment('سبب الضعف');
            $table->boolean('field_tent_school')->default(1)->comment('هل يكمل تعليمه في خيمة مدرسية');
            $table->boolean('field_orphan_ambition')->default(1)->comment('طموح اليتيم');

            // الحالة النفسية والسلوكية
            $table->boolean('field_psychological_state')->default(1)->comment('الحالة النفسية');
            $table->boolean('field_behavioral_state')->default(1)->comment('الحالة السلوكية');
            $table->boolean('field_orphan_behavior')->default(1)->comment('سلوك اليتيم');

            // الجوانب الدينية
            $table->boolean('field_religious_commitment')->default(1)->comment('الإلتزام الديني');
            $table->boolean('field_commitment')->default(1)->comment('التزامه');
            $table->boolean('field_quran_memorization')->default(1)->comment('مقدار حفظه للقرآن');
            $table->boolean('field_prayer_commitment')->default(1)->comment('التزام اليتيم بالصلاة');

            // الحالة الصحية
            $table->boolean('field_health_status')->default(1)->comment('الحالة الصحية للمكفول');
            $table->boolean('field_orphan_health')->default(1)->comment('الوضع الصحي لليتيم');
            $table->boolean('field_disease_type')->default(1)->comment('نوع المرض او الإصابة ان وجدت');
            $table->boolean('field_treatment_cost')->default(1)->comment('تكاليف العلاج');
            $table->boolean('field_receives_treatment')->default(1)->comment('هل يتلقى العلاج');
            $table->boolean('field_family_sick_member')->default(1)->comment('هل يوجد أحد من افراد الأسرة مريض');
            $table->boolean('field_family_disease_cost')->default(1)->comment('نوع المرض او الإصابة مع التكاليف');

            // احتياجات وإبداع
            $table->boolean('field_orphan_needs')->default(1)->comment('احتياجات المكفول');
            $table->boolean('field_creativity_aspects')->default(1)->comment('جوانب الإبداع');

            // معلومات المعيل
            $table->boolean('field_current_guardian')->default(1)->comment('معيله الحالي');
            $table->boolean('field_relationship')->default(1)->comment('صلة القرابة');
            $table->boolean('field_guardian_health')->default(1)->comment('حالته الصحية');
            $table->boolean('field_guardian_job')->default(1)->comment('وظيفته');
            $table->boolean('field_dependents_female')->default(1)->comment('عدد من يعيلهم من الإناث');
            $table->boolean('field_dependents_male')->default(1)->comment('عدد من يعيلهم من الذكور');
            $table->boolean('field_family_members_count')->default(1)->comment('عدد افراد الأسرة مع اليتيم');

            // معلومات اخوة المكفول
            $table->boolean('field_siblings_names')->default(1)->comment('اسماء اخوة المكفول');
            $table->boolean('field_sibling_birthdate')->default(1)->comment('تاريخ الميلاد للأخ/الأخت');
            $table->boolean('field_sibling_grade')->default(1)->comment('الصف للأخ/الأخت');
            $table->boolean('field_sibling_notes')->default(1)->comment('ملاحظات الحالة الصحية والإجتماعية');

            // تأثير الكفالة والمتابعة
            $table->boolean('field_sponsorship_impact')->default(1)->comment('تأثير الكفالة');
            $table->boolean('field_important_events')->default(1)->comment('اهم الأحداث التي مرت مع اسرة اليتيم');
            $table->boolean('field_supervisor_notes')->default(1)->comment('ملاحظات المشرف الإجتماعي وتوصياته');
            $table->boolean('field_data_update_date')->default(1)->comment('تاريخ تحديث البيانات');
            $table->boolean('field_supervisor_name')->default(1)->comment('إسم المشرف');

            // معلومات المؤسسة والمرفقات
            $table->boolean('field_institution_name')->default(1)->comment('إسم المؤسسة');
            $table->boolean('field_attachments')->default(1)->comment('المرفقات');

            $table->timestamps();

            // إضافة Foreign Key و Index
            $table->foreign('sponsor_id')->references('id')->on('sponsors')->onDelete('cascade');
            $table->unique('sponsor_id', 'unique_sponsor_field_settings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sponsor_field_settings');
    }
};
