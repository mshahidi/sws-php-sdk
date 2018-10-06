<?php

namespace Serato\SwsSdk;

use Serato\SwsSdk\Sdk;
use Serato\SwsSdk\Command;
use Serato\SwsSdk\Result;
use Serato\SwsSdk\Exception\BadRequestException;
use Serato\SwsSdk\Exception\AccessDeniedException;
use Serato\SwsSdk\Exception\UnauthorizedException;
use Serato\SwsSdk\Exception\ResourceNotFoundException;
use Serato\SwsSdk\Exception\ServerApplicationErrorException;
use Serato\SwsSdk\Exception\ServiceUnavailableException;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ClientException as GuzzleClientException;
use GuzzleHttp\Exception\ServerException as GuzzleServerException;
use Promise\PromiseInterface;
use InvalidArgumentException;

abstract class Client extends GuzzleClient
{
    /**
     * Client application ID
     *
     * @var string
     */
    protected $appId;

    /**
     * Client application password
     *
     * @var string
     */
    protected $appPassword;

    /**
     * Client configuration data
     *
     * @var array
     */
    protected $config;

    /**
     * The `$args` parameter is an array containing any value configuration for
     * a `GuzzleHttp\Client`. See the Guzzle docs for more info.
     *
     * @link http://guzzle.readthedocs.io/en/latest/quickstart.html Guzzle documentation
     *
     * @param array     $args           Client configuration arguments
     * @param string    $appId          Client application ID
     * @param string    $appPassword    Client application password
     *
     * @return void
     */
    public function __construct(array $args, string $appId, string $appPassword)
    {
        $this->appId        = $appId;
        $this->appPassword  = $appPassword;
        $this->config       = $args;
        unset($args[Sdk::BASE_URI]);
        $argsNoBaseUri      = $args;

        parent::__construct($argsNoBaseUri);
    }

    /**
     * Create a Command object with the provided args
     *
     * @param string $name Command name
     * @param array $args Command arguments
     *
     * @return Command
     */
    public function getCommand($name, array $args = [])
    {
        if (!isset($this->getCommandMap()[$name])) {
            throw new InvalidArgumentException(
                'Invalid command name `$name`'
            );
        }
        $className = $this->getCommandMap()[$name];
        return new $className(
            $this->appId,
            $this->appPassword,
            $this->getBaseUri(),
            $args
        );
    }

    /**
     * Execute a named Command with arguments
     *
     * @param string    $name           Command name
     * @param array     $args           Command arguments
     * @param string    $bearerToken    Bearer token (required for using that use JWT-based auth)
     * @param array     $options        Options to send with Command's Request
     *
     * @return Result
     */
    public function execute($name, array $args = [], $bearerToken = '', array $options = [])
    {
        return $this->executeCommand($this->getCommand($name, $args), $bearerToken, $options);
    }

    /**
     * Execute a Command on the Client
     *
     * @param Command   $command        Command object
     * @param string    $bearerToken    Bearer token (required for using that use JWT-based auth)
     * @param array     $options        Options to send with Command's Request
     *
     * @return Result
     *
     * @throws BadRequestException
     * @throws UnauthorizedException
     * @throws AccessDeniedException
     * @throws ResourceNotFoundException
     * @throws ServerApplicationErrorException
     * @throws ServiceUnavailableException
     * @throws RequestException
     */
    public function executeCommand(Command $command, $bearerToken = '', array $options = [])
    {
        if (is_a($command, 'Serato\SwsSdk\CommandBearerTokenAuth')) {
            $request = $command->getRequest($bearerToken);
        } else {
            $request = $command->getRequest();
        }
        $promise = $this->sendAsync(
            $request,
            array_merge(
                [
                    'timeout' => $this->config['timeout'],
                    RequestOptions::SYNCHRONOUS =>  true
                ],
                $options
            )
        );

        try {
            // $promise->wait() returns a Response object
            return new Result($promise->wait());
        } catch (GuzzleClientException $e) {
            switch ($e->getResponse()->getStatusCode()) {
                case 400:
                    throw new BadRequestException($e);
                    break;
                case 401:
                    throw new UnauthorizedException($e);
                    break;
                case 403:
                    throw new AccessDeniedException($e);
                case 404:
                    throw new ResourceNotFoundException($e);
                default:
                    // Re-throw the original error
                    throw $e;
            }
        } catch (GuzzleServerException $e) {
            switch ($e->getResponse()->getStatusCode()) {
                case 500:
                    throw new ServerApplicationErrorException($e);
                    break;
                case 503:
                    throw new ServiceUnavailableException($e);
                default:
                    // Re-throw the original error
                    throw $e;
            }
        }
        // Re-throw the original error
        throw $e;
    }

    /**
     * Asyncronously execute a Command on the Client
     *
     * @param string    $name           Command name
     * @param array     $args           Command arguments
     * @param string    $bearerToken    Bearer token (required for using that use JWT-based auth)
     * @param array     $options        Options to send with Command's Request
     *
     * @return PromiseInterface
     */
    public function executeAsync($name, array $args, $bearerToken = '', array $options = [])
    {
        $command = $this->getCommand($name, $args);
        if (!is_a($value, '\Serato\SwsSdk\Command\CommandBearerTokenAuth')) {
            $request = $command->getRequest($bearerToken);
        } else {
            $request = $command->getRequest();
        }
        return $this->sendAsync(
            $request,
            array_merge(['timeout' => $this->config['timeout']], $options)
        );
    }

    public function __call($name, array $args)
    {
        # Magic method that allows Commands to be executed on a Client by using
        # a method name that matches the Command name, albeit with a lower case
        # first letter.
        #
        # Method names can also be suffixed with `Async` to excecute
        # the command asynchronously.
        #
        # eg. If a Client instance called $client has a Command available
        # called `GetProducts` the Command can be excuted as:
        #
        #   $client->getProducts($args);
        #   $client->getProductsAsync($args);

        $bearerToken = '';
        $params = [];

        if (isset($args[0])) {
            if (is_string($args[0])) {
                $bearerToken = $args[0];
            }
            if (is_array($args[0])) {
                $params = $args[0];
            }
        }

        if (isset($args[1]) && is_array($args[1])) {
            $params = $args[1];
        }

        if (substr($name, -5) === 'Async') {
            return $this->executeAsync(ucfirst(substr($name, 0, -5)), $params, $bearerToken);
        }

        return $this->execute(ucfirst($name), $params, $bearerToken);
    }

    /**
     * Get the base URI for the Client
     *
     * @return string
     */
    abstract public function getBaseUri();

    /**
     * Get an array of all valid commands for the Client.
     * The key of the array is command's name and the value is the Command
     * class name
     *
     * @return array
     */
    abstract public function getCommandMap();
}
