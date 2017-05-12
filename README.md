[![Build Status](https://img.shields.io/travis/FriendsOfCake/crud-json-api/master.svg?style=flat-square)](https://travis-ci.org/FriendsOfCake/crud-json-api)
[![Coverage Status](https://img.shields.io/codecov/c/github/FriendsOfCake/crud-json-api.svg?style=flat-square)](https://codecov.io/github/FriendsOfCake/crud-json-api)
[![Total Downloads](https://img.shields.io/packagist/dt/FriendsOfCake/crud-json-api.svg?style=flat-square)](https://packagist.org/packages/FriendsOfCake/crud-json-api)
[![Latest Stable Version](https://img.shields.io/packagist/v/FriendsOfCake/crud-json-api.svg?style=flat-square)](https://packagist.org/packages/FriendsOfCake/crud-json-api)
[![Documentation Status](https://readthedocs.org/projects/crud-json-api/badge?style=flat-square)](https://crud-json-api.readthedocs.org)

# JSON API Crud Listener for CakePHP

Crud Listener for (rapidly) building CakePHP APIs following the
[JSON API specification](http://jsonapi.org/).

## Documentation
Documentation [found here](https://crud-json-api.readthedocs.io/).

## Installation

```
composer require friendsofcake/crud-json-api
```

## Why use it?

- standardized API data fetching, data posting and (validation) errors
- automated handling of complex associations/relationships
- instant compatibility with JSON API supporting tools like Ember Data
- tons of configurable options to manipulate the generated json

## Sample output

```json
  {
    "data": {
      "type": "countries",
      "id": "2",
      "attributes": {
        "code": "BE",
        "name": "Belgium"
      },
      "relationships": {
        "currency": {
          "data": {
            "type": "currencies",
            "id": "1"
          },
          "links": {
            "self": "/currencies/1"
          }
        },
        "cultures": {
          "data": [
            {
              "type": "cultures",
              "id": "2"
            },
            {
              "type": "cultures",
              "id": "3"
            }
          ],
          "links": {
            "self": "/cultures?country_id=2"
          }
        }
      },
      "links": {
        "self": "/countries/2"
      }
    },
    "included": [
      {
        "type": "currencies",
        "id": "1",
        "attributes": {
          "code": "EUR",
          "name": "Euro"
        },
        "links": {
          "self": "/currencies/1"
        }
      },
      {
        "type": "cultures",
        "id": "2",
        "attributes": {
          "code": "nl-BE",
          "name": "Dutch (Belgium)"
        },
        "links": {
          "self": "/cultures/2"
        }
      },
      {
        "type": "cultures",
        "id": "3",
        "attributes": {
          "code": "fr-BE",
          "name": "French (Belgium)"
        },
        "links": {
          "self": "/cultures/3"
        }
      }
    ]
  }
```

## Contribute

Before submitting a PR make sure:

- [PHPUnit](http://book.cakephp.org/3.0/en/development/testing.html#running-tests)
and [CakePHP Code Sniffer](https://github.com/cakephp/cakephp-codesniffer) tests pass
- [Codecov Code Coverage ](https://codecov.io/github/FriendsOfCake/crud-json-api) does not drop
