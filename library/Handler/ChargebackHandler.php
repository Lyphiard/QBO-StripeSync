<?php

namespace StripeSync\Handler;

use Stripe\BalanceTransaction;
use StripeSync\Logger;

class ChargebackHandler extends AdjustmentHandler
{

    public function handle(BalanceTransaction $transaction)
    {
        if ($transaction->amount > 0 || $transaction->fee < 0) {
            Logger::softError('Unexpected transaction amount/fee for txn ' . $transaction->id . ": amnt = {$transaction->amount}, fee = {$transaction->fee}");
            return 'unexpected data handle';
        }

        $purchase = $this->getPurchase($transaction);

        if ($purchase) {
            Logger::debug("Purchase for transaction {$transaction->id} already processed. Skipping.");
        } else {
            $vendorCredit = $this->createPurchase($transaction, $transaction->fee);

            if (!$vendorCredit) {
                return 'unable to create purchase (26)';
            }
        }

        $refundReceipt = $this->getRefundReceipt($transaction);

        if ($refundReceipt) {
            Logger::debug("RefundReceipt for transaction {$transaction->id} already processed. Skipping.");
        } else {
            $refundReceipt = $this->createRefundReceipt($transaction, -$transaction->amount);

            if (!$refundReceipt) {
                return 'unable to create refund receipt (38)';
            }
        }

        return false;
    }

}