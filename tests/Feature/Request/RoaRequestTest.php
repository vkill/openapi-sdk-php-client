<?php

namespace AlibabaCloud\Client\Tests\Feature\Request;

use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;
use AlibabaCloud\Client\Request\RoaRequest;
use PHPUnit\Framework\TestCase;

/**
 * Class RoaRequestTest
 *
 * @package   AlibabaCloud\Client\Tests\Feature\Request
 */
class RoaRequestTest extends TestCase
{
    public function testRoa()
    {
        // Setup
        $clusterId = \time();

        // Test
        try {
            if (!AlibabaCloud::has(\ALIBABA_CLOUD_GLOBAL_CLIENT)) {
                AlibabaCloud::accessKeyClient('key', 'secret')
                            ->asGlobalClient()
                            ->regionId('cn-hangzhou');
            }

            $result = (new RoaRequest())->pathPattern('/clusters/[ClusterId]/services')
                                        ->method('GET')
                                        ->product('CS')
                                        ->version('2015-12-15')
                                        ->action('DescribeClusterServices')
                                        ->withClusterId($clusterId)
                                        ->request();
            \assertNotEmpty($result->toArray());
        } catch (ClientException $e) {
            self::assertContains(
                $e->getErrorCode(),
                [
                    \ALIBABA_CLOUD_SERVER_UNREACHABLE,
                ]
            );
        } catch (ServerException $e) {
            self::assertContains(
                $e->getErrorMessage(),
                [
                    "cluster ($clusterId) not found in our records",
                    'Specified access key is not found.',
                ]
            );
        }
    }
}
