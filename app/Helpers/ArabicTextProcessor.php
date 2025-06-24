<?php

namespace App\Helpers;

class ArabicTextProcessor
{
    /**
     * معالجة النص العربي وتوحيد الأحرف
     *
     * @param string $text النص المدخل
     * @return string النص بعد المعالجة
     */
    public static function processArabicText(string $text): string
    {
        // استبدال الألف بأشكالها المختلفة بألف بسيطة
        $text = str_replace(['أ', 'إ', 'آ'], 'ا', $text);
        
        // استبدال التاء المربوطة بالهاء
        $text = str_replace('ة', 'ه', $text);
        
        // استبدال الواو بأشكالها
        $text = str_replace(['ۆ', 'ؤ'], 'و', $text);
        
        // استبدال الياء بأشكالها
        $text = str_replace(['ى', 'ئ'], 'ي', $text);
        
        // إزالة التشكيل
        $text = preg_replace('/[\x{064B}-\x{065F}]/u', '', $text);
        
        // إزالة أي مسافات زائدة
        $text = preg_replace('/\s+/', ' ', trim($text));
        
        return $text;
    }
}