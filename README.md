# iConneqt compatibility layer for Crazy Awesome ESP E-Ngine API Client

Drop-in replacement for the `Crazy Awesome ESP E-Ngine API Client` component.
Connect to iConneqt REST API, mimicking deprecated E-Ngine behaviour.

## Information ##
 - [iConneqt](https://iconneqt.nl/)
 - [iConneqt REST API](https://demo.iconneqt.nl/api/docs/)

## Installation ##
Preferred way of installing is though [Composer](http://getcomposer.org). Add the following line to you `require`

    "iconneqt/cac-e-ngine-client": ">=v0.1"

## API Configuration ##
The Adapter uses the E-Ngine SOAP Webservice for communication. When creating the `EngineApi` class some configuration is needed

 + `domain` - The domain where E-Ngine is availabe. (e.g. `newsletter.yourdomain.com`)
 + `path` - Path to the SOAP entry point on the `domain`. (e.g. `/soap/server.live.php`)
 + `customer` - Your E-Ngine customer name
 + `user` - Your E-Ngine user name
 + `password` - Your E-Ngine password

## Todo ##
The API Client doesn't have all calls implemented at the moment. To use the latest version download the development version.
