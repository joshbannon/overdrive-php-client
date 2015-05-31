<?php
/**
 * Created by PhpStorm.
 * User: jbannon
 * Date: 6/5/14
 * Time: 4:33 PM
 */

namespace OverDriveClient\client;

use OverDriveClient\interfaces\UserInterface, OverDriveClient\interfaces\EContentProviderFactoryInterface;

class OverDriveAPIClientFactoryInterface implements EContentProviderFactoryInterface {

    /** @var OverDriveLibraryAPIClient $_libraryClient */
    private static $_libraryClient = null;
    /** @var OverDrivePatronAPIClient[] $_patronAPIClients */
    private static $_patronAPIClients = [];

    /**
     * @param UserInterface $user
     * @param $configArray
     * @param \Memcached\Wrapper $memcachedWrapper
     * @return null|OverDrivePatronAPIClient
     * @throws \Exception
     * @throws \OverDriveClient\data\InvalidCredentialsException
     */
    static function getPatronServices(UserInterface $user, $configArray, \Memcached\Wrapper $memcachedWrapper) {

        $username = null;
        $password = null;
        if($user) {
            $username = $user->getBarcode();
            //$password = $user->getPin(); //Require password is probably turned off
        } else {
            throw new \Exception("No logged in User");
        }

        $patronClient = null;
        if(!empty($user)) {
            if(!empty(static::$_patronAPIClients[$username])) {
                $patronClient = static::$_patronAPIClients[$username];
            } else {
                $patronClient = new OverDrivePatronAPIClient(
                    new \GuzzleHttp\Client(), $configArray['OverDrive']['patronAuthURL'], $configArray['OverDrive']['patronApiURL'], $configArray['OverDrive']['libraryAuthURL'], $configArray['OverDrive']['libraryApiURL'], $configArray['OverDrive']['collection_id'], $configArray['OverDrive']['website_id'], $configArray['OverDrive']['librarycardILS_ID'], $user->getEmail(), $memcachedWrapper
                );
                $patronClient->login(
                    $configArray['OverDrive']['client_key'],
                    $configArray['OverDrive']['client_secret'],
                    $username,
                    false
                );

                static::$_patronAPIClients[$username] = $patronClient;
            }
        }

        return $patronClient;
    }

    /**
     * @param $configArray
     * @param \Memcached\Wrapper $memcachedWrapper
     * @return OverDriveLibraryAPIClient
     * @throws \OverDriveClient\data\InvalidCredentialsException
     */
    static function getLibraryServices($configArray, \Memcached\Wrapper $memcachedWrapper) {

        if(static::$_libraryClient === null) {
            static::$_libraryClient = new OverDriveLibraryAPIClient(
                new \GuzzleHttp\Client(),
                $configArray['OverDrive']['libraryAuthURL'],
                $configArray['OverDrive']['libraryApiURL'],
                $configArray['OverDrive']['collection_id'],
                $memcachedWrapper
            );

            static::$_libraryClient->login(
                $configArray['OverDrive']['client_key'],
                $configArray['OverDrive']['client_secret'],
                false
            );
        }

        return static::$_libraryClient;
    }
} 