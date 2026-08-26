# Complete Project Analysis by Gwen AI : Symfony DataContent Bundle

## 1. Overview

| Reference | Detail |
|---|---|
| **Name** | `republique-et-canton-de-geneve/php-symfony-data-content-bundle` |
| **Description** | Symfony Bundle for REST API integration with Tessi GED (Electronic Document Management) system. |
| **Author** | Michel Bobillier (Athos99) - Senior Lead Developer |
| **License** | Apache 2.0 |
| **Repository** | https://github.com/republique-et-canton-de-geneve/php-symfony-data-content-bundle |

### Runtime Dependencies

| Package | Minimum Version | Purpose |
|---|---|---|
| `php` | >= 8.0 | Language |
| `symfony/dependency-injection` | >= 6.4 | DI Container |
| `symfony/http-kernel` | >= 6.4 | Symfony Bundle System |
| `symfony/config` | >= 6.4 | Configuration DSL |
| `symfony/mime` | >= 6.4 | MIME handling, FormDataPart, DataPart |
| `symfony/security-bundle` | >= 6.4 | Security support (commented out in latest code) |
| `symfony/http-client` | >= 6.4 | Async HTTP client |
| `symfony/cache` | >= 6.4 | OAuth token caching |

### Development Dependencies

| Package | Version | Purpose |
|---|---|---|
| `phpunit/phpunit` | ^13 | Unit and integration tests |
| `phpstan/phpstan` | ^2 | Static analysis (max level) |
| `friendsofphp/php-cs-fixer` | ^3 | PSR-12 coding standards |
| `rector/rector` | ^2.6 | Automated code modernization |
| `phpunit/php-code-coverage` | ^14 | Coverage reports |

---

## 2. Project Structure

```
php-symfony-data-content-bundle/
├── .github/workflows/     # CI: 4 pipelines (PHP 8.4, PHPStan, Rector, CS Fixer)
├── cmd/                   # Utility shell scripts (check, phpunit, phpstan, rector, etc.)
├── config/packages/       # Default configuration file
├── html-coverage/         # HTML coverage reports
├── src/
│   ├── DataContentBundle.php          # Symfony Bundle extension DSL
│   ├── Exception/
│   │   ├── DataContentException.php                    # Base exception
│   │   ├── DataContentConfigException.php              # Invalid configuration
│   │   ├── DataContentAuthenticationException.php      # SSO auth failure
│   │   ├── DataContentNotFoundException.php            # Document not found
│   │   ├── DataContentJsonException.php                # Invalid JSON response
│   │   └── DataContentRemoteException.php              # HTTP >= 400 / network error
│   └── Service/
│       ├── InterfaceTokenAuthenticator.php             # Contract: getToken() / reset()
│       ├── TokenAuthenticator.php                      # OAuth password grant + cache
│       ├── DriverDataContent.php                       # HTTP driver (low-level layer)
│       └── DataContent.php                             # GED operations (business layer)
├── tests/
│   ├── Fixtures/
│   │   └── test-file.txt
│   └── Unit/
│       ├── DataContentBundleTest.php
│       ├── DataContentTest.php
│       ├── DriverDataContentTest.php
│       └── TokenAuthenticatorTest.php
├── composer.json
├── composer.lock
├── phpstan.neon               # Max level
├── phpunit.xml                # Test + coverage configuration
└── README.md
```

---

## 3. Detailed Component Analysis

### 3.1 `DataContentBundle.php` — Symfony Integration

The bundle extends `AbstractBundle` and uses the new Symfony 6.4 DSL configuration API.

- **`configure()`** : Defines the `data_content` YAML schema with types, defaults, and required fields.
- **`loadExtension()`** : Registers services `TokenAuthenticator` and `DataContent` with autowire/autoconfigure DI. Injects the full config array as `$config` argument.

**Notable points**:
- No external `services.xml` loading: everything is written in native PHP via `ContainerConfigurator`.
- `@phpstan-import-type` used for static type hints on the config parameter.

### 3.2 `DriverDataContent.php` — HTTP Layer

Base class that orchestrates all HTTP requests to the GED.

#### `command()` Method

Sends HTTP requests + error handling. All business methods call this function.

| HTTP Header | Value | Description |
|---|---|---|
| `X-Application-ID` | `$config['applicationId']` | Connector identifier |
| `X-Tenant-ID` | `$config['tenantId']` | Tenant identifier |
| `X-Correlation-ID` | `bin2hex(random_bytes(8))` | Unique ID for distributed tracing |
| `connectedAs` | `$config['username'] ?? null` | Connected user name |
| `Authorization` | Bearer `$token` | OAuth token from cache |

**HTTP Options**:

