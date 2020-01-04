<?php

namespace StripeSync\Handler;

use QuickBooksOnline\API\Facades\Deposit;
use QuickBooksOnline\API\Facades\Purchase;
use QuickBooksOnline\API\Facades\RefundReceipt;
use QuickBooksOnline\API\Facades\VendorCredit;
use Stripe\BalanceTransaction;
use StripeSync\Config;
use StripeSync\OAuth;

class AdjustmentHandler extends Handler
{

    public function handle(BalanceTransaction $transaction)
    {
        if (strpos($transaction->description, 'Chargeback withdrawal for ') !== false) {
            return (new ChargebackHandler())->handle($transaction);
        }

        return "transaction type '{$transaction->type}' of amount {$transaction->amount} (fee: {$transaction->fee}) is not recognized";
    }

    protected function getRefundReceipt($transaction)
    {
        $md5Reference = $this->generateMd5Reference($transaction);
        $txnDate = date('Y-m-d', $transaction->created);

        return @OAuth::queryDataService("
            SELECT *
            FROM RefundReceipt
            WHERE DocNumber = '{$md5Reference}'
                AND TxnDate = '{$txnDate}'
        ")[0];
    }

    protected function createRefundReceipt($transaction, $amount)
    {
        return @OAuth::addToDataService(RefundReceipt::create([
            'DepositToAccountRef' => Config::get('qbo/data/accounts/stripeBank'),
            'DocNumber' => $this->generateMd5Reference($transaction),
            'TxnDate' => date('Y-m-d', $transaction->created),
            'PrivateNote' => "Transaction ID: {$transaction->id} \nSource ID: {$transaction->source}",
            'PrintStatus' => 'NotSet',
            'Line' => [
                [
                    'DetailType' => 'SalesItemLineDetail',
                    'Amount' => $amount / 100.0,
                    'Description' => $transaction->description,
                    'SalesItemLineDetail' => [
                        'ItemRef' => Config::get('qbo/data/refundItem'),
                        'TaxCodeRef' => ['value' => 'NON'],
                        'Qty' => 1,
                    ]
                ]
            ]
        ]));
    }

    protected function getVendorCredit($transaction)
    {
        $md5Reference = $this->generateMd5Reference($transaction);
        $txnDate = date('Y-m-d', $transaction->created);

        return @OAuth::queryDataService("
            SELECT *
            FROM VendorCredit
            WHERE DocNumber = '{$md5Reference}'
                AND TxnDate = '{$txnDate}'
        ")[0];
    }

    protected function createVendorCredit($transaction, $amount)
    {
        $vendor = Config::get('qbo/data/vendor');
        unset($vendor['type']);

        return @OAuth::addToDataService(VendorCredit::create([
            'VendorRef' => $vendor,
            'TotalAmt' => $amount / 100.0,
            'TxnDate' => date('Y-m-d', $transaction->created),
            'DocNumber' => $this->generateMd5Reference($transaction),
            'Line' => [
                [
                    'DetailType' => 'AccountBasedExpenseLineDetail',
                    'Description' => 'Stripe Fee Refund',
                    'Amount' => $amount / 100.0,
                    'Id' => 1,
                    'AccountBasedExpenseLineDetail' => [
                        'TaxCodeRef' => ['value' => 'NON'],
                        'BillableStatus' => 'NotBillable',
                        'AccountRef' => Config::get('qbo/data/accounts/stripeFees'),
                    ],
                ],
            ],
        ]));
    }

    protected function createPurchase($transaction, $amount)
    {
        $vendor = Config::get('qbo/data/vendor');
        unset($vendor['name']);

        return @OAuth::addToDataService(Purchase::create([
            'AccountRef' => Config::get('qbo/data/accounts/stripeBank'),
            'PaymentMethodRef' => Config::get('qbo/data/paymentMethod'),
            'PaymentType' => 'Cash',
            'EntityRef' => $vendor,
            'TotalAmt' => $amount / 100.0,
            'DocNumber' => $this->generateMd5Reference($transaction),
            'PrivateNote' => "Transaction ID: {$transaction->id} \nSource ID: {$transaction->source}",
            'TxnDate' => date('Y-m-d', $transaction->created),
            'Line' => [
                [
                    'Id' => 1,
                    'Description' => 'Stripe Fees',
                    'Amount' => $amount / 100.0,
                    'DetailType' => 'AccountBasedExpenseLineDetail',
                    'AccountBasedExpenseLineDetail' => [
                        'AccountRef' => Config::get('qbo/data/accounts/stripeFees'),
                        'BillableStatus' => 'NotBillable',
                        'TaxCodeRef' => ['value' => 'NON'],
                    ]
                ]
            ]
        ]));
    }

    protected function getPurchase($transaction)
    {
        $md5Reference = $this->generateMd5Reference($transaction);
        $txnDate = date('Y-m-d', $transaction->created);

        return @OAuth::queryDataService("
            SELECT *
            FROM Purchase
            WHERE DocNumber = '{$md5Reference}'
                AND TxnDate = '{$txnDate}'
        ")[0];
    }

    protected function createDeposit($transaction, $amount)
    {
        $vendor = Config::get('qbo/data/vendor');
        unset($vendor['name']);

        return @OAuth::addToDataService(Deposit::create([
            'Line' => [
                [
                    'Amount' => $amount / 100.0,
                    'DetailType' => 'DepositLineDetail',
                    'DepositLineDetail' => [
                        'AccountRef' => Config::get('qbo/data/accounts/accountsPayable'),
                        'Entity' => $vendor,
                        'PaymentMethodRef' => Config::get('qbo/data/paymentMethod'),
                    ]
                ]
            ],
            'DepositToAccountRef' => Config::get('qbo/data/accounts/stripeBank'),
            'TxnDate' => date('Y-m-d', $transaction->created),
            'PrivateNote' => "Transaction ID: {$transaction->id} \nMD5: " . $this->generateMd5Reference($transaction),
        ]));
    }

    protected function getDeposit($transaction)
    {
        $txnDate = date('Y-m-d', $transaction->created);

        $deposits = @OAuth::queryDataService("
            SELECT *
            FROM Deposit
            WHERE TxnDate = '{$txnDate}'
        ");

        if (!$deposits) {
            return null;
        }

        foreach ($deposits as $deposit) {
            if (strpos($deposit->PrivateNote, $transaction->id) !== false) {
                return $deposit;
            }
        }

        return null;
    }

}