/**
 * admin-nav.js — شريط تنقّل موحّد لشاشات لوحة التحكم v4
 * يُحقن侧栏 + hamburger بدون حذف أي محتوى قائم
 * الاستخدام: <script src="../js/admin-nav.js"></script> ثم AdminNav.mount('records')
 */
(function () {
    'use strict';

    var SECTIONS = [
        {
            title: 'الرئيسية', items: [
                { id: 'dashboard', label: 'لوحة القيادة', icon: 'dashboard', href: 'dashboard.html' },
                { id: 'search', label: 'البحث الشامل', icon: 'search', href: 'search-records.html' },
                { id: 'global-search', label: 'البحث الموحّد', icon: 'travel_explore', href: '../screens/global-search.html' }
            ]
        },
        {
            title: 'إدارة التسجيلات', items: [
                { id: 'records', label: 'السجلات المدخلة', icon: 'list_alt', href: 'records.html' },
                { id: 'record-new', label: 'إدخال سجل جديد', icon: 'post_add', href: 'record-form.html?new=1' },
                { id: 'user-requests', label: 'طلبات المستخدمين', icon: 'inbox', href: 'user-requests.html' }
            ]
        },
        {
            title: 'التصنيفات', items: [
                { id: 'categories', label: 'إدارة التصنيفات (22)', icon: 'category', href: 'categories.html' }
            ]
        },
        {
            title: 'الكفالات والجمعيات', items: [
                { id: 'sponsorships', label: 'كل الكفالات', icon: 'handshake', href: 'sponsorships.html' },
                { id: 'sponsored', label: 'الأشخاص المكفولون', icon: 'volunteer_activism', href: 'sponsorships.html?tab=sponsored' },
                { id: 'unsponsored', label: 'غير المكفولين', icon: 'person_off', href: 'sponsorships.html?tab=unsponsored' },
                { id: 'sponsors', label: 'إدارة الجمعيات', icon: 'corporate_fare', href: 'sponsors.html' }
            ]
        },
        {
            title: 'الملفات والمرفقات', items: [
                { id: 'files', label: 'فهرس المرفقات', icon: 'folder', href: 'files.html' },
                { id: 'folders', label: 'إدارة المجلدات', icon: 'drive_folder_upload', href: 'folders.html' },
                { id: 'duplicates', label: 'الملفات المكررة', icon: 'copy_all', href: 'duplicates.html' },
                { id: 'attachment-audit', label: 'تدقيق المرفقات', icon: 'fact_check', href: 'attachment-audit.html' },
                { id: 'upload', label: 'رفع ملفات (كما هي)', icon: 'cloud_upload', href: '../upload.html', keep: true }
            ]
        },
        {
            title: 'السجل المدني', items: [
                { id: 'civil-registry', label: 'عرض السجل المدني', icon: 'badge', href: 'civil-registry.html' },
                { id: 'civil-import', label: 'استيراد Excel', icon: 'file_upload', href: 'civil-import.html' }
            ]
        },
        {
            title: 'الصلاحيات', items: [
                { id: 'permissions', label: 'Manifest الصلاحيات', icon: 'verified_user', href: 'permissions.html' },
                { id: 'users', label: 'المستخدمون', icon: 'people', href: 'users.html' },
                { id: 'roles', label: 'الأدوار والصلاحيات', icon: 'admin_panel_settings', href: 'roles.html' }
            ]
        },
        {
            title: 'التقارير', items: [
                { id: 'reports', label: 'التقارير', icon: 'assessment', href: 'reports.html' }
            ]
        },
        {
            title: 'نظام المزامنة', items: [
                { id: 'conflicts', label: 'مراجعة التعارضات', icon: 'merge_type', href: '../screens/conflict-review.html' },
                { id: 'sync-health', label: 'صحة المزامنة', icon: 'monitor_heart', href: '../screens/sync-health.html' },
                { id: 'audit', label: 'سجل التدقيق', icon: 'history', href: '../screens/audit.html' },
                { id: 'devices', label: 'إدارة الأجهزة', icon: 'devices', href: '../screens/devices.html' },
                { id: 'notifications', label: 'مركز الإشعارات', icon: 'notifications', href: '../screens/notifications.html' }
            ]
        },
        {
            title: 'الحساب', items: [
                { id: 'profile', label: 'الملف الشخصي', icon: 'account_circle', href: 'profile.html' },
                { id: 'settings', label: 'الإعدادات', icon: 'settings', href: 'settings.html' },
                { id: 'photography', label: 'شاشة التصوير (كما هي)', icon: 'photo_camera', href: '../photography.html', keep: true },
                { id: 'barcode', label: 'الباركود (كما هي)', icon: 'qr_code', href: '../barcode.html', keep: true }
            ]
        }
    ];

    function build() {
        if (document.getElementById('v4-sidebar')) return;

        var overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        overlay.id = 'sidebar-overlay';

        var side = document.createElement('aside');
        side.className = 'sidebar';
        side.id = 'v4-sidebar';

        var html = '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">' +
            '<strong style="font-size:15px;color:var(--brand-700)">القائمة الكاملة</strong>' +
            '<button class="modal-close" id="sidebar-close">×</button></div>';

        SECTIONS.forEach(function (sec) {
            html += '<div class="side-section">' + sec.title + '</div>';
            sec.items.forEach(function (it) {
                html += '<a href="' + it.href + '" data-nav="' + it.id + '">' +
                    '<span class="material-icons" style="font-size:18px">' + it.icon + '</span>' +
                    '<span>' + it.label + '</span></a>';
            });
        });

        side.innerHTML = html;
        document.body.appendChild(overlay);
        document.body.appendChild(side);

        // Hamburger in header
        var header = document.querySelector('.header');
        if (header && !document.getElementById('menu-btn')) {
            var h1 = header.querySelector('h1');
            var btn = document.createElement('button');
            btn.className = 'menu-btn';
            btn.id = 'menu-btn';
            btn.innerHTML = '☰';
            btn.setAttribute('aria-label', 'القائمة');
            if (h1) h1.parentNode.insertBefore(btn, h1);
            else header.insertBefore(btn, header.firstChild);
            btn.addEventListener('click', open);
        }

        document.getElementById('sidebar-close').addEventListener('click', close);
        overlay.addEventListener('click', close);
    }

    function open() {
        build();
        document.getElementById('v4-sidebar').classList.add('open');
        document.getElementById('sidebar-overlay').classList.add('open');
    }

    function close() {
        var s = document.getElementById('v4-sidebar');
        var o = document.getElementById('sidebar-overlay');
        if (s) s.classList.remove('open');
        if (o) o.classList.remove('open');
    }

    function markActive(activeId) {
        build();
        document.querySelectorAll('#v4-sidebar a[data-nav]').forEach(function (a) {
            if (a.dataset.nav === activeId) a.classList.add('active');
            else a.classList.remove('active');
        });
    }

    window.AdminNav = {
        sections: SECTIONS,
        mount: function (activeId) {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function () { markActive(activeId); });
            } else {
                markActive(activeId);
            }
        },
        open: open,
        close: close
    };
})();
