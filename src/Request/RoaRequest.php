<?php

namespace AlibabaCloud\Client\Request;

use Exception;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use AlibabaCloud\Client\SDK;
use AlibabaCloud\Client\Filter\Filter;
use AlibabaCloud\Client\Filter\ApiFilter;
use AlibabaCloud\Client\Credentials\StsCredential;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;
use AlibabaCloud\Client\Credentials\AccessKeyCredential;
use AlibabaCloud\Client\Credentials\BearerTokenCredential;
use AlibabaCloud\Client\Request\Traits\DeprecatedRoaTrait;

/**
 * RESTful ROA Request.
 *
 * @package   AlibabaCloud\Client\Request
 */
class RoaRequest extends Request
{
    use DeprecatedRoaTrait;

    /**
     * @var string
     */
    private static $headerSeparator = "\n";

    /**
     * @var string
     */
    public $pathPattern = '/';

    /**
     * @var array
     */
    public $pathParameters = [];

    /**
     * @var string
     */
    private $dateTimeFormat = "D, d M Y H:i:s \G\M\T";

    /**
     * Resolve request parameter.
     *
     * @throws ClientException
     * @throws Exception
     */
    public function resolveParameters()
    {
        $this->resolveQuery();
        $this->resolveBody();
        $this->resolveHeaders();
    }

    private function resolveQuery()
    {
        if (!isset($this->options['query']['Version'])) {
            $this->options['query']['Version'] = $this->version;
        }
    }

    private function resolveBody()
    {
        if (isset($this->options['form_params']) && !isset($this->options['body'])) {
            $this->options['body']                    = $this->ksortAndEncode($this->data);
            $this->options['headers']['Content-MD5']  = base64_encode(md5($this->options['body'], true));
            $this->options['headers']['Content-Type'] = 'application/x-www-form-urlencoded; charset=UTF-8';
            unset($this->options['form_params']);
        }
    }

    /**
     * @param array $data
     *
     * @return string
     */
    public function ksortAndEncode(array $data)
    {
        ksort($data);
        $string = '';
        foreach ($data as $key => $value) {
            $encode = urlencode($value);
            $string .= "$key=$encode&";
        }

        if (0 < count($data)) {
            $string = substr($string, 0, -1);
        }

        return $string;
    }

    /**
     * @throws ClientException
     * @throws ServerException
     */
    private function resolveHeaders()
    {
        $this->options['headers']['x-acs-version']   = $this->version;
        $this->options['headers']['x-acs-region-id'] = $this->realRegionId();
        $this->options['headers']['Date']            = gmdate($this->dateTimeFormat);
        $this->options['headers']['Accept']          = self::formatToAccept($this->format);
        $this->resolveSignature();
        $this->resolveContentType();
        $this->resolveSecurityToken();
        $this->resolveBearerToken();
        $this->options['headers']['Authorization'] = $this->signature();
    }

    /**
     * Returns the accept header according to format.
     *
     * @param string $format
     *
     * @return string
     */
    private static function formatToAccept($format)
    {
        switch (\strtoupper($format)) {
            case 'JSON':
                return 'application/json';
            case 'XML':
                return 'application/xml';
            default:
                return 'application/octet-stream';
        }
    }

    /**
     * @throws ClientException
     * @throws Exception
     */
    private function resolveSignature()
    {
        $signature                                           = $this->httpClient()->getSignature();
        $this->options['headers']['x-acs-signature-method']  = $signature->getMethod();
        $this->options['headers']['x-acs-signature-nonce']   = Uuid::uuid1()->toString();
        $this->options['headers']['x-acs-signature-version'] = $signature->getVersion();
        if ($signature->getType()) {
            $this->options['headers']['x-acs-signature-type'] = $signature->getType();
        }
    }

    private function resolveContentType()
    {
        if (!isset($this->options['headers']['Content-Type'])) {
            $this->options['headers']['Content-Type'] = "{$this->options['headers']['Accept']};chrset=utf-8";
        }
    }

    /**
     * @throws ClientException
     * @throws ServerException
     */
    private function resolveSecurityToken()
    {
        if ($this->credential() instanceof StsCredential && $this->credential()->getSecurityToken()) {
            $this->options['headers']['x-acs-security-token'] = $this->credential()->getSecurityToken();
        }
    }

    /**
     * @throws ClientException
     * @throws ServerException
     */
    private function resolveBearerToken()
    {
        if ($this->credential() instanceof BearerTokenCredential) {
            $this->options['headers']['x-acs-bearer-token'] = $this->credential()->getBearerToken();
        }
    }

