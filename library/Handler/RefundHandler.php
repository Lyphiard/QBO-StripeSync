<?php

namespace StripeSync\Handler;

use Stripe\BalanceTransaction;
use StripeSync\Logger;

class RefundHandler extends AdjustmentHandler
{

    public function handle(BalanceTransaction $transaction)
    {
        if ($transaction->amount > 0 || $transaction->fee > 0) {
            Logger::softError('Unexpected transaction amount/fee for txn ' . $transaction->id . ": amnt = {$transaction->amount}, fee = {$transaction->fee}");
            return 'unexpected data handle';
        }

        $vendorCredit = $this->getVendorCredit($transaction);

        if ($vendorCredit) {
            Logger::debug("Fee refund for transaction {$transaction->id} already processed. Skipping.");
        } else {
            $vendorCredit = $this->createVendorCredit($transaction, -$transaction->fee);

            if (!$vendorCredit) {
                return 'unable to create vendor credit (26)';
            }
        }

        $deposit = $this->getDeposit($transaction);

        if ($deposit) {
            Logger::debug("Deposit for transaction {$transaction->id} already processed. Skipping.");
        } else {
            $deposit = $this->createDeposit($transaction, -$transaction->fee);

            if (!$deposit) {
                return 'unable to create deposit (38)';
            }
        }

        $refundReceipt = $this->getRefundReceipt($transaction);

        if ($refundReceipt) {
            Logger::debug("RefundReceipt for transaction {$transaction->id} already processed. Skipping.");
        } else {
            $refundReceipt = $this->createRefundReceipt($transaction, -$transaction->amount);

            if (!$refundReceipt) {
                return 'unable to create refund receipt (50)';
            }
        }

        return false;
    }

}