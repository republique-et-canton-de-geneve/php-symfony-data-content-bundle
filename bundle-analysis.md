# Analyse du Bundle Symfony DataContent par AI Gwen

## 1. Objectif

Ce bundle fournit un client Symfony pour l'API REST d'un système **GED (Gestion Électronique de Documents) Tessi**. Il permet de rechercher, télécharger, stocker et supprimer des documents, avec une authentification OAuth2 (Password Grant).

---

## 2. Architecture

```
EtatGeneve\DataContentBundle
├── DataContentBundle.php          # Bundle Symfony (configuration DI + definition)
├── Exception/
│   ├── DataContentException.php           # Base (extends Exception)
│   ├── DataContentConfigException.php     # Config invalide
│   ├── DataContentAuthenticationException.php  # Echec auth SSO
│   ├── DataContentNotFoundException.php   # Document inexistant
│   ├── DataContentJsonException.php       # Payload JSON invalide
│   └── DataContentRemoteException.php     # Reponse HTTP >= 400 / network error
├── Service/
│   ├── InterfaceTokenAuthenticator.php    # Contrat getToken() / reset()
│   ├── TokenAuthenticator.php             # OAuth password grant + cache
│   ├── DriverDataContent.php              # Couche HTTP commune (etablissement)
│   └── DataContent.php                    # Operations GED (recherche, upload, etc.)
```

**Héritage `DriverDataContent` -> `DataContent`** : Pattern utile pour supporter multi-drivers. La classe DriverDataContent est un driver (HTTP + authentication). On peut ajouter de nouveaux drivers en étendant `DriverDataContent`.

---

## 3. Details par composant

### `DriverDataContent.php` — Couche HTTP

| Methode | Description |
|---|---|
| `command()` | Envoie une requete HTTP avec les headers GED obligatoires (`X-Application-ID`, `X-Tenant-ID`, `X-Correlation-ID`), le bearer token, et configuration SSL. Forge un `correlationId` unique. |
| `commandJsonRsp()` | Wrapper de `command()` qui decode la reponse JSON et lance des exceptions sur status >= 400. Supporte les reponses d'erreur structurées du serveur (`exceptionCode`/`exceptionMessage`). |

**Auth token refresh implicite** : Si le serveur repond 401/403, `tokenAuthenticator->reset()` est appele pour forcer un rafraichissement au prochain appel.

**SSL** : Validation SSL desactivable via `checkSSL` (avec un warning en production).

### `TokenAuthenticator.php` — Gestion des tokens OAuth

- Utilise le **Password Grant** OAuth2 (client_id, client_secret, username, password, audience).
- **Mise en cache du token** via `Symfony\Contracts\Cache\CacheInterface`. Le `keyCache` est un hash SHA-256 des parametres kombinés.
- Le `TTL` du cache est calcule a partir du `expires_in` renvoye par le serveur SSO, moins 10 secondes de tampon.
- Utilisation du pattern **lazy cache** (closure invocable) : le token n'est obtenu que s'il n'est pas en cache.
- Exceptions leves : `DataContentConfigException` (parametres obligatoires absents), `DataContentAuthenticationException` (echec SSO).

### `DataContent.php` — Operations GED

| Methode | HTTP | Endpoint | Description |
|---|---|---|---|
| `getBase()` | GET | `/bases/{baseId}` | Metadonnees de la base GED |
| `searchByQuery()` | POST | `/search/query` | Recherche fulltext avec filtres |
| `searchByUuid()` | GET | `/search/{baseId}/{uuid}` | Recherche par UUID |
| `getDocument()` | GET | `/store/{uuid}` | Telechargement du contenu |
| `storeDocument()` | POST | `/store` | Upload de document (multipart/form-data) |
| `deleteDocument()` | DELETE | `/store/{uuid}` | Suppression |

**`getDocument()`** — Retourne un `Response` HTTP avec headers de telechargement quand `httpResponse=true`, sinon le contenu brut. **Important** : verification prealable du metadata via `searchByUuid` pour un "fail fast" au lieu de telecharger un gros fichier inutilement.

**Security : whitelist MIME** — La constante `EXTENSION_MIME_MAP` contient une whitelist stricte d'extensions vers Content-Safe. Toute extension inconnue tombe sur `application/octet-stream`. Cela previent les attaques XSS via `Content-Type` corrompu.

**`storeDocument()`** — Envoi un `FormDataPart` Symfony avec `document.json` (metadata JSON serializes) + `inputStream` (le fichier brut). Controle preliminar de l'existencela et la lisibilite du fichier local.

### `DataContentBundle.php` — Configuration DI

