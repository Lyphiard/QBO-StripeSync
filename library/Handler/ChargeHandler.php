<?php

namespace StripeSync\Handler;

use QuickBooksOnline\API\Facades\Deposit;
use QuickBooksOnline\API\Facades\Purchase;
use QuickBooksOnline\API\Facades\SalesReceipt;
use Stripe\BalanceTransaction;
use StripeSync\Config;
use StripeSync\Logger;
use StripeSync\OAuth;

class ChargeHandler extends Handler
{

    public function handle(BalanceTransaction $transaction)
    {
        $saleReceipt = $this->getSaleReceipt($transaction);

        if ($saleReceipt) {
            Logger::debug("SaleReceipt found for transaction {$transaction->id}. Verifying presence of a Purchase and a Deposit ... ");

            if (@$saleReceipt->LinkedTxn) {
                $linkedTxn = $saleReceipt->LinkedTxn;

                if (is_array($linkedTxn)) {
                    $linkedTxn = $linkedTxn[0];
                }

                if ($linkedTxn->TxnType == 'Deposit') {
                    Logger::debug("Purchase & Deposit found for transaction {$transaction->id}. Moving on to next transaction.");

                    return false;
                }
            }

            $purchase = $this->getPurchase($transaction);

            if ($purchase) {
                Logger::debug("Purchase found for transaction {$transaction->id}. Will generate deposit.");

                return $this->createDeposit($transaction, $saleReceipt, $purchase) ? false : 'create deposit failed (30)';
            } else {
                Logger::debug("Purchase not found for transaction {$transaction->id}. Creating purchase and deposit.");

                $purchase = $this->createPurchase($transaction);

                if (!$purchase) {
                    Logger::softError("Purchase creation failed for {$transaction->id}. Not going to create deposit. Skipping.");

                    return 'purchase creation failed (39)';
                }

                return $this->createDeposit($transaction, $saleReceipt, $purchase) ? false : 'create deposit failed (42)';
            }
        } else {
            $saleReceipt = $this->createSaleReceipt($transaction);

            if (!$saleReceipt) {
                Logger::softError("Unable to create SaleReceipt for transaction {$transaction->id}. Skipping transaction.");

                return 'create sale receipt failed (50)';
            }

            $purchase = $this->createPurchase($transaction);

            if (!$purchase) {
                Logger::softError("Unable to create Purchase for transaction {$transaction->id}. Skipping transaction.");

                return 'create purchase failed (58)';
            }

            return $this->createDeposit($transaction, $saleReceipt, $purchase) ? false : 'create deposit failed (61)';
        }
    }

    private function getSaleReceipt($transaction)
    {
        $md5Reference = $this->generateMd5Reference($transaction);
        $txnDate = date('Y-m-d', $transaction->created);

        return @OAuth::queryDataService("
            SELECT *
            FROM SalesReceipt
            WHERE PaymentRefNum = '{$md5Reference}'
                AND TxnDate = '{$txnDate}'
        ")[0];
    }

    private function getPurchase($transaction)
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

    private function createSaleReceipt($transaction)
    {
        return @OAuth::addToDataService(SalesReceipt::create([
            'Line' => [
                [
                    'Description' => $transaction->description,
                    'DetailType' => 'SalesItemLineDetail',
                    'SalesItemLineDetail' => [
                        'TaxCodeRef' => ['value' => 'NON'],
                        'Qty' => 1,
                        'UnitPrice' => $transaction->amount / 100.0,
                        'ItemRef' => Config::get('qbo/data/item'),
                    ],
                    'LineNum' => 1,
                    'Amount' => $transaction->amount / 100.0,
                    'Id' => 1,
                ]
            ],
            'TotalAmt' => $transaction->amount / 100.0,
            'Balance' => 0,
            'PaymentMethodRef' => Config::get('qbo/data/paymentMethod'),
            'PaymentRefNum' => $this->generateMd5Reference($transaction),
            'PrivateNote' => "Transaction ID: {$transaction->id} \nSource ID: {$transaction->source}",
            'DepositToAccountRef' => Config::get('qbo/data/accounts/undepositedFunds'),
            'TxnDate' => date('Y-m-d', $transaction->created),
        ]));
    }

    private function createPurchase($transaction)
    {
        $vendor = Config::get('qbo/data/vendor');
        unset($vendor['name']);

        return @OAuth::addToDataService(Purchase::create([
            'AccountRef' => Config::get('qbo/data/accounts/undepositedFunds'),
            'PaymentMethodRef' => Config::get('qbo/data/paymentMethod'),
            'PaymentType' => 'Cash',
            'EntityRef' => $vendor,
            'TotalAmt' => $transaction->fee / 100.0,
            'DocNumber' => $this->generateMd5Reference($transaction),
            'PrivateNote' => "Transaction ID: {$transaction->id} \nSource ID: {$transaction->source}",
            'TxnDate' => date('Y-m-d', $transaction->created),
            'Line' => [
                [
                    'Id' => 1,
                    'Description' => 'Stripe Fees',
                    'Amount' => $transaction->fee / 100.0,
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

    private function createDeposit($transaction, $salesReceipt, $purchase)
    {
        return @OAuth::addToDataService(Deposit::create([
            'Line' => [
                [
                    'Amount' => $transaction->amount / 100.0,
                    'LinkedTxn' => [
                        [
                            'TxnId' => $salesReceipt->Id,
                            'TxnType' => 'SalesReceipt',
                            'TxnLineId' => 0
                        ]
                    ]
                ],
                [
                    'Amount' => $transaction->fee / -100.0,
                    'LinkedTxn' => [
                        [
                            'TxnId' => $purchase->Id,
                            'TxnType' => 'Purchase',
                            'TxnLineId' => 0
                        ]
                    ]
                ]
            ],
            'DepositToAccountRef' => Config::get('qbo/data/accounts/stripeBank'),
            'TxnDate' => date('Y-m-d', $transaction->created),
            'PrivateNote' => "Transaction ID: {$transaction->id} \nSource ID: {$transaction->source}",
        ]));
    }

}