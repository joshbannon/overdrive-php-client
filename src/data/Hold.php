<?php
/**
 * Created by PhpStorm.
 * User: jbannon
 * Date: 6/6/14
 * Time: 10:41 AM
 */

namespace OverDriveClient\data;


abstract class Hold {
    private $_externalItemId;
    private $_status;

    function __construct($externalItemId, $status)
    {
        $this->_externalItemId = $externalItemId;
        $this->_status = $status;
    }

    /**
     * @return string|int
     */
    public function getExternalItemId()
    {
        return $this->_externalItemId;
    }

    /**
     * @return mixed
     */
    public function getStatus()
    {
        return $this->_status;
    }

    public abstract function isAvailable();
}