| Option | Description |
|---|---|
| `verify_host` | Enabled when `checkSSL=true` |
| `verify_peer` | Enabled when `checkSSL=true` |
| `auth_bearer` | Token from `TokenAuthenticator::getToken()` |
| `timeout` + `max_duration` | `$config['timeout'] + $additionalTimeout` |

**Error Handling**:

| Status Code | Action | Associated Exception |
|---|---|---|
| HTTP >= 400 | `tokenAuthenticator->reset()` | `DataContentRemoteException` |
| Network error | Log + rethrow | `DataContentRemoteException` |
| 401/403 | Cache token cleanup | None (transparent token invalidation) |

#### `commandJsonRsp()` Method

Wrapper around `command()` that parses JSON responses and handles anomalies:

1. If response is empty => `null`.
2. If JSON invalid => `DataContentJsonException`.
3. If HTTP status >= 400 => inspect JSON body to extract server-side error code/message.
   - If server returns JSON with `{ exceptionCode, exceptionMessage }` => more descriptive message.
   - Otherwise => default message.

### 3.3 `TokenAuthenticator.php` — OAuth2 Management

#### Constructor

Validates presence of 6 mandatory parameters: `clientId`, `clientSecret`, `username`, `password`, `audience`, `tokenAuthSsoUrl`. Throws `DataContentConfigException` if any is missing.

Generates a unique cache key via `sha256(clientId + username + audience + tokenAuthSsoUrl)` in the form `DataContent-<hash>`.

#### `getToken()` Method — Lazy Cache Pattern

The token is not stored in PHP memory but in Symfony cache. The pattern:

```php
$token = $this->cache->get(
    $this->keyCache,
    function (ItemInterface $item): mixed {
        // Called only when cache is empty/expired
        // Performs POST /token request with password grant
        // Calculates TTL from expires_in - 10s
        // Returns id_token
    },
    0.1  // PSR-6 mental cache (100ms)
);
```

**OAuth Password Grant Flow**:
1. `POST $tokenAuthSsoUrl` with body: `client_id`, `client_secret`, `grant_type=password`, `username`, `password`, `audience`.
2. Parse response JSON to extract `id_token` and `expires_in`.
3. If valid: return token, set TTL = `expires_in - 10`.
4. If invalid: `cache->delete(keyCache)` + throw `DataContentAuthenticationException`.
5. If network error: `cache->delete(keyCache)` + throw `DataContentAuthenticationException` (with `Throwable` as cause).

### 3.4 `DataContent.php` — GED Operations

Class implementing high-level operations on the Tessi GED system.

#### `getBase()` — GET `/bases/{baseId}`

Returns GED base definition (column metadata, constraints).

#### `searchByQuery()` — POST `/search/query`

Full-text search with pagination and sorting options. Requires `net.docubase.toolkit.model.search.SortedSearchQuery` as the business class.

Supported options:

| Option | Type | Description |
|---|---|---|
| `fullText` | `?bool` | Enable full-text search |
| `pageSize` | `?int` | Results per page |
| `offset` | `?int` | Pagination offset |
| `sortCategoryName` | `?string` | Sort column |
| `reversedSort` | `?bool` | Descending sort |
| `indexOrderPreference` | `?string` | Order preference |
| `searchLimit` | `?int` | Global limit |
| `timeZone` | `?string` | Timezone (default: `Europe/Zurich`) |

Adds `additionalTimeout` in seconds for long transactions.

#### `searchByUuid()` — GET `/search/{baseId}/{uuid}`

Searches a single document by its UUID. Returns only metadata, not content.

#### `getDocument()` — GET `/store/{uuid}` or `/store/raw/{uuid}`

Downloads document content. Two modes:

**`httpResponse=true` mode** (default):
1. **Fail fast**: calls `searchByUuid()` first. If null => throws `DataContentNotFoundException`.
2. If confirmed, downloads raw content via `GET /store/{raw?}/{uuid}`.
3. Processes file name, extension, and MIME type.
4. **Security**: MIME type is determined by a strict whitelist based on extension.

**`httpResponse=false` mode**: returns raw binary content only.

**MIME Whitelist** (`EXTENSION_MIME_MAP`):

| Extension | Content-Type |
|---|---|
| `pdf` | `application/pdf` |
| `txt` | `text/plain` |
| `csv` | `text/csv` |
| `xml` | `application/xml` |
| `json` | `application/json` |
| `png` | `image/png` |
| `jpg/jpeg` | `image/jpeg` |
| `gif` | `image/gif` |
| `tif/tiff` | `image/tiff` |
| `doc` | `application/msword` |
| `docx` | `application/...wordprocessingml` |
| `xls` | `application/vnd.ms-excel` |
| `xlsx` | `application/...spreadsheetml` |
| `zip` | `application/zip` |
| `htm/html` | `text/html` |
| `rtf` | `application/rtf` |
| `odp/ods/odt` | `application/vnd.oasis.opendocument.*` |
| `webp` | `image/webp` |
| `avif` | `image/avif` |
| `svg` | `image/svg+xml` |
| **any other** | `application/octet-stream` |

