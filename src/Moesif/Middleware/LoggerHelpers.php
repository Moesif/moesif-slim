<?php
namespace Moesif\Middleware;

use DateTime as DateTime;
use DateTimeZone as DateTimeZone;


/**
 * Get Client Ip Address.
 */
function getIp(){
    foreach (array('HTTP_X_CLIENT_IP', 'HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_TRUE_CLIENT_IP', 
    'HTTP_X_REAL_IP', 'HTTP_X_REAL_IP',  'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR') as $key){
        if (array_key_exists($key, $_SERVER) === true){
            foreach (explode(',', $_SERVER[$key]) as $ip){
                $ip = trim($ip); // just to be safe
                if (strpos($ip, ':') !== false) {
                    $ip = array_values(explode(':', $ip))[0];
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false){
                    return $ip;
                }
            }
        }
    }
}

/**
 * Generate GUID.
 */
function guidv4($data)
{
    assert(strlen($data) == 16);

    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Function for basic field validation (present and neither empty nor only white space.
 */
function IsNullOrEmptyString($str){
    $isNullOrEmpty = false;
    if (!isset($str) || trim($str) === '') {
        $isNullOrEmpty = true;
    } 
    return $isNullOrEmpty;
}

/**
 * Function to generate random percentage.
 */
function generate_random_percentage() 	
{	
    return ((float)rand() / (float)getrandmax()) * 100;	
}

/**
 * Function to ensure string.
 */
function ensureString($item) {
    if (is_null($item)) {
        return $item;
    }
    if (is_string($item)) {
        return $item;
    }
    return strval($item);
}

/**
 * Get value if set, else default
 */
function getOrElse(&$var, $default=null) {
    return isset($var) ? $var : $default;
}

/**
 * Calculate Event Weight
 */
function calculateWeight($samplingPercentage) {
    return ($samplingPercentage==0) ? 1 : floor(100 / $samplingPercentage);
}

/**
 * Add TransactionId to Headers
 */
function addTransactionId($headers, $transactionId) {
    if (!is_null($transactionId)) {
        $headers['X-Moesif-Transaction-Id'] = $transactionId;
        return $headers;
    }
    return $headers;
}

/**
 * Get or Create TransactionId
 */
function getOrCreateTransactionId($request) {

    if ($request->hasHeader('X-Moesif-Transaction-Id')) {
        $reqTransId = $request->getHeaderLine('X-Moesif-Transaction-Id');
        if (!is_null($reqTransId)) {
            return $reqTransId;
        }
        if (IsNullOrEmptyString($this->transactionId)) {
            return guidv4(openssl_random_pseudo_bytes(16));
        }
    }
    else {
        return guidv4(openssl_random_pseudo_bytes(16));
    }
}

/**
 * Get Current Time
 */
function getCurrentTime() 
{
    $dateTime = new DateTime('now');
    $dateTime->setTimezone(new DateTimeZone("UTC"));
    return $dateTime;
}

/**
 * Fetch Request URL
 */
function fetchURL($request) {
    $uri = $request->getUri();
    $basePath = $uri->getScheme().'://'.$uri->getHost().':'.$uri->getPort().$uri->getPath(); 
    if (!IsNullOrEmptyString($uri->getQuery())) {
        return $basePath.'?'.$uri->getQuery();
    }
    else {
        return $basePath;
    }
}

/**
 * Mask Headers
 */
function maskHeaders($moesifOptions, $headers, $headerName)
{
    if (isset($moesifOptions[$headerName]) && !is_null($moesifOptions[$headerName])) {
        return $moesifOptions[$headerName]($headers);
    } else {
        return $headers;
    }
}