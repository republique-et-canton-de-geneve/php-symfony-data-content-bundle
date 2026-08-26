# php-symfony-data-content-bundle

Symfony bundle for GED Tessi Data Content, REST API integration.

## Requirements

- PHP >= 8.0
- Symfony >= 6.4 (for Symfony 5.4, see previous releases)


License
Released under the Apache-2.0 license



## Installation

The bundle is automatically enabled by Symfony Flex. If you don't use Flex, you'll need to enable it manually.

```bash
composer require republique-et-canton-de-geneve/php-symfony-data-content-bundle
```

## Configuration

Define the bundle configuration in your YAML files (e.g., `config/packages/datacontent.yml`):

```yaml
data_content:
    checkSSL: true          # Enable SSL certificate/host verification
    applicationId: myAppli  # Application identifier
    tenantId: admin         # Tenant ID
    clientId: clientId      # GED client ID
    clientSecret: '%env(DATA_CONTENT_CLIENT_SECRET)%'  # GED client secret
    restUrl: 'https://ged.example.com'  # GED REST API URL
    baseId: baseId          # Base ID for the document database
    timeout: 20             # Connection timeout in seconds

    # Authentication parameters (Password Grant)
    username: 'User'                    # Username for token authentication
    password: '%env(DATA_CONTENT_PASSWORD)%'  # Password for token authentication
    audience: 'my-audience'             # OAuth audience for token request
    tokenAuthSsoUrl: 'https://sso.example.com'  # SSO/OAuth token URL
    tokenTimeout: 15                    # Token request timeout in seconds

```

### Configuration Reference

| Parameter             | Type    | Required | Default      | Description                                      |
|-----------------------|---------|----------|--------------|--------------------------------------------------|
| checkSSL              | bool    | No       | `true`       | Enable SSL certificate and host verification     |
| applicationId         | string  | **Yes**  | —            | Application identifier                           |
| tenantId              | string  | No       | `'admin'`    | Tenant identifier                                |
| clientId              | string  | No       | —            | Client ID for token authentication               |
| clientSecret          | string  | No       | —            | Client secret for token authentication           |
| restUrl               | string  | **Yes**  | —            | GED REST API base URL                            |
| baseId                | string  | **Yes**  | —            | Document database base ID                        |
| timeout               | int     | No       | `10`         | HTTP connection timeout in seconds               |
| username              | string  | No       | —            | Username for OAuth password grant                |
| password              | string  | No       | —            | Password for OAuth password grant                |
| audience              | string  | No       | —            | OAuth audience for token request                 |
| tokenTimeout          | int     | No       | `10`         | Token request timeout in seconds                 |
| tokenAuthSsoUrl       | string  | No       | —            | OAuth/SAML token endpoint URL                    |

## Usage

### Basic Usage

```php
use EtatGeneve\DataContentBundle\Service\DataContent;

// Inject the DataContent service
public function __construct(
    private DataContent $dataContent,
) {
}

// Search documents
$results = $this->dataContent->searchByQuery(
    'DOCUMENT_TYPE:quotidienne',
    ['pageSize' => 100, 'searchLimit' => 1000],
    30
);

// Get document metadata by UUID
$metadata = $this->dataContent->searchByUuid('document-uuid-123');

// Download document content as binary string
$content = $this->dataContent->getDocument('document-uuid-123', httpResponse: false);

// Download document with Symfony Response (headers + download)
$response = $this->dataContent->getDocument('document-uuid-123', httpResponse: true);

// Store a new document
$this->dataContent->storeDocument(
    '/path/to/file.pdf',
    title: 'My Document',
    criterions: [
        ['categoryName' => 'METIER_DATE', 'wordValue' => '20231124', 'wordType' => 'String']
    ],
    options: ['filename' => 'my-file.pdf', 'extension' => 'pdf', 'documentType' => '10A_SUPPRESSION']
);

// Delete a document
$this->dataContent->deleteDocument('document-uuid-123');
```

### Authentication

The bundle supports OAuth grant type:

- **`password`** : Uses username/password credentials for token acquisition.

## Exceptions

| Exception                         | Description                              |
|-----------------------------------|------------------------------------------|
| `DataContentConfigException`      | Missing required configuration parameters |
| `DataContentAuthenticationException` | Token authentication/refresh failed    |
| `DataContentNotFoundException`    | Document not found                       |
| `DataContentJsonException`        | Invalid JSON payload                     |
| `DataContentRemoteException`      | HTTP/network error                       |


## Quality gate
Code coverage :
![coverage line](https://raw.githubusercontent.com/republique-et-canton-de-geneve/php-symfony-data-content-bundle/refs/heads/main/coverage_line.svg)
![coverage branche](https://raw.githubusercontent.com/republique-et-canton-de-geneve/php-symfony-data-content-bundle/refs/heads/main/coverage_branch.svg)

[![phpunit php8.4](https://github.com/republique-et-canton-de-geneve/php-symfony-data-content-bundle/actions/workflows/php84unit.yml/badge.svg)](https://github.com/republique-et-canton-de-geneve/php-symfony-data-content-bundle/actions/workflows/php84unit.yml)
[![phpstan](https://github.com/republique-et-canton-de-geneve/php-symfony-data-content-bundle/actions/workflows/phpstan.yml/badge.svg)](https://github.com/republique-et-canton-de-geneve/php-symfony-data-content-bundle/actions/workflows/phpstan.yml)
[![rector](https://github.com/republique-et-canton-de-geneve/php-symfony-data-content-bundle/actions/workflows/rector.yml/badge.svg)](https://github.com/republique-et-canton-de-geneve/php-symfony-data-content-bundle/actions/workflows/rector.yml)
[![php-cs-fixer](https://github.com/republique-et-canton-de-geneve/php-symfony-data-content-bundle/actions/workflows/php-cs-fixer.yml/badge.svg)](https://github.com/republique-et-canton-de-geneve/php-symfony-data-content-bundle/actions/workflows/php-cs-fixer.yml)



| Tool              | Description                      |
|-------------------|----------------------------------|
| PHPUnit 13        | Unit and integration tests       |
| PHPStan           | Static analysis                  |
| PHP CS Fixer      | Code style consistency           |
| Rector 2.6        | Automated code modernization     |
