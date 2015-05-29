<?php

use \OverDrivePHPClient\client\OverDriveAPIClientFactory,
    \OverDrivePHPClient\client\OverDriveLibraryAPIClient,
    \OverDrivePHPClient\client\OverDrivePatronAPIClient;

use \Memcached\Wrapper as Cache;

class OverDriveDriverTests extends PHPUnit_Framework_TestCase
{
    private $useMemcache = false;

    static private $libraryAuthUrlBase;
    static private $libraryAPIUrlBase;
    static private $patronAuthUrlBase;
    static private $patronAPIUrlBase;
    static private $clientKey;
    static private $clientSecret;
    static private $libraryId;
    static private $collectionId;
    static private $websiteId;
    static private $ilsId;

    /** @var  OverDriveLibraryAPIClient */
    static private $libraryDriver;

    /** @var  OverDrivePatronAPIClient */
    static private $patronDriver;

    public static function setUpBeforeClass()
    {
        static::$libraryAuthUrlBase = "https://oauth.overdrive.com";
        static::$libraryAPIUrlBase = "http://api.overdrive.com";
        static::$patronAuthUrlBase = "https://oauth-patron.overdrive.com";
        static::$patronAPIUrlBase = "http://patron.api.overdrive.com";
        static::$clientKey = "xxx";
        static::$clientSecret = "xxx";
        static::$libraryId = 0;
        static::$collectionId = "xxx";
        static::$websiteId = 269;
        static::$ilsId = "xxx";
    }

    public function test_libraryLoginShouldSucceed()
    {
        $cache = null;
        if($this->useMemcache) {
            // Connect to Memcache:
            $cache = new Cache("defaultPool", array(
                array("localhost", 11211)
            ));
        }

        $driver = new OverDriveLibraryAPIClient(
            new \GuzzleHttp\Client(),
            static::$libraryAuthUrlBase,
            static::$libraryAPIUrlBase,
            static::$collectionId,
            $cache);
        $res = $driver->login(static::$clientKey, static::$clientSecret, true);

        //Give the cache a workout
        $driver = new OverDriveLibraryAPIClient(
            new \GuzzleHttp\Client(),
            static::$libraryAuthUrlBase,
            static::$libraryAPIUrlBase,
            static::$collectionId,
            $cache);
        $res = $driver->login(static::$clientKey, static::$clientSecret, false);

        $this->assertTrue($res);
        static::$libraryDriver = $driver;
    }

    public function test_libraryLoginShouldFail()
    {
        try {
            $cache = null;
            if($this->useMemcache) {
                $cache = new Cache("fakePool", array(
                    array("localhost", 11211)
                ));
                $cache->deactivate();
            }

            $driver = new OverDriveLibraryAPIClient(
                new \GuzzleHttp\Client(),
                static::$libraryAuthUrlBase,
                static::$libraryAPIUrlBase,
                static::$collectionId,
                $cache);
            $res = $driver->login(0, 0, static::$libraryId, true);
            $this->isFalse($res);
        } catch(InvalidCredentialsException $e) {

        }
    }

    /**
     * @return array
     * @depends test_libraryLoginShouldSucceed
     */
    public function test_search() {
        $limit = 5;
        $offset = rand(0, 20000);
        $res = static::$libraryDriver->search(static::$collectionId, $offset, $limit);

        $this->assertNotEmpty($res);

        return $res['products'];
    }

    /**
     * @param $items
     * @depends test_search
     */
    public function test_getAvailableCopies($items) {
        $res = static::$libraryDriver->getAvailable($items[0]['id']);
        $this->assertTrue(is_numeric($res));
    }

    /**
     * @param $items
     * @depends test_search
     */
    public function test_getHoldLength($items) {
        $res = static::$libraryDriver->getHoldLength($items[0]['id']);
        $this->assertTrue(is_numeric($res));
    }

    /**
     * @param $items
     * @depends test_search
     */
    public function test_getTotalCopies($items) {
        $res = static::$libraryDriver->getTotalCopies($items[0]['id']);
        $this->assertTrue(is_numeric($res));
    }

    public function test_patronLoginShouldSucceed() {
        $cache = null;
        if(true) {
            // Connect to Memcache:
            $cache = new Cache("defaultPool", array(
                array("localhost", 11211)
            ));
        }
        $driver = new OverDrivePatronAPIClient(
            new \GuzzleHttp\Client(),
            static::$patronAuthUrlBase,
            static::$patronAPIUrlBase,
            static::$libraryAuthUrlBase,
            static::$libraryAPIUrlBase,
            static::$collectionId,
            static::$websiteId,
            static::$ilsId,
            $cache,
            "jbannon@dclibraries.org");
        $username = '23025006522064';
        $res = $driver->login(static::$clientKey, static::$clientSecret, $username, true);
        $this->isTrue($res);

        static::$patronDriver = $driver;
    }

    /**
     * @depends test_patronLoginShouldSucceed
     */
    public function test_getPatronCheckouts() {
        /** @var Loan[] $res */
        $res = static::$patronDriver->getCheckedOut();
        $this->assertTrue(is_array($res));
        return $res;
    }

    /**
     * @param $items
     * @depends test_search
     */
    public function test_createPatronHold($items) {
        $loanOptionsCollection = static::$patronDriver->getCheckoutOptions($items[0]['id']);
        $hold = static::$patronDriver->holdItem($loanOptionsCollection->getLoanOptions()[0]);
        $this->assertNotEmpty($hold);
    }

    /**
     * @depends test_createPatronHold
     */
    public function test_getPatronHolds() {
        $res = static::$patronDriver->getHolds();
        $this->assertNotEmpty($res);
        return $res;
    }

    /**
     * @param Hold[] $holds
     * @depends test_getPatronHolds
     */
    public function test_releasePatronHolds($holds) {
        foreach($holds as $hold) {
            $res = static::$patronDriver->releaseHold($hold);
            $this->assertTrue($res);
        }

    }

    /**
     * @param Loan[] $checkouts
     * @depends test_getPatronCheckouts
     */
    public function test_getDownloadLink($checkouts) {
        if(!empty($checkouts)) {
            $checkout = $checkouts[0];
            $res = static::$patronDriver->getDownloadLinks($checkout);
            //$this->assertNotEmpty($res);//Can be empty because of audio books.
        }

    }

    /**
     * @param $items
     * @return Loan
     * @depends test_search
     */
    public function test_checkout($items) {
        $loanOptionsCollection = static::$patronDriver->getCheckoutOptions($items[0]['id']);
        //Build a generic so we don't lock in the type and prevent returning
        $loanOption = new LoanOption($items[0]['id'], null, 1, null);
        static::$patronDriver->checkoutItem($loanOption);
        $loans = static::$patronDriver->getCheckedOut();
        return $loans[0];
    }

    /**
     * @param Loan $loan
     * @depends test_checkout
     */
    public function test_returnCheckedOut(Loan $loan) {
        try {
            static::$patronDriver->returnItem($loan);
        } catch(CannotReturnException $e) {
            //This is ok. Maybe we downloaded it
        }
    }

    /**
     * @param $items
     * @depends test_search
     */
    public function test_getCoverURL($items) {
        $coverUrl = static::$libraryDriver->getCoverURL($items[0]['id']);
        $this->assertNotEmpty($coverUrl);
    }

}