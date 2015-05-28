<?php
/**
 * Created by PhpStorm.
 * User: jbannon
 * Date: 9/10/14
 * Time: 9:14 AM
 */

namespace OverDrivePHPClient\client;

require_once dirname(__FILE__).'/../Hold.php';

class OverDriveHold extends Hold {

    public function isAvailable()
    {
        return $this->getStatus() === 'AVAILABLE';
    }
}