<?php
/**
 * Created by PhpStorm.
 * User: jbannon
 * Date: 9/5/14
 * Time: 2:50 PM
 */

namespace OverDriveClient\data;


class AccessToken {
    private $_token;
    private $_type;
    /** @var  \DateTime $_expirationTime*/
    private $_expirationTime;
    private $_scope;

    function __construct($_token, $_type, \DateTime $_expirationTime, $_scope)
    {
        $this->_expirationTime = $_expirationTime;
        $this->_scope = $_scope;
        $this->_token = $_token;
        $this->_type = $_type;
    }

    public function isExpired() {
        //Make sure there's at least a second of margin
        return $this->_expirationTime->getTimestamp() - 1000 < (new \DateTime())->getTimestamp();
    }

    /**
     * @return \DateTime
     */
    public function getExpirationTime()
    {
        return $this->_expirationTime;
    }

    /**
     * @return String
     */
    public function getScope()
    {
        return $this->_scope;
    }

    /**
     * @return String
     */
    public function getToken()
    {
        return $this->_token;
    }

    /**
     * @return String
     */
    public function getType()
    {
        return $this->_type;
    }
} 