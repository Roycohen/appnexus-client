<?php

namespace Audiens\AppnexusClient\service;

use Audiens\AppnexusClient\Auth;
use Audiens\AppnexusClient\CachableTrait;
use Audiens\AppnexusClient\CacheableInterface;
use Audiens\AppnexusClient\entity\ReportStatus;
use Audiens\AppnexusClient\entity\ReportTicket;
use Audiens\AppnexusClient\exceptions\ReportException;
use Audiens\AppnexusClient\repository\RepositoryResponse;
use Doctrine\Common\Cache\Cache;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;

/**
 * Class Report
 */
class Report implements CacheableInterface
{

    use CachableTrait;

    const BASE_URL          = 'http://api.adnxs.com/report';
    const BASE_URL_DOWNLOAD = 'http://api.adnxs.com/';

    const SANDBOX_BASE_URL          = 'http://api-test.adnxs.com/report';
    const SANDBOX_BASE_URL_DOWNLOAD = 'http://api-test.adnxs.com/';


    /** @var  \SplQueue */
    protected $userSegments;

    /** @var Client|Auth */
    protected $client;

    /** @var  int */
    protected $memberId;

    /** @var  Cache */
    protected $cache;

    const CACHE_NAMESPACE = 'appnexus_report';

    const CACHE_EXPIRATION = 3600;

    const REVENUE_REPORT = [
        'report' =>
            [
                'report_type' => 'seller_platform_billing',
                'timezone' => 'PST',
                'report_interval' => 'last_7_days',
                'name' => 'Weekly SSP Revenue Report',
                'columns' =>
                    [
                        0 => 'day',
                        1 => 'seller_member',
                        2 => 'publisher_id',
                        3 => 'publisher_name',
                        4 => 'publisher_code',
                        5 => 'buyer_member_id',
                        6 => 'buyer_member_name',
                        7 => 'imps',
                        8 => 'imps_delivered',
                        9 => 'seller_revenue',
                    ],
            ],
    ];

    /** @var string */
    protected $baseUrl;

    /** @var  string */
    protected $baseUrlDownload;

    /**
     * SegmentRepository constructor.
     *
     * @param ClientInterface $client
     * @param Cache|null      $cache
     */
    public function __construct(ClientInterface $client, Cache $cache = null)
    {
        $this->client = $client;
        $this->cache = $cache;
        $this->cacheEnabled = $cache instanceof Cache;

        $this->baseUrl = self::BASE_URL;
        $this->baseUrlDownload = self::BASE_URL_DOWNLOAD;

    }

    /**
     * @return string
     */
    public function getBaseUrl()
    {
        return $this->baseUrl;
    }

    /**
     * @param string $baseUrl
     */
    public function setBaseUrl($baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }

    /**
     * @return string
     */
    public function getBaseUrlDownload()
    {
        return $this->baseUrlDownload;
    }

    /**
     * @param string $baseUrlDownload
     */
    public function setBaseUrlDownload($baseUrlDownload)
    {
        $this->baseUrlDownload = $baseUrlDownload;
    }


    /**
     * @param array $reportFormat
     *
     * @return ReportTicket
     * @throws ReportException
     */
    public function getReportTicket($reportFormat = self::REVENUE_REPORT)
    {

        $compiledUrl = $this->baseUrl;

        $response = $this->client->request('POST', $compiledUrl, ['body' => json_encode($reportFormat)]);

        $repositoryResponse = RepositoryResponse::fromResponse($response);

        if (!$repositoryResponse->isSuccessful()) {
            throw ReportException::failed($repositoryResponse);
        }

        if (!isset($repositoryResponse->getResponseAsArray()['response']['report_id'])) {
            throw ReportException::missingIndex('response->report_id');
        }

        $reportTicket = ReportTicket::fromArray(
            $repositoryResponse->getResponseAsArray()['response']
        );

        return $reportTicket;

    }


    /**
     * @param ReportTicket $reportTicket
     *
     * @return ReportStatus
     * @throws ReportException
     */
    public function getReportStatus(ReportTicket $reportTicket)
    {

        $compiledUrl = $this->baseUrl.'?id='.$reportTicket->getReportId();

        $response = $this->client->request('GET', $compiledUrl);

        $repositoryResponse = RepositoryResponse::fromResponse($response);

        if (!$repositoryResponse->isSuccessful()) {
            throw ReportException::failed($repositoryResponse);
        }

        if (!isset($repositoryResponse->getResponseAsArray()['response']['report'])) {
            throw ReportException::missingIndex('response->report');
        }

        if (!isset($repositoryResponse->getResponseAsArray()['response']['execution_status'])) {
            throw ReportException::missingIndex('response->execution_status');
        }

        /** @var ReportStatus $reportStatus */
        $reportStatus = ReportStatus::fromArray($repositoryResponse->getResponseAsArray()['response']['report']);
        $reportStatus->setStatus($repositoryResponse->getResponseAsArray()['response']['execution_status']);
        $reportStatus->setReportId($reportTicket->getReportId());
        $reportStatus->setCached($reportTicket->getCached());

        return $reportStatus;

    }


    /**
     * @param ReportStatus $reportStatus
     *
     * @return array
     * @throws ReportException
     */
    public function getReport(ReportStatus $reportStatus)
    {
        if (!$reportStatus->isReady()) {
            throw ReportException::validation('report status not ready');
        }

        if (!$reportStatus->getUrl()) {
            throw ReportException::validation('missing url in the report status');
        }

        $cacheKey = self::CACHE_NAMESPACE.sha1($reportStatus->getUrl());

        if ($this->isCacheEnabled()) {
            if ($this->cache->contains($cacheKey)) {
                return $this->cache->fetch($cacheKey);
            }
        }

        $compiledUrl = $this->baseUrlDownload.$reportStatus->getUrl();

        $response = $this->client->request('GET', $compiledUrl);

        $lines = explode(PHP_EOL, $response->getBody()->getContents());
        $result = [];
        foreach ($lines as $line) {
            if (!empty($line)) {
                $result[] = str_getcsv($line);
            }
        }

        if ($this->isCacheEnabled()) {
            $this->cache->save($cacheKey, $result, self::CACHE_EXPIRATION);
        }

        return $result;

    }
}