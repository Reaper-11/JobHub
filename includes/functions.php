<?php
// includes/functions.php
require_once __DIR__ . '/job_salary_helper.php';
require_once __DIR__ . '/mailer.php';

if (!function_exists('get_category_card_icon')) {
    function get_category_card_icon($category): string
    {
        $categoryKey = strtolower(trim((string) $category));

        return match ($categoryKey) {
            'information technology',
            'it' => "\u{1F4BB}",
            'finance' => "\u{1F4B0}",
            'marketing' => "\u{1F4E2}",
            'human resources' => "\u{1F465}",
            'sales' => "\u{1F6D2}",
            'design' => "\u{1F3A8}",
            'customer support' => "\u{1F3A7}",
            'internship' => "\u{1F393}",
            'freelance' => "\u{1F9D1}\u{200D}\u{1F4BB}",
            default => "\u{1F4BC}",
        };
    }
}
