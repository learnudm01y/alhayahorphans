/**
 * ═══════════════════════════════════════════════════════════════
 * إرشادات استخدام IndexedDBBridge لحل مشكلة تسمية المجلدات
 * ═══════════════════════════════════════════════════════════════
 *
 * المشكلة:
 * - الملفات تُحفظ في: Pictures/sponsorships_alhayahorphans/General/unknown/
 * - بدلاً من: Pictures/sponsorships_alhayahorphans/[اسم_الجمعية]/[اسم_المكفول]/
 *
 * السبب:
 * - Java لا يمكنه قراءة IndexedDB مباشرة
 * - يجب أن يمرر JavaScript البيانات قبل رفع الملف
 *
 * الحل:
 * استدعاء IndexedDBBridge.updateCurrentContext() قبل رفع أي ملف
 */

// ════════════════════════════════════════════════════════════════
// الخطوة 1: عند فتح صفحة الكفالة أو تحديد مكفول
// ════════════════════════════════════════════════════════════════

async function onSponsorshipPageOpened(sponsorshipId) {
  try {
    // جلب بيانات الكفالة من IndexedDB
    const sponsorship = await db.sponsorships.get(sponsorshipId);

    if (sponsorship) {
      // جلب اسم الجمعية
      const association = await db.associations.get(sponsorship.associationId);

      // تحديث السياق في Android
      await IndexedDBBridge.updateCurrentContext({
        associationName: association?.name || 'General',
        personName: sponsorship.person_name || 'unknown'
      });

      console.log('✅ Context updated:', {
        association: association?.name,
        person: sponsorship.person_name
      });
    }
  } catch (error) {
    console.error('❌ Failed to update context:', error);
  }
}

// ════════════════════════════════════════════════════════════════
// الخطوة 2: عند رفع ملف - تمرير البيانات مباشرة (اختياري)
// ════════════════════════════════════════════════════════════════

async function uploadPhoto(file, sponsorshipId) {
  try {
    // طريقة 1: تمرير البيانات مباشرة (الأفضل)
    const sponsorship = await db.sponsorships.get(sponsorshipId);
    const association = await db.associations.get(sponsorship.associationId);

    const result = await UploadService.addFileToQueue({
      filePath: fileDataUri,
      fileName: file.name,
      photoId: sponsorshipId,
      apiUrl: API_URL,
      // ✅ تمرير البيانات مباشرة
      associationName: association.name,
      personName: sponsorship.person_name
    });

    // طريقة 2: الاعتماد على updateCurrentContext السابق
    // إذا استدعيت updateCurrentContext() عند فتح الصفحة،
    // Android سيستخدم البيانات المخزنة تلقائياً

    console.log('✅ File queued for upload');

  } catch (error) {
    console.error('❌ Upload failed:', error);
  }
}

// ════════════════════════════════════════════════════════════════
// مثال كامل: صفحة تفاصيل الكفالة
// ════════════════════════════════════════════════════════════════

import { IndexedDBBridge } from '@/plugins/IndexedDBBridge';
import { UploadService } from '@/plugins/UploadService';

export default {
  data() {
    return {
      sponsorshipId: null,
      sponsorshipData: null,
      associationData: null
    }
  },

  async mounted() {
    // جلب البيانات من IndexedDB
    await this.loadSponsorshipData();

    // تحديث السياق في Android
    await this.updateAndroidContext();
  },

  methods: {
    async loadSponsorshipData() {
      this.sponsorshipData = await db.sponsorships.get(this.sponsorshipId);
      this.associationData = await db.associations.get(
        this.sponsorshipData.associationId
      );
    },

    async updateAndroidContext() {
      try {
        await IndexedDBBridge.updateCurrentContext({
          associationName: this.associationData?.name || 'General',
          personName: this.sponsorshipData?.person_name || 'unknown'
        });
        console.log('✅ Android context updated');
      } catch (error) {
        console.error('❌ Failed to update Android context:', error);
      }
    },

    async handleFileUpload(file) {
      try {
        // الآن عند رفع الملف، Android سيستخدم البيانات الصحيحة
        const result = await UploadService.addFileToQueue({
          filePath: await this.fileToDataUri(file),
          fileName: file.name,
          photoId: this.sponsorshipId,
          apiUrl: '/api/upload',
          // يمكن تمرير البيانات مباشرة أيضاً (أكثر أماناً)
          associationName: this.associationData.name,
          personName: this.sponsorshipData.person_name
        });

        console.log('✅ File uploaded successfully');
      } catch (error) {
        console.error('❌ Upload failed:', error);
      }
    }
  }
}

// ════════════════════════════════════════════════════════════════
// هيكلية قاعدة البيانات المتوقعة
// ════════════════════════════════════════════════════════════════

/**
 * IndexedDB Schema:
 *
 * 1. associations (جدول الجمعيات)
 *    - id: number
 *    - name: string
 *    - ...
 *
 * 2. sponsorships (جدول الكفالات)
 *    - id: number
 *    - person_name: string
 *    - associationId: number (foreign key → associations.id)
 *    - ...
 *
 * 3. files (جدول الملفات)
 *    - id: number
 *    - sponsorshipId: number (foreign key → sponsorships.id)
 *    - fileName: string
 *    - ...
 */

// ════════════════════════════════════════════════════════════════
// الخطوات التالية للمطور
// ════════════════════════════════════════════════════════════════

/**
 * 1. ابحث في الكود عن جميع استدعاءات UploadService.addFileToQueue()
 *
 * 2. قبل كل استدعاء، تأكد من:
 *    أ) تمرير associationName و personName مباشرة
 *    أو
 *    ب) استدعاء IndexedDBBridge.updateCurrentContext() في mounted() أو created()
 *
 * 3. تأكد من أسماء الحقول في IndexedDB:
 *    - هل اسم الجمعية في: association.name أم association.association_name؟
 *    - هل اسم المكفول في: sponsorship.person_name أم sponsorship.name؟
 *
 * 4. اختبر بـ Logcat وابحث عن:
 *    "✅ Context updated from JavaScript"
 *    "✅ Got association from bridge"
 *    "✅ Got person from bridge"
 *
 * 5. إذا رأيت:
 *    "⚠️⚠️⚠️ CRITICAL: Still using default names!"
 *    معناه: JavaScript لم يمرر البيانات بعد
 */
