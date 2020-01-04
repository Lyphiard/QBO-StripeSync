<?php

namespace StripeSync\Handler;

use QuickBooksOnline\API\DataService\DataService;
use Stripe\BalanceTransaction;
use StripeSync\Logger;

abstract class Handler
{

    private static $_handlers = [
        'charge' => ChargeHandler::class,
        'payment' => ChargeHandler::class,
        'transfer' => TransferHandler::class,
        'adjustment' => AdjustmentHandler::class,
        'refund' => RefundHandler::class,
    ];

    /**
     * @param BalanceTransaction $transaction
     * @return bool Whether the transaction has been uploaded correctly.
     */
    public static function processTransaction(BalanceTransaction $transaction)
    {
        Logger::debug("Transaction {$transaction->id} made on " . date('m/d/Y', $transaction->created) . " is of type '{$transaction->type}'");

        if ($transaction->status == 'pending') {
            Logger::debug("Transaction {$transaction->id} is pending. Skipping it for now.");
            return false;
        }

        foreach (self::$_handlers as $handlerName => $handlerClass) {
            if ($transaction->type == $handlerName) {
                /** @var Handler $handler */
                $handler = new self::$_handlers[$handlerName]();
                return $handler->handle($transaction);
            }
        }

        return "transaction type '{$transaction->type}' of amount {$transaction->amount} (fee: {$transaction->fee}) is not recognized";
    }


    /**
     * @param BalanceTransaction $transaction
     * @return bool Whether the transaction has been uploaded correctly.
     */
    public abstract function handle(BalanceTransaction $transaction);

    protected function generateMd5Reference($transaction)
    {
        return sprintf('md5_%s', substr(md5($transaction->id), 0, 17));
    }

}