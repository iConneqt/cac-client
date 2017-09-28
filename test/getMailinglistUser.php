<?php

/*
 * 1. Prepare the `config.ini` file with your authentication details
 * 2. Make sure `composer` has created a `vendor` directory.
 */

require_once '../vendor/autoload.php';

$config = parse_ini_file('config.ini', true, INI_SCANNER_RAW);

$Client = new \CAC\Component\ESP\Api\Engine\EngineApi($config);

$result = $Client->getMailinglistUser($config['mailinglist'], $config['recipient']['email']);
var_dump($result);