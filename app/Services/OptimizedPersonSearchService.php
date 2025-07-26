<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Services\SearchCacheService;

class OptimizedPersonSearchService
{
    protected $cacheService;

    public function __construct(SearchCacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * بحث سريع بالنص الكامل باستخدام فهارس منفصلة
     */
    public function fulltextSearch($searchTerm, $limit = 100)
    {
        $cacheKey = "fulltext_search_" . md5($searchTerm . "_" . $limit);
        
        return $this->cacheService->getQuickSearchResults($searchTerm, $limit, function() use ($searchTerm, $limit) {
            
            // تنظيف مصطلح البحث
            $cleanTerm = trim($searchTerm);
            $booleanTerm = "+$cleanTerm";
            
            // البحث في جميع الأعمدة باستخدام UNION
            $query = "
                (SELECT *, 'first_name' as match_type, 3 as relevance 
                 FROM persons 
                 WHERE MATCH(CI_FIRST_ARB) AGAINST(? IN BOOLEAN MODE)
                 LIMIT ?)
                UNION
                (SELECT *, 'father_name' as match_type, 2 as relevance 
                 FROM persons 
                 WHERE MATCH(CI_FATHER_ARB) AGAINST(? IN BOOLEAN MODE)
                 LIMIT ?)
                UNION
                (SELECT *, 'grand_father_name' as match_type, 1 as relevance 
                 FROM persons 
                 WHERE MATCH(CI_GRAND_FATHER_ARB) AGAINST(? IN BOOLEAN MODE)
                 LIMIT ?)
                UNION
                (SELECT *, 'family_name' as match_type, 2 as relevance 
                 FROM persons 
                 WHERE MATCH(CI_FAMILY_ARB) AGAINST(? IN BOOLEAN MODE)
                 LIMIT ?)
                ORDER BY relevance DESC, ID DESC
                LIMIT ?
            ";
            
            return DB::select($query, [
                $booleanTerm, $limit,
                $booleanTerm, $limit,  
                $booleanTerm, $limit,
                $booleanTerm, $limit,
                $limit
            ]);
        });
    }

    /**
     * بحث سريع مبسط
     */
    public function quickSearch($searchTerm, $limit = 50)
    {
        $cacheKey = "quick_search_" . md5($searchTerm . "_" . $limit);
        
        return $this->cacheService->getQuickSearchResults($searchTerm, $limit, function() use ($searchTerm, $limit) {
            
            $searchPattern = "%" . trim($searchTerm) . "%";
            
            return DB::table("persons")
                ->where(function($query) use ($searchPattern) {
                    $query->where("CI_FIRST_ARB", "LIKE", $searchPattern)
                          ->orWhere("CI_FATHER_ARB", "LIKE", $searchPattern)
                          ->orWhere("CI_FAMILY_ARB", "LIKE", $searchPattern)
                          ->orWhere("CI_ID_NUM", "LIKE", $searchPattern);
                })
                ->orderBy("ID", "DESC")
                ->limit($limit)
                ->get();
        });
    }

    /**
     * بحث متقدم مع فلاتر
     */
    public function advancedSearch($filters, $limit = 100)
    {
        $cacheKey = "advanced_search_" . md5(serialize($filters) . "_" . $limit);
        
        return $this->cacheService->getSearchResults($cacheKey, function() use ($filters, $limit) {
            
            $query = DB::table("persons");
            
            if (!empty($filters["first_name"])) {
                $query->where("CI_FIRST_ARB", "LIKE", "%" . $filters["first_name"] . "%");
            }
            
            if (!empty($filters["father_name"])) {
                $query->where("CI_FATHER_ARB", "LIKE", "%" . $filters["father_name"] . "%");
            }
            
            if (!empty($filters["family_name"])) {
                $query->where("CI_FAMILY_ARB", "LIKE", "%" . $filters["family_name"] . "%");
            }
            
            if (!empty($filters["id_num"])) {
                $query->where("CI_ID_NUM", "=", $filters["id_num"]);
            }
            
            if (!empty($filters["birth_year"])) {
                $query->whereYear("CI_BIRTH_DT", $filters["birth_year"]);
            }
            
            if (!empty($filters["gender"])) {
                $query->where("CI_SEX_CD", $filters["gender"]);
            }
            
            if (!empty($filters["city"])) {
                $query->where("CITY", $filters["city"]);
            }
            
            return $query->orderBy("ID", "DESC")->limit($limit)->get();
        });
    }
}