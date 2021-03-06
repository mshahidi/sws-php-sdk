<?php

namespace Serato\SwsSdk\License;

use Serato\SwsSdk\Sdk;
use Serato\SwsSdk\Client;

/**
 * Client used to interact with SWS License service.
 *
 * @method \Psr\Http\Message\RequestInterface getProducts(array $args)
 * @method \Psr\Http\Message\RequestInterface getProduct(array $args)
 * @method \Psr\Http\Message\RequestInterface createProduct(array $args)
 * @method \Psr\Http\Message\RequestInterface updateProduct(array $args)
 */
class LicenseClient extends Client
{
    /**
     * Get the base URI for the Client
     *
     * @return string
     */
    public function getBaseUri(): string
    {
        return $this->config[Sdk::BASE_URI][Sdk::BASE_URI_LICENSE];
    }

    /**
     * Get an array of all valid commands for the Client.
     * The key of the array is command's name and the value is the Command
     * class name
     *
     * @return array
     */
    public function getCommandMap(): array
    {
        return [
            'GetProducts'   => '\\Serato\\SwsSdk\\License\\Command\\ProductList',
            'GetProduct'    => '\\Serato\\SwsSdk\\License\\Command\\ProductGet',
            'CreateProduct' => '\\Serato\\SwsSdk\\License\\Command\\ProductCreate',
            'UpdateProduct' => '\\Serato\\SwsSdk\\License\\Command\\ProductUpdate',
            'DeleteProduct' => '\\Serato\\SwsSdk\\License\\Command\\ProductDelete',
        ];
    }
}
