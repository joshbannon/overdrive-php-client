<?php
/**
 * Created by PhpStorm.
 * User: jbannon
 * Date: 6/5/14
 * Time: 4:33 PM
 */

namespace OverDrivePHPClient\client;

use OverDrivePHPClient\interfaces\I_User, OverDrivePHPClient\interfaces\I_EContentProviderFactory;

class OverDriveAPIClientFactory implements I_EContentProviderFactory {

    /** @var OverDriveLibraryAPIClient $_libraryClient */
    private static $_libraryClient = null;
    /** @var OverDrivePatronAPIClient[] $_patronAPIClients */
    private static $_patronAPIClients = [];

    /**
     * @param I_User $user
     * @throws \Exception
     * @return OverDrivePatronAPIClient
     */
    static function getPatronServices(I_User $user) {
        global $configArray, $memcachedWrapper;
        $userId = 0;
        $username = null;
        $password = null;
        if($user) {
            $userId = $user->getId();
            $username = $user->getBarcode();
            $password = $user->getPin();
        } else {
            throw new \Exception("No logged in User");
        }

        $patronClient = null;
        if(!empty($user)) {
            if(!empty(static::$_patronAPIClients[$userId])) {
                $patronClient = static::$_patronAPIClients[$userId];
            } else {
                $patronClient = new OverDrivePatronAPIClient(
                    new \GuzzleHttp\Client(),
                    $configArray['OverDrive']['patronAuthURL'],
                    $configArray['OverDrive']['patronApiURL'],
                    $configArray['OverDrive']['libraryAuthURL'],
                    $configArray['OverDrive']['libraryApiURL'],
                    $configArray['OverDrive']['collection_id'],
                    $configArray['OverDrive']['website_id'],
                    $configArray['OverDrive']['librarycardILS_ID'],
                    $memcachedWrapper,
                    $user->getEmail()
                );
                $patronClient->login(
                    $configArray['OverDrive']['client_key'],
                    $configArray['OverDrive']['client_secret'],
                    $username,
                    false
                );

                static::$_patronAPIClients[$userId] = $patronClient;
            }
        }

        return $patronClient;
    }

    /**
     * @return OverDriveLibraryAPIClient
     */
    static function getLibraryServices() {
        global $configArray, $memcachedWrapper;

        if(static::$_libraryClient == null) {
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