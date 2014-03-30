<?php

/*
 * This file is part of the DigiDoc package.
 *
 * (c) Kristen Gilden <kristen.gilden@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KG\DigiDoc\Soap;

use KG\DigiDoc\Exception\ApiException;

/**
 * Converts SoapFault exceptions to ApiExceptions
 */
class Client extends \SoapClient
{
    /**
     * @var \SoapClient
     */
    private $client;

    /**
     * @param \SoapClient $client
     */
    public function __construct(\SoapClient $client)
    {
        $this->client = $client;
    }

    /**
     * {@inheritDoc}
     */
    public function SoapClient(\SoapClient $client)
    {
        return new static($client);
    }

    /**
     * {@inheritDoc}
     */
    public function __call($function_name, $arguments)
    {
        return $this->client->__call($function_name, $arguments);
    }

    /**
     * {@inheritDoc}
     */
    public function __doRequest($request, $location, $action, $version, $one_way = 0)
    {
        return $this->client->__doRequest($request, $location, $action, $version, $one_way);
    }

    /**
     * {@inheritDoc}
     */
    public function __getFunctions()
    {
        return $this->client->__doRequest();
    }

    /**
     * {@inheritDoc}
     */
    public function __getLastRequest()
    {
        return $this->client->__getLastRequest();
    }

    /**
     * {@inheritDoc}
     */
    public function __getLastRequestHeaders()
    {
        return $this->client->__getLastRequestHeaders();
    }

    /**
     * {@inheritDoc}
     */
    public function __getLastResponse()
    {
        return $this->client->__getLastResponse();
    }

    /**
     * {@inheritDoc}
     */
    public function __getLastResponseHeaders()
    {
        return $this->client->__getLastResponseHeaders();
    }

    /**
     * {@inheritDoc}
     */
    public function __getTypes()
    {
        return $this->client->__getTypes();
    }

    /**
     * {@inheritDoc}
     */
    public function __setCookie($name, $value = null)
    {
        return $this->client->__setCookie($name, $value);
    }

    /**
     * {@inheritDoc}
     */
    public function __setLocation($new_location = null)
    {
        return $this->client->__setLocation($new_location);
    }

    /**
     * {@inheritDoc}
     */
    public function __setSoapHeaders($soapHeaders = null)
    {
        return $this->client->__setSoapHeaders($soapHeaders);
    }

    /**
     * {@inheritDoc}
     */
    public function __soapCall($function_name, $arguments, $options = array(), $input_headers = null, &$output_headers = null)
    {
        try {
            return $this->client->__soapCall($function_name, $arguments, $options, $input_headers, $output_headers);
        } catch (\SoapFault $e) {
            throw ApiException::createFromSoapFault($e);
        }
    }
}
