<?php

$conf = \Drupal\salesforce_jwt\Entity\JWTAuthConfig::load('calevip');
print_r($conf);

$cred = new \Drupal\salesforce_auth\Consumer\JWTCredentials($conf->getConsumerKey(), $conf->getLoginUser(), $conf->getLoginUrl(), $conf->getEncryptKey());
$auth = new \Drupal\salesforce_auth\Service\SalesforceAuthServiceJWT($cred, new \OAuth\Common\Http\Client\CurlClient(), new \Drupal\salesforce_auth\Storage\SalesforceAuthTokenStorage());
$token = $auth->requestAccessToken('');

print_r($token);