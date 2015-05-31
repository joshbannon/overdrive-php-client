<?php
/**
 * Created by PhpStorm.
 * User: jbannon
 * Date: 9/16/14
 * Time: 9:48 AM
 */

namespace OverDriveClient\interfaces;

interface I_EContentProviderFactory {
    /**
     * @param I_User $user
     * @return I_ProvidePatronServices
     */
    static function getPatronServices(I_User $user, $configArray, \Memcached\Wrapper $memcachedWrapper);

    /**
     * @return I_ProvideItemInformation
     */
    static function getLibraryServices($configArray, \Memcached\Wrapper $memcachedWrapper);
} 