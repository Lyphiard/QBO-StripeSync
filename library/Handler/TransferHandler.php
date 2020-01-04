<?php

namespace StripeSync\Handler;

use QuickBooksOnline\API\Facades\Transfer;
use Stripe\BalanceTransaction;
use StripeSync\Config;
use StripeSync\Logger;
use StripeSync\OAuth;

class TransferHandler extends Handler
{

    public function handle(BalanceTransaction $transaction)
    {
        $transfer = $this->getTransfer($transaction);

        if ($transfer) {
            Logger::debug('Transfer transaction ' . $transaction->id . ' already exists. Skipping.');

            return false;
        }

        return $this->createTransfer($transaction) ? false : 'create transfer failed';
    }

    private function getTransfer($transaction)
    {
        $txnDate = date('Y-m-d', $transaction->created);

        $transfers = @OAuth::queryDataService("
            SELECT *
            FROM Transfer
            WHERE TxnDate = '{$txnDate}'
        ");

        if (!$transfers) {
            return null;
        }

        $fromAccount = $transaction->amount < 0 ? 'stripeBank' : 'depositBank';
        $toAccount = $transaction->amount < 0 ? 'depositBank' : 'stripeBank';

        foreach ($transfers as $transfer) {
            if (
                @$transfer->FromAccountRef == Config::get("qbo/data/accounts/{$fromAccount}/value")
                && @$transfer->ToAccountRef == Config::get("qbo/data/accounts/{$toAccount}/value")
                && strpos(@$transfer->PrivateNote, $transaction->id) !== false
                && $transfer->Amount == abs($transaction->amount / 100.0)
            ) {
                return $transfer;
            }
        }

        return null;
    }

    private function createTransfer($transaction)
    {
        $fromAccount = $transaction->amount < 0 ? 'stripeBank' : 'depositBank';
        $toAccount = $transaction->amount < 0 ? 'depositBank' : 'stripeBank';

        return @OAuth::addToDataService(Transfer::create([
            'Amount' => abs($transaction->amount / 100.0),
            'ToAccountRef' => Config::get("qbo/data/accounts/{$toAccount}"),
            'FromAccountRef' => Config::get("qbo/data/accounts/{$fromAccount}"),
            'PrivateNote' => 'Transaction ID: ' . $transaction->id,
            'TxnDate' => date('Y-m-d', $transaction->created)
        ]));
    }

}