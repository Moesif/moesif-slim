<?php
namespace Moesif\Middleware;

/**
 * Prepare Moesif EventRequest Model
 */
function toRequest($request, $moesifOptions, $startDateTime, $logBody, $disableTransactionId, $logger)
{
    // TransactionId
    $transactionId = null;

    // Request URL
    $fullURL = fetchURL($request);

    // Event Request Model
    $requestData = [
        'time' => $startDateTime,
        'verb' => $request->getMethod(),
        'uri' => $fullURL
    ];

    // IP Address
    try {
        $requestData['ip_address'] = getIp();
    } catch (Exception $e) {
        $logger->debug("Error fetching client Ip address");
    }

    // Get and Parsed Request Body
    $contentType = $request->getHeaderLine('Content-Type');
    list($reqBody,$reqTransferEncoding) = searlizeBody($logBody, $request, null, $moesifOptions, $contentType, 'maskRequestBody');
    if (!is_null($reqBody)) {
        $requestData['body'] = $reqBody;
        $requestData['transfer_encoding'] = $reqTransferEncoding;
    }

    // Request Headers 
    $requestHeaders = $request->getHeaders();

    // Add Transaction Id to the request headers
    if (!$disableTransactionId) {
        $transactionId = getOrCreateTransactionId($request);
        // Filter out the old key as HTTP Headers case are not preserved
        if(array_key_exists('x-moesif-transaction-id', $requestHeaders)) { unset($requestHeaders['x-moesif-transaction-id']); }
        // Add Transaction Id to the request headers
        $requestHeaders = addTransactionId($requestHeaders, $transactionId);
    }

    // Mask Request headers
    $requestData['headers'] = maskHeaders($moesifOptions, $requestHeaders, 'maskRequestHeaders');

    // API Version
    if (isset($moesifOptions['apiVersion']) && !is_null($moesifOptions['apiVersion'])) {
        $requestData['api_version'] = $moesifOptions['apiVersion'];
    }

    return array($requestData, $transactionId);
}

/**
 * Prepare Moesif EventResponse Model
 */
function toResponse($response, $moesifOptions, $logBody, $transactionId)
{
    // End Time
    $endDateTime = getCurrentTime()->format('Y-m-d\TH:i:s.uP');

    // Event Response Model
    $responseData = [
        'time' => $endDateTime,
        'status' => $response->getStatusCode()
    ];

    // Get and Parsed Response Body
    $responseContentType = $response->getHeaderLine('Content-Type');
    list($rspBody,$rspTransferEncoding) = searlizeBody($logBody, null, $response, $moesifOptions, $responseContentType, 'maskResponseBody');
    if (!is_null($rspBody)) {
        $responseData['body'] = $rspBody;
        $responseData['transfer_encoding'] = $rspTransferEncoding;
    }

    // Response headers
    $responseHeaders = $response->getHeaders();

    // Add Transaction Id to the response headers
    $responseHeaders = addTransactionId($responseHeaders, $transactionId);

    // Mask Response headers
    $responseData['headers'] = maskHeaders($moesifOptions, $responseHeaders, 'maskResponseHeaders');

    return $responseData;
}

/**
 * Prepare Moesif Event Model
 */
function toEvent($requestData, $responseData, $moesifOptions, $request, $response)
{
    // Event Model
    $event = [
        'request' => $requestData,
        'response' => $responseData
    ];

    // Get User Id
    if (isset($moesifOptions['identifyUserId']) && !is_null($moesifOptions['identifyUserId'])) {
        $event['user_id'] = ensureString($moesifOptions['identifyUserId']($request, $response));
    }

    // Get CompanyId
    if (isset($moesifOptions['identifyCompanyId']) && !is_null($moesifOptions['identifyCompanyId'])) {
        $event['company_id'] = ensureString($moesifOptions['identifyCompanyId']($request, $response));
    }

    // Get Session Token
    if (isset($moesifOptions['identifySessionId']) && !is_null($moesifOptions['identifySessionId'])) {
        $event['session_token'] = ensureString($moesifOptions['identifySessionId']($request, $response));
    }

    // Get Metadata
    if (isset($moesifOptions['getMetadata']) && !is_null($moesifOptions['getMetadata'])) {
        $event['metadata'] = $moesifOptions['getMetadata']($request, $response);
    }

    // Add Direction 
    $event['direction'] = 'Incoming';

    return $event;
}