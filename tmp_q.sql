SELECT 'reserved_like' AS src, code, used, reserved_at FROM reserved_codes WHERE code LIKE '%1355%'
UNION ALL
SELECT 'in_data', file_id_number, NULL, NULL FROM data WHERE file_id_number IN ('013551','13551')
UNION ALL
SELECT 'in_sponsorships_internal', internal_file_number, NULL, NULL FROM sponsorships WHERE internal_file_number IN ('013551','13551')
UNION ALL
SELECT 'in_sponsorships_relation', relation_id_number, NULL, NULL FROM sponsorships WHERE relation_id_number IN ('013551','13551')
UNION ALL
SELECT 'in_dead_people', re_file_id, NULL, NULL FROM dead_people WHERE re_file_id IN ('013551','13551')
UNION ALL
SELECT 'in_re_people', registration_id, NULL, NULL FROM re_people WHERE registration_id IN ('013551','13551');
