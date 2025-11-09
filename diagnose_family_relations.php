<?php
/**
 * تشخيص مشكلة "لا توجد علاقات عائلية"
 * 
 * يفحص:
 * 1. الاتصال بقاعدة البيانات
 * 2. وجود البيانات في جدول relations
 * 3. استجابة API
 * 4. Cache
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

echo "\n=================================================================\n";
echo "        تشخيص مشكلة: لا توجد علاقات عائلية                       \n";
echo "=================================================================\n\n";

$testId = '407015692'; // الرقم الذي نعرف أن له علاقات

// 1. فحص الاتصال بقاعدة البيانات
echo "1️⃣ فحص الاتصال بقاعدة البيانات civilregistry\n";
echo "-----------------------------------------------------------------\n";
try {
    $connection = DB::connection('civilregistry');
    $connection->getPdo();
    echo "   ✅ الاتصال ناجح\n";
    
    // اسم قاعدة البيانات
    $dbName = $connection->getDatabaseName();
    echo "   📋 اسم القاعدة: {$dbName}\n\n";
} catch (\Exception $e) {
    echo "   ❌ فشل الاتصال: " . $e->getMessage() . "\n\n";
    die("توقف البرنامج بسبب فشل الاتصال\n");
}

// 2. فحص وجود جدول relations
echo "2️⃣ فحص وجود جدول relations\n";
echo "-----------------------------------------------------------------\n";
try {
    $tableExists = DB::connection('civilregistry')
        ->select("SHOW TABLES LIKE 'relations'");
    
    if (count($tableExists) > 0) {
        echo "   ✅ الجدول موجود\n";
        
        // عدد السجلات
        $count = DB::connection('civilregistry')->table('relations')->count();
        echo "   📊 عدد السجلات: " . number_format($count) . "\n\n";
    } else {
        echo "   ❌ الجدول غير موجود!\n\n";
        die("توقف البرنامج: الجدول غير موجود\n");
    }
} catch (\Exception $e) {
    echo "   ❌ خطأ: " . $e->getMessage() . "\n\n";
}

// 3. فحص وجود بيانات للرقم المعروف
echo "3️⃣ فحص وجود بيانات للرقم {$testId}\n";
echo "-----------------------------------------------------------------\n";
try {
    // فحص وجود الشخص
    $person = DB::connection('civilregistry')
        ->table('persons')
        ->where('CI_ID_NUM', $testId)
        ->first();
    
    if ($person) {
        echo "   ✅ الشخص موجود في جدول persons\n";
        $fullName = trim(implode(' ', [
            $person->CI_FIRST_ARB ?? '',
            $person->CI_FATHER_ARB ?? '',
            $person->CI_GRAND_FATHER_ARB ?? '',
            $person->CI_FAMILY_ARB ?? ''
        ]));
        echo "   👤 الاسم: {$fullName}\n";
    } else {
        echo "   ❌ الشخص غير موجود في جدول persons\n";
    }
    
    // فحص العلاقات المباشرة
    $directRelations = DB::connection('civilregistry')
        ->table('relations')
        ->where('CF_ID_NUM', $testId)
        ->count();
    
    echo "   📊 عدد العلاقات المباشرة (CF_ID_NUM): {$directRelations}\n";
    
    if ($directRelations > 0) {
        echo "   ✅ توجد علاقات مباشرة\n";
        
        // عرض أول 3 علاقات
        $relations = DB::connection('civilregistry')
            ->table('relations')
            ->where('CF_ID_NUM', $testId)
            ->limit(3)
            ->get();
        
        echo "   📋 أول 3 علاقات:\n";
        foreach ($relations as $rel) {
            echo "      - CF_ID_RELATIVE: {$rel->CF_ID_RELATIVE}, CF_RELATIVE_CD: {$rel->CF_RELATIVE_CD}\n";
        }
    } else {
        echo "   ⚠️  لا توجد علاقات مباشرة!\n";
    }
    
    echo "\n";
} catch (\Exception $e) {
    echo "   ❌ خطأ: " . $e->getMessage() . "\n\n";
}

// 4. فحص استجابة API
echo "4️⃣ فحص استجابة API\n";
echo "-----------------------------------------------------------------\n";
try {
    $controller = new \App\Http\Controllers\Admin\FamilyRelationController();
    $request = new \Illuminate\Http\Request();
    $request->merge(['id_number' => $testId]);
    
    $response = $controller->searchFamilyRelations($request);
    $data = json_decode($response->getContent(), true);
    
    if ($data['success']) {
        echo "   ✅ API يعمل بنجاح\n";
        echo "   📊 عدد أفراد العائلة: " . count($data['data']['family_members']) . "\n";
        echo "   📊 إجمالي العلاقات: " . $data['data']['statistics']['total_relations'] . "\n";
        
        if (count($data['data']['family_members']) === 0) {
            echo "   ⚠️  API يرجع 0 أفراد (هذه هي المشكلة!)\n";
        }
    } else {
        echo "   ❌ API فشل: " . $data['message'] . "\n";
    }
    
    echo "\n";
} catch (\Exception $e) {
    echo "   ❌ خطأ في API: " . $e->getMessage() . "\n\n";
}

// 5. فحص Cache
echo "5️⃣ فحص Cache\n";
echo "-----------------------------------------------------------------\n";
try {
    $cacheKey = "family_relations_{$testId}";
    
    if (Cache::has($cacheKey)) {
        echo "   ⚠️  Cache موجود للرقم {$testId}\n";
        echo "   💡 الحل: مسح Cache\n";
        
        Cache::forget($cacheKey);
        echo "   ✅ تم مسح Cache للرقم {$testId}\n";
    } else {
        echo "   ✅ لا يوجد Cache (جيد)\n";
    }
    
    echo "\n";
} catch (\Exception $e) {
    echo "   ❌ خطأ: " . $e->getMessage() . "\n\n";
}

// 6. إعادة اختبار بعد مسح Cache
echo "6️⃣ إعادة اختبار بعد مسح Cache\n";
echo "-----------------------------------------------------------------\n";
try {
    $controller = new \App\Http\Controllers\Admin\FamilyRelationController();
    $request = new \Illuminate\Http\Request();
    $request->merge(['id_number' => $testId]);
    
    $response = $controller->searchFamilyRelations($request);
    $data = json_decode($response->getContent(), true);
    
    if ($data['success']) {
        $membersCount = count($data['data']['family_members']);
        echo "   📊 عدد أفراد العائلة: {$membersCount}\n";
        
        if ($membersCount > 0) {
            echo "   ✅ المشكلة محلولة!\n";
        } else {
            echo "   ❌ المشكلة مازالت موجودة\n";
        }
    }
    
    echo "\n";
} catch (\Exception $e) {
    echo "   ❌ خطأ: " . $e->getMessage() . "\n\n";
}

// 7. التشخيص النهائي
echo "=================================================================\n";
echo "📋 التشخيص النهائي\n";
echo "=================================================================\n\n";

try {
    $directCount = DB::connection('civilregistry')
        ->table('relations')
        ->where('CF_ID_NUM', $testId)
        ->count();
    
    if ($directCount === 0) {
        echo "❌ المشكلة: لا توجد بيانات في جدول relations\n";
        echo "💡 الحل:\n";
        echo "   1. تأكد من أن قاعدة البيانات civilregistry تحتوي على بيانات\n";
        echo "   2. تأكد من اسم الجدول (relations)\n";
        echo "   3. تأكد من أن الرقم {$testId} موجود في الجدول\n";
        echo "   4. راجع ملف config/database.php للاتصال\n";
    } else {
        echo "✅ البيانات موجودة ({$directCount} علاقات)\n";
        echo "💡 الحل: مسح Cache على السحابة\n";
        echo "   الأمر: php artisan cache:clear\n";
    }
} catch (\Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}

echo "\n";
echo "=================================================================\n";
echo "✅ انتهى التشخيص\n";
echo "=================================================================\n\n";

// 8. معلومات البيئة
echo "📊 معلومات البيئة:\n";
echo "   - PHP Version: " . PHP_VERSION . "\n";
echo "   - Laravel Version: " . app()->version() . "\n";
echo "   - Database Driver: " . config('database.connections.civilregistry.driver') . "\n";
echo "   - Database Host: " . config('database.connections.civilregistry.host') . "\n";
echo "   - Database Name: " . config('database.connections.civilregistry.database') . "\n";
echo "\n";
