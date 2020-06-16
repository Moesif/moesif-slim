<?php
namespace Moesif\Middleware;

/**
 * Check if body is Json
 */
function isJsonBody($body) {
    return (substr($body, 0, 1) === '{' || substr($body, 0, 1) === '[');
}

/**
 * Serialize request/response body into Json/Base64
 */
function searlizeBody($logBody, $request, $response, $moesifOptions, $contentType, $maskBody) {

    if (!is_null($request)) {
        // Get Request Body
        $bodyContent = file_get_contents('php://input');
    } 
    else {
        // Get Response Body
        $responseBody = $response->getBody();
        $responseBody->rewind(); // Rewind response body
        $bodyContent = $responseBody->getContents();
    }

    if($logBody && !IsNullOrEmptyString($bodyContent)) {
        if (strstr($contentType, 'application/json') && isJsonBody($bodyContent)) {
            $body = json_decode($bodyContent, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                // Mask Body
                if (isset($moesifOptions[$maskBody]) && !is_null($moesifOptions[$maskBody])) {
                    return array($moesifOptions[$maskBody]($body), 'json');
                } else {
                    return array($body, 'json');
                }
            }
        }
        else {
            return array(base64_encode($bodyContent), 'base64');
        }
    }
}
