# A Symfony bundle for GED tessi data content .

A Symfony bundle to easily send headers in your HTTP response

For Symfony 5.4

### Usage
You define one or more headers response in your yaml configuration, for exemple:

```
---
#config/packages/datacontent.yml
data_content:
  checkSSL: true
        # Name of application
  applicationId: myAppli
        # GED client Id
  clientId: clientId
        # Ged client secret
  clientSecret: 'secret'
        # URL for GED REST request
  restUrl: 'http://ged.localhost'
        # Base ID
  baseId: baseId
        # Timeout for DataContent in seconds
  timeout: 20
        # service for token authentication
  tokenAuthenticatorClass: ~
        # Username account for get a token
  username: 'User'
        # Password  for get a token
  password: 'password'
        # Audience for token request
  audience: audience
        # URL for get a token
  tokenAuthSsoUrl: http://sso.localhost
        # Timeout for token in seconds
  tokenTimeout: 15
...
```

| parameter                 |  definition                              |
|---------------------------|------------------------------------------|
| checkSSL                  | (bool) check the ssl                    |
| applicationId             | Application name Id                     |
| clientId                  | GED client Id                           |
| clientSecret              | Ged client secret                       |
| restUrl                   | URL for GED REST request                |
| baseId                    | Base ID                                 |
| timeout                   | Timeout for DataContent in seconds      |

When using the default service for token authentification
| parameter                 |  definition                              |
|---------------------------|------------------------------------------|
| username                  | Username account for get a token         |
| password                  | Password  for get a token                |
| audience                  | Audience for token request               |
| tokenAuthSsoUrl           | URL for get a token                      |
| tokenTimeout              | Timeout for token in seconds             |


If you want to use your own authentication service instead of the standard one
| parameter                 |  definition                              |
|---------------------------|------------------------------------------|
| tokenAuthenticatorClass   | service id for token authentication      |

Your service implement class EtatGeneve\DataContentBundle\Service\InterfaceTokenAuthenticator




## Installation
The bundle should be automatically enabled by Symfony Flex. If you don't use Flex, you'll need to enable it manually as explained in the docs.

```
composer config extra.symfony.allow-contrib true
composer require republique-et-canton-de-geneve/php-symfony-data-content-bundle
```



License
Released under the Apache-2.0 license

## Quality and test code
Code coverage :
![coverage line](https://raw.githubusercontent.com/republique-et-canton-de-geneve/php-symfony-data-content-bundle/refs/heads/main/coverage_line.svg)
![coverage branche](https://raw.githubusercontent.com/republique-et-canton-de-geneve/php-symfony-data-content-bundle/refs/heads/main/coverage_branch.svg)

[![phpunit php7.4](https://github.com/republique-et-canton-de-geneve/php-symfony-data-content-bundle/actions/workflows/php74unit.yml/badge.svg)](https://github.com/republique-et-canton-de-geneve/php-symfony-data-content-bundle/actions/workflows/php74unit.yml)
[![phpunit php8.4](https://github.com/republique-et-canton-de-geneve/php-symfony-data-content-bundle/actions/workflows/php84unit.yml/badge.svg)](https://github.com/republique-et-canton-de-geneve/php-symfony-data-content-bundle/actions/workflows/php84unit.yml)
[![phpstan](https://github.com/republique-et-canton-de-geneve/php-symfony-data-content-bundle/actions/workflows/phpstan.yml/badge.svg)](https://github.com/republique-et-canton-de-geneve/php-symfony-data-content-bundle/actions/workflows/phpstan.yml)
[![rector](https://github.com/republique-et-canton-de-geneve/php-symfony-data-content-bundle/actions/workflows/rector.yml/badge.svg)](https://github.com/republique-et-canton-de-geneve/php-symfony-data-content-bundle/actions/workflows/rector.yml)
[![php-cs-fixer](https://github.com/republique-et-canton-de-geneve/php-symfony-data-content-bundle/actions/workflows/php-cs-fixer.yml/badge.svg)](https://github.com/republique-et-canton-de-geneve/php-symfony-data-content-bundle/actions/workflows/php-cs-fixer.yml)