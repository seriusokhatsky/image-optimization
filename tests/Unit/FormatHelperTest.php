<?php

use App\Helpers\FormatHelper;

describe('FormatHelper', function () {
    describe('bytes formatting', function () {
        it('formats bytes correctly', function () {
            expect(FormatHelper::bytes(0))->toBe('0 B');
            expect(FormatHelper::bytes(1))->toBe('1 B');
            expect(FormatHelper::bytes(1000))->toBe('1000 B');
            expect(FormatHelper::bytes(1024))->toBe('1.00 KB');
            expect(FormatHelper::bytes(1536))->toBe('1.50 KB');
            expect(FormatHelper::bytes(1048576))->toBe('1.00 MB');
            expect(FormatHelper::bytes(1073741824))->toBe('1.00 GB');
        });

        it('handles large numbers', function () {
            expect(FormatHelper::bytes(1099511627776))->toBe('1,024.00 GB');
            expect(FormatHelper::bytes(5368709120))->toBe('5.00 GB');
        });

        it('handles decimal precision', function () {
            expect(FormatHelper::bytes(1536, 2))->toBe('1.50 KB');
            expect(FormatHelper::bytes(1536, 0))->toBe('2 KB');
        });

        it('handles negative numbers', function () {
            expect(FormatHelper::bytes(-1024))->toBe('-1024 B');
        });
    });
}); 