<?php
/**
 * WordPress Function Mocks for Testing
 *
 * @package DatabaseImportPro\Tests
 */

// Mock WordPress functions that are commonly used
if (!function_exists('__')) {
    function __(string $text, string $domain = 'default'): string {
        return $text;
    }
}

if (!function_exists('esc_html')) {
    function esc_html(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__(string $text, string $domain = 'default'): string {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_sql')) {
    function esc_sql($data) {
        if (is_array($data)) {
            return array_map('esc_sql', $data);
        }
        return addslashes($data);
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $str): string {
        return strip_tags($str); // phpcs:ignore WordPressVIPMinimum.Functions.StripTags.StripTags,WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Test mock, strip_tags is appropriate for sanitization
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null): void {
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null): void {
        echo json_encode(['success' => false, 'data' => $data]);
        exit;
    }
}

if (!function_exists('wp_date')) {
    function wp_date(string $format, ?int $timestamp = null, $timezone = null): string {
        return gmdate($format, $timestamp ?? time());
    }
}

if (!function_exists('wp_timezone')) {
    function wp_timezone() {
        return new DateTimeZone('UTC');
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id(): int {
        return 1;
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can(string $capability): bool {
        return true;
    }
}

if (!function_exists('size_format')) {
    function size_format(int $bytes, int $decimals = 0): string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor((strlen($bytes) - 1) / 3);
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . $units[$factor];
    }
}
