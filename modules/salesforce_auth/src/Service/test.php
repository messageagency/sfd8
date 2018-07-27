<?php

$conf = \Drupal\salesforce_jwt\Entity\JWTAuthConfig::load('calevip');
print_r($conf);

$cred = new \Drupal\salesforce_auth\Consumer\JWTCredentials($conf->getConsumerKey(), $conf->getLoginUser(), $conf->getLoginUrl(), $conf->getEncryptKey());
$auth = new \Drupal\salesforce_auth\Service\SalesforceJWT($cred, new \OAuth\Common\Http\Client\CurlClient(), new \Drupal\salesforce_auth\Storage\TokenStorage());
$token = $auth->requestAccessToken('');

print_r($token);