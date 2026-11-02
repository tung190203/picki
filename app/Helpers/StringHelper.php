<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;

class StringHelper
{
    /**
     * Bảng ánh xạ chữ cái tiếng Việt có dấu (thường & hoa) sang chữ cái ASCII không dấu (thường).
     */
    public const VIETNAMESE_CHAR_TO_ASCII_LOWER = [
        'à' => 'a', 'á' => 'a', 'ạ' => 'a', 'ả' => 'a', 'ã' => 'a',
        'â' => 'a', 'ầ' => 'a', 'ấ' => 'a', 'ậ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a',
        'ă' => 'a', 'ằ' => 'a', 'ắ' => 'a', 'ặ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a',
        'À' => 'a', 'Á' => 'a', 'Ạ' => 'a', 'Ả' => 'a', 'Ã' => 'a',
        'Â' => 'a', 'Ầ' => 'a', 'Ấ' => 'a', 'Ậ' => 'a', 'Ẩ' => 'a', 'Ẫ' => 'a',
        'Ă' => 'a', 'Ằ' => 'a', 'Ắ' => 'a', 'Ặ' => 'a', 'Ẳ' => 'a', 'Ẵ' => 'a',

        'è' => 'e', 'é' => 'e', 'ẹ' => 'e', 'ẻ' => 'e', 'ẽ' => 'e',
        'ê' => 'e', 'ề' => 'e', 'ế' => 'e', 'ệ' => 'e', 'ể' => 'e', 'ễ' => 'e',
        'È' => 'e', 'É' => 'e', 'Ẹ' => 'e', 'Ẻ' => 'e', 'Ẽ' => 'e',
        'Ê' => 'e', 'Ề' => 'e', 'Ế' => 'e', 'Ệ' => 'e', 'Ể' => 'e', 'Ễ' => 'e',

        'ì' => 'i', 'í' => 'i', 'ị' => 'i', 'ỉ' => 'i', 'ĩ' => 'i',
        'Ì' => 'i', 'Í' => 'i', 'Ị' => 'i', 'Ỉ' => 'i', 'Ĩ' => 'i',

        'ò' => 'o', 'ó' => 'o', 'ọ' => 'o', 'ỏ' => 'o', 'õ' => 'o',
        'ô' => 'o', 'ồ' => 'o', 'ố' => 'o', 'ộ' => 'o', 'ổ' => 'o', 'ỗ' => 'o',
        'ơ' => 'o', 'ờ' => 'o', 'ớ' => 'o', 'ợ' => 'o', 'ở' => 'o', 'ỡ' => 'o',
        'Ò' => 'o', 'Ó' => 'o', 'Ọ' => 'o', 'Ỏ' => 'o', 'Õ' => 'o',
        'Ô' => 'o', 'Ồ' => 'o', 'Ố' => 'o', 'Ộ' => 'o', 'Ổ' => 'o', 'Ỗ' => 'o',
        'Ơ' => 'o', 'Ờ' => 'o', 'Ớ' => 'o', 'Ợ' => 'o', 'Ở' => 'o', 'Ỡ' => 'o',

        'ù' => 'u', 'ú' => 'u', 'ụ' => 'u', 'ủ' => 'u', 'ũ' => 'u',
        'ư' => 'u', 'ừ' => 'u', 'ứ' => 'u', 'ự' => 'u', 'ử' => 'u', 'ữ' => 'u',
        'Ù' => 'u', 'Ú' => 'u', 'Ụ' => 'u', 'Ủ' => 'u', 'Ũ' => 'u',
        'Ư' => 'u', 'Ừ' => 'u', 'Ứ' => 'u', 'Ự' => 'u', 'Ử' => 'u', 'Ữ' => 'u',

        'ỳ' => 'y', 'ý' => 'y', 'ỵ' => 'y', 'ỷ' => 'y', 'ỹ' => 'y',
        'Ỳ' => 'y', 'Ý' => 'y', 'Ỵ' => 'y', 'Ỷ' => 'y', 'Ỹ' => 'y',

        'đ' => 'd', 'Đ' => 'd',
    ];

