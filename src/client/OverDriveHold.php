<?php
/**
 * Created by PhpStorm.
 * User: jbannon
 * Date: 9/10/14
 * Time: 9:14 AM
 */

namespace OverDriveClient\client;

use OverDriveClient\data\Hold;

class OverDriveHold extends Hold {

    public function isAvailable()
    {
        return $this->getStatus() === 'AVAILABLE';
    }
}
