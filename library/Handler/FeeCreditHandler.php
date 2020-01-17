<?php

namespace StripeSync\Handler;

use Stripe\BalanceTransaction;
use StripeSync\Logger;

class FeeCreditHandler extends AdjustmentHandler
{

    public function handle(BalanceTransaction $transaction)
    {
        if ($transaction->amount <= 0 || $transaction->fee != 0) {
            Logger::softError('Unexpected transaction amount/fee for txn ' . $transaction->id . ": amnt = {$transaction->amount}, fee = {$transaction->fee}");
            return 'unexpected data handle';
        }

        $vendorCredit = $this->getVendorCredit($transaction);

        if ($vendorCredit) {
            Logger::debug("Fee refund for transaction {$transaction->id} already processed. Skipping.");
        } else {
            $vendorCredit = $this->createVendorCredit($transaction, $transaction->amount);

            if (!$vendorCredit) {
                return 'unable to create vendor fee credit (26)';
            }
        }

        $deposit = $this->getDeposit($transaction);

        if ($deposit) {
            Logger::debug("Deposit for transaction {$transaction->id} already processed. Skipping.");
        } else {
            $deposit = $this->createDeposit($transaction, $transaction->amount);

            if (!$deposit) {
                return 'unable to create deposit (38)';
            }
        }

        return false;
    }

}