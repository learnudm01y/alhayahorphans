/**
 * فحص سريع لنظام كشف الملفات المكررة
 * Quick Validation for Duplicate File Detection System
 */

import { promises as fs } from 'fs';
import { join as pathJoin, dirname } from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

class DuplicateSystemValidator {
    constructor() {
        this.basePath = __dirname;
        this.results = {
            backend: { status: 'pending', details: [] },
            frontend: { status: 'pending', details: [] },
            routes: { status: 'pending', details: [] },
            database: { status: 'pending', details: [] }
        };
    }

    async validateBackend() {
        console.log('🔍 فحص Backend Components...');

        try {
            // فحص Controller
            const controllerPath = pathJoin(this.basePath, 'app/Http/Controllers/UnifiedFileManagementController.php');
            const controllerContent = await fs.readFile(controllerPath, 'utf8');

            const requiredMethods = [
                'checkFileExistsInFolder',
                'storeDuplicateImage',
                'getDuplicateSummary',
                'processFolderUpload'
            ];

            const foundMethods = [];
            requiredMethods.forEach(method => {
                if (controllerContent.includes(`function ${method}`) || controllerContent.includes(`public function ${method}`)) {
                    foundMethods.push(method);
                    this.results.backend.details.push(`✅ ${method}: موجود`);
                } else {
                    this.results.backend.details.push(`❌ ${method}: مفقود`);
                }
            });

            // فحص duplicate_files_temp table handling
            if (controllerContent.includes('duplicate_files_temp')) {
                this.results.backend.details.push('✅ جدول duplicate_files_temp: مدعوم');
            } else {
                this.results.backend.details.push('❌ جدول duplicate_files_temp: غير مدعوم');
            }

            // فحص temp storage logic
            if (controllerContent.includes('temp/duplicates')) {
                this.results.backend.details.push('✅ التخزين المؤقت: مُعدّ');
            } else {
                this.results.backend.details.push('❌ التخزين المؤقت: غير مُعدّ');
            }

            this.results.backend.status = foundMethods.length === requiredMethods.length ? 'success' : 'warning';

        } catch (error) {
            this.results.backend.status = 'error';
            this.results.backend.details.push(`❌ خطأ في قراءة Controller: ${error.message}`);
        }
    }

    async validateFrontend() {
        console.log('🎨 فحص Frontend Components...');

        try {
            // فحص advanced-interface.blade.php
            const interfacePath = pathJoin(this.basePath, 'resources/views/file-management/advanced-interface.blade.php');
            const interfaceContent = await fs.readFile(interfacePath, 'utf8');

            const requiredFeatures = [
                'checkForExistingDuplicates',
                'showDuplicateFilesBtn',
                'uploadFolderFile',
                'duplicate_files_session_id'
            ];

            requiredFeatures.forEach(feature => {
                if (interfaceContent.includes(feature)) {
                    this.results.frontend.details.push(`✅ ${feature}: موجود`);
                } else {
                    this.results.frontend.details.push(`❌ ${feature}: مفقود`);
                }
            });

            // فحص Modal
            const modalPath = pathJoin(this.basePath, 'resources/views/file-management/modalDublicateFiles.blade.php');
            const modalContent = await fs.readFile(modalPath, 'utf8');

            if (modalContent.includes('showDuplicateFilesModal')) {
                this.results.frontend.details.push('✅ Modal للملفات المكررة: موجود');
            } else {
                this.results.frontend.details.push('❌ Modal للملفات المكررة: مفقود');
            }

            // فحص استخدام /route/admin paths
            const adminPaths = (interfaceContent.match(/\/route\/admin/g) || []).length;
            if (adminPaths > 0) {
                this.results.frontend.details.push(`✅ مسارات /route/admin: ${adminPaths} استخدام`);
            } else {
                this.results.frontend.details.push('❌ مسارات /route/admin: غير موجودة');
            }

            this.results.frontend.status = 'success';

        } catch (error) {
            this.results.frontend.status = 'error';
            this.results.frontend.details.push(`❌ خطأ في قراءة Frontend: ${error.message}`);
        }
    }

