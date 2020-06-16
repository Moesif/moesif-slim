<?php
namespace Moesif\Middleware;

/**
 * Get Application configuration
 */
function getConfig($client, $debug, $logger)
{
    try {
        return $client->getAppConfig();
    } 
    catch (Exception $e) {
        $logger->debug("Error getting application configuration");
        $logger->debug($e->getMessage());
    }
}

/**
 * Parse Application configuration
 */
function parseConfiguration($configHeaders, $config, $debug, $logger)
{
    try {
        $configBody = json_decode($config, true);
        $parseConf = array(getOrElse($configHeaders['x-moesif-config-etag'], null), getOrElse($configBody['sample_rate'], 100),  getCurrentTime());
        return $parseConf;
    } 
    catch (Exception $e)
    {
        $logger->debug('Error while parsing the configuration object, setting the sample rate to default');
        return array(null, 100, getCurrentTime());
    }
}

/**
 * Get Sampling percentage
 */
function getSamplingPercentage($config, $userId, $companyId, $logger)
{
    try {
        $configBody = json_decode($config, true);

        $userSampleRate = getOrElse($configBody['user_sample_rate'], null);

        $companySampleRate = getOrElse($configBody['company_sample_rate'], null);

        if (!is_null($userId) && !is_null($userSampleRate) && array_key_exists($userId, $userSampleRate)) {
            return $userSampleRate[$userId];
        }

        if (!is_null($companyId) && !is_null($companySampleRate) && array_key_exists($companyId, $companySampleRate)) {
            return $companySampleRate[$companyId];
        }

        return getOrElse($configBody['sample_rate'], 100);

    } 
    catch (Exception $e) {
        $logger->debug('Error while getting the sampling percentage, setting the sample rate to default');
        return 100;
    }
}