<?php

namespace Tests\Feature\Payments;

use App\Domain\Documents\DocumentWriter;
use App\Domain\Money\Exact;
use App\Domain\Payments\Ageing;
use App\Domain\Payments\Books;
use App\Domain\Payments\DailyCloser;
use App\Domain\Payments\PartyBalance;
use App\Domain\Payments\PaymentWriter;
use App\Models\AuditLog;
use App\Models\DailyClosing;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Retailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_allocation_partial_and_advance_ageing(): void
    {
        $owner = $this->owner();
        $product = Product::query()->create(['name_en' => 'Soap', 'unit' => 'Pcs', 'buy_price' => '10', 'sell_price' => '20']);
        $retailer = Retailer::query()->create(['name' => 'Shop A', 'credit_limit' => '10000']);
        $docs = app(DocumentWriter::class);
        $payments = app(PaymentWriter::class);
        $balances = app(PartyBalance::class);
        $ageing = app(Ageing::class);

        $this->travelTo('2026-10-17 12:00:00');

        $inv1 = $docs->save('sale', [
            'number' => 'S-1', 'retailer_id' => $retailer->id, 'doc_date' => '2026-09-01', 'action' => 'post',
        ], [['product_id' => $product->id, 'qty' => '10', 'unit_price' => '100', 'line_value' => '1000']], $owner);

        $inv2 = $docs->save('sale', [
            'number' => 'S-2', 'retailer_id' => $retailer->id, 'doc_date' => '2026-09-20', 'action' => 'post',
        ], [['product_id' => $product->id, 'qty' => '5', 'unit_price' => '100', 'line_value' => '500']], $owner);

        $inv3 = $docs->save('sale', [
            'number' => 'S-3', 'retailer_id' => $retailer->id, 'doc_date' => '2026-10-10', 'action' => 'post',
        ], [['product_id' => $product->id, 'qty' => '8', 'unit_price' => '100', 'line_value' => '800']], $owner);

        // Partial: 600 of the 1000 on S-1.
        $payments->save([
            'kind' => 'receipt',
            'number' => 'R-1',
            'retailer_id' => $retailer->id,
            'method' => 'cash',
            'amount' => '600',
            'payment_date' => '2026-10-15',
            'action' => 'post',
        ], [
            ['document_id' => $inv1->id, 'amount' => '600'],
        ], $owner);

        // Advance: 200 with no allocation.
        $payments->save([
            'kind' => 'receipt',
            'number' => 'R-2',
            'retailer_id' => $retailer->id,
            'method' => 'bkash',
            'amount' => '200',
            'payment_date' => '2026-10-16',
            'action' => 'post',
        ], [], $owner);

        $balance = $balances->retailer($retailer->id, '2026-10-17');
        // 1000+500+800 = 2300 invoices; allocated 600; advance 200; balance 2300-600-200 = 1500
        $this->assertSame('2300.00', $balance['invoices']);
        $this->assertSame('600.00', $balance['allocated']);
        $this->assertSame('200.00', $balance['advances']);
        $this->assertSame('1500.00', $balance['balance']);

        $open = $balances->openInvoices('retailer', $retailer->id, '2026-10-17');
        $this->assertCount(3, $open);
        $byNumber = $open->keyBy('number');
        $this->assertSame('400.00', $byNumber['S-1']->remaining);
        $this->assertSame('500.00', $byNumber['S-2']->remaining);
        $this->assertSame('800.00', $byNumber['S-3']->remaining);

        $report = $ageing->forRetailer($retailer->id, '2026-10-17');
        // S-1: 46 days → 31-60 (400); S-2: 27 days → 0-30 (500); S-3: 7 days → 0-30 (800)
        $this->assertSame('1300.00', $report['buckets']['0-30']);
        $this->assertSame('400.00', $report['buckets']['31-60']);
        $this->assertSame('0.00', $report['buckets']['61-90']);
        $this->assertSame('0.00', $report['buckets']['90+']);
        $this->assertSame('1700.00', $report['total']);

        $this->assertTrue(
            AuditLog::query()->where('event', 'payment.posted')->where('auditable_type', 'payment')->exists()
        );
        $this->assertTrue(Payment::query()->where('number', 'R-2')->where('is_advance', true)->exists());
    }

    public function test_day_book_equals_cash_plus_bank_and_closing_carries_forward(): void
    {
        $owner = $this->owner();
        $product = Product::query()->create(['name_en' => 'Soap', 'unit' => 'Pcs', 'sell_price' => '20']);
        $retailer = Retailer::query()->create(['name' => 'Shop']);
        $docs = app(DocumentWriter::class);
        $payments = app(PaymentWriter::class);
        $books = app(Books::class);
        $closer = app(DailyCloser::class);

        $docs->save('sale', [
            'number' => 'S-D1', 'retailer_id' => $retailer->id, 'doc_date' => '2026-10-10', 'action' => 'post',
        ], [['product_id' => $product->id, 'qty' => '10', 'unit_price' => '100', 'line_value' => '1000']], $owner);

        $payments->save([
            'kind' => 'receipt', 'number' => 'RC-1', 'retailer_id' => $retailer->id,
            'method' => 'cash', 'amount' => '300', 'payment_date' => '2026-10-17', 'action' => 'post',
        ], [], $owner);

        $payments->save([
            'kind' => 'receipt', 'number' => 'RB-1', 'retailer_id' => $retailer->id,
            'method' => 'bank', 'amount' => '400', 'payment_date' => '2026-10-17', 'action' => 'post',
        ], [], $owner);

        $payments->save([
            'kind' => 'payment', 'number' => 'PX-1', 'method' => 'petty', 'amount' => '50',
            'payment_date' => '2026-10-17', 'action' => 'post',
            'expense_category_id' => \App\Models\ExpenseCategory::query()->where('key', 'fuel')->value('id'),
        ], [], $owner);

        $cash = $books->cashBook('2026-10-17', '2026-10-17');
        $bank = $books->bankBook('2026-10-17', '2026-10-17');
        $day = $books->dayBook('2026-10-17', '2026-10-17');

        $this->assertSame('300.00', $cash['in']);
        $this->assertSame('50.00', $cash['out']);
        $this->assertSame('400.00', $bank['in']);
        $this->assertSame('0.00', $bank['out']);
        $this->assertSame(Exact::money(Exact::of($cash['in'])->plus(Exact::of($bank['in']))), $day['in']);
        $this->assertSame(Exact::money(Exact::of($cash['out'])->plus(Exact::of($bank['out']))), $day['out']);
        $this->assertSame('700.00', $day['in']);
        $this->assertSame('50.00', $day['out']);

        $closing = $closer->close('2026-10-17', $owner);
        $this->assertSame('250.00', Exact::money($closing->cash_closing));
        $this->assertSame('400.00', Exact::money($closing->bank_closing));

        $next = $books->cashBook('2026-10-18', '2026-10-18');
        $this->assertSame('250.00', $next['opening']);
        $this->assertSame('250.00', $next['closing']);

        $this->assertTrue(
            AuditLog::query()->where('event', 'daily_closing.created')->where('auditable_type', 'daily_closing')->exists()
        );
        $this->assertSame(1, DailyClosing::query()->count());
    }

    public function test_pdc_counts_only_after_cleared(): void
    {
        $owner = $this->owner();
        $product = Product::query()->create(['name_en' => 'Soap', 'unit' => 'Pcs', 'sell_price' => '20']);
        $retailer = Retailer::query()->create(['name' => 'Shop']);
        $docs = app(DocumentWriter::class);
        $payments = app(PaymentWriter::class);
        $books = app(Books::class);
        $balances = app(PartyBalance::class);

        $inv = $docs->save('sale', [
            'number' => 'S-PDC', 'retailer_id' => $retailer->id, 'doc_date' => '2026-10-01', 'action' => 'post',
        ], [['product_id' => $product->id, 'qty' => '5', 'unit_price' => '100', 'line_value' => '500']], $owner);

        $pdc = $payments->save([
            'kind' => 'receipt',
            'number' => 'PDC-1',
            'retailer_id' => $retailer->id,
            'method' => 'pdc',
            'amount' => '500',
            'payment_date' => '2026-10-05',
            'due_date' => '2026-10-20',
            'action' => 'post',
        ], [
            ['document_id' => $inv->id, 'amount' => '500'],
        ], $owner);

        $this->assertSame(Payment::CLEARING_PENDING, $pdc->clearing_status);
        $this->assertFalse($pdc->countsInBooks());

        $bankBefore = $books->bankBook('2026-10-05', '2026-10-20');
        $this->assertSame('0.00', $bankBefore['in']);
        $this->assertSame('500.00', $balances->retailer($retailer->id, '2026-10-10')['balance']);

        $payments->clearPdc($pdc, $owner, '2026-10-18');
        $pdc->refresh();
        $this->assertSame(Payment::CLEARING_CLEARED, $pdc->clearing_status);
        $this->assertTrue($pdc->countsInBooks());
        $this->assertSame('2026-10-18', $pdc->bookDate());

        $bankAfter = $books->bankBook('2026-10-18', '2026-10-18');
        $this->assertSame('500.00', $bankAfter['in']);
        $this->assertSame('0.00', $balances->retailer($retailer->id, '2026-10-18')['balance']);

        $this->assertTrue(
            AuditLog::query()->where('event', 'payment.cleared')->where('auditable_type', 'payment')->exists()
        );
    }

    public function test_http_payment_and_ledger_pages(): void
    {
        $owner = $this->owner();
        $product = Product::query()->create(['name_en' => 'Soap', 'unit' => 'Pcs', 'sell_price' => '20']);
        $retailer = Retailer::query()->create(['name' => 'Shop HTTP', 'credit_limit' => '100']);
        $docs = app(DocumentWriter::class);

        $inv = $docs->save('sale', [
            'number' => 'HTTP-S1', 'retailer_id' => $retailer->id, 'doc_date' => '2026-10-01', 'action' => 'post',
        ], [['product_id' => $product->id, 'qty' => '5', 'unit_price' => '50', 'line_value' => '250']], $owner);

        $this->actingAs($owner)->post('/payments', [
            'kind' => 'receipt',
            'number' => 'HTTP-R1',
            'retailer_id' => $retailer->id,
            'method' => 'cash',
            'amount' => '100',
            'payment_date' => '2026-10-17',
            'action' => 'post',
            'allocations' => [
                ['document_id' => $inv->id, 'amount' => '100'],
            ],
        ])->assertRedirect();

        $this->actingAs($owner)->get('/payments')->assertOk()->assertSee('HTTP-R1');
        $this->actingAs($owner)->get('/accounts/receivables')->assertOk()->assertSee('Shop HTTP');
        $this->actingAs($owner)->get('/accounts/cash-book?from=2026-10-17&to=2026-10-17')->assertOk()->assertSee('HTTP-R1');
        $this->actingAs($owner)->get('/accounts/day-book?from=2026-10-17&to=2026-10-17')->assertOk();

        // Credit-limit warning on a further posted sale that pushes over the limit.
        $this->actingAs($owner)->post('/sales', [
            'number' => 'HTTP-S2',
            'retailer_id' => $retailer->id,
            'doc_date' => '2026-10-17',
            'action' => 'post',
            'lines' => [[
                'product_id' => $product->id,
                'qty' => '1',
                'unit_price' => '50',
                'line_value' => '50',
            ]],
        ])->assertRedirect()->assertSessionHas('warning');
    }
}
