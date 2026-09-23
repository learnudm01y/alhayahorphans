/**
 * categories-config.js — تعريفات التصنيفات الـ22 لشاشة موحدة
 */
(function () {
    'use strict';

    window.CATEGORIES = [
        { key: 'academic_degrees', name: 'الدرجة العلمية', icon: 'school', fields: [{ name: 'name', label: 'اسم الدرجة', required: true }] },
        { key: 'category_of_relations', name: 'صلة القرابة', icon: 'group', fields: [{ name: 'name', label: 'اسم صلة القرابة', required: true }] },
        { key: 'aid_statuses', name: 'حالة المساعدة', icon: 'volunteer_activism', fields: [{ name: 'name', label: 'اسم الحالة', required: true }] },
        { key: 'bank_names', name: 'أسماء البنوك', icon: 'account_balance', fields: [{ name: 'name', label: 'اسم البنك', required: true }] },
        { key: 'city', name: 'أسماء المدن', icon: 'location_city', fields: [{ name: 'name', label: 'اسم المدينة', required: true }, { name: 'province_id', label: 'المحافظة', type: 'select', options: [] }] },
        { key: 'currency_types', name: 'العملات', icon: 'currency_exchange', fields: [{ name: 'name', label: 'اسم العملة', required: true }, { name: 'code', label: 'الرمز', required: true }] },
        { key: 'death_reasons', name: 'أسباب الوفاة', icon: 'heart_broken', fields: [{ name: 'name', label: 'سبب الوفاة', required: true }] },
        { key: 'displacement_statuses', name: 'حالة النزوح', icon: 'directions_walk', fields: [{ name: 'name', label: 'اسم الحالة', required: true }] },
        { key: 'document_types', name: 'أنواع الوثائق', icon: 'description', fields: [{ name: 'name', label: 'نوع الوثيقة', required: true }] },
        { key: 'employment', name: 'الحالة الوظيفية', icon: 'work', fields: [{ name: 'name', label: 'الحالة الوظيفية', required: true }] },
        { key: 'general_category', name: 'الأقسام الرئيسية', icon: 'category', fields: [{ name: 'name', label: 'اسم القسم', required: true }, { name: 'is_active', label: 'مفعل', type: 'checkbox' }] },
        { key: 'health_statuses', name: 'الحالة الصحية', icon: 'health_and_safety', fields: [{ name: 'name', label: 'الحالة الصحية', required: true }] },
        { key: 'orphan_needs', name: 'احتياجات المكفول', icon: 'loyalty', fields: [{ name: 'name', label: 'الحاجة', required: true }] },
        { key: 'creativity_aspects', name: 'جوانب الإبداع', icon: 'lightbulb', fields: [{ name: 'name', label: 'جانب الإبداع', required: true }] },
        { key: 'housing_status', name: 'حالة المنزل', icon: 'home', fields: [{ name: 'name', label: 'حالة المنزل', required: true }] },
        { key: 'marital_status', name: 'الحالة الاجتماعية', icon: 'favorite', fields: [{ name: 'name', label: 'الحالة الاجتماعية', required: true }] },
        { key: 'provinces', name: 'المحافظات', icon: 'map', fields: [{ name: 'name', label: 'اسم المحافظة', required: true }] },
        { key: 'request_status', name: 'حالة الطلب', icon: 'pending_actions', fields: [{ name: 'name', label: 'حالة الطلب', required: true }] },
        { key: 'sponsorship_statuses', name: 'حالة الكفالة', icon: 'handshake', fields: [{ name: 'name', label: 'حالة الكفالة', required: true }] },
        { key: 'type_of_accommodation', name: 'نوع السكن', icon: 'apartment', fields: [{ name: 'name', label: 'نوع السكن', required: true }] },
        { key: 'type_of_guarantee', name: 'نوع الكفالة', icon: 'card_giftcard', fields: [{ name: 'name', label: 'نوع الكفالة', required: true }] },
        { key: 'data_request_status', name: 'حالة طلب المستخدم', icon: 'assignment', fields: [{ name: 'name', label: 'حالة الطلب', required: true }] }
    ];

    window.getCatByKey = function (key) {
        return CATEGORIES.find(function (c) { return c.key === key; });
    };
})();
