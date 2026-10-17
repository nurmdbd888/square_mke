<?php

namespace App\Support\Access;

use App\Models\Payment;
use App\Models\User;

final class PaymentAccess
{
    public static function canManage(User $user): bool
    {
        return $user->can('payments.manage');
    }

    public static function canCollectReceipts(User $user): bool
    {
        return $user->can('payments.manage') || $user->can('payments.collect');
    }

    public static function canWriteReceipt(User $user, ?Payment $payment = null): bool
    {
        if (! self::canCollectReceipts($user)) {
            return false;
        }

        if ($payment === null) {
            return true;
        }

        if ($payment->isVoid()) {
            return false;
        }

        if ($payment->isPayment()) {
            return self::canManage($user);
        }

        if ($payment->isPosted() && ! self::canManage($user)) {
            return false;
        }

        return true;
    }

    public static function canWritePayment(User $user, ?Payment $payment = null): bool
    {
        if (! self::canManage($user)) {
            return false;
        }

        if ($payment?->isVoid()) {
            return false;
        }

        return true;
    }
}