    /**
     * Các dấu thanh tổ hợp (Unicode Combining Diacritical Marks - NFD) thường gặp trong tiếng Việt.
     */
    public const COMBINING_MARKS = [
        "\u{0300}", // Huyền
        "\u{0301}", // Sắc
        "\u{0303}", // Ngã
        "\u{0309}", // Hỏi
        "\u{0323}", // Nặng
    ];

    /**
     * Remove Vietnamese tones/diacritics from string
     * Converts: "Hải Đăng" -> "Hai Dang", "Điểm trình" -> "Diem trinh"
     */
    public static function removeVietnameseTones(string $str): string
    {
        // Loại bỏ dấu tổ hợp (Unicode NFD) trước khi thay thế nguyên âm/phụ âm
        $str = preg_replace('/[\x{0300}-\x{036f}]/u', '', $str) ?? $str;

        $str = preg_replace('/[àáạảãâầấậẩẫăằắặẳẵ]/u', 'a', $str);
        $str = preg_replace('/[ÀÁẠẢÃÂẦẤẬẨẪĂẰẮẶẲẴ]/u', 'A', $str);
        $str = preg_replace('/[èéẹẻẽêềếệểễ]/u', 'e', $str);
        $str = preg_replace('/[ÈÉẸẺẼÊỀẾỆỂỄ]/u', 'E', $str);
        $str = preg_replace('/[ìíịỉĩ]/u', 'i', $str);
        $str = preg_replace('/[ÌÍỊỈĨ]/u', 'I', $str);
        $str = preg_replace('/[òóọỏõôồốộổỗơờớợởỡ]/u', 'o', $str);
        $str = preg_replace('/[ÒÓỌỎÕÔỒỐỘỔỖƠỜỚỢỞỠ]/u', 'O', $str);
        $str = preg_replace('/[ùúụủũưừứựửữ]/u', 'u', $str);
        $str = preg_replace('/[ÙÚỤỦŨƯỪỨỰỬỮ]/u', 'U', $str);
        $str = preg_replace('/[ỳýỵỷỹ]/u', 'y', $str);
        $str = preg_replace('/[ỲÝỴỶỸ]/u', 'Y', $str);
        $str = preg_replace('/[đ]/u', 'd', $str);
        $str = preg_replace('/[Đ]/u', 'D', $str);

        return $str;
    }

    /**
     * Chuẩn hóa chuỗi phục vụ tìm kiếm: bỏ dấu tiếng Việt, chuyển chữ thường, gộp khoảng trắng thừa.
     */
    public static function normalizeForSearch(?string $str): string
    {
        $str = trim((string) $str);
        if ($str === '') {
            return '';
        }

        $noTone = self::removeVietnameseTones($str);
        $lower = mb_strtolower($noTone, 'UTF-8');

        return trim((string) (preg_replace('/\s+/u', ' ', $lower) ?? $lower));
    }

    /**
     * Kiểm tra chuỗi $haystack có chứa $needle hay không (không phân biệt hoa thường & dấu tiếng Việt).
     */
    public static function containsIgnoreAccents(?string $haystack, ?string $needle): bool
    {
        $needle = trim((string) $needle);
        if ($needle === '') {
            return true;
        }

        $haystack = (string) $haystack;
        if ($haystack === '') {
            return false;
        }

        if (mb_stripos($haystack, $needle, 0, 'UTF-8') !== false) {
            return true;
        }

        $normHaystack = self::normalizeForSearch($haystack);
        $normNeedle = self::normalizeForSearch($needle);

        return $normNeedle !== '' && str_contains($normHaystack, $normNeedle);
    }

