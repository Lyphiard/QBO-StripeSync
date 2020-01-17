<?php

use Stripe\BalanceTransaction;
use Stripe\Stripe;
use StripeSync\Config;
use StripeSync\Handler\Handler;
use StripeSync\Logger;
use StripeSync\OAuth;
use StripeSync\QBOValidator;

require_once __DIR__ . '/vendor/autoload.php';

// Setup OAuth authorization
OAuth::authorize();

// Validate configuration data from config.php
QBOValidator::validate();

// Setup Stripe API
Stripe::setApiKey(Config::get('stripe/secretKey'));
Stripe::setApiVersion('2016-07-06');

$hasMoreTransactions = true;
$startingAfter = null;
$retries = 0;
$pageSize = 20;

while ($hasMoreTransactions) {
    Logger::debug("Fetching {$pageSize} transactions from Stripe, beginning after '{$startingAfter}' ... ");
    $transactions = BalanceTransaction::all([
        'limit' => $pageSize,
        'starting_after' => $startingAfter
    ]);

    if (!@$transactions->data) {
        $retries++;

        if ($retries > 5) {
            Logger::error('Stripe transaction fetch failed. $startingAfter = ' . $startingAfter);
            die();
        } else {
            Logger::debug('Stripe transaction fetch failed. Retrying (' . $retries . ' of 5) ... ');
            $hasMoreTransactions = true;
            continue;
        }
    } else {
        $retries = 0;
    }

    Logger::debug('Successfully fetched ' . count($transactions->data) . ' transactions from Stripe.');
    Logger::debug('');

    $hasMoreTransactions = @$transactions->has_more;
    $startingAfter = @$transactions->data[count($transactions->data) - 1]->id;

    /** @var BalanceTransaction $transaction */
    foreach ($transactions->data as $transaction) {
        $error = Handler::processTransaction($transaction);

        if ($error === false) {
            Logger::debug("Successfully processed transaction $transaction->id");
        } else {
            Logger::softError("!!! Unable to process transaction {$transaction->id}: {$error}");
        }

        Logger::debug('');
    }
}
