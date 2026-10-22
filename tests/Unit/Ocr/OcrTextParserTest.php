<?php

namespace Tests\Unit\Ocr;

use App\Domain\Ocr\OcrTextParser;
use PHPUnit\Framework\TestCase;

class OcrTextParserTest extends TestCase
{
    public function test_parses_receipt_amount_date_and_reference(): void
    {
        $text = <<<'TXT'
Cash Receipt
Date: 15/10/2026
Amount Received: 1,250.50
Ref: TRX-9988
Invoice No: S-1001
TXT;

        $parsed = (new OcrTextParser)->parse($text, 'receipt');

        $this->assertSame('1250.50', $parsed['amount']);
        $this->assertSame('2026-10-15', $parsed['date']);
        $this->assertSame('TRX-9988', $parsed['reference']);
        $this->assertSame('S-1001', $parsed['invoice_number']);
    }

    public function test_normalizes_bangla_digits(): void
    {
        $text = "তারিখ: ০১/১১/২০২৬\nমোট: ৫০০.০০";
        $parsed = (new OcrTextParser)->parse($text, 'receipt');

        $this->assertSame('2026-11-01', $parsed['date']);
        $this->assertSame('500.00', $parsed['amount']);
    }

    public function test_parses_invoice_party_hint(): void
    {
        $text = "Invoice No: INV-44\nDate 22-10-2026\nRetailer: Corner Shop Ltd\nTotal 900.00";
        $parsed = (new OcrTextParser)->parse($text, 'invoice');

        $this->assertSame('INV-44', $parsed['invoice_number']);
        $this->assertSame('2026-10-22', $parsed['date']);
        $this->assertSame('Corner Shop Ltd', $parsed['party']);
        $this->assertSame('900.00', $parsed['amount']);
    }
}
