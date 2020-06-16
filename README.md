# Moesif Slim Middlware

[![Built For][ico-built-for]][link-built-for]
[![Latest Version][ico-version]][link-package]
[![Total Downloads][ico-downloads]][link-downloads]
[![Software License][ico-license]][link-license]
[![Source Code][ico-source]][link-source]

[Source Code on GitHub](https://github.com/moesif/moesif-slim)

Middleware for PHP Slim Framework to automatically log API Calls and 
sends to [Moesif](https://www.moesif.com) for API analytics and log analysis

## How to install

Via Composer

```bash
$ composer require moesif/moesif-slim
```
or add 'moesif/moesif-slim' to your composer.json file accordingly.

## How to use

### Add Middleware

Add to the root level:

```php

use Slim\App;
use Slim\Factory\AppFactory;
use Moesif\Middleware\MoesifSlim;

// Create App instance
$app = AppFactory::create();

$moesifOptions = [
    'applicationId' => 'Your Moesif Application Id',
];

$app->addErrorMiddleware(true, true, true);

// Add Moesif Middleware
$middleware = new MoesifSlim($moesifOptions);
$app->add($middleware);
```

To track only certain routes, use route specific middleware setup.

### Setup config

Edit `config/moesif.php` file.

```php

// In config/moesif.php

return [
    //
    'applicationId' => 'Your Moesif Application Id',
    'logBody' => true,
];
```

Your Moesif Application Id can be found in the [_Moesif Portal_](https://www.moesif.com/).
After signing up for a Moesif account, your Moesif Application Id will be displayed during the onboarding steps. 

You can always find your Moesif Application Id at any time by logging 
into the [_Moesif Portal_](https://www.moesif.com/), click on the top right menu,
and then clicking _Installation_.

For other configuration options, see below.

## Configuration options

You can define Moesif configuration options in the `config/moesif.php` file. Some of these fields are functions. For options functions that take request and response as input arguments, the request and response objects passed in are [Request](https://www.php-fig.org/psr/psr-7/#321-psrhttpmessageserverrequestinterface) request or [Response](https://www.php-fig.org/psr/psr-7/#33-psrhttpmessageresponseinterface) objects.

#### __`applicationId`__
Type: `String`
Required, a string that identifies your application.

#### __`identifyUserId`__
Type: `($request, $response) => String`
Optional, a function that takes a $request and $response and return a string for userId.

```php

// In config/moesif.php

$identifyUserId = function($request, $response) {
    // Your custom code that returns a user id string
    return '12345';
};
```

```php
return [
  //
  'identifyUserId' => $identifyUserId
];
```

#### __`identifyCompanyId`__
Type: `($request, $response) => String`
Optional, a function that takes a $request and $response and return a string for companyId.

```php

// In config/moesif.php

$identifyCompanyId = function($request, $response) {
    # Your custom code that returns a company id string
    return '67890';
};
```

```php
return [
  //
  'identifyCompanyId' => $identifyCompanyId
];
```

#### __`identifySessionId`__
Type: `($request, $response) => String`
Optional, a function that takes a $request and $response and return a string for sessionId. Moesif automatically sessionizes by processing at your data, but you can override this via identifySessionId if you're not happy with the results.

#### __`getMetadata`__
Type: `($request, $response) => Associative Array`
Optional, a function that takes a $request and $response and returns $metdata which is an associative array representation of JSON.

```php

// In config/moesif.php

$getMetadata = function($request, $response) {
  return array("foo"=>"Slim Framework example", "boo"=>"custom data");
};

return [
  //
  'getMetadata' => $getMetadata
];

```

#### __`apiVersion`__
Type: `String`
Optional, a string to specifiy an API Version such as 1.0.1, allowing easier filters.

#### __`maskRequestHeaders`__
Type: `$headers => $headers`
Optional, a function that takes a $headers, which is an associative array, and
returns an associative array with your sensitive headers removed/masked.

```php
// In config/moesif.php

$maskRequestHeaders = function($headers) {
    $headers['password'] = '****';
    return $headers;
};

return [
  //
  'maskRequestHeaders' => $maskRequestHeaders
];
```

#### __`maskRequestBody`__
Type: `$body => $body`
Optional, a function that takes a $body, which is an associative array representation of JSON, and
returns an associative array with any information removed.

```php

// In config/moesif.php

$maskRequestBody = function($body) {
    // remove any sensitive information.
    $body['password'] = '****';
    return $body;
};

return [
  //
  'maskRequestBody' => $maskRequestBody
];
```

#### __`maskResponseHeaders`__
Type: `$headers => $headers`
Optional, same as above, but for Responses.

#### __`maskResponseBody`__
Type: `$body => $body`
Optional, same as above, but for Responses.

#### __`skip`__
Type: `($request, $response) => String`
Optional, a function that takes a $request and $response and returns true if
this API call should be not be sent to Moesif.

#### __`debug`__
Type: `Boolean`
Optional, If true, will print debug messages using Illuminate\Support\Facades\Log

#### __`logBody`__
Type: `Boolean`
Optional, Default true, Set to false to remove logging request and response body to Moesif.

## Update a Single User

Create or update a user profile in Moesif.
The metadata field can be any customer demographic or other info you want to store.
Only the `user_id` field is required.

```php
use Moesif\Middleware\MoesifSlim;

// Only userId is required.
// Campaign object is optional, but useful if you want to track ROI of acquisition channels
// See https://www.moesif.com/docs/api#users for campaign schema
// metadata can be any custom object
$user = array(
    "user_id" => "12345",
    "company_id" => "67890", // If set, associate user with a company object
    "campaign" => array(
        "utm_source" => "google",
        "utm_medium" => "cpc",
        "utm_campaign" => "adwords",
        "utm_term" => "api+tooling",
        "utm_content" => "landing"
    ),
    "metadata" => array(
        "email" => "john@acmeinc.com",
        "first_name" => "John",
        "last_name" => "Doe",
        "title" => "Software Engineer",
        "sales_info" => array(
            "stage" => "Customer",
            "lifetime_value" => 24000,
            "account_owner" => "mary@contoso.com"
        )
    )
);

$middleware = new MoesifSlim(['applicationId' => 'Your Moesif Application Id']);
$middleware->updateUser($user);
```

The `metadata` field can be any custom data you want to set on the user. The `user_id` field is required.

## Update Users in Batch

Similar to updateUser, but used to update a list of users in one batch. 
Only the `user_id` field is required.

```php
use Moesif\Middleware\MoesifSlim;

$userA = array(
    "user_id" => "12345",
    "company_id" => "67890", // If set, associate user with a company object
    "campaign" => array(
        "utm_source" => "google",
        "utm_medium" => "cpc",
        "utm_campaign" => "adwords",
        "utm_term" => "api+tooling",
        "utm_content" => "landing"
    ),
    "metadata" => array(
        "email" => "john@acmeinc.com",
        "first_name" => "John",
        "last_name" => "Doe",
        "title" => "Software Engineer",
        "sales_info" => array(
            "stage" => "Customer",
            "lifetime_value" => 24000,
            "account_owner" => "mary@contoso.com"
        )
    )
);

$userB = array(
    "user_id" => "1234",
    "company_id" => "6789", // If set, associate user with a company object
    "campaign" => array(
        "utm_source" => "google",
        "utm_medium" => "cpc",
        "utm_campaign" => "adwords",
        "utm_term" => "api+tooling",
        "utm_content" => "landing"
    ),
    "metadata" => array(
        "email" => "john@acmeinc.com",
        "first_name" => "John",
        "last_name" => "Doe",
        "title" => "Software Engineer",
        "sales_info" => array(
            "stage" => "Customer",
            "lifetime_value" => 24000,
            "account_owner" => "mary@contoso.com"
        )
    )
);

$users = array($userA, $userB);

$middleware = new MoesifSlim(['applicationId' => 'Your Moesif Application Id']);
$middleware->updateUsersBatch($users);
```

The `metadata` field can be any custom data you want to set on the user. The `user_id` field is required.

## Update a Single Company

Create or update a company profile in Moesif.
The metadata field can be any company demographic or other info you want to store.
Only the `company_id` field is required.

```php
use Moesif\Middleware\MoesifSlim;

// Only companyId is required.
// Campaign object is optional, but useful if you want to track ROI of acquisition channels
// See https://www.moesif.com/docs/api#update-a-company for campaign schema
// metadata can be any custom object
$company = array(
    "company_id" => "67890",
    "company_domain" => "acmeinc.com", // If domain is set, Moesif will enrich your profiles with publicly available info 
    "campaign" => array(
        "utm_source" => "google",
        "utm_medium" => "cpc",
        "utm_campaign" => "adwords",
        "utm_term" => "api+tooling",
        "utm_content" => "landing"
    ),
    "metadata" => array(
        "org_name" => "Acme, Inc",
        "plan_name" => "Free",
        "deal_stage" => "Lead",
        "mrr" => 24000,
        "demographics" => array(
            "alexa_ranking" => 500000,
            "employee_count" => 47
        )
    )
);

$middleware = new MoesifSlim(['applicationId' => 'Your Moesif Application Id']);
$middleware->updateCompany($company);
```

The `metadata` field can be any custom data you want to set on the company. The `company_id` field is required.

## Update Companies in Batch

Similar to update_company, but used to update a list of companies in one batch. 
Only the `company_id` field is required.

```php
use Moesif\Middleware\MoesifSlim;

$companyA = array(
    "company_id" => "67890",
    "company_domain" => "acmeinc.com", // If domain is set, Moesif will enrich your profiles with publicly available info 
    "campaign" => array(
        "utm_source" => "google",
        "utm_medium" => "cpc",
        "utm_campaign" => "adwords",
        "utm_term" => "api+tooling",
        "utm_content" => "landing"
    ),
    "metadata" => array(
        "org_name" => "Acme, Inc",
        "plan_name" => "Free",
        "deal_stage" => "Lead",
        "mrr" => 24000,
        "demographics" => array(
            "alexa_ranking" => 500000,
            "employee_count" => 47
        )
    )
);

$companies = array($companyA);

$middleware = new MoesifSlim(['applicationId' => 'Your Moesif Application Id']);
$middleware->updateCompaniesBatch($companies);
```
The `metadata` field can be any custom data you want to set on the company. The `company_id` field is required.

## An Example Slim App with Moesif Integrated

[Moesif Slim Example](https://github.com/Moesif/moesif-slim-example)

## Other integrations

To view more documentation on integration options, please visit __[the Integration Options Documentation](https://www.moesif.com/docs/getting-started/integration-options/).__

[ico-built-for]: https://img.shields.io/badge/built%20for-slim-blue.svg
[ico-version]: https://img.shields.io/packagist/v/moesif/moesif-slim.svg
[ico-downloads]: https://img.shields.io/packagist/dt/moesif/moesif-slim.svg
[ico-license]: https://img.shields.io/badge/License-Apache%202.0-green.svg
[ico-source]: https://img.shields.io/github/last-commit/moesif/moesif-slim.svg?style=social

[link-built-for]: http://www.slimframework.com/
[link-package]: https://packagist.org/packages/moesif/moesif-slim
[link-downloads]: https://packagist.org/packages/moesif/moesif-slim
[link-license]: https://raw.githubusercontent.com/Moesif/moesif-slim/master/LICENSE
[link-source]: https://github.com/moesif/moesif-slim
