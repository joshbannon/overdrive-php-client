<?php
/**
 * Created by PhpStorm.
 * User: jbannon
 * Date: 9/5/14
 * Time: 2:32 PM
 */

namespace OverDrivePHPClient\client;

use OverDrivePHPClient\interfaces\I_ProvideItemInformation,
    OverDrivePHPClient\data\AccessToken,
    OverDrivePHPClient\data\Format,
    OverDrivePHPClient\data\InvalidCredentialsException,
    OverDrivePHPClient\data\LoanOption,
    OverDrivePHPClient\data\LoanOptionsCollection;

use \Memcached\Wrapper as Cache;

class OverDriveLibraryAPIClient implements I_ProvideItemInformation {
    /** @var  \GuzzleHttp\Client */
    private $_client;
    private $_authUrlBase;
    private $_apiUrlBase;
    private $collectionId;
    /** @var  Cache|null $_cache */
    private $_cache;
    /** @var  string $_userAgent */
    private $_userAgent;

    /** @var  AccessToken $_access_token */
    private $_access_token;

    function __construct($client, $libraryAuthUrlBase, $libraryAPIUrlBase, $collectionId, Cache $cache=null, $userAgent="OverDrivePHPClient")
    {
        $this->_client = $client;
        $this->_authUrlBase = $libraryAuthUrlBase;
        $this->_apiUrlBase = $libraryAPIUrlBase;
        $this->collectionId = $collectionId;
        $this->_cache = $cache;
    }

    public function login($clientKey, $clientSecret, $force = false)
    {
        //TODO we should think about a CAS lock on memcache so that we only ask for one access_token at a time
        if($this->_access_token && !$this->_access_token->isExpired()) {
            return true;
        }
        $memcacheKey = "OverDrive:LibAccessToken";
        if(!empty($this->_cache) && !$force) {
            $accessToken = null;
            if($this->_cache->get($memcacheKey, $accessToken)) {
                $this->_access_token = $accessToken;
                return true;
            }
        }

        $grantBody = "grant_type=client_credentials";
        $timeout = 5;
        $encodedAuthValue = base64_encode($clientKey . ":" . $clientSecret);

        try {
            $response = $this->_client->post($this->_authUrlBase . "/token", array(
                'headers' => array(
                    "User-Agent" => $this->_userAgent,
                    "Authorization" => "Basic ".$encodedAuthValue,
                    "Content-Length" => strlen($grantBody),
                    "Content-Type" => "application/x-www-form-urlencoded;charset=UTF-8"
                ),
                'timeout' => $timeout,
                'connect_timeout' => $timeout,
                'body' => $grantBody
            ));

            if ($response->getStatusCode() == 200) {
                $bodyStream = $response->getBody();
                $body = (string)$bodyStream;
                /** @var array $responseJ */
                $responseJ = json_decode($body, true);

                $expiresSecondsFromNow = $responseJ['expires_in'];
                $accessToken = new AccessToken(
                    $responseJ['access_token'],
                    $responseJ['token_type'],
                    (new \DateTime())->add( \DateInterval::createFromDateString($expiresSecondsFromNow.'second') ),
                    $responseJ['scope']
                );
                $this->_access_token = $accessToken;

                if($this->_cache != null) {
                    $this->_cache->set($memcacheKey, $accessToken, $expiresSecondsFromNow - 2);
                }

                return true;
            } else {
                throw new InvalidCredentialsException();
            }
        } catch(\GuzzleHttp\Exception\ClientException $e) {
            $message = (string)$e->getResponse()->getBody();
            if(!empty($message)) {
                throw new InvalidCredentialsException();
            }
        } catch(\GuzzleHttp\Exception\ServerException $e) {
            $json = json_decode((string)$e->getResponse()->getBody(), true);
            if(!empty($json) && $json['response']['code'] == "FORBIDDEN_ACCESS") {
                throw new InvalidCredentialsException();
            }
        } catch(\Exception $e) {
            print($e->getMessage());
        }

        return false;
    }

    public function isCheckoutAvailable($overdriveId) {
        $itemInfo = $this->getItemAvailability($overdriveId);
        return $itemInfo['available'];
    }

    public function isPlaceHoldAvailable($overdriveId) {
        return true;
    }

    /**
     * How many copies do we own?
     * @param String $externalRecordId
     * @return int
     */
    public function getTotalCopies($externalRecordId)
    {
        $itemInfo = $this->getItemAvailability($externalRecordId);
        return $itemInfo['copiesOwned'];
    }

    /**
     * How many holds are there?
     * @param String $externalRecordId
     * @return int
     */
    public function getHoldLength($externalRecordId)
    {
        $itemInfo = $this->getItemAvailability($externalRecordId);
        if(array_key_exists('numberOfHolds', $itemInfo)) {
            return $itemInfo['numberOfHolds'];
        } else {
            return 0;
        }
    }