Prevents XSS attacks via falsified Content-Type.

#### `storeDocument()` — POST `/store`

Document upload with metadata. Sends a `multipart/form-data` containing:

- `document`: `DataPart` with JSON metadata (`net.docubase.toolkit.model.document.Document`)
- `inputStream`: `DataPart::fromPath()` — the raw file

Included metadata:

| Field | Type | Description |
|---|---|---|
| `@class` | `string` | GED business class |
| `baseId` | `string` | GED document base |
| `title` | `string` | Document title |
| `type` | `string` | Document type (default: `10A_SUPPRESSION`) |
| `creationDate` | `string|int` | Creation date |
| `filename` | `string` | File name |
| `extension` | `string` | File extension |
| `criterions` | `array` | Additional metadata |

Pre-validation: file must exist and be readable. Otherwise throws `DataContentException`.

Date formats:

| Type | Example Format | Description |
|---|---|---|
| `Date` | `1700818240000` (epoch ms) | Millisecond epoch timestamp |
| `String` | `"20191015"` | YYYYMMDD |
| `DateTime` | `1700818240000` (epoch ms) | Millisecond epoch timestamp |
| `DateTime String` | `"20231124102543000"` | YYYYMMDDHHmmssSSS UTC |

#### `deleteDocument()` — DELETE `/store/{uuid}`

Deletes an existing document by UUID.

---

## 4. Exception Hierarchy

```
DataContentException (extends Exception)
├── DataContentConfigException
│   └── Origin: Missing mandatory config parameters
├── DataContentAuthenticationException
│   └── Origin: SSO/token failure, invalid SSO request, expired token
├── DataContentNotFoundException
│   └── Origin: Non-existent document UUID
├── DataContentJsonException
│   └── Origin: Invalid JSON payload (encode or decode)
└── DataContentRemoteException
    └── Origin: HTTP >= 400, network error, timeout
```

None of the exceptions leak sensitive information (except local file path in `DataContentException -> storeDocument`, which is user-provided).

---

## 5. Full YAML Configuration

| Parameter | Type | Required | Default | Constraint |
|---|---|---|---|---|
| `checkSSL` | `bool` | No | `true` | — |
| `applicationId` | `string` | **Yes** | — | `cannotBeEmpty` |
| `tenantId` | `string` | No | `'admin'` | — |
| `clientId` | `string` | No (Auth) | — | `cannotBeEmpty` |
| `clientSecret` | `string` | No (Auth) | — | `cannotBeEmpty` |
| `restUrl` | `string` | **Yes** | — | `cannotBeEmpty` |
| `baseId` | `string` | **Yes** | — | `cannotBeEmpty` |
| `timeout` | `int` | No | `10` | — |
| `username` | `string` | No (Auth) | — | — |
| `password` | `string` | No (Auth) | — | — |
| `audience` | `string` | No (Auth) | — | — |
| `tokenTimeout` | `int` | No | `10` | — |
| `tokenAuthSsoUrl` | `string` | No (Auth) | — | — |

Secrets can be loaded via Symfony `%env()%` parameters.

---

## 6. Testing & Software Quality

### PHPUnit Configuration

- **Version**: PHPUnit 13
- **Bootstrap**: `vendor/autoload.php`
- **Execution order**: `depends,defects` (tests that broke last are run first)
- **Coverage output**: `clover.xml`, `cobertura.xml`, `html-coverage/`
- **Coverage thresholds**: `lowUpperBound=50`, `highLowerBound=90`
- **Strict behavior**: `beStrictAboutCoverageMetadata`, `beStrictAboutOutputDuringTests`, `failOnPhpunitDeprecation`

### Unit Tests (4 files)

| File | Covers | Methods |
|---|---|---|
| `DataContentTest.php` | GED CRUD (getBase, search, get, store, delete) | 10 |
| `DriverDataContentTest.php` | HTTP layer (command, commandJsonRsp, errors 400/500/501) | 8 |
| `TokenAuthenticatorTest.php` | OAuth token, cache, invalid token, config error | 5 |
| `DataContentBundleTest.php` | Definition configuration, DI loadExtension | 2 |

### Static Analysis

| Tool | Configuration | Target |
|---|---|---|
| **PHPStan** | Level `max`, `treatPhpDocTypesAsCertain: false` | `src/` + `tests/` |
| **PHP CS Fixer** | PSR-12, configured in `.php-cs-fixer.php` | `src/` + `tests/` |
| **Rector** | Version ^2.6 (automated modernization) | `src/` |

