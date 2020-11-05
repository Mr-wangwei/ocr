<?php

namespace Godruoyi\OCR\Requests;

use GuzzleHttp\Middleware;
use InvalidArgumentException;
use Godruoyi\OCR\Support\Encoder;
use Godruoyi\OCR\Support\Response;
use Godruoyi\OCR\Support\FileConverter;
use Psr\Http\Message\RequestInterface;
use Godruoyi\OCR\Support\TencentSignatureV3;

class TencentRequest extends Request
{
    const VERSION = 'Godruoyi_OCR_PHP_SDK_2.0';

    const BASEURI = 'https://ocr.tencentcloudapi.com';

    /**
     * Signature request
     *
     * @var \Godruoyi\OCR\Support\TencentSignatureV3
     */
    protected $signer;

    /**
     * {@inheritdoc}
     */
    protected function init()
    {
        $id  = $this->app['config']->get('drivers.tencent.secret_id');
        $key = $this->app['config']->get('drivers.tencent.secret_key');

        $this->signer = new TencentSignatureV3($id, $key);
    }

    /**
     * sdk default options.
     *
     * @return array
     */
    protected function requestOptions(string $action, string $region = '', string $apiVersion = '2018-11-19')
    {
        $apiVersion = $apiVersion ?: '2018-11-19';

        $headers = [
            'X-TC-Action'        => ucfirst($action),
            'X-TC-RequestClient' => self::VERSION,
            'X-TC-Timestamp'     => time(),
            'X-TC-Version'       => $apiVersion,
        ];

        if (!empty($region)) {
            $headers['X-TC-Region'] = $region;
        }

        return ['headers' => $headers];
    }

    /**
     * {@inheritdoc}
     */
    public function request($action, $images, array $options = []) : Response
    {
        $region = $options['region'] ?? $options['Region'] ?? '';
        $version = $options['version'] ?? $options['Version'] ?? '';

        return $this->http->json(
            self::BASEURI,
            $this->formatRequestBody($images, $options),
            '',
            $this->requestOptions($action, $region, $version)
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function middlewares(): array
    {
        return [
            'tencent' => $this->authMiddleware(),
        ];
    }

    /**
     * Format reqyest body.
     *
     * @param  mixed $images
     * @param  array  $options
     *
     * @return array
     */
    protected function formatRequestBody($images, array $options = [])
    {
        $images = $this->filterOneImage($images, 'Tencent ocr only one image can be identified at a time, default to array[0].');

        unset($options['region']);
        unset($options['Region']);
        unset($options['version']);
        unset($options['Version']);

        if (FileConverter::isUrl($images)) {
            return array_merge($options, ['ImageUrl' => $images]);
        } else {
            return array_merge($options, ['ImageBase64' => FileConverter::toBase64Encode($images)]);
        }
    }

    /**
     * Tencent auth middleware.
     *
     * @return callable
     */
    protected function authMiddleware()
    {
        return function (callable $handler) {
            return function (RequestInterface $request, array $options) use ($handler) {
                $a = $request->withHeader('Authorization', $this->signer->authorization($request));

                return $handler($a, $options);
            };
        };
    }
}