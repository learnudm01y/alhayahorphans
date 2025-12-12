<?php

if (!function_exists('normalizeArabicForSearch')) {
    /**
     * تطبيع النص العربي للبحث
     *
     * @param string $text
     * @return string
     */
    function normalizeArabicForSearch($text)
    {
        if (empty($text)) {
            return '';
        }

        // تحويل إلى lowercase
        $text = mb_strtolower($text, 'UTF-8');

        // إزالة التشكيل
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $text);

        // توحيد الهمزات
        $text = preg_replace('/[إأآا]/u', 'ا', $text);
        $text = preg_replace('/[ى]/u', 'ي', $text);
        $text = str_replace('ة', 'ه', $text);
        $text = str_replace('ؤ', 'و', $text);
        $text = str_replace('ئ', 'ي', $text);

        // إزالة المسافات الزائدة
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = trim($text);

        return $text;
    }
}

if (!function_exists('extractSearchTerms')) {
    /**
     * استخراج كلمات البحث وتطبيعها
     *
     * @param string $searchTerm
     * @return array
     */
    function extractSearchTerms($searchTerm)
    {
        if (empty($searchTerm)) {
            return [];
        }

        // تطبيع النص
        $normalized = normalizeArabicForSearch($searchTerm);

        // تقسيم إلى كلمات
        $words = array_filter(array_map('trim', explode(' ', $normalized)));

        return [
            'original' => $searchTerm,
            'normalized' => $normalized,
            'words' => $words,
            'word_count' => count($words)
        ];
    }
}
