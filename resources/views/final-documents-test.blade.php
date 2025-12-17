<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>اختبار نهائي - الوثائق</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body style="font-family: Arial; padding: 20px;">
    <h1>اختبار نهائي - نظام الوثائق</h1>

    <button onclick="test()" style="padding: 10px 20px; font-size: 16px; background: #007bff; color: white; border: none; cursor: pointer;">
        تشغيل الاختبار الكامل
    </button>

    <hr>

    <div id="output" style="background: #f5f5f5; padding: 15px; margin-top: 20px; white-space: pre-wrap; font-family: monospace;"></div>

    <script>
        const sponsorId = 5;
        let output = '';

        function log(msg) {
            output += msg + '\n';
            $('#output').text(output);
            console.log(msg);
        }

        async function test() {
            output = '';
            log('🔄 بدء الاختبار...\n');

            try {
                // 1. جلب الإعدادات الحالية
                log('📥 الخطوة 1: جلب الإعدادات الحالية...');
                const getResp = await $.get(`/admin/sponsors/${sponsorId}/documents`);
                log(`✅ تم الجلب بنجاح`);
                log(`📊 عدد الوثائق: ${getResp.data.length}`);
                log(`📊 الوثائق المفعلة حالياً: ${getResp.data.filter(d => d.is_enabled).length}`);
                log(`🔍 Debug Info: ${JSON.stringify(getResp.debug || {}, null, 2)}`);

                // عرض أول 5 وثائق
                log('\n📋 أول 5 وثائق:');
                getResp.data.slice(0, 5).forEach(doc => {
                    log(`  ${doc.is_enabled ? '✅' : '❌'} [${doc.id}] ${doc.description}`);
                });

                // 2. حفظ إعدادات جديدة (تفعيل الوثائق 1, 3, 5, 7, 9)
                log('\n💾 الخطوة 2: حفظ إعدادات جديدة...');
                log('تفعيل الوثائق: 1, 3, 5, 7, 9');

                const documents = getResp.data.map(doc => ({
                    document_type_id: doc.id,
                    is_enabled: [1, 3, 5, 7, 9].includes(doc.id)
                }));

                const saveResp = await $.ajax({
                    url: `/admin/sponsors/${sponsorId}/documents`,
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Content-Type': 'application/json'
                    },
                    data: JSON.stringify({ documents }),
                    dataType: 'json'
                });

                log(`✅ تم الحفظ بنجاح`);
                log(`📊 عدد الوثائق المفعلة: ${saveResp.data?.enabled_count || 'غير معروف'}`);

                // 3. إعادة جلب الإعدادات للتحقق
                log('\n🔍 الخطوة 3: إعادة الجلب للتحقق...');
                await new Promise(resolve => setTimeout(resolve, 500)); // انتظار 500ms

                const verifyResp = await $.get(`/admin/sponsors/${sponsorId}/documents`);
                log(`✅ تم إعادة الجلب`);
                log(`📊 الوثائق المفعلة الآن: ${verifyResp.data.filter(d => d.is_enabled).length}`);

                log('\n📋 النتيجة النهائية - أول 10 وثائق:');
                verifyResp.data.slice(0, 10).forEach(doc => {
                    log(`  ${doc.is_enabled ? '✅' : '❌'} [${doc.id}] ${doc.description}`);
                });

                // 4. فحص قاعدة البيانات مباشرة
                log('\n🗄️ الخطوة 4: فحص قاعدة البيانات...');
                const dbResp = await $.get(`/admin/sponsors/${sponsorId}/check-field-settings`);
                log(`📊 enabled_documents في DB: ${JSON.stringify(dbResp.field_settings?.enabled_documents || null)}`);

                log('\n\n✅✅✅ الاختبار اكتمل بنجاح! ✅✅✅');

                if (verifyResp.data.filter(d => d.is_enabled).length === 5) {
                    log('\n🎉🎉🎉 النظام يعمل بشكل صحيح! 🎉🎉🎉');
                } else {
                    log('\n⚠️⚠️⚠️ هناك مشكلة - العدد المتوقع 5 والفعلي ' + verifyResp.data.filter(d => d.is_enabled).length);
                }

            } catch (error) {
                log('\n❌❌❌ حدث خطأ:');
                log(JSON.stringify(error.responseJSON || error, null, 2));
                console.error(error);
            }
        }
    </script>
</body>
</html>
