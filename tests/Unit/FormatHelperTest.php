<?php

namespace Tests\Unit;

use App\Helpers\FormatHelper;
use Tests\TestCase;

class FormatHelperTest extends TestCase
{
    public function test_formats_bytes_correctly(): void
    {
        $this->assertEquals('0 B', FormatHelper::bytes(0));
        $this->assertEquals('1 B', FormatHelper::bytes(1));
        $this->assertEquals('1000 B', FormatHelper::bytes(1000));
        $this->assertEquals('1.00 KB', FormatHelper::bytes(1024));
        $this->assertEquals('1.50 KB', FormatHelper::bytes(1536));
        $this->assertEquals('1.00 MB', FormatHelper::bytes(1048576));
        $this->assertEquals('1.00 GB', FormatHelper::bytes(1073741824));
    }

    public function test_handles_large_numbers(): void
    {
        $this->assertEquals('1,024.00 GB', FormatHelper::bytes(1099511627776));
        $this->assertEquals('5.00 GB', FormatHelper::bytes(5368709120));
    }

    public function test_handles_decimal_precision(): void
    {
        $this->assertEquals('1.50 KB', FormatHelper::bytes(1536, 2));
        $this->assertEquals('1.5 KB', FormatHelper::bytes(1536, 1));
        $this->assertEquals('2 KB', FormatHelper::bytes(1536, 0));
    }

    public function test_handles_negative_numbers(): void
    {
        $this->assertEquals('-1024 B', FormatHelper::bytes(-1024));
    }
} 