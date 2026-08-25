<?php

declare(strict_types=1);

namespace EtatGeneve\DataContentBundle\Service;

use EtatGeneve\DataContentBundle\DataContentJsonException;
use EtatGeneve\DataContentBundle\DataContentNotFoundException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;

/**
 * @phpstan-type DataContentConfig array{
 * checkSSL: bool,
 * applicationId: string,
 * tenantId: string,
 * clientId?: string,
 * clientSecret?: string,
 * username? : string,
 * password? : string,
 * tokenTimeout? : int,
 * timeout : int,
 * tokenAuthSsoUrl? : string,
 * restUrl : string,
 * baseId : string,
 * audience : string,
 * tokenAuthenticatorClass : ?string
 * }
 */
class DataContent extends DriverDataContent
{
    /**
     * Whitelist of extensions the GED is known to return, mapped to a safe Content-Type.
     * Anything not in this list falls back to application/octet-stream rather than
     * blindly trusting a server-provided extension in the Content-Type header.
     */
    private const EXTENSION_MIME_MAP = [
        'pdf' => 'application/pdf',
        'txt' => 'text/plain',
        'csv' => 'text/csv',
        'xml' => 'application/xml',
        'json' => 'application/json',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'tif' => 'image/tiff',
        'tiff' => 'image/tiff',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'zip' => 'application/zip',
    ];

    /**
     * Retrieving the definition and status of a document database.
     *
     * This method allows you to retrieve the definition of a document database,
     * which provides a list of the metadata associated with it and their constraints.
     *
     * @return mixed|null
     */
    public function getBase()
    {
        $this->logger->debug('DataContent : get base ');

        return $this->commandJsonRsp('GET', '/bases/' . $this->config['baseId']);
    }

    /**
     * Searching for a set of documents.
     *
     * This method allows you to search for documents based on a query and returns
     * all the metadata associated with those documents.
     *
     * This method does not return the content of the documents; for that, you will need
     * to call the method with the documentUUID of the desired document for each document.
     *
     *
     * example of query :
     *  $searchResult = $DataContent->searchByQuery(
     *      RM_DATE_REFERENCE_CYCLE_VIE:['.$dateFrom->format('Ymd').' TO '.
     *      $dateTo->format('Ymd').']  AND DOCUMENT_TYPE:quotidienne',
     *      [
     *          'pageSize' => 10000,
     *          'searchLimit' => 100000,
     *          'sortCategoryName' => 'RM_DATE_REFERENCE_CYCLE_VIE',
     *          'reversedSort' => true,
     *      ],
     *      30
     *  );
     *
     * @param array{fulltext?:?bool,pagesize?:?int,offset?:?int,sortCategoryName?:?string,reversedSort?:?bool,indexOrderPreference?:?string,searchLimit?:?int,timeZone?:?string} $options
     * @param int                                                                                                                                                                $additionalTimeout tiemout additonnel pour une transaction
     *
     * @return mixed
     */
    public function searchByQuery(?string $query, array $options = [], int $additionalTimeout = 0)
    {
        $this->logger->debug(
            'DataContent : search by query',
            ['query' => $query, 'options' => $options, 'additionalTimeout' => $additionalTimeout]
        );
        $parameters = [
            '@class' => 'net.docubase.toolkit.model.search.SortedSearchQuery',
            'query' => $query,
            'fullText' => $options['fullText'] ?? null,
            // be careful,  a exception is throw if the DataContent base is a not a fullText
            'pageSize' => $options['pageSize'] ?? null,
            'offset' => $options['offset'] ?? null,
            'sortCategoryName' => $options['sortCategoryName'] ?? null,
            'reversedSort' => $options['reversedSort'] ?? null,
            'indexOrderPreference' => $options['indexOrderPreference'] ?? null,
            'searchLimit' => $options['searchLimit'] ?? null,
            'base' => [
                'baseId' => $this->config['baseId'],
            ],
            'timeZone' => $options['timeZone'] ?? 'Europe/Zurich',
        ];
        if (isset($options['searchLimit'])) {
            $parameters['searchLimit'] = $options['searchLimit'];
        }
        $json = json_encode($parameters);
        if (false === $json) {
            throw new DataContentJsonException();
        }

        return $this->commandJsonRsp(
            'POST',
            '/search/query',
            $json,
            ['Content-Type:application/json'],
            $additionalTimeout
        );
    }

    /**
     *  Search for a document's metadata in a database directly using the document's UUID.
     *
     * @return mixed
     */
    public function searchByUuid(string $uuid)
    {
        $this->logger->debug('DataContent : search by uuid', ['uuid' => $uuid]);

        return $this->commandJsonRsp('GET', '/search/' . $this->config['baseId'] . '/' . $uuid);
    }

