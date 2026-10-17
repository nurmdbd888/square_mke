<?php

namespace App\Http\Controllers\Documents;

use App\Domain\Print\DocumentQr;
use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DocumentPrintController extends Controller
{
    public function __construct(private readonly DocumentQr $qr) {}

    public function invoiceA4(Request $request, Document $document): View
    {
        $this->authorizePrint($request, $document);
        abort_unless($document->isSale(), 404);

        return $this->renderBranded($request, $document, __('Tax invoice'));
    }

    public function challanA4(Request $request, Document $document): View
    {
        $this->authorizePrint($request, $document);

        return $this->renderBranded($request, $document, __('Delivery challan'));
    }

    public function thermal80(Request $request, Document $document): View
    {
        $this->authorizePrint($request, $document);
        abort_unless($document->isSale(), 404);

        return $this->render($document, 'print.thermal-80mm', __('Receipt'));
    }

    private function render(Document $document, string $view, string $docTitle): View
    {
        $document->load(['lines.product', 'supplier', 'retailer', 'salesRep', 'route', 'vehicle']);

        return view($view, [
            'document' => $document,
            'docTitle' => $docTitle,
            'firmName' => setting('firm_name'),
            'distributorCode' => setting('distributor_code'),
            'qrSvg' => $this->qr->forDocument($document, $view === 'print.thermal-80mm' ? 96 : 120),
        ]);
    }

    private function renderBranded(Request $request, Document $document, string $docTitle): View
    {
        $document->load(['lines.product', 'supplier', 'retailer', 'salesRep', 'route', 'vehicle']);
        $size = $request->query('size', (string) setting('default_print_size', 'a5'));
        if (! in_array($size, ['a5', 'a4'], true)) {
            $size = 'a5';
        }

        return view('print.invoice-branded', [
            'document' => $document,
            'docTitle' => $docTitle,
            'firmName' => setting('firm_name'),
            'distributorCode' => setting('distributor_code'),
            'printSize' => $size,
            'qrSvg' => $this->qr->forDocument($document, $size === 'a4' ? 120 : 100),
        ]);
    }

    private function authorizePrint(Request $request, Document $document): void
    {
        $permission = $document->isPurchase() ? 'purchases.view' : 'sales.view';
        abort_unless($request->user()?->can($permission), 403);
        abort_if($document->isVoid(), 404);
    }
}
