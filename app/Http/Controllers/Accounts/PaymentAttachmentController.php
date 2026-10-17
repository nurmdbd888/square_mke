<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentAttachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentAttachmentController extends Controller
{
    public function show(Payment $payment, PaymentAttachment $attachment): StreamedResponse
    {
        abort_unless(request()->user()?->can('payments.view'), 403);
        abort_if($attachment->payment_id !== $payment->id, 404);

        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404);

        return $disk->response(
            $attachment->path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime ?: 'application/octet-stream']
        );
    }
}
