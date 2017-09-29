<?php

/*
 * 1. Prepare the `config.ini` file with your authentication details
 * 2. Make sure `composer` has created a `vendor` directory.
 */

require_once '../vendor/autoload.php';

$config = parse_ini_file('config.ini', true, INI_SCANNER_RAW);

$Client = new \CAC\Component\ESP\Api\Engine\EngineApi($config);

$deliveryid = $Client->createMailingFromTemplate($config['testdata']['templateid'], 'Subject: <!--[[if $fields["discount"] > 0]]-->Discounted!<!--[[/if]]-->,  <!--[[if $fields["bonus"] > 0]]-->With &euro; %%bonus%% bonus<!--[[else]]-->No bonus<!--[[/if]]-->.', 'John Doe', 'johndoe@example.test');
var_dump('Delivery ID', $deliveryid);

$recipientid = $Client->sendMailing($deliveryid, [[
	'email' => $config['recipient']['email'],
	'discount' => 3,
	'bonus' => 0,
		]]);
var_dump('Delivery recipient ID', $recipientid);