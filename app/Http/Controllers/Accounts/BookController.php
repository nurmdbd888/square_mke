<?php

namespace App\Http\Controllers\Accounts;

use App\Domain\Payments\Books;
use App\Domain\Payments\DailyCloser;
use App\Domain\Payments\DailyClosingWhatsAppReport;
use App\Http\Controllers\Controller;
use App\Models\DailyClosing;
use App\Support\Http\BusinessDaysFilter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use RuntimeException;

class BookController extends Controller
{
    public function __construct(
        private readonly Books $books,
        private readonly DailyCloser $closer,
        private readonly DailyClosingWhatsAppReport $closingReport,
    ) {}

    public function cash(Request $request): View
    {
        return $this->showBook($request, 'cash');
    }

    public function bank(Request $request): View
    {
        return $this->showBook($request, 'bank');
    }

    public function day(Request $request): View
    {
        [$from, $to, $businessDaysOnly] = $this->range($request);
        $book = $this->books->dayBook($from, $to, $businessDaysOnly);

        return view('accounts.day-book', [
            'from' => $from,
            'to' => $to,
            'book' => $book,
            'businessDaysOnly' => $businessDaysOnly,
            'canManage' => $request->user()->can('accounts.manage'),
        ]);
    }

    public function closings(Request $request): View
    {
        $closings = DailyClosing::query()->orderByDesc('closed_on')->paginate(50);

        return view('accounts.closings', [
            'closings' => $closings,
            'canManage' => $request->user()->can('accounts.manage'),
            'previewDate' => $request->input('date', now()->toDateString()),
        ]);
    }

    public function close(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'closed_on' => ['required', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $closing = $this->closer->close($data['closed_on'], $request->user(), $data['notes'] ?? null);
        $reportSent = false;
        try {
            $reportSent = $this->closingReport->send($closing);
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            Log::error('Day-end WhatsApp report could not be sent.', [
                'daily_closing_id' => $closing->id,
                'error' => $exception->getMessage(),
            ]);
        }

        $redirect = redirect()->route('accounts.closings')
            ->with('status', __('Day :date was closed.', ['date' => $closing->closed_on->format('d-m-Y')]));

        if ($reportSent) {
            return $redirect->with('status', __('Day :date was closed and the WhatsApp report was sent.', ['date' => $closing->closed_on->format('d-m-Y')]));
        }
        if (trim((string) setting('day_end_whatsapp_recipient')) !== '') {
            return $redirect->with('warning', __('The day was closed, but the WhatsApp report could not be sent. Check the WhatsApp credentials and server logs.'));
        }

        return $redirect;
    }

    private function showBook(Request $request, string $type): View
    {
        [$from, $to, $businessDaysOnly] = $this->range($request);
        $book = $type === 'cash'
            ? $this->books->cashBook($from, $to, $businessDaysOnly)
            : $this->books->bankBook($from, $to, $businessDaysOnly);

        return view('accounts.book', [
            'type' => $type,
            'title' => $type === 'cash' ? __('Cash book') : __('Bank book'),
            'from' => $from,
            'to' => $to,
            'book' => $book,
            'businessDaysOnly' => $businessDaysOnly,
        ]);
    }

    /**
     * @return array{0: string, 1: string, 2: bool}
     */
    private function range(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'business_days' => ['nullable', 'boolean'],
        ]);

        $to = $data['to'] ?? now()->toDateString();
        $from = $data['from'] ?? $to;

        return [$from, $to, BusinessDaysFilter::fromRequest($request)];
    }
}
