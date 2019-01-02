<?php

namespace AlibabaCloud\Client\Tests\Unit\Request;

use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;
use AlibabaCloud\Client\Request\RpcRequest;
use PHPUnit\Framework\TestCase;

/**
 * Class AcsTraitTest
 *
 * @package            AlibabaCloud\Client\Tests\Unit\Request
 *
 * @coversDefaultClass \AlibabaCloud\Client\Request\Request
 */
class AcsTraitTest extends TestCase
{
    public function testAction()
    {
        // Setup
        $action  = 'action';
        $request = new RpcRequest();

        // Test
        $request->action($action);

        // Assert
        self::assertEquals($action, $request->action);
    }

    public function testVersion()
    {
        // Setup
        $version = 'version';
        $request = new RpcRequest();

        // Test
        $request->version($version);

        // Assert
        self::assertEquals($version, $request->version);
    }

    public function testProduct()
    {
        // Setup
        $product = 'version';
        $request = new RpcRequest();

        // Test
        $request->product($product);

        // Assert
        self::assertEquals($product, $request->product);
    }

    public function testLocationEndpointType()
    {
        // Setup
        $endpointType = 'endpointType';
        $request      = new RpcRequest();

        // Test
        $request->endpointType($endpointType);

        // Assert
        self::assertEquals(
            $endpointType,
            $request->endpointType
        );
    }

    public function testLocationServiceCode()
    {
        // Setup
        $serviceCode = 'serviceCode';
        $request     = new RpcRequest();

        // Test
        $request->serviceCode($serviceCode);

        // Assert
        self::assertEquals(
            $serviceCode,
            $request->serviceCode
        );
    }

    /**
     * @throws \AlibabaCloud\Client\Exception\ClientException
     */
    public function testRealRegionIdOnRequest()
    {
        // Setup
        $regionId = 'regionId';
        $request  = new RpcRequest();

        // Test
        $request->regionId($regionId);

        // Assert
        self::assertEquals(
            $regionId,
            $request->realRegionId()
        );
    }

    /**
     * @throws \AlibabaCloud\Client\Exception\ClientException
     */
    public function testRealRegionIdOnClient()
    {
        // Setup
        $regionId = 'regionId';
        AlibabaCloud::accessKeyClient('foo', 'bar')
                    ->regionId($regionId)
                    ->name('regionId');
        $request = new RpcRequest();

        // Test
        $request->client('regionId');

        // Assert
        self::assertEquals(
            $regionId,
            $request->realRegionId()
        );
    }

    /**
     * @throws \AlibabaCloud\Client\Exception\ClientException
     */
    public function testRealRegionIdOnGlobal()
    {
        // Setup
        $regionId = 'regionId';
        AlibabaCloud::accessKeyClient('foo', 'bar')
                    ->name('regionId');
        AlibabaCloud::setGlobalRegionId($regionId);

        // Test
        $request = new RpcRequest();
        $request->client('regionId');

        // Assert
        self::assertEquals(
            $regionId,
            $request->realRegionId()
        );
    }

    /**
     * @expectedException        \AlibabaCloud\Client\Exception\ClientException
     * @expectedExceptionMessage Missing required 'RegionId' for Request
     * @throws                   \AlibabaCloud\Client\Exception\ClientException
     */
    public function testRealRegionIdException()
    {
        // Setup
        $regionId = 'regionId';
        AlibabaCloud::accessKeyClient('foo', 'bar')
                    ->name('regionId');
        AlibabaCloud::setGlobalRegionId(null);

        // Test
        $request = new RpcRequest();
        $request->client('regionId');

        // Assert
        self::assertEquals(
            $regionId,
            $request->realRegionId()
        );
    }

    /**
     * @throws ClientException
     * @throws ServerException
     */
    public function testFindDomainInConfig()
    {
        // Setup
        $regionId = 'cn-shanghai';
        AlibabaCloud::accessKeyClient('foo', 'bar')
                    ->name($regionId);
        AlibabaCloud::setGlobalRegionId($regionId);

        // Test
        $request = new RpcRequest();
        $request->client($regionId);
        $request->product('ecs');
        $request->resolveUri();

        // Assert
        self::assertEquals(
            'ecs-cn-hangzhou.aliyuncs.com',
            $request->uri->getHost()
        );
    }

    public function testFindDomainOnLocationService()
    {
        // Setup
        $regionId = 'cn-shanghai';
        AlibabaCloud::accessKeyClient('foo', 'bar')
                    ->name($regionId);
        AlibabaCloud::setGlobalRegionId($regionId);

        // Test
        $request = new RpcRequest();
        $request->client($regionId);
        $request->product('ecs2');
        $request->serviceCode('ecs');

        // Assert
        try {
            $request->resolveUri();
        } catch (ServerException $exception) {
            self::assertEquals('InvalidAccessKeyId.NotFound', $exception->getErrorCode());
        } catch (ClientException $exception) {
            self::assertEquals(\ALIBABA_CLOUD_SERVER_UNREACHABLE, $exception->getErrorCode());
        }
    }
}
