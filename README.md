# iConneqt compatibility layer for Crazy Awesome ESP E-Ngine API Client

Drop-in replacement for the `Crazy Awesome ESP E-Ngine API Client` component.
Connect to iConneqt REST API, mimicking deprecated E-Ngine behaviour.

## Information ##
 - [Crazy Awesome ESP E-Ngine API Client](https://github.com/CrazyAwesomeCompany/esp-api-engine)
 - [iConneqt](https://iconneqt.nl/)
 - [iConneqt REST API documentation](https://demo.iconneqt.nl/api/docs/)
 - [iConneqt PHP REST API client](https://github.com/iConneqt/PHP-REST-API-client)

## Installation ##
Preferred way of installing is though [Composer](http://getcomposer.org).

	composer require iconneqt/rest-api-client

Or add the following item to the `require` section of your `composer.json` file.

    "iconneqt/cac-client": "*"

Update composer using `composer update`.

This component requires the iConneqt PHP REST API client, which should be
automatically installed when updating composer.

## API Configuration ##
The Adapter uses the iConneqt REST API to emulate behaviour of the E-ngine SOAP
Webservice.
When creating the `EngineApi` class some configuration is needed;

 + `domain` - The domain where iConneqt is availabe. (e.g. `crm01.iconneqt.nl`)
 + `user` - Your iConneqt user name
 + `password` - Your iConneqt password or API token (contact iConneqt).

## Compatibility notes ##
### Mailinglist ID ###
Although strongly recommended, the E-ngine SOAP API did not require mailinglist
ID's to be provided before sending mailings to recipients. iConneqt requires the
mailinglist ID to be selected before creating a new mailing.
You can set the mailinglist ID through configuration during construction or by
calling the `selectMailingList()` method.

### Logger ###
Support for the PSR logger is removed.
