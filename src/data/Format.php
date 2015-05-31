<?php
/**
 * Created by PhpStorm.
 * User: jbannon
 * Date: 9/11/14
 * Time: 3:26 PM
 */

namespace OverDriveClient\data;


class Format {
    private $_id;
    private $_name;

    function __construct($_id, $_name)
    {
        $this->_id = $_id;
        $this->_name = $_name;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->_id;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->_name;
    }

    function __toString()
    {
        return $this->getName();
    }


}
