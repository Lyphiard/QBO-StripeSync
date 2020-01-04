<?php

namespace StripeSync;

class QBOValidator
{

    public static function validate()
    {
        Logger::debug('Beginning validation of QBO configuration ... ');

        self::validateEntity('PaymentMethod', 'Name', 'qbo/data/paymentMethod');
        self::validateEntity('Item', 'Name', 'qbo/data/item');
        self::validateEntity('Item', 'Name', 'qbo/data/refundItem');
        self::validateEntity('Vendor', 'DisplayName', 'qbo/data/vendor');

        self::validateEntity('Account', 'Name', 'qbo/data/accounts/depositBank');
        self::validateEntity('Account', 'Name', 'qbo/data/accounts/stripeBank');
        self::validateEntity('Account', 'Name', 'qbo/data/accounts/stripeFees');
        self::validateEntity('Account', 'Name', 'qbo/data/accounts/undepositedFunds');
        self::validateEntity('Account', 'Name', 'qbo/data/accounts/accountsPayable');

        Logger::debug('Validation of QBO has been successfully completed.');
    }

    private static function validateEntity($entity, $nameField, $configurationPath)
    {
        $dataService = OAuth::getDataService();

        $data = $dataService->FindById($entity, Config::get("{$configurationPath}/value"));

        if (!$data || $data->$nameField != Config::get("{$configurationPath}/name")) {
            Logger::error('Failed to validate ' . $configurationPath);
        } else {
            Logger::debug('Successfully validated ' . $configurationPath);
        }
    }

}