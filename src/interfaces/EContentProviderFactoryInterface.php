<?php
/**
 * Created by PhpStorm.
 * User: jbannon
 * Date: 9/16/14
 * Time: 9:48 AM
 */

namespace OverDriveClient\interfaces;

interface EContentProviderFactoryInterface {
    /**
     * @param UserInterface $user
     * @param $configArray
     * @param \Memcached\Wrapper $memcachedWrapper
     * @return mixed
     */
    static function getPatronServices(UserInterface $user, $configArray, \Memcached\Wrapper $memcachedWrapper);

    /**
     * @param $configArray
     * @param \Memcached\Wrapper $memcachedWrapper
     * @return mixed
     */
    static function getLibraryServices($configArray, \Memcached\Wrapper $memcachedWrapper);
} 