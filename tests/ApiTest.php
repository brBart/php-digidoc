<?php

/*
 * This file is part of the DigiDoc package.
 *
 * (c) Kristen Gilden <kristen.gilden@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KG\DigiDoc\Tests\ApiTest;

use KG\DigiDoc\Api;
use KG\DigiDoc\Container;

class ApiTest extends \PHPUnit_Framework_TestCase
{
    public function testCreateCreatesNewContainer()
    {
        $client = $this->getMockClient();
        $this->mockStartSession($client, $sessionId = 42);

        $client
            ->expects($this->at(1))
            ->method('__soapCall')
            ->with('CreateSignedDoc')
        ;

        $api = new Api($client, $this->getMockEncoder(), $this->getMockTracker());

        $container = $api->create();

        $this->assertInstanceOf('KG\DigiDoc\Container', $container);
        $this->assertEquals($sessionId, $container->getSession()->getId());
    }

    public function testOpenCreatesNewContainer()
    {
        $info = $this->getMockSignedDocInfo();
        $info->DataFileInfo = $info->SignatureInfo = null;

        $client = $this->getMockClient();

        $client
            ->expects($this->once())
            ->method('__soapCall')
            ->with('StartSession', $this->anything())
            ->will($this->returnValue(array(
                'Status'        => 'OK',
                'Sesscode'      => $sessionId = 42,
                'SignedDocInfo' => $info,
            )))
        ;

        $api = new Api($client, $this->getMockEncoder(), $this->getMockTracker());

        $container = $api->open('/path/to/file.bdoc');

        $this->assertInstanceOf('KG\DigiDoc\Container', $container);
        $this->assertEquals($sessionId, $container->getSession()->getId());
    }

    public function testOpenAddsFilesToContainer()
    {
        $fileInfo = $this->getMockDataFileInfo();
        $fileInfo->Id = 'example.doc';

        $info = $this->getMockSignedDocInfo();
        $info->DataFileInfo = $fileInfo;
        $info->SignatureInfo = null;

        $client = $this->getMockClient();

        $client
            ->expects($this->once())
            ->method('__soapCall')
            ->with('StartSession', $this->anything())
            ->will($this->returnValue(array(
                'Status'        => 'OK',
                'Sesscode'      => $sessionId = 42,
                'SignedDocInfo' => $info,
            )))
        ;

        $api = new Api($client, $this->getMockEncoder(), $this->getMockTracker());

        $container = $api->open('/path/to/file.bdoc');
        $file = $container->getFiles()->first();

        $this->assertSame($fileInfo->Id, $file->getId());
    }

    public function testOpenAddsSignaturesToContainer()
    {
        $signatureInfo = $this->getMockSignatureInfo();
        $signatureInfo->Id = 'S0';

        $info = $this->getMockSignedDocInfo();
        $info->DataFileInfo = null;
        $info->SignatureInfo = $signatureInfo;

        $client = $this->getMockClient();

        $client
            ->expects($this->once())
            ->method('__soapCall')
            ->with('StartSession', $this->anything())
            ->will($this->returnValue(array(
                'Status'        => 'OK',
                'Sesscode'      => $sessionId = 42,
                'SignedDocInfo' => $info,
            )))
        ;

        $api = new Api($client, $this->getMockEncoder(), $this->getMockTracker());

        $container = $api->open('/path/to/file.bdoc');
        $signature = $container->getSignatures()->first();

        $this->assertSame($signatureInfo->Id, $signature->getId());
    }

    public function testCloseClosesSession()
    {
        $session = $this->getMockSession();

        $session
            ->expects($this->once())
            ->method('getId')
            ->will($this->returnValue($sessionId = 69))
        ;

        $container = $this->getMockContainer();

        $container
            ->expects($this->once())
            ->method('getSession')
            ->will($this->returnValue($session))
        ;

        $client = $this->getMockClient();

        $client
            ->expects($this->once())
            ->method('__soapCall')
            ->with('CloseSession', array($sessionId))
        ;

        $api = new Api($client, $this->getMockEncoder(), $this->getMockTracker());
        $api->close($container);
    }

    /**
     * @expectedException \KG\DigiDoc\Exception\ApiException
     * @expectedExceptionMessage DigiDoc container must be merged with Api
     */
    public function testUpdateFailsIfContainerNotMerged()
    {
        $api = new Api($this->getMockClient());
        $api->update($this->getMockContainer());
    }

    public function testUpdateAddsContainerToTrackerIfMergeTrue()
    {
        $container = new Container($this->getMockSession());

        $tracker = $this->getMockTracker();
        $tracker
            ->expects($this->at(1))
            ->method('add')
            ->with($container)
        ;

        $api = new Api($this->getMockClient(), null, $tracker);
        $api->update($container, true);
    }

    /**
     * @return \KG\DigiDoc\Container|PHPUnit_Framework_MockObject_MockObject
     */
    private function getMockContainer()
    {
        return $this
            ->getMockBuilder('KG\DigiDoc\Container')
            ->disableOriginalConstructor()
            ->getMock()
        ;
    }

    /**
     * @return \KG\DigiDoc\Certificate|PHPUnit_Framework_MockObject_MockObject
     */
    private function getMockCertificate()
    {
        return $this
            ->getMockBuilder('KG\DigiDoc\Certificate')
            ->disableOriginalConstructor()
            ->getMock()
        ;
    }

    /**
     * @return \SoapClient|PHPUnit_Framework_MockObject_MockObject
     */
    private function getMockClient()
    {
        return $this
            ->getMockBuilder('KG\DigiDoc\Soap\Client')
            ->getMock()
        ;
    }

    /**
     * @return \KG\DigiDoc\Soap\Wsdl\DataFileInfo|PHPUnit_Framework_MockObject_MockObject
     */
    private function getMockDataFileInfo()
    {
        return $this
            ->getMockBuilder('KG\DigiDoc\Soap\Wsdl\DataFileInfo')
            ->disableOriginalConstructor()
            ->getMock()
        ;
    }

    /**
     * @return \KG\DigiDoc\Encoder|PHPUnit_Framework_MockObject_MockObject
     */
    private function getMockEncoder()
    {
        return $this->getMock('KG\DigiDoc\Encoder');
    }

    /**
     * @return \KG\DigiDoc\Soap\Wsdl\SignedDocInfo|PHPUnit_Framework_MocObject_MocObject
     */
    private function getMockSignedDocInfo()
    {
        return $this
            ->getMockBuilder('KG\DigiDoc\Soap\Wsdl\SignedDocInfo')
            ->disableOriginalConstructor()
            ->getMock()
        ;
    }

    /**
     * @return \KG\DigiDoc\Session|PHPUnit_Framework_MockObject_MockObject
     */
    private function getMockSession()
    {
        return $this
            ->getMockBuilder('KG\DigiDoc\Session')
            ->disableOriginalConstructor()
            ->getMock()
        ;
    }

    /**
     * @return \KG\DigiDoc\Signature|PHPUnit_Framework_MockObject_MockObject
     */
    private function getMockSignature()
    {
        return $this
            ->getMockBuilder('KG\DigiDoc\Signature')
            ->disableOriginalConstructor()
            ->getMock()
        ;
    }

    /**
     * @return \KG\DigiDoc\Soap\Wsdl\SignatureInfo|PHPUnit_Framework_MockObject_MockObject
     */
    private function getMockSignatureInfo()
    {
        return $this
            ->getMockBuilder('KG\DigiDoc\Soap\Wsdl\SignatureInfo')
            ->disableOriginalConstructor()
            ->getMock()
        ;
    }

    /**
     * @return \KG\DigiDoc\Tracker|PHPUnit_Framework_MockObject_MockObject
     */
    private function getMockTracker()
    {
        return $this->getMock('KG\DigiDoc\Tracker');
    }

    /**
     * Mocks the start session soap call.
     */
    private function mockStartSession(\PHPUnit_Framework_MockObject_MockObject $client, $sessionId)
    {
        $client
            ->expects($this->at(0))
            ->method('__soapCall')
            ->with('StartSession')
            ->will($this->returnValue(array('Sesscode' => $sessionId)))
        ;
    }
}
