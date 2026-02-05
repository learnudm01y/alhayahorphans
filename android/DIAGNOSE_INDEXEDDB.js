/**
 * 🔍 IndexedDB Diagnostic Tool
 *
 * قم بنسخ ولصق هذا الكود كاملاً في Browser Console
 * (افتح التطبيق → chrome://inspect → اختر التطبيق → Console)
 *
 * سيقوم بفحص IndexedDB وإظهار المشكلة بالضبط
 */

(async function diagnoseIndexedDB() {
    console.log('\n\n');
    console.log('╔════════════════════════════════════════════════════════════════╗');
    console.log('║        🔍 IndexedDB Diagnostic Tool - بدء الفحص            ║');
    console.log('╚════════════════════════════════════════════════════════════════╝');
    console.log('\n');

    const results = {
        step1_dbExists: false,
        step2_dbType: '',
        step3_dbVariable: '',
        step4_tables: [],
        step5_hasSponsorships: false,
        step6_hasAssociations: false,
        step7_sampleSponsorship: null,
        step8_sampleAssociation: null,
        step9_sponsorshipFields: [],
        step10_associationFields: [],
        issues: [],
        solutions: []
    };

    try {
        // ==================== Step 1: فحص وجود db ====================
        console.log('📋 [Step 1/10] Checking if "db" variable exists...');

        const dbCandidates = [
            { name: 'db', obj: typeof db !== 'undefined' ? db : null },
            { name: 'window.db', obj: window.db },
            { name: 'window.$db', obj: window.$db },
            { name: 'window.appDatabase', obj: window.appDatabase },
            { name: 'window.database', obj: window.database }
        ];

        let foundDB = null;
        let dbVarName = '';

        for (const candidate of dbCandidates) {
            if (candidate.obj) {
                foundDB = candidate.obj;
                dbVarName = candidate.name;
                console.log('   ✅ FOUND:', candidate.name);
                results.step1_dbExists = true;
                results.step3_dbVariable = candidate.name;
                break;
            }
        }

        if (!foundDB) {
            console.error('   ❌ ERROR: No database variable found!');
            console.log('   🔍 Searching for variables containing "db"...');
            const dbLikeVars = Object.keys(window).filter(k => k.toLowerCase().includes('db'));
            console.log('   📊 Found variables:', dbLikeVars.join(', '));
            results.issues.push('Database variable not found');
            results.solutions.push('Check the actual variable name in your app and update IndexedDBReader.java line 69');
            throw new Error('Database variable not found');
        }

        results.step2_dbType = typeof foundDB;
        console.log('   ✅ Database variable:', dbVarName);
        console.log('   ✅ Type:', typeof foundDB);

        // ==================== Step 2: عرض الجداول ====================
        console.log('\n📋 [Step 2/10] Listing database tables...');

        const tableNames = Object.keys(foundDB).filter(key => {
            return foundDB[key] && typeof foundDB[key] === 'object';
        });

        results.step4_tables = tableNames;
        console.log('   ✅ Found ' + tableNames.length + ' tables:', tableNames.join(', '));

        // ==================== Step 3: فحص جدول sponsorships ====================
        console.log('\n📋 [Step 3/10] Checking "sponsorships" table...');

        const sponsorshipTableNames = ['sponsorships', 'sponsorship', 'sponsor_data', 'sponsors'];
        let sponsorshipTable = null;
        let sponsorshipTableName = '';

        for (const name of sponsorshipTableNames) {
            if (foundDB[name]) {
                sponsorshipTable = foundDB[name];
                sponsorshipTableName = name;
                console.log('   ✅ FOUND sponsorships table as:', name);
                results.step5_hasSponsorships = true;
                break;
            }
        }

        if (!sponsorshipTable) {
            console.error('   ❌ ERROR: No sponsorships table found!');
            console.log('   💡 Available tables:', tableNames.join(', '));
            results.issues.push('Sponsorships table not found');
            results.solutions.push('Update IndexedDBReader.java line 79 to use the correct table name from: ' + tableNames.join(', '));
            throw new Error('Sponsorships table not found');
        }

        // ==================== Step 4: فحص جدول associations ====================
        console.log('\n📋 [Step 4/10] Checking "associations" table...');

        const associationTableNames = ['associations', 'association', 'association_data', 'orgs'];
        let associationTable = null;
        let associationTableName = '';

        for (const name of associationTableNames) {
            if (foundDB[name]) {
                associationTable = foundDB[name];
                associationTableName = name;
                console.log('   ✅ FOUND associations table as:', name);
                results.step6_hasAssociations = true;
                break;
            }
        }

        if (!associationTable) {
            console.error('   ❌ ERROR: No associations table found!');
            console.log('   💡 Available tables:', tableNames.join(', '));
            results.issues.push('Associations table not found');
            results.solutions.push('Update IndexedDBReader.java line 90 to use the correct table name from: ' + tableNames.join(', '));
            throw new Error('Associations table not found');
        }

        // ==================== Step 5: جلب عينة من sponsorships ====================
        console.log('\n📋 [Step 5/10] Fetching sample sponsorship...');

        const allSponsorships = await sponsorshipTable.toArray();
        console.log('   ✅ Total sponsorships in DB:', allSponsorships.length);

        if (allSponsorships.length === 0) {
            console.error('   ❌ ERROR: Sponsorships table is EMPTY!');
            results.issues.push('Sponsorships table is empty');
            results.solutions.push('Make sure the app syncs data from the server before uploading files');
            throw new Error('No sponsorships in database');
        }

        const sampleSponsorship = allSponsorships[0];
        results.step7_sampleSponsorship = sampleSponsorship;
        results.step9_sponsorshipFields = Object.keys(sampleSponsorship);

        console.log('   ✅ Sample sponsorship ID:', sampleSponsorship.id || sampleSponsorship._id || 'NO_ID');
        console.log('   ✅ Sample sponsorship:', JSON.stringify(sampleSponsorship, null, 2).substring(0, 500));
        console.log('   ✅ Fields:', Object.keys(sampleSponsorship).join(', '));

        // ==================== Step 6: فحص حقل association_id ====================
        console.log('\n📋 [Step 6/10] Checking association_id field...');

        const associationIdFields = ['association_id', 'assoc_id', 'associationId', 'organization_id', 'org_id'];
        let foundAssocField = null;

        for (const field of associationIdFields) {
            if (sampleSponsorship[field] !== undefined) {
                foundAssocField = field;
                console.log('   ✅ FOUND association ID field as:', field);
                console.log('   ✅ Value:', sampleSponsorship[field]);
                break;
            }
        }

        if (!foundAssocField) {
            console.error('   ❌ ERROR: No association_id field found!');
            console.log('   💡 Available fields:', Object.keys(sampleSponsorship).join(', '));
            results.issues.push('Association ID field not found in sponsorship');
            results.solutions.push('Update IndexedDBReader.java lines 90, 95 to use the correct field name');
            throw new Error('Association ID field not found');
        }

        // ==================== Step 7: جلب الجمعية المرتبطة ====================
        console.log('\n📋 [Step 7/10] Fetching associated association...');

        const associationId = sampleSponsorship[foundAssocField];
        console.log('   🔍 Looking for association with ID:', associationId);

        const association = await associationTable.get(associationId);

        if (!association) {
            console.error('   ❌ ERROR: Association not found for ID:', associationId);
            results.issues.push('Association not found (broken relationship)');
            results.solutions.push('Check data integrity - sponsorship.association_id does not match any association');
            throw new Error('Association not found');
        }

        results.step8_sampleAssociation = association;
        results.step10_associationFields = Object.keys(association);

        console.log('   ✅ Association found!');
        console.log('   ✅ Association:', JSON.stringify(association, null, 2).substring(0, 500));
        console.log('   ✅ Fields:', Object.keys(association).join(', '));

        // ==================== Step 8: فحص اسم الجمعية ====================
        console.log('\n📋 [Step 8/10] Checking association name field...');

        const nameFields = ['name', 'title', 'association_name', 'org_name', 'organization_name'];
        let foundNameField = null;

        for (const field of nameFields) {
            if (association[field]) {
                foundNameField = field;
                console.log('   ✅ FOUND association name field as:', field);
                console.log('   ✅ Value:', association[field]);
                break;
            }
        }

        if (!foundNameField) {
            console.error('   ❌ ERROR: No name field found in association!');
            console.log('   💡 Available fields:', Object.keys(association).join(', '));
            results.issues.push('Association name field not found');
            results.solutions.push('Update IndexedDBReader.java line 101 to use the correct field name');
            throw new Error('Association name field not found');
        }

        // ==================== Step 9: فحص اسم الشخص ====================
        console.log('\n📋 [Step 9/10] Checking person name field...');

        const personNameFields = ['person_name', 'name', 'full_name', 'sponsored_person', 'sponsor_name'];
        let foundPersonField = null;

        for (const field of personNameFields) {
            if (sampleSponsorship[field]) {
                foundPersonField = field;
                console.log('   ✅ FOUND person name field as:', field);
                console.log('   ✅ Value:', sampleSponsorship[field]);
                break;
            }
        }

        if (!foundPersonField) {
            console.error('   ❌ ERROR: No person name field found in sponsorship!');
            console.log('   💡 Available fields:', Object.keys(sampleSponsorship).join(', '));
            results.issues.push('Person name field not found');
            results.solutions.push('Update IndexedDBReader.java line 102 to use the correct field name');
            throw new Error('Person name field not found');
        }

        // ==================== Step 10: اختبار الاستعلام الكامل ====================
        console.log('\n📋 [Step 10/10] Testing full query (simulating IndexedDBReader)...');

        const testSponsorshipId = sampleSponsorship.id || sampleSponsorship._id;
        console.log('   🔍 Testing with sponsorship ID:', testSponsorshipId);

        const testCode = `
            const sp = await ${dbVarName}.${sponsorshipTableName}.get(${testSponsorshipId});
            const assoc = await ${dbVarName}.${associationTableName}.get(sp.${foundAssocField});
            const result = {
                associationName: assoc.${foundNameField},
                personName: sp.${foundPersonField}
            };
        `;

        console.log('   📝 Test code:\n' + testCode);

        const testSp = await foundDB[sponsorshipTableName].get(testSponsorshipId);
        const testAssoc = await foundDB[associationTableName].get(testSp[foundAssocField]);
        const finalResult = {
            associationName: testAssoc[foundNameField],
            personName: testSp[foundPersonField]
        };

        console.log('   ✅ TEST RESULT:', JSON.stringify(finalResult, null, 2));

        // ==================== النتيجة النهائية ====================
        console.log('\n\n');
        console.log('╔════════════════════════════════════════════════════════════════╗');
        console.log('║           ✅✅✅ DIAGNOSIS COMPLETE - SUCCESS! ✅✅✅         ║');
        console.log('╚════════════════════════════════════════════════════════════════╝');
        console.log('\n');
        console.log('📊 Summary:');
        console.log('   ✅ Database variable:', dbVarName);
        console.log('   ✅ Sponsorships table:', sponsorshipTableName);
        console.log('   ✅ Associations table:', associationTableName);
        console.log('   ✅ Association ID field:', foundAssocField);
        console.log('   ✅ Association name field:', foundNameField);
        console.log('   ✅ Person name field:', foundPersonField);
        console.log('\n');
        console.log('🔧 Required changes in IndexedDBReader.java:');
        console.log('\n   Line 69 - Database variable:');
        console.log('   OLD: const database = window.db || window.$db || db;');
        console.log('   NEW: const database = ' + dbVarName + ';');
        console.log('\n   Line 79 - Sponsorships query:');
        console.log('   OLD: const sp = await database.sponsorships.get(...);');
        console.log('   NEW: const sp = await database.' + sponsorshipTableName + '.get(...);');
        console.log('\n   Line 90 - Associations query:');
        console.log('   OLD: const assoc = await database.associations.get(sp.association_id);');
        console.log('   NEW: const assoc = await database.' + associationTableName + '.get(sp.' + foundAssocField + ');');
        console.log('\n   Line 101-102 - Field names:');
        console.log('   OLD: associationName: assoc?.name || \'General\'');
        console.log('   NEW: associationName: assoc?.' + foundNameField + ' || \'General\'');
        console.log('\n   OLD: personName: sp.person_name || sp.name || \'unknown\'');
        console.log('   NEW: personName: sp.' + foundPersonField + ' || \'unknown\'');
        console.log('\n');
        console.log('✅ All fields detected successfully!');
        console.log('✅ Copy the changes above and apply them to IndexedDBReader.java');

        return results;

    } catch (error) {
        console.log('\n\n');
        console.log('╔════════════════════════════════════════════════════════════════╗');
        console.log('║           ❌❌❌ DIAGNOSIS FAILED - ERROR! ❌❌❌            ║');
        console.log('╚════════════════════════════════════════════════════════════════╝');
        console.log('\n');
        console.error('❌ Error:', error.message);
        console.log('\n📊 Issues found:', results.issues.length);
        results.issues.forEach((issue, i) => {
            console.log('   ' + (i + 1) + '. ❌ ' + issue);
        });
        console.log('\n🔧 Suggested solutions:');
        results.solutions.forEach((solution, i) => {
            console.log('   ' + (i + 1) + '. 💡 ' + solution);
        });
        console.log('\n');
        console.log('📤 Please send these logs for support!');

        return results;
    }
})();