    /**
     * Download the contents of a document.
     *
     * This method allows you to retrieve the contents of a document.
     *
     * @param bool $httpResponse : If is true, then it returns a symfony Response object which allows
     *                           automatic downloading on the browser
     * @param bool $raw          : If is true, download the native content of a spool document without
     *                           formatting with the page background
     */
    public function getDocument(string $uuid, bool $httpResponse = true, bool $raw = false): Response|string
    {
        $this->logger->debug(
            'DataContent : get document',
            ['uuid' => $uuid, 'httpResponse' => $httpResponse, 'raw' => $raw]
        );
        if ($httpResponse) {
            // Fetch metadata first: fail fast on a missing document instead of downloading
            // (potentially large) content that will be discarded.
            $info = $this->searchByUuid($uuid);
            if (!$info || !is_object($info)) {
                throw new DataContentNotFoundException(sprintf('DataContent : Error, document %s not found', $uuid));
            }
            $document = $this->command('GET', '/store/' . ($raw ? 'raw/' : '') . $uuid);

            $response = new Response($document->getContent());
            $file = (isset($info->filename) && is_string($info->filename)) ? $info->filename : 'file';
            $extension = (isset($info->extension) && is_string($info->extension))
                ? strtolower($info->extension) : 'bin';
            // Only forward a small, known-safe set of extensions/MIME types; anything else
            // falls back to a generic binary type instead of trusting whatever the GED returns.
            $mimeType = self::EXTENSION_MIME_MAP[$extension] ?? 'application/octet-stream';
            $disposition = HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                sprintf('%s.%s', $file, $extension)
            );
            $response->headers->set('Content-Disposition', $disposition);
            $response->headers->set('Content-Type', $mimeType);

            return $response;
        }

        return $this->command('GET', '/store/' . ($raw ? 'raw/' : '') . $uuid)->getContent();
    }

    /**
     * Create a document.
     *
     * This method allows you to create a new document in a database by passing the document's
     * metadata and content as parameters.
     *
     * @param array<string,string>                                                          $criterions
     * @param array{'creationDate'?: string|int, 'filename'?: string, 'extension'?: string} $options
     *
     * @return mixed
     *
     * Date and Datetime Management
     * For each "criterion" object representing document metadata, its type can be specified by
     * adding the "wordType" attribute.
     *
     * The "wordType" attribute can contain the following values ​​(case-sensitive): Date, String,
     * Boolean, UUID, Long, Integer, Double.
     *
     * Date Type Criterion
     *   Dates can be represented either as a WordType String or as a WordType Date.
     *   If using the WordType Date, the date must be represented as a timestamp (epochtime) to the millisecond.
     *
     *    Example:
     *    { "categoryName": "METIER_DATETIME_RECEPTION","wordValue": 1700818240000,"wordType": "Date"}
     *    If using the WordType String, the date must be represented as a string containing YYYYMMDD.
     *
     *    Example:
     *    { "categoryName": "METIER_DATE_VALEUR", "wordValue": "20191015","wordType": "String" }
     *
     * DateTime Type Criterion
     *    Dates can be represented either as a WordType String or as a WordType Date.
     *    If you use the Date wordType, the dateTime must be represented as a timestamp (epochtime) to the millisecond.
     *
     *    Example:
     *    { "categoryName": "METIER_DATETIME_RECEPTION","wordValue": 1700818240000,"wordType": "Date"}
     *
     *    If you use the String wordType, the dateTime must be represented as a string containing YYYYMMDDHHmmssSSS.
     *    In this case, the value must be a date in UTC time.
     *
     *    Example:
     *    { "categoryName": "METIER_DATE_VALEUR", "wordValue": "20231124102543000","wordType": "String" }
     *
     *    This date, 20231124102543000 UTC, corresponds to November 24, 2023, at 11:25:43 GMT+1
     */
    public function storeDocument(string $filePath, ?string $title = null, array $criterions = [], $options = [])
    {
        $this->logger->debug(
            'DataContent :  storeDocument  ',
            ['filePath' => $filePath, 'title' => $title, 'criterions' => $criterions, 'options' => $options]
        );

        $path_parts = pathinfo($filePath);
        if (null === $title) {
            $title = $path_parts['basename'];
        }
        $parameters = [
            '@class' => 'net.docubase.toolkit.model.document.Document',
            'baseId' => $this->config['baseId'],
            'title' => $title,
            'creationDate' => $options['creationDate'] ?? '',
            'filename' => $options['filename'] ?? $path_parts['filename'],
            'extension' => $options['extension'] ?? $path_parts['extension'] ?? '',
        ];
        $gedCriterions = [];
        foreach ($criterions as $key => $value) {
            $gedCriterions[] = ['categoryName' => $key, 'wordValue' => $value];
        }
        $parameters['criterions'] = $gedCriterions;
        $formFields = [
            'document' => new DataPart(strval(json_encode($parameters)), 'document.json', 'application/json'),
            'inputStream' => DataPart::fromPath($filePath, 'inputStream', 'application/octet-stream'),
        ];
        $formData = new FormDataPart($formFields);
        $headers = $formData->getPreparedHeaders()->toArray();
        $body = $formData->bodyToIterable();

        return $this->commandJsonRsp('POST', '/store', $body, $headers);
    }

    /**
     * Delete a document.
     *
     * This method allows you to delete a document.
     *
     * @return mixed
     */
    public function deleteDocument(string $uuid)
    {
        $this->logger->debug('DataContent :  delete document', ['uuid' => $uuid]);

        return $this->commandJsonRsp('DELETE', '/store/' . $uuid);
    }
}
