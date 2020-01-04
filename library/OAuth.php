<?php

namespace StripeSync;

use QuickBooksOnline\API\Core\OAuth\OAuth2\OAuth2LoginHelper;
use QuickBooksOnline\API\DataService\DataService;

class OAuth
{

    private static $_dataService;

    private static $_accessToken;
    private static $_refreshToken;

    public static function authorize()
    {
        Logger::debug('Authorizing OAuth2 Application with Intuit QuickBooks ... ');
        self::$_refreshToken = trim(file_get_contents(__DIR__ . '/../oauth.key'));

        if (!self::$_refreshToken) {
            Logger::error('oauth.key does not contain refresh token.');
        }

        $oauth = new OAuth2LoginHelper(
            Config::get('qbo/oauth2/clientId'),
            Config::get('qbo/oauth2/clientSecret')
        );

        $data = $oauth->refreshAccessTokenWithRefreshToken(self::$_refreshToken);

        if (!$data || !$data->getAccessToken() || !$data->getRefreshToken()) {
            Logger::error('Unable to complete OAuth2 process.');
        }

        self::$_accessToken = $data->getAccessToken();
        self::$_refreshToken = $data->getRefreshToken();

        Logger::debug('Saving new oauth.key file ... ');
        file_put_contents(__DIR__ . '/../oauth.key', self::$_refreshToken);
    }

    /**
     * @return DataService
     */
    public static function getDataService()
    {
        if (self::$_dataService) {
            return self::$_dataService;
        }

        Logger::debug('Creating new instance of DataService ... ');

        self::$_dataService = DataService::Configure([
            'auth_mode' => 'oauth2',
            'ClientID' => Config::get('qbo/oauth2/clientId'),
            'ClientSecret' => Config::get('qbo/oauth2/clientSecret'),
            'accessTokenKey' => self::$_accessToken,
            'refreshTokenKey' => self::$_refreshToken,
            'QBORealmID' => Config::get('qbo/oauth2/realmId'),
            'baseUrl' => Config::get('qbo/oauth2/baseUrl'),
        ]);
        self::$_dataService->throwExceptionOnError(false);
        self::$_dataService->disableLog();

        return self::$_dataService;
    }

    public static function queryDataService($query, $startPosition = null, $maxResults = null)
    {
        $dataService = self::getDataService();
        $query = str_replace("\n", '', $query);
        $query = trim(preg_replace('/\s+/', ' ', $query));

        Logger::debug("Running query '{$query}' on DataService.");
        $data = $dataService->Query($query, $startPosition, $maxResults);

        $error = $dataService->getLastError();

        if (!$error) {
            return $data;
        }

        Logger::softError(
            'DataService Query Error: Code ' . @$error->getIntuitErrorCode()
            . ', Msg: ' . @$error->getResponseBody()
        );

        return null;
    }

    public static function addToDataService($entity)
    {
        $dataService = self::getDataService();
        $data = $dataService->Add($entity);
        $error = $dataService->getLastError();

        if (!$error) {
            Logger::info('Added entity type ' . get_class($entity) . ' to DataService');
            return $data;
        }

        Logger::softError(
            'DataService Add Error: Code ' . @$error->getIntuitErrorCode()
            . ', Msg: ' . @$error->getResponseBody()
        );

        return null;
    }

}