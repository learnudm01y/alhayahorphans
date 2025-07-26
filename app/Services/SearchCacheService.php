<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class SearchCacheService
{
    protected $cachePrefix = 'person_search_';
    protected $cacheTTL = 300; // 5 دقائق

    /**
     * الحصول على نتائج البحث من الكاش
     */
    public function getSearchResults($searchKey, $callback = null)
    {
        $cacheKey = $this->cachePrefix . md5($searchKey);

        // محاولة الحصول على النتائج من الكاش
        $results = Cache::get($cacheKey);

        if ($results === null && $callback !== null) {
            // إذا لم توجد النتائج في الكاش، تنفيذ البحث وحفظ النتيجة
            $results = $callback();
            $this->cacheSearchResults($searchKey, $results);
        }

        return $results;
    }

    /**
     * حفظ نتائج البحث في الكاش
     */
    public function cacheSearchResults($searchKey, $results)
    {
        $cacheKey = $this->cachePrefix . md5($searchKey);
        Cache::put($cacheKey, $results, $this->cacheTTL);
    }

    /**
     * مسح الكاش عند إضافة أو تعديل البيانات
     */
    public function clearSearchCache()
    {
        $pattern = $this->cachePrefix . '*';

        if (config('cache.default') === 'redis') {
            // إذا كان Redis متاحاً
            $keys = Redis::keys($pattern);
            if (!empty($keys)) {
                Redis::del($keys);
            }
        } else {
            // مسح الكاش العادي
            Cache::flush();
        }
    }

    /**
     * إنشاء مفتاح كاش فريد للبحث
     */
    public function generateSearchKey($term, $filters = [])
    {
        $data = [
            'term' => $term,
            'filters' => $filters,
            'timestamp' => floor(time() / 60) // تجديد كل دقيقة
        ];

        return serialize($data);
    }

    /**
     * كاش البحث السريع مع أولوية أعلى
     */
    public function getQuickSearchResults($term, $limit, $callback = null)
    {
        $cacheKey = $this->cachePrefix . 'quick_' . md5($term . '_' . $limit);

        $results = Cache::get($cacheKey);

        if ($results === null && $callback !== null) {
            $results = $callback();
            // كاش أطول للبحث السريع
            Cache::put($cacheKey, $results, $this->cacheTTL * 2);
        }

        return $results;
    }
}
