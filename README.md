OverDrive PHP Client
=======================

The OverDrive PHP Client provides an easy way to use overdrive.com to query library and patron information, place checkouts and holds, check availability, and download titles. OverDrive did an exceptional job making a robust, easy to use api, but they didn't provide a reference client library. You can use this library in your PHP based discovery layer, or just treat it as a starting point for a project in another language.

This client is fairly feature complete, but it isn't perfect. Desired enhancements could include additional exception cases and perhaps async methods that return promises. That was the originally intended path, but the client proved to be pretty performant even with serial calls, so that work was never completed. 

There are also many small features that might be useful to some organizations, but were never added due to lack of need. For instance, the patron's password is not currently used because most organizations ask OverDrive to ignore the pin/password. Search is also just barely stubbed out because it's expected that you are doing your own searching locally. 

If you'd like to help out and submit a pull request, it would be welcome. 

Additional test methods would also be welcome. Currently testing consists of a small functional suite that serves to spot check features and demonstrate the basic use of the client.

## Using the Client
The library is broken up into a "Library Client" and a "Patron Client". The library client queries basic item information like the number of copies owned and the number available, the formats available for an item, and meta-data for an item like description and cover images.
```php
// Connect to Memcache:
$cache = new \Memcached\Wrapper("defaultPool", array(array("localhost", 11211)));

//Instantiate Library Client            
$client = new OverDriveLibraryAPIClient(
            new \GuzzleHttp\Client(),
            $libraryAuthUrlBase, //https://oauth.overdrive.com
            $libraryAPIUrlBase, //http://api.overdrive.com
            //Provided by OverDrive. Identifies the collection owned by the library. 
            //It appears to be technically possible for a library to have more than one collection with OverDrive.
            $collectionId, 
            $cache);
//Login() stores an access token for subsequent calls. Automatically handles timeout.
$client->login($clientKey, $clientSecret);
$totalCopies = $client->getTotalCopies($itemId);
$numAvailable = $client->getAvailable($itemId);
```

The patron client (OverDrivePatronAPIClient) extends the library client (OverDriveLibraryAPIClient). It needs all the standard configuration for the library client and additionally requires the patron's barcode and an email address. The patron client can checkout and return titles, create and release holds, and download checked out titles.

```php

//Instantiate Patron Client            
$client = new OverDrivePatronAPIClient(
            new \GuzzleHttp\Client(),
            $patronAuthUrlBase,
            $patronAPIUrlBase,
            $libraryAuthUrlBase,
            $libraryAPIUrlBase,
            $collectionId,
            $websiteId,
            $ilsId,
            $notificationEmail,
            $cache);
//Login() stores an access token for subsequent calls. Automatically handles timeout.
$client->login($clientKey, $clientSecret);
$totalCopies = $client->getTotalCopies($itemId);
$numAvailable = $client->getAvailable($itemId);
$loanOptionsCollection = $client->getLoanOptions($itemId);
$loanOption = $loanOptionsCollection->getLoanOptions()[0]; //Format choice is unimportant for holds. Just take the first
$hold = $client->holdItem($loanOption)
```

A "Factory" class is also included in the package largely as a reference. It depends on the global $config and $cache that was available in an experimental VuFind branch. It may be useful in your project with minor tweaks.

## Installing the client

The recommended method is to use composer
[Composer](http://getcomposer.org). 

```bash
# Install Composer
curl -sS https://getcomposer.org/installer | php
```

Next, run the Composer command to install the client:

```bash
composer.phar require joshbannon/overdrive-client
```

After installing, you need to require Composer's autoloader:

```php
require 'vendor/autoload.php';
```

## Acknowledgments
This package was based on the Douglas County Libraries' experimental e-content-enabled VuFind project. It would not have been possible without their support.

The OverDrive client depends on a number of other packages. In particular it uses the excellent Guzzle package to handle HTTP connections. I found the Guzzle project to be useful not only for its fantastic capabilities, but also as a guide to project structure. PHP is not really my forte these days, and I made heavy use of their readme.md and composer.json as a template for my own project.

## Code Quality Analysis
I thought this was a really useful tool, so I'll leave a link here to help you decide whether to use this library in your project. It rightfully points out that I have a bunch of debug helpers commented out, and that I have a number of TODO items.

[![SensioLabsInsight](https://insight.sensiolabs.com/projects/db29c25a-3267-4c29-9637-3a3e0aefb421/mini.png)](https://insight.sensiolabs.com/projects/db29c25a-3267-4c29-9637-3a3e0aefb421)