    const KEY_GET_ITEM_AVAILABILITY = "OverDrive:GetItemAvailability:";
    /**
     * Base call behind several OverDrive Methods. Returns like
     *  {
     *      "available": true,
     *      "copiesOwned": 7,
     *      "copiesAvailable": 1,
     *      "numberOfHolds": 2
     *  }
     * @param $overdriveId
     * @internal param $collectionId
     * @return array
     */
    private function getItemAvailability($overdriveId) {
        $cacheKey = self::KEY_GET_ITEM_AVAILABILITY.":".$overdriveId;
        if(!empty($this->_cache)) {
            $availability = null;
            if($this->_cache->get($cacheKey, $availability)) {
                return $availability;
            }
        }

        try {
            $response = $this->_client->get(
                $this->_apiUrlBase . "/v1/collections/{$this->collectionId}/products/{$overdriveId}/availability",
                array(
                    'headers' => array(
                        "Accept" => "application/json",
                        "User-Agent" => "DCL User Agent",
                        "Authorization" => "Bearer ".$this->_access_token->getToken()
                    ),
                    'timeout' => 10,
                    'connect_timeout' => 10,
                ));

            if ($response->getStatusCode() == 200) {
                $bodyStream = $response->getBody();
                $body = (string)$bodyStream;
                /** @var array $responseJ */
                $responseJ = json_decode($body, true);

                if($this->_cache != null) {
                    $this->_cache->set($cacheKey, $responseJ, 60*5);//Cache for five minutes
                }
                return $responseJ;
            }
        }catch (\Exception $e) {
            print $e->getMessage();
        }

        return array();
    }

    const KEY_GET_ITEM_METADATA = "OverDrive:GetItemMetaData:";
    /**
     * Base call behind several OverDrive Methods. See https://developer.overdrive.com/apis/metadata
     * @param $overdriveId
     * @internal param $collectionId
     * @return array
     */
    private function getItemMetaData($overdriveId) {
        $cacheKey = self::KEY_GET_ITEM_METADATA.$overdriveId;
        $jsonCache = null;
        if($this->_cache->get($cacheKey, $jsonCache)) {
            return $jsonCache;
        }

        try {
            $response = $this->_client->get(
                $this->_apiUrlBase . "/v1/collections/{$this->collectionId}/products/{$overdriveId}/metadata",
                array(
                    'headers' => array(
                        "Accept" => "application/json",
                        "User-Agent" => "DCL User Agent",//TODO
                        "Authorization" => "Bearer ".$this->_access_token->getToken()
                    ),
                    'timeout' => 10,
                    'connect_timeout' => 10,
                ));

            if ($response->getStatusCode() == 200) {
                $bodyStream = $response->getBody();
                $body = (string)$bodyStream;
                /** @var array $responseJ */
                $responseJ = json_decode($body, true);
                $this->_cache->set($cacheKey, $responseJ, 30*60);//cache for thirty minutes
                return $responseJ;
            }
        }catch (\Exception $e) {
            print $e->getMessage();
        }

        return array();
    }

    /**
     * Used for tests only. The real indexing happens in Java.
     */
    public function search($collectionId = 'L1BGAEAAA2f', $offset = 100, $limit = 5) {
        try {
            $response = $this->_client->get($this->_apiUrlBase . "/v1/collections/".$collectionId."/products", array(
                'headers' => array(
                    "Accept" => "application/json",
                    "User-Agent" => "DCL User Agent",//TODO
                    "Authorization" => "Bearer ".$this->_access_token->getToken()
                ),
                'query' => array("offset"=>$offset, "limit"=>$limit),
                'timeout' => 10,
                'connect_timeout' => 10,
            ));

            if ($response->getStatusCode() == 200) {
                $bodyStream = $response->getBody();
                $body = (string)$bodyStream;
                /** @var array $responseJ */
                $responseJ = json_decode($body, true);
                return $responseJ;
            }
        }catch (\Exception $e) {
            print $e->getMessage();
        }

        return array();
    }

    /**
     * @param String $externalRecordId
     * @return LoanOptionsCollection
     */
    public function getCheckoutOptions($externalRecordId)
    {
        $jsonObject = $this->getItemMetaData($externalRecordId);
        $loanOptionsCollection = new LoanOptionsCollection($externalRecordId);
        if(array_key_exists('formats', $jsonObject)) {
            $formats = $jsonObject['formats'];
            foreach($formats as $format) {
                $loanOptionsCollection->addLoanOption(new LoanOption(
                    $externalRecordId,
                    $format['id'],
                    $this->getAvailable($externalRecordId),
                    new Format($format['id'], $format['name'])
                ));
            }
        } else {
            $i = 0;
            $i++;
        }

        return $loanOptionsCollection;
    }

    /**
     * @param String[] $externalRecordIds
     * @return LoanOptionsCollection[]
     */
    public function getCheckoutOptionsForRecords(array $externalRecordIds)
    {
        // TODO: parrallelize this. We probably need to have getItemMetaData() return a promise()
        $ret = [];
        foreach($externalRecordIds as $recordId) {
            $ret[] = $this->getCheckoutOptions($recordId);
        }

        return $ret;
    }

    /**
     * How many copies are currently available for checkout?
     * @param String $externalRecordId
     * @return int
     */
    public function getAvailable($externalRecordId)
    {
        $itemInfo = $this->getItemAvailability($externalRecordId);
        if(array_key_exists('copiesAvailable', $itemInfo)) {
            return $itemInfo['copiesAvailable'];
        } else {
            return 0;
        }
    }

    /**
     * @param $externalRecordId
     * @return String
     */
    public function getCoverURL($externalRecordId)
    {
        $meta = $this->getItemMetaData($externalRecordId);
        if(!empty($meta["images"]["cover"]["href"])) {
            return $meta["images"]["cover"]["href"];
        }
        return "";
    }
}