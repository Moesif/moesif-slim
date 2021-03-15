<?php
namespace Moesif\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Factory\AppFactory;
use Slim\Psr7\Response;
use Moesif\Sender\MoesifApi;
use Exception;
use DateTime as DateTime;
use DateTimeZone as DateTimeZone;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Formatter\JsonFormatter;
include('EventMapper.php');
include('BodySerializer.php');
include('AppConfig.php');
include('LoggerHelpers.php');

class MoesifSlim {

    /**
     * Default options that can be overridden via the $moesifOptions constructor arg
     * @var array
     */
    private $_defaults = array(
        "debug"                 => false, // enable/disable debug mode
        "disableTransactionId"  => false, // enable/disable transactionId
        "logBody"               => true, // enable/disable logging Body
        "disableForking"        => false, // enable/disable forking
    );

    /**
     * An array of options to be used by the moesif library.
     * @var array
     */
    protected $moesifOptions = array();
  
    protected $config;

    protected $samplingPercentage;

    protected $lastUpdatedTime;

    protected $eTag;

    protected $logger;

    public function __construct($moesifOptions) {
        $moesifOptions = array_merge($this->_defaults, $moesifOptions);
        $this->moesifOptions = $moesifOptions;
        $this->applicationId = getOrElse($this->moesifOptions['applicationId'], null);
        $this->debug = $this->moesifOptions['debug'];
        $this->disableTransactionId = $this->moesifOptions['disableTransactionId'];
        $this->logBody = $this->moesifOptions['logBody'];
        $this->disableForking = $this->moesifOptions['disableForking'];

        // Logger
        // the default date format is "Y-m-d H:i:s"
        $dateFormat = "Y n j, g:i a";
        // the default output format is "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n"
        $output = "%datetime% > %channel%.%level_name% > %message% %context% %extra%\n";
        // finally, create a formatter
        $formatter = new LineFormatter($output, $dateFormat, true, true);

        // Create a handler
        $stream = new StreamHandler('moesif.log', Logger::DEBUG);
        $stream->setFormatter($formatter);
        // bind it to a logger object
        $this->logger = new Logger('moesif');
        $this->logger->pushHandler($stream);

        if (!is_null($this->applicationId)) {
            $this->transactionId = null;
            $this->moesifApi = MoesifApi::getInstance($this->applicationId, ['fork'=>!$this->disableForking, 'debug'=>$this->debug]);
            $this->samplingPercentage = 100;
            $this->lastUpdatedTime = getCurrentTime();
            $this->eTag = null;
            
            // App Config
            try {
                list($configHeaders ,$this->config) = getConfig($this->moesifApi, $this->debug, $this->logger);
                list($this->eTag, $this->samplingPercentage, $this->lastUpdatedTime) = parseConfiguration($configHeaders, $this->config, $this->debug, $this->logger);
            } catch(Exception $e) {
                if ($this->debug) {
                    $this->logger->debug('Error while fetching application configuration on initialization');
                }
            }
        }
    }

    /**
     * Update user.
     */
    public function updateUser($userData){

        if (is_null($this->applicationId)) {
            throw new Exception('ApplicationId is missing. Please provide applicationId in moesif.php in config folder.');
        }
        $this->moesifApi->updateUser($userData);
    }

    /**
     * Update users in batch.
     */
    public function updateUsersBatch($usersData){

        if (is_null($this->applicationId)) {
            throw new Exception('ApplicationId is missing. Please provide applicationId in moesif.php in config folder.');
        }
        $this->moesifApi->updateUsersBatch($usersData);
    }

    /**
     * Update company.
     */
    public function updateCompany($companyData){
        
        if (is_null($this->applicationId)) {
            throw new Exception('ApplicationId is missing. Please provide applicationId in moesif.php in config folder.');
        }
        $this->moesifApi->updateCompany($companyData);
    }

    /**
     * Update companies in batch.
     */
    public function updateCompaniesBatch($companiesData){
        
        if (is_null($this->applicationId)) {
            throw new Exception('ApplicationId is missing. Please provide applicationId in moesif.php in config folder.');
        }
        $this->moesifApi->updateCompaniesBatch($companiesData);
    }

    
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        // Start Time
        $startDateTime = getCurrentTime()->format('Y-m-d\TH:i:s.uP');

        // Prepare event request model
        list($requestData, $this->transactionId) = toRequest($request, $this->moesifOptions, $startDateTime, $this->logBody, $this->disableTransactionId, $this->logger);
        
        // Next Middleware
        $response = $handler->handle($request);

        // Check if application Id is provided
        if (is_null($this->applicationId)) {
            if ($this->debug) {
                $this->logger->debug('ApplicationId is missing. Please provide applicationId in moesif.php in config folder.');
            }
            return $response;
        }

        // Prepare event response model
        $responseData = toResponse($response, $this->moesifOptions, $this->logBody, $this->transactionId);

        // if skip is defined, invoke skip function.
        if (isset($this->moesifOptions['skip']) && !is_null($this->moesifOptions['skip'])) {
            if($this->moesifOptions['skip']($request, $response)) {
                if ($this->debug) {
                    $this->logger->debug('[Moesif] : Skip function returned true, so skipping this event.');
                }
                return $response;
            }
        } 

        // Add Transaction Id to the response headers send to client
        if (!is_null($this->transactionId)) {
            $response = $response->withAddedHeader('X-Moesif-Transaction-Id', $this->transactionId);
        }

        // Prepare event model
        $event = toEvent($requestData, $responseData, $this->moesifOptions, $request, $response);

        // Sampling percentage
        $this->samplingPercentage = getSamplingPercentage($this->config, getOrElse($event['user_id'], null), getOrElse($event['company_id'], null), $this->logger);

        // Random percentage
        $random_percentage = generate_random_percentage();

        if ($this->samplingPercentage >= $random_percentage) {

            // Add Weight
            $event['weight'] = calculateWeight($this->samplingPercentage);
            
            // Send Event to Moesif
            list($eventRespHeaders, $eventRespBody) = $this->moesifApi->createEvent($event); 

            if ($this->debug) {
                $this->logger->debug('Send Event to Moesif');
            }
            
            $eventETag = getOrElse($eventRespHeaders['x-moesif-config-etag'], null);

            if (!empty($eventETag) && 
                !empty($this->eTag) && 
                new DateTime('now', new DateTimeZone("UTC")) > $this->lastUpdatedTime->modify('+5 minutes') && 
                strcasecmp(reset($eventETag), reset($this->eTag)) == 1) 
            {
                // Update App Config
                try {
                    list($configHeaders ,$this->config) = getConfig($this->moesifApi, $this->debug, $this->logger);
                    list($this->eTag, $this->samplingPercentage, $this->lastUpdatedTime) = parseConfiguration($configHeaders, $this->config, $this->debug, $this->logger);
                } catch(Exception $e) {
                    if ($this->debug) {
                        $this->logger->debug('Error while fetching application configuration');
                    }
                }
            }
        }
        else {
            if ($this->debug) {
                $this->logger->debug("Skipped Event due to sampling percentage:".(string)$this->samplingPercentage." and random percentage: ".(string)$random_percentage);
            }
        }
        return $response;
    }
}
