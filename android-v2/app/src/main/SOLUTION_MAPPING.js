/**
 * ═══════════════════════════════════════════════════════════════
 * CRITICAL: حل مشكلة "unknown" في أسماء المجلدات
 * ═══════════════════════════════════════════════════════════════
 *
 * المشكلة: الملفات تُحفظ في General/unknown بدلاً من اسم الجمعية والشخص الحقيقي
 *
 * السبب: Java لا يمكنه قراءة IndexedDB
 *
 * الحل: استدعاء IndexedDBBridge.saveSponsorshipMappings() مرة واحدة
 *        عند بدء التطبيق أو عند تحميل البيانات من IndexedDB
 */

import { Plugins } from '@capacitor/core';
const { IndexedDBBridge } = Plugins;

/**
 * ═══════════════════════════════════════════════════════════════
 * الخطوة 1: استدعاء هذه الدالة عند بدء التطبيق أو تسجيل الدخول
 * ═══════════════════════════════════════════════════════════════
 */
export async function syncAllSponsorshipsToAndroid() {
  try {
    console.log('🔄 Starting sponsorship mapping sync to Android...');

    // جلب جميع الكفالات من IndexedDB
    const sponsorships = await db.sponsorships.toArray();

    // جلب جميع الجمعيات
    const associations = await db.associations.toArray();

    // إنشاء map للجمعيات (للبحث السريع)
    const associationMap = {};
    associations.forEach(assoc => {
      associationMap[assoc.id] = assoc.name || 'General';
    });

    // تحويل البيانات للصيغة المطلوبة
    const mappings = sponsorships.map(sp => ({
      sponsorshipId: sp.id,
      associationName: associationMap[sp.association_id] || 'General',
      personName: sp.person_name || sp.name || 'unknown'
    }));

    // حفظ في Android SQLite
    const result = await IndexedDBBridge.saveSponsorshipMappings({ mappings });

    console.log(`✅ Synced ${result.count} sponsorships to Android`);
    console.log('✅ Now all file uploads will use correct folder names!');

    return result;

  } catch (error) {
    console.error('❌ Failed to sync sponsorships:', error);
    throw error;
  }
}

/**
 * ═══════════════════════════════════════════════════════════════
 * الخطوة 2: حفظ mapping لكفالة واحدة (عند إضافة كفالة جديدة)
 * ═══════════════════════════════════════════════════════════════
 */
export async function saveSingleSponsorshipMapping(sponsorshipId, associationName, personName) {
  try {
    await IndexedDBBridge.saveSingleMapping({
      sponsorshipId,
      associationName,
      personName
    });

    console.log(`✅ Saved mapping for sponsorshipId=${sponsorshipId}`);
  } catch (error) {
    console.error(`❌ Failed to save mapping for ${sponsorshipId}:`, error);
  }
}

/**
 * ═══════════════════════════════════════════════════════════════
 * التحقق من عدد mappings المحفوظة
 * ═══════════════════════════════════════════════════════════════
 */
export async function checkMappingCount() {
  try {
    const result = await IndexedDBBridge.getMappingCount();
    console.log(`📊 Android has ${result.count} sponsorship mappings`);
    return result.count;
  } catch (error) {
    console.error('❌ Failed to get mapping count:', error);
    return 0;
  }
}

/**
 * ═══════════════════════════════════════════════════════════════
 * مسح جميع mappings (للصيانة)
 * ═══════════════════════════════════════════════════════════════
 */
export async function clearAllMappings() {
  try {
    await IndexedDBBridge.clearMappings();
    console.log('🗑️ All mappings cleared from Android');
  } catch (error) {
    console.error('❌ Failed to clear mappings:', error);
  }
}

/**
 * ═══════════════════════════════════════════════════════════════
 * مثال: استخدام في main.js أو App.vue
 * ═══════════════════════════════════════════════════════════════
 */

// في main.js أو App.vue created()
export async function initializeApp() {
  // 1. فتح IndexedDB
  await openDatabase();

  // 2. مزامنة جميع الكفالات إلى Android
  await syncAllSponsorshipsToAndroid();

  // 3. التحقق من النجاح
  const count = await checkMappingCount();
  console.log(`✅ App ready with ${count} sponsorships mapped`);
}

/**
 * ═══════════════════════════════════════════════════════════════
 * مثال: عند إضافة كفالة جديدة
 * ═══════════════════════════════════════════════════════════════
 */
export async function addNewSponsorship(data) {
  // حفظ في IndexedDB
  const id = await db.sponsorships.add(data);

  // حفظ mapping في Android فوراً
  await saveSingleSponsorshipMapping(
    id,
    data.association_name,
    data.person_name
  );

  return id;
}

/**
 * ═══════════════════════════════════════════════════════════════
 * TESTING: للتحقق من أن كل شيء يعمل
 * ═══════════════════════════════════════════════════════════════
 */
export async function testMapping() {
  console.log('🧪 Testing sponsorship mapping...');

  // 1. حفظ mapping تجريبي
  await saveSingleSponsorshipMapping(
    999,
    'جمعية الاختبار',
    'شخص تجريبي'
  );

  // 2. التحقق
  const count = await checkMappingCount();
  console.log(`✅ Test complete. Total mappings: ${count}`);

  // 3. الآن جرب رفع ملف بـ photoId=999
  // يجب أن يُحفظ في: Pictures/sponsorships_alhayahorphans/جمعية_الاختبار/شخص_تجريبي/
}

/**
 * ═══════════════════════════════════════════════════════════════
 * ملاحظات مهمة
 * ═══════════════════════════════════════════════════════════════
 *
 * 1. يجب استدعاء syncAllSponsorshipsToAndroid() مرة واحدة على الأقل
 *    - عند بدء التطبيق
 *    - عند تسجيل الدخول
 *    - بعد مزامنة بيانات جديدة من السيرفر
 *
 * 2. هيكلية IndexedDB المتوقعة:
 *    - sponsorships.id → رقم الكفالة
 *    - sponsorships.person_name → اسم المكفول
 *    - sponsorships.association_id → رقم الجمعية
 *    - associations.id → رقم الجمعية
 *    - associations.name → اسم الجمعية
 *
 * 3. إذا كانت أسماء الحقول مختلفة، عدّل في syncAllSponsorshipsToAndroid()
 *
 * 4. للتحقق من نجاح العملية، انظر إلى Logcat:
 *    ✅ "Found in mapping DB for sponsorshipId=..."
 *    ✅ "Using REAL names from mapping database!"
 *    ❌ "No mapping found for sponsorshipId=..."
 */