    /**
     * Tạo biểu thức SQL chuẩn hóa cột văn bản về dạng chữ thường không dấu.
     * Nếu $includeJsonEscapes = true (dùng cho cột notifications.data lưu JSON có \uXXXX),
     * biểu thức sẽ đồng thời chuyển các mã \uXXXX của chữ cái tiếng Việt về ký tự ASCII không dấu.
     */
    public static function sqlNormalizeColumn(string $column, bool $includeJsonEscapes = false): string
    {
        $expr = "LOWER({$column})";

        foreach (self::COMBINING_MARKS as $mark) {
            $expr = "REPLACE({$expr}, '{$mark}', '')";
        }

        foreach (self::VIETNAMESE_CHAR_TO_ASCII_LOWER as $from => $to) {
            $expr = "REPLACE({$expr}, '{$from}', '{$to}')";
        }

        if ($includeJsonEscapes) {
            $driver = 'mysql';
            try {
                $driver = DB::connection()->getDriverName();
            } catch (\Throwable) {
                $driver = 'mysql';
            }

            // Trong MySQL string literal, '\\' đại diện cho 1 ký tự '\'. Trong SQLite, '\' là 1 ký tự '\'.
            $bs = $driver === 'sqlite' ? '\\' : '\\\\';

            foreach (self::COMBINING_MARKS as $mark) {
                $hex = strtolower(substr((string) json_encode($mark), 3, -1)); // 4 hex digits
                $expr = "REPLACE({$expr}, '{$bs}u{$hex}', '')";
            }

            foreach (self::VIETNAMESE_CHAR_TO_ASCII_LOWER as $from => $to) {
                $hex = strtolower(substr((string) json_encode($from), 3, -1)); // 4 hex digits
                $expr = "REPLACE({$expr}, '{$bs}u{$hex}', '{$to}')";
            }
        }

        return $expr;
    }

    /**
     * Áp dụng điều kiện tìm kiếm LIKE hỗ trợ cả có dấu và không dấu tiếng Việt trên 1 cột SQL.
     */
    public static function applyWhereLikeIgnoreAccents($query, string $column, ?string $keyword): void
    {
        $keyword = trim((string) $keyword);
        if ($keyword === '') {
            return;
        }

        $normalizedKeyword = self::normalizeForSearch($keyword);
        $normalizedColumnSql = self::sqlNormalizeColumn($column, false);

        $query->where(function ($sub) use ($column, $keyword, $normalizedKeyword, $normalizedColumnSql) {
            $sub->where($column, 'like', "%{$keyword}%");
            if ($normalizedKeyword !== '') {
                $sub->orWhereRaw("{$normalizedColumnSql} LIKE ?", ["%{$normalizedKeyword}%"]);
            }
        });
    }

    /**
     * Áp dụng điều kiện tìm kiếm từ khóa trên cột notifications.data (hỗ trợ UTF-8, \uXXXX và tìm không dấu).
     */
    public static function applyNotificationDataKeywordFilter($query, ?string $keyword): void
    {
        $keyword = trim((string) $keyword);
        if ($keyword === '') {
            return;
        }

        $normalizedKeyword = self::normalizeForSearch($keyword);
        $jsonEscaped = substr((string) json_encode($keyword), 1, -1);
        $sqlEscapedJson = str_replace('\\', '\\\\', $jsonEscaped);
        $normalizedDataSql = self::sqlNormalizeColumn('data', true);

        $query->where(function ($sub) use ($keyword, $jsonEscaped, $sqlEscapedJson, $normalizedKeyword, $normalizedDataSql) {
            $sub->where('data', 'like', "%{$keyword}%");

            if ($jsonEscaped !== '' && $jsonEscaped !== $keyword) {
                $sub->orWhere('data', 'like', "%{$sqlEscapedJson}%");
            }

            if ($normalizedKeyword !== '') {
                $sub->orWhereRaw("{$normalizedDataSql} LIKE ?", ["%{$normalizedKeyword}%"]);

                // Hỗ trợ tìm kiếm theo tiêu đề hiển thị mặc định của một số loại notification
                if (str_contains('xac thuc email vui long xac thuc email cua ban', $normalizedKeyword)) {
                    $sub->orWhere('type', 'like', '%VerifyEmailNotification%');
                }
                if (str_contains('da gui tin nhan tin nhan moi', $normalizedKeyword)) {
                    $sub->orWhere('type', 'like', '%MessageNotification%');
                }
            }
        });
    }
}