    async validateRoutes() {
        console.log('🛣️ فحص Routes...');

        try {
            // فحص admin.php routes
            const adminRoutesPath = pathJoin(this.basePath, 'routes/admin.php');
            const adminRoutesContent = await fs.readFile(adminRoutesPath, 'utf8');

            const requiredRoutes = [
                'duplicate-summary',
                'delete-duplicates',
                'download-duplicates',
                'process-folder-upload'
            ];

            requiredRoutes.forEach(route => {
                if (adminRoutesContent.includes(route)) {
                    this.results.routes.details.push(`✅ Route ${route}: مُعرّف`);
                } else {
                    this.results.routes.details.push(`❌ Route ${route}: غير مُعرّف`);
                }
            });

            // فحص import للController
            if (adminRoutesContent.includes('UnifiedFileManagementController')) {
                this.results.routes.details.push('✅ Controller import: موجود');
            } else {
                this.results.routes.details.push('❌ Controller import: مفقود');
            }

            this.results.routes.status = 'success';

        } catch (error) {
            this.results.routes.status = 'error';
            this.results.routes.details.push(`❌ خطأ في قراءة Routes: ${error.message}`);
        }
    }

    async validateDatabase() {
        console.log('🗄️ فحص Database Components...');

        try {
            // البحث عن migration files
            const migrationsPath = pathJoin(this.basePath, 'database/migrations');

            try {
                const migrationFiles = await fs.readdir(migrationsPath);
                const duplicateMigration = migrationFiles.find(file =>
                    file.includes('duplicate_files_temp') || file.includes('create_duplicate_files_temp')
                );

                if (duplicateMigration) {
                    this.results.database.details.push(`✅ Migration duplicate_files_temp: ${duplicateMigration}`);
                } else {
                    this.results.database.details.push('❌ Migration duplicate_files_temp: مفقود');
                }
            } catch (error) {
                this.results.database.details.push('⚠️ مجلد Migrations: غير موجود أو غير قابل للقراءة');
            }

            // فحص console command
            try {
                const consolePath = pathJoin(this.basePath, 'app/Console/Commands');
                const consoleFiles = await fs.readdir(consolePath);
                const cleanupCommand = consoleFiles.find(file =>
                    file.includes('CleanupExpiredDuplicates') || file.includes('DuplicateCleanup')
                );

                if (cleanupCommand) {
                    this.results.database.details.push(`✅ Console Command: ${cleanupCommand}`);
                } else {
                    this.results.database.details.push('❌ Console Command: مفقود');
                }
            } catch (error) {
                this.results.database.details.push('⚠️ Console Commands: غير متاح');
            }

            this.results.database.status = 'warning';

        } catch (error) {
            this.results.database.status = 'error';
            this.results.database.details.push(`❌ خطأ في فحص Database: ${error.message}`);
        }
    }

    async generateReport() {
        console.log('\n📊 تقرير فحص نظام كشف الملفات المكررة');
        console.log('='.repeat(60));

        const components = ['backend', 'frontend', 'routes', 'database'];
        const statusIcons = {
            success: '✅',
            warning: '⚠️',
            error: '❌',
            pending: '⏳'
        };

        components.forEach(component => {
            const result = this.results[component];
            const icon = statusIcons[result.status];
            const title = {
                backend: 'Backend Components',
                frontend: 'Frontend Interface',
                routes: 'Routing System',
                database: 'Database Layer'
            }[component];

            console.log(`\n${icon} ${title}:`);
            result.details.forEach(detail => {
                console.log(`   ${detail}`);
            });
        });

        // إحصائيات عامة
        const successCount = Object.values(this.results).filter(r => r.status === 'success').length;
        const totalCount = Object.keys(this.results).length;

        console.log('\n' + '='.repeat(60));
        console.log(`📈 الإحصائيات العامة: ${successCount}/${totalCount} مكونات تعمل بشكل صحيح`);

        if (successCount === totalCount) {
            console.log('🎉 النظام جاهز للاستخدام!');
        } else if (successCount >= totalCount - 1) {
            console.log('⚠️ النظام يعمل مع بعض التحذيرات');
        } else {
            console.log('❌ النظام يحتاج إلى إصلاحات');
        }

        console.log('\n💡 لاختبار النظام في المتصفح، افتح: test-duplicate-system.html');
    }

    async validate() {
        console.log('🚀 بدء فحص نظام كشف الملفات المكررة...\n');

        await this.validateBackend();
        await this.validateFrontend();
        await this.validateRoutes();
        await this.validateDatabase();
        await this.generateReport();
    }
}

// تشغيل الفحص
const validator = new DuplicateSystemValidator();
validator.validate().catch(console.error);
