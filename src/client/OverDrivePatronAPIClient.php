<?php
/**
 * Created by PhpStorm.
 * User: jbannon
 * Date: 9/5/14
 * Time: 2:32 PM
 */

namespace OverDriveClient\client;

use OverDriveClient\interfaces\I_ProvidePatronServicesInterface,
    OverDriveClient\data\AccessLink,
    OverDriveClient\data\AccessToken,
    OverDriveClient\data\AlreadyReservedException,
    OverDriveClient\data\CannotReturnException,
    OverDriveClient\data\Hold,
    OverDriveClient\data\InvalidCredentialsException,
    OverDriveClient\data\Loan,
    OverDriveClient\data\LoanOption
   ;

use \Memcached\Wrapper as Cache;

class OverDrivePatronAPIClient extends OverDriveLibraryAPIClient implements I_ProvidePatronServicesInterface {
    /** @var  \GuzzleHttp\Client */
    private $_client;
    private $_authUrlBase;
    private $_apiUrlBase;
    private $_websiteId;
    private $_ilsId;
    private $_notificationEmail;
    /** @var  string $_userAgent */
    private $_userAgent;

    /** @var  Cache|null $_cache */
    private $_cache;

    /** @var  AccessToken $_access_token */
    private $_access_token;

    function __construct($client, $patronAuthUrlBase, $patronAPIUrlBase, $libraryAuthBase, $libraryAPIBase, $collectionId, $websiteId, $ilsId, $notificationEmail, Cache $cache = null, $userAgent = "OverDriveClient")
    {
        parent::__construct($client, $libraryAuthBase, $libraryAPIBase, $collectionId, $cache, $userAgent);
        $this->_client = $client;
        $this->_authUrlBase = $patronAuthUrlBase;
        $this->_apiUrlBase = $patronAPIUrlBase;
        $this->_websiteId = $websiteId;
        $this->_ilsId = $ilsId;
        $this->_cache = $cache;
        $this->_notificationEmail = $notificationEmail;
        $this->_userAgent = $userAgent;
    }

