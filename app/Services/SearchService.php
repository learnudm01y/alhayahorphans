<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SearchService
{
    public function smartSearch(array $filters, int $perPage = 25): array
    {
        $perPage = max(1, min($perPage, config('search.max_per_page', 100)));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $filters = array_filter($filters, fn ($value) => $value !== null && $value !== '');
        ksort($filters);
        $cacheKey = $this->cachePrefix().hash('sha256', serialize([auth()->id(), $filters, $perPage, $page]));

        if (config('search.enable_cache') && ($cached = Cache::get($cacheKey)) !== null) {
            $cached['cached'] = true;
            return $cached;
        }

        if (DB::connection()->getDriverName() === 'sqlite') {
            // SQLite's parser cannot handle deeply nested REPLACE chains.
            $pdo = DB::connection()->getPdo();
            $pdo->sqliteCreateFunction('records_search_name', fn ($text) => preg_replace('/\s+/u', '', $this->normalizeName($text)), 1);
            $pdo->sqliteCreateFunction('records_search_phone', fn ($text) => str_replace([' ', '-', '+', '(', ')', '.', "\t", "\n", "\r", "\u{00A0}"], '', $this->normalizeDigits($text)), 1);
        }
        $union = null;
        $sources = $this->sources($filters);
        foreach ($sources as $source => $config) {
            $query = $this->buildQuery($config, $filters);
            [$exactSql, $bindings] = $this->exactCondition($config, $filters);
            $query->select('s.id as record_key')
                ->selectRaw('? as source', [$source])
                ->selectRaw("CASE WHEN {$exactSql} THEN 2 ELSE 1 END as relevance", $bindings);
            if ($union === null) {
                $union = $query;
            } else {
                $union->unionAll($query);
            }
        }

        $total = $union === null ? 0 : DB::query()->fromSub(clone $union, 'matches')->count();
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $keys = $union === null ? collect() : DB::query()->fromSub($union, 'matches')
            ->orderByDesc('relevance')->orderBy('source')->orderByDesc('record_key')
            ->offset(($page - 1) * $perPage)->limit($perPage)->get();

        $models = [];
        foreach ($keys->groupBy('source') as $source => $sourceKeys) {
            $config = $sources[$source];
            $models[$source] = $config['model']::with($config['relations'])
                ->whereIn('id', $sourceKeys->pluck('record_key'))->get()->keyBy('id');
        }

        $rows = [];
        foreach ($keys as $key) {
            $record = $models[$key->source]->get($key->record_key);
            if ($record !== null) {
                $rows[] = $this->formatRecord($record, $sources[$key->source]);
            }
        }

        $result = [
            'data' => $rows,
            'total_count' => $total,
            'cached' => false,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_records' => $total,
                'per_page' => $perPage,
                'from' => $total ? ($page - 1) * $perPage + 1 : 0,
                'to' => $total ? ($page - 1) * $perPage + count($rows) : 0,
                'has_more' => $page < $totalPages,
            ],
        ];
        if (config('search.enable_cache')) {
            Cache::put($cacheKey, $result, (int) config('search.cache_duration', 15) * 60);
        }
        return $result;
    }

    private function sources(array $filters): array
    {
        $type = $filters['search_type'] ?? 'all';
        $sources = [];
        foreach (config('search.searchable_tables') as $name => $config) {
            if ($type !== 'all' && $type !== $name) {
                continue;
            }
            $config['type'] = $name;
            $individualFilters = ['relationship_id', 'marital_status_id', 'academic_qualification_id', 'health_status_id', 'employment_status_id'];
            foreach ($individualFilters as $filter) {
                if (!empty($filters[$filter]) && !isset($config['filters'][$filter])) {
                    continue 2;
                }
            }
            $hasBirth = !empty($filters['birth_date_from']) || !empty($filters['birth_date_to']);
            $hasDeath = !empty($filters['death_date_from']) || !empty($filters['death_date_to']);
            if (($name === 'deceased' && $hasBirth) || ($name !== 'deceased' && $hasDeath)) {
                continue;
            }
            if ($name === 'deceased') {
                foreach (['father' => 'male', 'mother' => 'female'] as $parent => $gender) {
                    if (!empty($filters['gender']) && $filters['gender'] !== $gender) {
                        continue;
                    }
                    $sources[$parent] = array_merge($config, [
                        'parent' => $parent,
                        'identity' => $parent.'_id',
                        'names' => array_map(fn ($part) => $parent.'_'.$part, ['first_name', 'second_name', 'third_name', 'last_name']),
                        'death_date' => $parent.'_death_date',
                    ]);
                }
            } else {
                $sources[$name] = $config;
            }
        }
        return $sources;
    }

    private function buildQuery(array $config, array $filters): Builder
    {
        $query = DB::table($config['table'].' as s');
        if ($config['type'] === 'deceased') {
            $this->requirePerson($query, $config);
        }
        foreach (['file_id' => $config['file'], 'identity_number' => $config['identity']] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $query->whereRaw("s.{$column} LIKE ? ESCAPE '!'", [$this->contains($this->normalizeDigits($filters[$filter]))]);
            }
        }
        foreach ($config['filters'] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $query->where('s.'.$column, $filters[$filter]);
            }
        }
        if (isset($config['gender']) && !empty($filters['gender'])) {
            $query->where('s.'.$config['gender'], $filters['gender'] === 'male' ? 1 : 2);
        }
        foreach (['birth_date', 'death_date'] as $date) {
            if (!isset($config[$date])) {
                continue;
            }
            foreach (['from' => '>=', 'to' => '<='] as $suffix => $operator) {
                if (!empty($filters[$date.'_'.$suffix])) {
                    $query->where('s.'.$config[$date], $operator, $filters[$date.'_'.$suffix]);
                }
            }
        }

        $guardianFilters = array_filter(config('search.guardian_filters'), fn ($column, $key) => !empty($filters[$key]), ARRAY_FILTER_USE_BOTH);
        if ($guardianFilters || !empty($filters['phone_number'])) {
            $apply = function (Builder $guardian, string $alias) use ($guardianFilters, $filters) {
                foreach ($guardianFilters as $filter => $column) {
                    $guardian->where($alias.'.'.$column, $filters[$filter]);
                }
                if (!empty($filters['phone_number'])) {
                    [$sql, $bindings] = $this->phoneCondition($alias, $filters['phone_number']);
                    $guardian->whereRaw($sql, $bindings);
                }
            };
            if ($config['type'] === 'main_records') {
                $apply($query, 's');
            } else {
                $query->whereExists(function (Builder $guardian) use ($config, $apply) {
                    $guardian->selectRaw('1')->from('data as g')
                        ->whereColumn('g.file_id_number', 's.'.$config['file']);
                    $apply($guardian, 'g');
                });
            }
        }
        if (!empty($filters['search_text'])) {
            [$sql, $bindings] = $this->textCondition($config, $filters['search_text']);
            $query->whereRaw($sql, $bindings);
        }
        return $query;
    }

    private function requirePerson(Builder $query, array $config): void
    {
        $query->where(function (Builder $person) use ($config) {
            foreach (array_merge([$config['identity']], $config['names']) as $column) {
                $person->orWhere(function (Builder $field) use ($column) {
                    $field->whereNotNull('s.'.$column)->where('s.'.$column, '<>', '');
                });
            }
        });
    }

    private function textCondition(array $config, string $text, bool $exact = false): array
    {
        $text = trim($this->normalizeDigits($text));
        $numeric = preg_match('/^[\d\s+().-]+$/u', $text);
        $clauses = [];
        $bindings = [];
        if ($numeric) {
            $digits = preg_replace('/\D/', '', $text);
            foreach ([$config['identity'], $config['file']] as $column) {
                $clauses[] = 's.'.$column.($exact ? ' = ?' : " LIKE ? ESCAPE '!'");
                $bindings[] = $exact ? $digits : $this->contains($digits);
            }
            if (strlen($digits) >= 4) {
                [$phoneSql, $phoneBindings] = $this->recordPhoneCondition($config, $text, $exact);
                $clauses[] = $phoneSql;
                array_push($bindings, ...$phoneBindings);
            }
        } else {
            $name = $this->nameSql($config);
            $normalized = $this->normalizeName($text);
            if ($normalized === '') {
                return ['1 = 0', []];
            }
            if ($exact) {
                $clauses[] = $name.' = ?';
                $bindings[] = str_replace(' ', '', $normalized);
            } else {
                $terms = array_values(array_unique(explode(' ', $normalized)));
                $parts = [];
                foreach ($terms as $term) {
                    $parts[] = $name." LIKE ? ESCAPE '!'";
                    $bindings[] = $this->contains($term);
                }
                $clauses[] = '('.implode(' AND ', $parts).')';
            }
            $clauses[] = 's.'.$config['file'].($exact ? ' = ?' : " LIKE ? ESCAPE '!'");
            $bindings[] = $exact ? $text : $this->contains($text);
        }
        return ['('.implode(' OR ', $clauses).')', $bindings];
    }

    private function exactCondition(array $config, array $filters): array
    {
        $clauses = [];
        $bindings = [];
        if (!empty($filters['search_text'])) {
            [$sql, $values] = $this->textCondition($config, $filters['search_text'], true);
            $clauses[] = $sql;
            array_push($bindings, ...$values);
        }
        foreach (['identity_number' => $config['identity'], 'file_id' => $config['file']] as $key => $column) {
            if (!empty($filters[$key])) {
                $clauses[] = 's.'.$column.' = ?';
                $bindings[] = $this->normalizeDigits($filters[$key]);
            }
        }
        if (!empty($filters['phone_number'])) {
            [$sql, $values] = $this->recordPhoneCondition($config, $filters['phone_number'], true);
            $clauses[] = $sql;
            array_push($bindings, ...$values);
        }
        return [$clauses ? '('.implode(' OR ', $clauses).')' : '1 = 1', $bindings];
    }

    private function recordPhoneCondition(array $config, string $phone, bool $exact = false): array
    {
        $alias = $config['type'] === 'main_records' ? 's' : 'phone_guardian';
        [$sql, $bindings] = $this->phoneCondition($alias, $phone, $exact);
        if ($alias !== 's') {
            $sql = 'EXISTS (SELECT 1 FROM data AS phone_guardian WHERE phone_guardian.file_id_number = s.'.$config['file'].' AND '.$sql.')';
        }
        return [$sql, $bindings];
    }

    private function phoneCondition(string $alias, string $phone, bool $exact = false): array
    {
        $digits = preg_replace('/\D/', '', $this->normalizeDigits($phone));
        $fullPhone = preg_match('/^(?:009700|009720|00970|00972|9700|9720|970|972|0)?(5\d{8})$/', $digits, $matches);
        $values = $fullPhone
            ? array_map(fn ($prefix) => $prefix.$matches[1], ['', '0', '970', '972', '00970', '00972', '9700', '9720', '009700', '009720'])
            : [$digits];
        $clauses = [];
        $bindings = [];
        foreach (['data_phone_number', 'data_alt_phone_number'] as $column) {
            $expression = "COALESCE({$alias}.{$column}, '')";
            if (DB::connection()->getDriverName() === 'sqlite') {
                $expression = 'records_search_phone('.$expression.')';
            } else {
                $expression = $this->digitsSql($expression);
                foreach ([' ', '-', '+', '(', ')', '.', "\t", "\n", "\r", "\u{00A0}"] as $character) {
                    $expression = $this->replaceSql($expression, $character, '');
                }
            }
            if ($fullPhone || $exact) {
                $clauses[] = $expression.' IN ('.implode(',', array_fill(0, count($values), '?')).')';
                array_push($bindings, ...$values);
            } else {
                $clauses[] = $expression." LIKE ? ESCAPE '!'";
                $bindings[] = $this->contains($digits);
            }
        }
        return ['('.implode(' OR ', $clauses).')', $bindings];
    }

    public function normalizeDigits(string $text): string
    {
        return strtr($text, array_combine(
            preg_split('//u', '٠١٢٣٤٥٦٧٨٩۰۱۲۳۴۵۶۷۸۹', -1, PREG_SPLIT_NO_EMPTY),
            str_split('01234567890123456789')
        ));
    }

    private function normalizeName(string $text): string
    {
        $text = mb_strtolower($text);
        foreach ($this->nameReplacements() as $from => $to) {
            $text = str_replace($from, $to, $text);
        }
        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    private function nameReplacements(): array
    {
        $map = ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي', 'ـ' => ''];
        foreach (preg_split('//u', 'ًٌٍَُِّْٰ', -1, PREG_SPLIT_NO_EMPTY) as $mark) {
            $map[$mark] = '';
        }
        return $map;
    }

    private function nameSql(array $config): string
    {
        $parts = array_map(fn ($column) => "COALESCE(s.{$column}, '')", $config['names']);
        if (DB::connection()->getDriverName() === 'sqlite') {
            return 'records_search_name('.implode(" || ' ' || ", $parts).')';
        }
        $sql = 'CONCAT('.implode(", ' ', ", $parts).')';
        $sql = 'LOWER('.$sql.')';
        foreach ($this->nameReplacements() as $from => $to) {
            $sql = $this->replaceSql($sql, $from, $to);
        }
        foreach ([' ', "\t", "\n", "\r", "\u{00A0}"] as $space) {
            $sql = $this->replaceSql($sql, $space, '');
        }
        return $sql;
    }

    private function digitsSql(string $sql): string
    {
        foreach (preg_split('//u', '٠١٢٣٤٥٦٧٨٩۰۱۲۳۴۵۶۷۸۹', -1, PREG_SPLIT_NO_EMPTY) as $index => $digit) {
            $sql = $this->replaceSql($sql, $digit, (string) ($index % 10));
        }
        return $sql;
    }

    private function replaceSql(string $sql, string $from, string $to): string
    {
        return "REPLACE({$sql}, '".str_replace("'", "''", $from)."', '".str_replace("'", "''", $to)."')";
    }

    private function contains(string $text): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $text).'%';
    }

    private function formatRecord($record, array $config): array
    {
        $main = $config['type'] === 'main_records';
        $deceased = $config['type'] === 'deceased';
        $guardian = $main ? $record : $record->dataRecord;
        $parent = $config['parent'] ?? null;
        $birthDate = isset($config['birth_date']) ? $record->{$config['birth_date']} : null;
        $age = null;
        if ($birthDate) {
            try {
                $birth = new \DateTimeImmutable($birthDate);
                $today = new \DateTimeImmutable('today');
                $age = $birth <= $today ? $today->diff($birth)->y : null;
            } catch (\Exception $e) {
                $age = null;
            }
        }
        $gender = $deceased ? ($parent === 'father' ? 'ذكر' : 'أنثى')
            : match ((int) $record->{$config['gender']}) { 1 => 'ذكر', 2 => 'أنثى', default => null };
        $name = fn ($person, $fields) => trim(implode(' ', array_filter(array_map(fn ($field) => $person?->{$field}, $fields), fn ($value) => $value !== null && $value !== '')));

        return [
            'id' => $record->id,
            'type' => $main ? 'main_record' : ($deceased ? 'deceased' : 'family_member'),
            'record_type' => $main ? 'سجل رئيسي' : ($deceased ? ($parent === 'father' ? 'متوفى — الأب' : 'متوفاة — الأم') : 'فرد أسرة'),
            'file_id_number' => $record->{$config['file']},
            'full_name' => $name($record, $config['names']),
            'identity_number' => $record->{$config['identity']},
            'phone' => $guardian?->data_phone_number,
            'alt_phone' => $guardian?->data_alt_phone_number,
            'phone_label' => $main ? 'الجوال' : 'جوال المعيل',
            'guardian_name' => $main ? null : $name($guardian, config('search.searchable_tables.main_records.names')),
            'birth_date' => $birthDate,
            'age' => $age,
            'gender' => $gender,
            'city_name' => $guardian?->city?->city,
            'address' => $guardian?->data_current_address,
            'request_status_name' => $guardian?->requestStatus?->description,
            'section_name' => $guardian?->section?->description,
            'health_status_name' => $deceased ? null : $record->healthStatus?->description,
            'relationship_name' => $main ? $record->categoryOfRelation?->attribute : null,
            'marital_status_name' => $main ? $record->maritalStatus?->CI_PERSONAL_CD : null,
            'education_level' => $main ? $record->academicQualification?->description : null,
            'employment_status_name' => $main ? $record->employmentStatusBreadwinner?->description : null,
            'death_date' => $deceased ? $record->{$config['death_date']} : null,
            'death_reason' => $deceased ? $record->{$parent.'DeathReason'}?->description : null,
            'deceased_parent' => $parent,
            'view_url' => $guardian ? route('admin.records.management.show', $guardian->id) : null,
        ];
    }

    private function cachePrefix(): string
    {
        return 'records_search_v2:'.Cache::get('records_search_generation', 'initial').':';
    }

    public function clearSearchCache(): void
    {
        // A new namespace invalidates all result keys without flushing unrelated application caches.
        Cache::forever('records_search_generation', (string) Str::uuid());
    }

    public function getSearchStatistics(): array
    {
        return Cache::remember($this->cachePrefix().'statistics', 60, function () {
            $stats = ['main_records' => DB::table('data')->count(), 'family_members' => DB::table('re_people')->count(), 'deceased_records' => 0];
            foreach ($this->sources(['search_type' => 'deceased']) as $config) {
                $query = DB::table('dead_people as s');
                $this->requirePerson($query, $config);
                $stats['deceased_records'] += $query->count();
            }
            $stats['total_records'] = array_sum($stats);
            $stats['last_updated'] = now()->toDateTimeString();
            return $stats;
        });
    }

    public function getSearchSuggestions(string $query, int $limit = 10, array $filters = []): array
    {
        $filters['search_text'] = $query;
        $filters['page'] = 1;
        $result = $this->smartSearch($filters, min($limit, config('search.autocomplete_max_results', 10)));
        $numeric = preg_match('/^[\d\s+().-]+$/u', $this->normalizeDigits($query));
        $suggestions = [];
        foreach ($result['data'] as $record) {
            $values = $numeric
                ? [$record['identity_number'], $record['file_id_number'], $record['phone'], $record['alt_phone']]
                : [$record['full_name']];
            foreach ($values as $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                if ($numeric) {
                    $needle = preg_replace('/\D/', '', $this->normalizeDigits($query));
                    $digits = preg_replace('/\D/', '', $this->normalizeDigits((string) $value));
                    if (!str_contains($digits, $needle) && !str_ends_with($digits, substr($needle, -9))) {
                        continue;
                    }
                }
                $suggestions[] = (string) $value;
            }
        }
        return array_slice(array_values(array_unique($suggestions)), 0, $limit);
    }
}