    /**
     * Sign the request message.
     *
     * @return string
     * @throws ClientException
     * @throws ServerException
     */
    private function signature()
    {
        /**
         * @var AccessKeyCredential $credential
         */
        $credential  = $this->credential();
        $accessKeyId = $credential->getAccessKeyId();
        $signature   = $this->httpClient()
                            ->getSignature()
                            ->sign(
                                $this->stringToSign(),
                                $credential->getAccessKeySecret()
                            );

        return "acs $accessKeyId:$signature";
    }

    /**
     * @return string
     */
    public function stringToSign()
    {
        return $this->headerStringToSign() . $this->resourceStringToSign();
    }

    /**
     * @return string
     */
    private function headerStringToSign()
    {
        $string = $this->method . self::$headerSeparator;
        if (isset($this->options['headers']['Accept'])) {
            $string .= $this->options['headers']['Accept'];
        }
        $string .= self::$headerSeparator;

        if (isset($this->options['headers']['Content-MD5'])) {
            $string .= $this->options['headers']['Content-MD5'];
        }
        $string .= self::$headerSeparator;

        if (isset($this->options['headers']['Content-Type'])) {
            $string .= $this->options['headers']['Content-Type'];
        }
        $string .= self::$headerSeparator;

        if (isset($this->options['headers']['Date'])) {
            $string .= $this->options['headers']['Date'];
        }
        $string .= self::$headerSeparator;

        $string .= $this->acsHeaderString();

        return $string;
    }

    /**
     * Construct standard Header for Alibaba Cloud.
     *
     * @return string
     */
    private function acsHeaderString()
    {
        $array = [];
        foreach ($this->options['headers'] as $headerKey => $headerValue) {
            $key = strtolower($headerKey);
            if (strpos($key, 'x-acs-') === 0) {
                $array[$key] = $headerValue;
            }
        }
        ksort($array);
        $string = '';
        foreach ($array as $sortMapKey => $sortMapValue) {
            $string .= $sortMapKey . ':' . $sortMapValue . self::$headerSeparator;
        }

        return $string;
    }

    /**
     * @return string
     */
    private function resourceStringToSign()
    {
        $this->uri = $this->uri->withPath($this->resolvePath())
                               ->withQuery(
                                   $this->ksortAndEncode(
                                       isset($this->options['query'])
                                           ? $this->options['query']
                                           : []
                                   )
                               );

        return $this->uri->getPath() . '?' . $this->uri->getQuery();
    }

    /**
     * Assign path parameters to the url.
     *
     * @return string
     */
    private function resolvePath()
    {
        $result = $this->pathPattern;
        foreach ($this->pathParameters as $pathKey => $apiValue) {
            $target = "[$pathKey]";
            $result = str_replace($target, $apiValue, $result);
        }

        return $result;
    }

    /**
     * Set path parameter by name.
     *
     * @param string $name
     * @param string $value
     *
     * @return RoaRequest
     * @throws ClientException
     */
    public function pathParameter($name, $value)
    {
        Filter::name($name);

        if ($value === '') {
            throw new ClientException(
                'Value cannot be empty',
                SDK::INVALID_ARGUMENT
            );
        }

        $this->pathParameters[$name] = $value;

        return $this;
    }

    /**
     * Set path pattern.
     *
     * @param string $pattern
     *
     * @return self
     * @throws ClientException
     */
    public function pathPattern($pattern)
    {
        ApiFilter::pattern($pattern);

        $this->pathPattern = $pattern;

        return $this;
    }

    /**
     * Magic method for set or get request parameters.
     *
     * @param string $name
     * @param mixed  $arguments
     *
     * @return $this
     */
    public function __call($name, $arguments)
    {
        if (\strpos($name, 'get') === 0) {
            $parameterName = $this->propertyNameByMethodName($name);

            return $this->__get($parameterName);
        }

        if (\strpos($name, 'with') === 0) {
            $parameterName = $this->propertyNameByMethodName($name, 4);
            $this->__set($parameterName, $arguments[0]);
            $this->pathParameters[$parameterName] = $arguments[0];

            return $this;
        }

        if (\strpos($name, 'set') === 0) {
            $parameterName = $this->propertyNameByMethodName($name);
            $withMethod    = "with$parameterName";

            throw new RuntimeException("Please use $withMethod instead of $name");
        }

        throw new RuntimeException('Call to undefined method ' . __CLASS__ . '::' . $name . '()');
    }
}