### Utility Scripts (`cmd/`)

| Command | Action |
|---|---|
| `cmd/check` | CS check + PHPStan + Rector dry-run + PHPUnit |
| `cmd/php-cs-fixer` | Apply CS Fixer |
| `cmd/phpstan` | Static analysis |
| `cmd/rector-exec` | Apply Rector refactorings |
| `cmd/phpunit` | Run tests |
| `cmd/clear` | Clear cache |
| `cmd/update_composer` | Update composer.lock |

---

## 7. CI/CD Pipeline

4 GitHub Actions workflows:

| File | Description |
|---|---|
| `php84unit.yml` | PHPUnit on PHP 8.4 |
| `phpstan.yml` | PHPStan static analysis |
| `rector.yml` | Rector checks |
| `php-cs-fixer.yml` | Code style checks |

---

## 8. Strengths

### Security
- **Strict MIME whitelist**: no blind trust in server MIME types. Unknown extensions are forced to `application/octet-stream` (generic binary file).
- **SSL verification**: enabled by default, configurable for development with a warning message.
- **Correlation ID**: every HTTP request generates a unique `X-Correlation-ID` for distributed tracing.
- **Secret management**: `%env()%` usage for credentials avoids plaintext storage.

### Architecture
- **HTTP/Business Separation**: the `DriverDataContent` driver is independent of GED business logic. New drivers can be easily added (e.g., `DriverDataContentMyProvider`).
- **`InterfaceTokenAuthenticator`**: allows implementing another token management strategy (JWT, refresh token, etc.).
- **Clean Dependency Injection**: uses `autowire` + `arg('$config', $config)` without external XML files.
- **PHPStan Typing**: extensive use of `@phpstan-type` everywhere to static-type the config array.

### Robustness
- **Smart token caching**: TTL dynamically calculated from SSO server `expires_in`, minus 10s to avoid expiration during use.
- **Fail fast**: `getDocument()` checks document existence via metadata before downloading potentially large content.
- **Graceful error handling**: 401/403 errors trigger automatic token cache cleanup for transparent re-authentication.
- **Extendable timeout**: `additionalTimeout` supported for long-running transactions.

---

## 9. Areas for Improvement

### 9.1 No Automatic Retry

No retry attempt on network errors (timeout, DNS, connection refused). A Retry pattern (e.g., `symfony/retry` or a wrapper in `command()`) would improve resilience.

### 9.2 Preventive Token Refresh

Token refresh is only triggered when receiving 401/403. A pre-check (before each request) to refresh the token before use would avoid authentication failures. Adding a validity check and a preventive mechanism would enhance resilience.

### 9.3 No Circuit Breaker

In case of prolonged GED server unavailability, all requests will fail until timeout. A circuit breaker (e.g., `symfony/circuit-breaker`) would protect the system from resource saturation.

### 9.4 Automatic Pagination Not Available

The user must manually pass `pageSize`, `searchLimit`, `offset` on each call. An automatic helper **`searchByQueryPaginated()`** would improve UX.

### 9.5 Config Validation

No strong type-hint on `$config` in the `DriverDataContent` constructor. `@phpstan-type` annotations are only docblocks. Runtime validation (at `DataContentBundle` level) is correct but could go further with a Symfony validator.

### 9.6 `connectedAs` Header Uses `$config['userName']`

The `connectedAs` header reads `$config['userName']` instead of `$config['username']` used everywhere else. These are different keys: `userName` vs `username`. In the YAML config, only `username` is defined. The `userName` field is not defined in the bundle configuration. This is likely a bug: the `connectedAs` header will never be set.

### 9.7 Test `testStoreDocument()` Has a Typo

`'crtiterions' => 'value1'` instead of `'criterions' => 'value1'`. Not critical but indicates criterion validation in the code is not thorough.

---

## 10. Summary

The **Symfony DataContent** bundle is a cleanly architected client layer for the Tessi GED REST API. The separation between the HTTP driver (`DriverDataContent`) and high-level operations (`DataContent`) enables easy extensibility for new providers. Robust exception handling at all levels, PSR-6 token caching with dynamic TTL, and a strict MIME whitelist for downloaded documents demonstrate good security-by-design principles.

Weaknesses: no automatic retry, no circuit breaker, and a likely bug on config key `userName` instead of `username`.

---

## Appendix: Authentication Methods Supported

| Grant Type | Description | Required Params |
|---|---|---|
| `password` (OAuth2) | Uses username/password credentials for token acquisition | `clientId`, `clientSecret`, `username`, `password`, `audience`, `tokenAuthSsoUrl` |
