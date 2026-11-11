<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PickiUserNameValidationTest extends TestCase
{
    private string $junkPattern = '/^picki([\s_-]*u?s*e*r*|\d+|$)/i';

    public function test_rejects_picki_default_and_junk_names(): void
    {
        $junkNames = [
            'PickiUser',
            'PickiUser123',
            'pickiuser',
            'Pickiusser',
            'Pickiusser123',
            'Picki user',
            'picki_user',
            'Picki-User',
            'Picki',
            'picki12345',
            'PICKIUSER',
            'picki  user',
        ];

        foreach ($junkNames as $name) {
            $isMatched = (bool) preg_match($this->junkPattern, trim($name));
            $this->assertTrue(
                $isMatched,
                "Name '{$name}' should be matched as junk name, but it was not."
            );
        }
    }

    public function test_allows_valid_user_names(): void
    {
        $validNames = [
            'Nguyễn Văn A',
            'Dũng Đỗ',
            'Phan Hoa',
            'Cường Cháo',
            'Tạ Thiếu Hiến',
            'Trần Trường',
            'Lê Minh Tuấn',
            'John Doe',
            'Alice',
        ];

        foreach ($validNames as $name) {
            $isMatched = (bool) preg_match($this->junkPattern, trim($name));
            $this->assertFalse(
                $isMatched,
                "Valid name '{$name}' should NOT be matched as junk name, but it was."
            );
        }
    }
}

