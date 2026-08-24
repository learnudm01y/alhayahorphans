const fs = require('fs');
const initSqlJs = require('sql.js');

async function main() {
  const buf = fs.readFileSync('C:\\xampp\\htdocs\\alhayahorphans\\temp_check\\sponsorships_data.db');
  const SQL = await initSqlJs();
  const db = new SQL.Database(buf);

  // Count
  let r = db.exec('SELECT COUNT(*) as cnt FROM sponsorships');
  console.log('Total records:', r[0].values[0][0]);

  // Schema
  r = db.exec('PRAGMA table_info(sponsorships)');
  console.log('\nColumns:', r[0].values.map(c => c[1]).join(', '));

  // Sample records
  r = db.exec('SELECT id, relation_id_number, internal_file_number FROM sponsorships ORDER BY id LIMIT 10');
  console.log('\nFirst 10 IDs:');
  r[0].values.forEach(v => console.log('  id=' + v[0] + ', relation=' + v[1] + ', internal=' + v[2]));

  // Check for record 1297
  r = db.exec('SELECT id, relation_id_number, internal_file_number FROM sponsorships WHERE id = 1297');
  console.log('\nRecord 1297:', r.length ? JSON.stringify(r[0].values) : 'NOT FOUND');

  // Check some specific IDs
  r = db.exec("SELECT id, relation_id_number, internal_file_number FROM sponsorships WHERE relation_id_number IN ('310565447','320458104','407592010','407041584','409820605')");
  console.log('\nSpecific records (by relation_id_number):');
  if (r.length) r[0].values.forEach(v => console.log('  id=' + v[0] + ', relation=' + v[1] + ', internal=' + v[2]));
  else console.log('  None found');

  // Check how many have json_payload
  r = db.exec("SELECT COUNT(*) FROM sponsorships WHERE json_payload IS NOT NULL AND json_payload != ''");
  console.log('\nRecords with json_payload:', r[0].values[0][0]);

  // Check has_result distribution
  r = db.exec('SELECT has_result, COUNT(*) FROM sponsorships GROUP BY has_result');
  console.log('\nhas_result distribution:');
  if (r.length) r[0].values.forEach(v => console.log('  ' + v[0] + ': ' + v[1]));

  // Check search_text
  r = db.exec("SELECT COUNT(*) FROM sponsorships WHERE search_text IS NOT NULL AND search_text != ''");
  console.log('\nRecords with search_text:', r[0].values[0][0]);

  // Sample a record's json_payload structure
  r = db.exec("SELECT id, relation_id_number, json_payload FROM sponsorships WHERE id = 1297");
  if (r.length && r[0].values.length) {
    const jp = JSON.parse(r[0].values[0][2] || '{}');
    console.log('\nRecord 1297 json_payload keys:', Object.keys(jp));
    if (jp.data) console.log('  data keys:', Object.keys(jp.data).join(', '));
    if (jp.orphan_name) console.log('  orphan_name:', jp.orphan_name);
    if (jp.guardian_name) console.log('  guardian_name:', jp.guardian_name);
    if (jp.file_id) console.log('  file_id:', jp.file_id);
    if (jp.identity_number) console.log('  identity_number:', jp.identity_number);
  }

  // Sample another record
  r = db.exec("SELECT id, relation_id_number, json_payload FROM sponsorships WHERE json_payload IS NOT NULL AND json_payload != '' ORDER BY id LIMIT 1");
  if (r.length && r[0].values.length) {
    const jp = JSON.parse(r[0].values[0][2] || '{}');
    console.log('\nRecord id=' + r[0].values[0][0] + ' (relation=' + r[0].values[0][1] + ') json_payload keys:', Object.keys(jp));
    if (jp.data) console.log('  data keys:', Object.keys(jp.data).join(', '));
  }

  // Check orphan_name field
  r = db.exec("SELECT COUNT(*) FROM sponsorships WHERE json_extract(json_payload, '$.orphan_name') != '' AND json_extract(json_payload, '$.orphan_name') IS NOT NULL");
  console.log('\nRecords with orphan_name in json_payload:', r[0].values[0][0]);

  // Check if data.id exists in json_payload
  r = db.exec("SELECT COUNT(*) FROM sponsorships WHERE json_extract(json_payload, '$.data.id') IS NOT NULL");
  console.log('\nRecords with data.id in json_payload:', r[0].values[0][0]);

  // Check internal_file_number
  r = db.exec("SELECT internal_file_number, COUNT(*) FROM sponsorships GROUP BY internal_file_number ORDER BY COUNT(*) DESC LIMIT 5");
  console.log('\nTop internal_file_numbers:');
  if (r.length) r[0].values.forEach(v => console.log('  ' + v[0] + ': ' + v[1]));

  // Check relation_id_number type
  r = db.exec("SELECT typeof(relation_id_number), COUNT(*) FROM sponsorships GROUP BY typeof(relation_id_number)");
  console.log('\nrelation_id_number types:');
  if (r.length) r[0].values.forEach(v => console.log('  ' + v[0] + ': ' + v[1]));

  db.close();
}

main().catch(e => console.error(e));