- Utilise `AbstractBundle` (Symfony 6.4+ style).
- `configure()` : definit la structure YAML avec des types captures (boolean, scalar, required).
- `loadExtension()` : autowire + autoconfigure, injecte le tableau de config comme argument `$config`.

---

## 4. Exceptions — Hiérarchie

```
DataContentException (base)
├── DataContentConfigException      # Parametres de config manquants
├── DataContentAuthenticationException  # SSO/token error
├── DataContentNotFoundException    # Document introuvable
├── DataContentJsonException        # JSON decode failed
└── DataContentRemoteException     # HTTP >= 400 / network error
```

Hiérarchie propre et extensible. Pas d'informations sensibles dans les messages d'exception (sauf chemin de fichier local).

---

## 5. Configuration YAML

```yaml
data_content:
    checkSSL: true          # Default: true
    applicationId:          # Required
    tenantId: 'admin'       # Default: 'admin'
    clientId:               # For auth
    clientSecret:           # For auth
    restUrl:                # Required
    baseId:                 # Required
    timeout: 10             # Default: 10s
    username:               # For auth
    password:               # For auth
    audience:               # For auth
    tokenTimeout: 10        # Default: 10s
    tokenAuthSsoUrl:        # For auth
```

Les secrets utilisent `%env()%` pour l'injection-safe.

---

## 6. Tests & Qualité

| Outil | Configuration |
|---|---|
| **PHPUnit 13** | Tests dans `tests/Unit/` + `tests/Fixtures/`. Coverage outputs: clover, cobertura, html |
| **PHPStan** | Niveau `max` sur `src/` + `tests/`, `treatPhpDocTypesAsCertain: false` |
| **PHP CS Fixer** | Style de codage |
| **Rector 2.6** | Modernisation automatique du code |

Backend test files : `DataContentBundleTest.php`, `DriverDataContentTest.php`, `DataContentTest.php`, `TokenAuthenticatorTest.php`.

---

## 7. Points forts

- **Security-by-design** : Whitelist MIME stricte pour les documents telecharges, pas de confiance aveugle dans la reponse serveur.
- **Correlation ID** : Chaque requete HTTP a un `X-Correlation-ID` unique genere pour le trace/logging.
- **Cache de tokens** : Optimisation importante — pas d'appel SSO a chaque requete, caching intelligent avec TTL dynamique.
- **Fail fast** : `getDocument()` verifie l'existence du metadata avant le telechargement du contenu.
- **Exception typing** : Hiérarchie fine permettant un `catch` specifique selon le type d'erreur.
- **Bundle moderne** : Utilise `AbstractBundle` + `DefinitionConfigurator` de Symfony 6.4, pas de chargement de fichiers XML/YAML manuels.

---

## 8. Points d'attention / Améliorations possibles

1. **Pas de retry automatique** : Si une requete HTTP echoue (timeout, network error), aucune tentative de retry n'est prévue. Un pattern avec `symfony/retry` ou une couche de retry serait utile pour les appels critiques.

2. **Token refresh manuel** : Le rafraichissement du token n'est déclenché que sur 401/403. Il n'y a pas de vérification "before request" pour rafraîchir le token avant expiration, ce qui pourrait causer des 401 inutiles.

3. **Pas de circuit breaker** : En cas d'indisponibilité prolongée du serveur GED, toutes les requêtes échoueront jusqu'à timeout. Un circuit breaker (ex: `symfony/circuit-breaker`) préviendrait la saturation.

4. **`searchByQuery` n'utilise pas la pagination automatique** : L'utilisateur doit passer `pageSize`, `searchLimit`, `offset` à chaque appel. Un helper de pagination automatique pourrait être ajouté.

5. **Pas de validation stricte du type de la config** au niveau du `DriverDataContent` constructeur (pas de type-hint forte sur `$config`, seulement un `@phpstan-type`).

6. **Authentification : Username/Password pour token** — Le bundle supporte cette méthode, mais 403 sur le token request ne provoque pas de reset du token (uniquement 401/403 sur les requêtes du GED même). Standard OAuth2 utilise 401 pour auth failed, 403 pour forbidden — logiquement, les deux devraient reset le token (ce qui est le cas, donc OK).

---

## 9. Résumé

Ce bundle est une couche client proprement architecturée pour l'API GED Tessi, avec une bonne séparation des responsabilités (HTTP driver vs business operations), une gestion d'exceptions bien typée, une sécurité orientation whitelist MIME, et une configuration DI moderne Symfony 6.4+. Il manque des mécanismes avancés de résilience (retry, circuit breaker) qui seraient bénéfiques pour un appel API critique en production.
