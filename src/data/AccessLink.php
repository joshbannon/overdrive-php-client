<?php
/**
 * Created by PhpStorm.
 * User: jbannon
 * Date: 9/11/14
 * Time: 11:05 AM
 */

namespace OverDriveClient\data;


class AccessLink {
    const TYPE_DOWNLOAD = "DOWNLOAD";
    const TYPE_STREAM = "STREAM";
    const TYPE_VIEW = "VIEW";

    private $_url;
    private $_type;

    /** @var  String $label */
    private $_label;

    function __construct($type, $url, $lable=null)
    {
        $this->_type = $type;
        $this->_url = $url;
        $this->_label = $lable;
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->_type;
    }

    /**
     * @return mixed
     */
    public function getUrl()
    {
        return $this->_url;
    }

    /**
     * @param String $label
     */
    public function setLabel($label)
    {
        $this->_label = $label;
    }

    /**
     * @return String
     */
    public function getLabel()
    {
        return $this->_label;
    }
}