    private $_username;
    /**
     * @param $clientKey
     * @param $clientSecret
     * @param $username - library barcode
     * @param bool $force
     * @return bool
     * @throws InvalidCredentialsException
     */
    public function login($clientKey, $clientSecret, $username, $force = false)
    {
        parent::login($clientKey, $clientSecret, $force);
        //TODO we should think about a CAS lock on memcache so that we only ask for one access_token at a time
        if($this->_access_token && !$this->_access_token->isExpired()) {
            return true;
        }
        $this->_username = $username;
        $memcacheKey = "OverDrive:login:".$username;
        if(!empty($this->_cache) && !$force) {
            $accessToken = null;
            if($this->_cache->get($memcacheKey, $accessToken)) {
                $this->_access_token = $accessToken;
                return true;
            }
        }

        $timeout = 5;
        $encodedAuthValue = base64_encode($clientKey . ":" . $clientSecret);

        $response = $this->_client->post($this->_authUrlBase . "/patrontoken", array(
            'headers' => array(
                //"Accept" => "application/json",
                "User-Agent" => $this->_userAgent,
                "Authorization" => "Basic {$encodedAuthValue}",
                "Content-Type" => "application/x-www-form-urlencoded;charset=UTF-8"
            ),
            'timeout' => $timeout,
            'connect_timeout' => $timeout,
            'form_params' => array(
                "grant_type" => "password",
                "username" => $username,
                "password" => "x-ignoreme-x",
                "scope" => "websiteid:{$this->_websiteId} authorizationname:{$this->_ilsId}"
            )));

        try {
            if ($response->getStatusCode() === 200) {
                $this->_access_token = self::getOathTokenFromResponse($response);

                if($this->_cache !== null) {
                    $secondsTillExpiration = $this->_access_token->getExpirationTime()->getTimestamp() - time(); //PHP timestamps are apparently in seconds... crazy
                    $this->_cache->set($memcacheKey, $this->_access_token, $secondsTillExpiration - 2);
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
            if(!empty($json) && $json['response']['code'] === "FORBIDDEN_ACCESS") {
                throw new InvalidCredentialsException();
            }
        } catch(\Exception $e) {
            print($e->getMessage());
        }

        return false;
    }

    const KEY_GET_CHECKED_OUT = "OverDrive:getCheckedOut:";
    /**
     * @return Loan[]
     */
    public function getCheckedOut()
    {
        $cacheKey = self::KEY_GET_CHECKED_OUT.$this->_username;
        /** @var Loan[] $ret */
        $ret = [];
        if($this->_cache->get($cacheKey, $ret)) {
            return $ret;
        }

        $response = $this->_client->get($this->_apiUrlBase . "/v1/patrons/me/checkouts", array(
            'headers' => array(
                "Accept" => "application/json",
                "User-Agent" => $this->_userAgent,
                "Authorization" => "Bearer ".$this->_access_token->getToken()
            ),
            'timeout' => 5,
            'connect_timeout' => 5));
        $bodyStream = $response->getBody();
        $strCast = (string)$bodyStream;
        $jsonResponse = json_decode($strCast, true);

        if(!empty($jsonResponse)) {
            if(!empty($jsonResponse['checkouts'])) {
                foreach($jsonResponse['checkouts'] as $checkout) {
                    $loanId = $checkout['reserveId'];
                    $recordId = $checkout['reserveId'];
                    $endDate = (new \DateTime($checkout['expires']))->setTimezone(new \DateTimeZone(date_default_timezone_get()));
                    $tempDate = clone $endDate;//Because PHP is stupid and DateTime->sub() modifies its input
                    $startDate = $tempDate->sub(new \DateInterval("P3W")); //3 week checkout

                    /** @var AccessLink[] $links */
                    $links = array();
                    if(array_key_exists('formats', $checkout)) {
                        foreach($checkout['formats'] as $format) {
                            $linkType = null;
                            $linkLabel = "";
                            switch($format['formatType'])
                            {
                                case 'ebook-overdrive':
                                    $linkType = AccessLink::TYPE_STREAM;
                                    $linkLabel = "Read Online";
                                    break;
                                case 'ebook-epub-adobe':
                                    $linkLabel = "Download Epub";
                                    $linkType = AccessLink::TYPE_DOWNLOAD;
                                    break;
                                case 'ebook-pdf-adobe':
                                    $linkLabel = "Download PDF";
                                    $linkType = AccessLink::TYPE_DOWNLOAD;
                                    break;

                                default:
                                    $linkType = AccessLink::TYPE_DOWNLOAD;

                            }
                            if(empty($linkLabel)) {
                                $linkLabel = "Download: ".$format['formatType'];
                            }

                            $links[] = new AccessLink($linkType, $format['linkTemplates']['downloadLink']['href'], $linkLabel);
                        }
                    } else {
                        //Should be an audio book or something without a default download link before locking the format
                    }

                    $ret[] = new OverDriveLoan($loanId, $recordId, $startDate, $endDate, $links);
                }
                $this->_cache->set($cacheKey, $ret, 5*60);
                return $ret;
            } else {
                $this->_cache->set($cacheKey, array(), 5*60);
                return array(); //No checkouts
            }
        } else {
            $i = 0;
            $i++;
        }

        return array();
    }

    /**
     * @param $downloadLinkTemplate - Provided as part of getCheckedOut()
     * @return string
     */
    private function getDownloadLink($downloadLinkTemplate) {
        $downloadLinkTemplate = str_replace(
            "{errorpageurl}",
            urlencode("http://www.overdrive.com/errorpage.htm"),
            $downloadLinkTemplate);

        $downloadLinkTemplate = str_replace(
            "{odreadauthurl}", //yes this is just another error page or something. It seems like anything can go here
            urlencode("http://www.overdrive.com/errorpage.htm"),
            $downloadLinkTemplate);

        $response = $this->_client->get($downloadLinkTemplate, array(
            'headers' => array(
                "Accept" => "application/json",
                "User-Agent" => $this->_userAgent,
                "Authorization" => "Bearer ".$this->_access_token->getToken()
            ),
            'timeout' => 5,
            'connect_timeout' => 5));
        $bodyStream = $response->getBody();
        $strCast = (string)$bodyStream;
        $jsonResponse = json_decode($strCast, true);

        if(!empty($jsonResponse)) {
            return $jsonResponse['links']['contentlink']['href'];
        }

        return null;
    }

    const KEY_GET_HOLDS = "OverDrive:GetHolds:";
    public function getHolds()
    {
        $cacheKey = OverDrivePatronAPIClient::KEY_GET_HOLDS.$this->_username;
        /** @var Hold[] $ret */
        $ret = array();
        if($this->_cache->get($cacheKey, $ret)) {
            return $ret;
        }

        $response = $this->_client->get($this->_apiUrlBase . "/v1/patrons/me/holds", array(
            'headers' => array(
                "Accept" => "application/json",
                "User-Agent" => $this->_userAgent,
                "Authorization" => "Bearer ".$this->_access_token->getToken()
            ),
            'timeout' => 5,
            'connect_timeout' => 5));
        $bodyStream = $response->getBody();
        $strCast = (string)$bodyStream;
        $jsonResponse = json_decode($strCast, true);

        if(!empty($jsonResponse['holds'])) {
            foreach($jsonResponse['holds'] as $hold) {
                $status = "WAITING";
                if(array_key_exists("actions", $hold) && array_key_exists("checkout", $hold["actions"])) {
                    $status = "READY";
                }
                $ret[] = new OverDriveHold(
                    $hold['reserveId'],
                    $status
                );
            }
            $this->_cache->set($cacheKey, $ret, 5*60);
            return $ret;
        }

        if(!empty($jsonResponse)) {
            $this->_cache->set($cacheKey, array(), 5*60); //This is fine. The user just doesn't have any holds.
        }

        return array();
    }

    public function releaseHold(Hold $hold) {
        $response = $this->_client->delete($this->_apiUrlBase . "/v1/patrons/me/holds/{$hold->getExternalItemId()}",
            array(
                'headers' => array(
                    "Accept" => "application/json",
                    "User-Agent" => $this->_userAgent,
                    "Authorization" => "Bearer ".$this->_access_token->getToken()
                ),
                'timeout' => 5,
                'connect_timeout' => 5)
        );

        if($response->getStatusCode() === 204) { //204 Correct, no content returned
            $this->_cache->delete(self::KEY_GET_HOLDS.$this->_username);
            $this->_cache->delete(self::KEY_GET_ITEM_AVAILABILITY.$hold->getExternalItemId());

            return true;
        }

        return false;
    }

    /**
     * @param Loan $loan
     * @throws \Exception
     */
    public function returnItem(Loan $loan)
    {
        try{
            $response = $this->_client->delete($this->_apiUrlBase . "/v1/patrons/me/checkouts/{$loan->getExternalItemId()}",
                array(
                    'headers' => array(
                        "Accept" => "application/json",
                        "User-Agent" => $this->_userAgent,
                        "Authorization" => "Bearer ".$this->_access_token->getToken()
                    ),
                    'timeout' => 5,
                    'connect_timeout' => 5)
            );
            if($response->getStatusCode() === 204) { //204 Correct, no content returned
                $this->_cache->delete(self::KEY_GET_CHECKED_OUT.$this->_username);
                $this->_cache->delete(self::KEY_GET_ITEM_AVAILABILITY.$loan->getExternalItemId());
                return;
            }

        } catch( \GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
            $bodyStream = $response->getBody();
            $strCast = (string)$bodyStream;
            $jsonResponse = json_decode($strCast, true);
            throw new CannotReturnException("Could not return title: " . $jsonResponse->message);
        }catch( \GuzzleHttp\Exception\RequestException $e) {
            $response = $e->getResponse();
            $bodyStream = $response->getBody();
            $strCast = (string)$bodyStream;
            $jsonResponse = json_decode($strCast, true);
        }
        throw new \Exception("Error when returning overdrive item");
    }

    /**
     * @return Loan[]
     */
    public function getCheckOutHistory()
    {
        //Overdrive does not support this behavior at this time
        return [];
    }

    /**
     * @param LoanOption $loanOption
     * @throws AlreadyReservedException
     * @throws \Exception
     * @return Hold
     */
    public function holdItem(LoanOption $loanOption)
    {
        $postBody = array(
            "fields" => [array("name"=>"reserveId", "value"=>$loanOption->getExternalRecordId())]
        );
        $postBody['fields'][] = array("name"=>"emailAddress", "value"=>$this->_notificationEmail);
        $postBody = json_encode($postBody);

        try{
            $response = $this->_client->post($this->_apiUrlBase . "/v1/patrons/me/holds", array(
                'headers' => array(
                    "Accept" => "application/json",
                    "User-Agent" => $this->_userAgent,
                    "Authorization" => "Bearer ".$this->_access_token->getToken(),
                    "Content-Type" => "application/json; charset=utf-8",
                    "Content-Length" => strlen($postBody),
                    "Expect" => "100-continue"
                ),
                'timeout' => 5,
                'connect_timeout' => 5,
                'body' =>$postBody
            ));

            if($response->getStatusCode()==201) { //201: Created
                $bodyStream = $response->getBody();
                $strCast = (string)$bodyStream;
                /** @noinspection PhpUnusedLocalVariableInspection */
                $jsonResponse = json_decode($strCast, true); //We want to parse this because it should have content, but we don't need to use that content
                $this->_cache->delete(self::KEY_GET_HOLDS.$this->_username);
                $this->_cache->delete(self::KEY_GET_ITEM_AVAILABILITY.$loanOption->getExternalRecordId());
                return new OverDriveHold(
                    $loanOption->getExternalRecordId(),
                    "WAITING"
                );
            }
        } catch( \GuzzleHttp\Exception\ClientException $e) {
            //$response = $e->getResponse();
            //$bodyStream = $response->getBody();
            //$strCast = (string)$bodyStream;
            //$jsonResponse = json_decode($strCast, true);
        }catch( \GuzzleHttp\Exception\RequestException $e) {
            //$response = $e->getResponse();
            //$bodyStream = $response->getBody();
            //$strCast = (string)$bodyStream;
            //$jsonResponse = json_decode($strCast, true);
        }

        throw new \Exception("Unable to place OverDrive hold.");
    }

    /**
     * @param LoanOption $loanOption
     * @throws \Exception
     */
    public function checkoutItem(LoanOption $loanOption)
    {
        $postBody = array(
            "fields" => [array("name"=>"reserveId", "value"=>$loanOption->getExternalRecordId())]
        );
        if($loanOption->getLoanOptionId() !== null) {
            $postBody['fields'][] = array("name"=>"formatType", "value"=>$loanOption->getLoanOptionId());
        }
        $postBody = json_encode($postBody);

        try {
            $response = $this->_client->post($this->_apiUrlBase . "/v1/patrons/me/checkouts", array(
                    'headers' => array(
                        "Accept" => "application/json",
                        "User-Agent" => $this->_userAgent,
                        "Authorization" => "Bearer ".$this->_access_token->getToken(),
                        "Content-Type" => "application/json; charset=utf-8",
                        "Content-Length" => strlen($postBody),
                        "Expect" => "100-continue"
                    ),
                    'timeout' => 5,
                    'connect_timeout' => 5,
                    'body' => $postBody)
            );
            $bodyStream = $response->getBody();
            if($response->getStatusCode() === 201) {
                $strCast = (string)$bodyStream;
                $jsonResponse = json_decode($strCast, true);

                if(!empty($jsonResponse)) {
                    if(array_key_exists("reserveId", $jsonResponse)) {
                        $this->_cache->delete(self::KEY_GET_CHECKED_OUT.$this->_username);
                        $this->_cache->delete(self::KEY_GET_ITEM_AVAILABILITY.$loanOption->getExternalRecordId());
                        return; //We're OK
                    }
                }
            }
            throw new \Exception("Did not get back a good confirmation when checking out OverDrive Item");
        } catch( \GuzzleHttp\Exception\ClientException $e) {
//            $response = $e->getResponse();
//            $bodyStream = $response->getBody();
//            $strCast = (string)$bodyStream;
//            $jsonResponse = json_decode($strCast, true);
            throw new \Exception("Client error when checking out OverDrive Item");
        }catch( \GuzzleHttp\Exception\RequestException $e) {
//            $response = $e->getResponse();
//            $bodyStream = $response->getBody();
//            $strCast = (string)$bodyStream;
//            $jsonResponse = json_decode($strCast, true);
            throw new \Exception("Request error when checking out OverDrive Item");
        }
    }

    /**
     * @param Loan $loan
     * @return AccessLink[]
     */
    public function getDownloadLinks(Loan $loan)
    {
        if($loan instanceof OverDriveLoan) {
            $links = $loan->getLinks();
            $processedLinks = [];
            foreach($links as $link) {
                switch($link->getType())
                {
                    //unsure if we ever get simple links back
                    default:
                        $processedLinks[] = new AccessLink($link->getType(), $this->getDownloadLink($link->getUrl()), $link->getLabel());
                }
            }
            return $processedLinks;
        }
        return [];
    }

    /**
     * Choose the format. This generally fixes the format and makes downloading available.
     * @param LoanOption $loanOption
     * @throws \Exception
     */
    public function selectFormat(LoanOption $loanOption)
    {
        $postBody = array(
            "fields" => [array("name"=>"reserveId", "value"=>$loanOption->getExternalRecordId())]
        );
        if($loanOption->getLoanOptionId() !== null) {
            $postBody['fields'][] = array("name"=>"formatType", "value"=>$loanOption->getLoanOptionId());
        } else {
            throw new \Exception("No format type selected");
        }
        $postBody = json_encode($postBody);

        try {
            $response = $this->_client->post(
                $this->_apiUrlBase . "/v1/patrons/me/checkouts/{$loanOption->getExternalRecordId()}/formats",
                array(
                    'headers' => array(
                        "Accept" => "application/json",
                        "User-Agent" => $this->_userAgent,
                        "Authorization" => "Bearer ".$this->_access_token->getToken(),
                        "Content-Type" => "application/json; charset=utf-8",
                        "Content-Length" => strlen($postBody),
                        "Expect" => "100-continue"
                    ),
                    'timeout' => 5,
                    'connect_timeout' => 5,
                    'body' => $postBody)

            );
            $bodyStream = $response->getBody();
            if($response->getStatusCode() === 201) {
                $strCast = (string)$bodyStream;
                $jsonResponse = json_decode($strCast, true);

                if(!empty($jsonResponse)) {
                    if(array_key_exists("reserveId", $jsonResponse)) {
                        return; //We're OK
                    }
                }
            }
            throw new \Exception("Did not get back a good confirmation when checking out OverDrive Item");
        } catch( \GuzzleHttp\Exception\ClientException $e) {
//            $response = $e->getResponse();
//            $bodyStream = $response->getBody();
//            $strCast = (string)$bodyStream;
//            $jsonResponse = json_decode($strCast, true);
            throw new \Exception("Client error when checking out OverDrive Item");
        }catch( \GuzzleHttp\Exception\RequestException $e) {
//            $response = $e->getResponse();
//            $bodyStream = $response->getBody();
//            $strCast = (string)$bodyStream;
//            $jsonResponse = json_decode($strCast, true);
            throw new \Exception("Request error when checking out OverDrive Item");
        }
    }

    /**
     * @param String $externalRecordId
     * @return boolean
     */
    public function isFormatSelected($externalRecordId)
    {
        $response = $this->_client->get($this->_apiUrlBase . "/v1/patrons/me/checkouts/{$externalRecordId}",
            array(
            'headers' => array(
                "Accept" => "application/json",
                "User-Agent" => $this->_userAgent,
                "Authorization" => "Bearer ".$this->_access_token->getToken()
            ),
            'timeout' => 5,
            'connect_timeout' => 5));
        $bodyStream = $response->getBody();
        $strCast = (string)$bodyStream;
        $jsonResponse = json_decode($strCast, true);

        if(!empty($jsonResponse)) {
            if(!empty($jsonResponse['checkouts'])) {
                /** @var Loan[] $ret */
                $ret = [];
                foreach($jsonResponse['checkouts'] as $checkout) {
                    $loanId = $checkout['reserveId'];
                    $recordId = $checkout['reserveId'];
                    $endDate = (new \DateTime($checkout['expires']))->setTimezone(new \DateTimeZone(date_default_timezone_get()));
                    $tempDate = clone $endDate;//Because PHP is stupid
                    $startDate = $tempDate->sub(new \DateInterval("P3W")); //3 week checkout

                    /** @var AccessLink[] $links */
                    $links = array();
                    if(array_key_exists('formats', $checkout)) {
                        foreach($checkout['formats'] as $format) {
                            $linkType = null;
                            switch($format['formatType'])
                            {
                                case 'ebook-overdrive':
                                    $linkType = AccessLink::TYPE_STREAM;
                                    break;
                                default:
                                    $linkType = AccessLink::TYPE_DOWNLOAD;
                            }

                            $links[] = new AccessLink($linkType, $format['linkTemplates']['downloadLink']['href']);
                        }
                    } else {
                        //Should be an audio book or something without a default download link before locking the format
                    }

                    $ret[] = new OverDriveLoan($loanId, $recordId, $startDate, $endDate, $links);
                }
                return $ret;
            } else {
                return array(); //No checkouts
            }
        } else {
            $i = 0;
            $i++;
        }

        return array();
    }

    /**
     * @param Loan $loan
     * @return boolean
     */
    public function canReturn(Loan $loan)
    {
        // TODO: Implement canReturn() method.
        return false;
    }
}
