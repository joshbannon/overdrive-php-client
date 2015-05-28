<?php
/**
 * Created by PhpStorm.
 * User: jbannon
 * Date: 9/16/14
 * Time: 9:48 AM
 */

namespace OverDrivePHPClient\interfaces;

interface I_EContentProviderFactory {
    /**
     * @param I_User $user
     * @return I_ProvidePatronServices
     */
    static function getPatronServices(I_User $user);

    /**
     * @return I_ProvideItemInformation
     */
    static function getLibraryServices();
} 