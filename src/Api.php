<?php

/*
 * This file is part of the DigiDoc package.
 *
 * (c) Kristen Gilden <kristen.gilden@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KG\DigiDoc;

use KG\DigiDoc\Exception\ApiException;
use KG\DigiDoc\Exception\RuntimeException;
use KG\DigiDoc\Collections\FileCollection;
use KG\DigiDoc\Collections\SignatureCollection;
use KG\DigiDoc\Soap\Wsdl\SignedDocInfo;

class Api
{
    const CONTENT_TYPE_HASHCODE = 'HASHCODE';
    const CONTENT_TYPE_EMBEDDED = 'EMBEDDED_BASE64';

    const DOC_FORMAT = 'BDOC';
    const DOC_VERSION = '2.1';

    /**
     * @var \SoapClient
     */
    private $client;

    /**
     * @var Encoder
     */
    private $encoder;

    /**
     * @var Tracker
     */
    private $tracker;

    /**
     * @param \SoapClient $client
     * @param Encoder
     */
    public function __construct(\SoapClient $client, Encoder $encoder, Tracker $tracker)
    {
        $this->client = $client;
        $this->encoder = $encoder;
        $this->tracker = $tracker;
    }

    /**
     * Creates a DigiDoc container.
     *
     * @api
     *
     * @return Container
     */
    public function create()
    {
        $result = $this->call('startSession', ['', '', true, '']);
        $result = $this->call('createSignedDoc', [$sessionId = $result['Sesscode'], self::DOC_FORMAT, self::DOC_VERSION]);

        $container = new Container(new Session($sessionId));

        $this->tracker->add($container);

        return $container;
    }

    /**
     * Opens a DigiDoc container on the local filesystem.
     *
     * @api
     *
     * @param string $path Path to the DigiDoc container
     *
     * @return Container
     */
    public function open($path)
    {
        $result = $this->call('startSession', ['', $this->encoder->encodeFileContent($path), true, '']);

        $container = new Container(
            new Session($result['Sesscode']),
            new FileCollection($this->createAndTrack($result['SignedDocInfo']->DataFileInfo, 'KG\DigiDoc\File')),
            new SignatureCollection($this->createAndTrack($result['SignedDocInfo']->SignatureInfo, 'KG\DigiDoc\Signature'))
        );

        $this->tracker->add($container);

        return $container;
    }

    /**
     * Closes the session between the local and remote systems of the given
     * DigiDoc container. This must be the last method called after all other
     * transactions.
     *
     * @api
     *
     * @param Container $container
     */
    public function close(Container $container)
    {
        $this->call('closeSession', [$container->getSession()->getId()]);
    }

    /**
     * Updates the state in the remote api to match the contents of the given
     * DigiDoc container. The following is done in the same order:
     *
     *  - new files uploaded;
     *  - new signatures added and challenges injected;
     *  - signatures with solutions to challenges sealed;
     *
     * @api
     *
     * @param Container $container
     */
    public function update(Container $container)
    {
        $this->failIfNotMerged($container);

        $session = $container->getSession();
        $tracker = $this->tracker;

        $untrackedFn = function ($object) use ($tracker) {
            return !$tracker->has($object);
        };

        $this
            ->addFiles($session, $container->getFiles()->filter($untrackedFn))
            ->addSignatures($session, $container->getSignatures()->filter($untrackedFn))
            ->sealSignatures($session, $container->getSignatures()->getSealable())
        ;
    }

    /**
     * Downloads the contents of the DigiDoc container from the server and
     * writes them to the given local path. If you modify a container and call
     * this method without prior updating, the changes will not be reflected
     * in the written file.
     *
     * @api
     *
     * @param Container $container
     * @param string  $path
     */
    public function write(Container $container, $path)
    {
        $this->failIfNotMerged($container);

        $result = $this->call('getSignedDoc', [$container->getSession()->getId()]);

        file_put_contents($path, $this->encoder->decode($result['SignedDocData']));
    }

    /**
     * Merges the DigiDoc container back with the api. This is necessary, when
     * working with a container over multiple requests and storing it somewhere
     * (session, database etc) inbetween the requests.
     *
     * @param Container $container
     */
    public function merge(Container $container)
    {
        if ($this->tracker->has($container)) {
            return;
        }

        $this->tracker->add($container);
        $this->tracker->add($container->getFiles()->toArray());
        $this->tracker->add($container->getSignatures()->toArray());
    }

    private function addFiles(Session $session, FileCollection $files)
    {
        foreach ($files as $file) {
            $this->call('addDataFile', [
                $session->getId(),
                $file->getName(),
                $file->getMimeType(),
                self::CONTENT_TYPE_EMBEDDED,
                $file->getSize(),
                '',
                '',
                $this->encoder->encodeFileContent($file->getPathname()),
            ]);

            $this->tracker->add($file);
        }

        return $this;
    }

    private function addSignatures(Session $session, SignatureCollection $signatures)
    {
        foreach ($signatures as $signature) {
            $result = $this->call('prepareSignature', [$session->getId(), $signature->getCertificate()->getSignature(), $signature->getCertificate()->getId()]);

            $signature->setId($result['SignatureId']);
            $signature->setChallenge($result['SignedInfoDigest']);

            $this->tracker->add($signature);
        }

        return $this;
    }

    private function sealSignatures(Session $session, SignatureCollection $signatures)
    {
        foreach ($signatures as $signature) {
            $result = $this->call('finalizeSignature', [$session->getId(), $signature->getId(), $signature->getSolution()]);

            $signature->seal();
        }

        return $this;
    }

    private function getById($remoteObjects, $id)
    {
        $remoteObjects = !is_array($remoteObjects) ? [$remoteObjects] : $remoteObjects;

        foreach ($remoteObjects as $remoteObject) {
            if ($remoteObject->Id === $id) {
                return $remoteObject;
            }
        }

        throw new RuntimeException(sprintf('No remote object with id "%s" was not found.', $id));
    }

    private function createAndTrack($remoteObjects, $class)
    {
        if (is_null($remoteObjects)) {
            return [];
        }

        $remoteObjects = !is_array($remoteObjects) ? [$remoteObjects] : $remoteObjects;

        $objects = [];

        foreach ($remoteObjects as $remoteObject) {
            $objects[] = $object = $class::createFromSoap($remoteObject);

            $this->tracker->add($object);
        }


        return $objects;
    }

    private function call($method, array $arguments)
    {
        return $this->client->__soapCall(ucfirst($method), $arguments);
    }

    /**
     * @param Container $container
     *
     * @throws ApiException If the DigiDoc container is not merged
     */
    private function failIfNotMerged(Container $container)
    {
        if (!$this->tracker->has($container)) {
            throw ApiException::createNotTracked($container);
        }
    }
}
