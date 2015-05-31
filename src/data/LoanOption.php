<?php
/**
 * Created by PhpStorm.
 * User: jbannon
 * Date: 5/28/14
 * Time: 8:50 AM
 */

namespace OverDriveClient\data;


class LoanOption
{
    /** @var  string */
    private $_externalRecordId;
    /** @var  string */
    private $_loanOptionId;
    /** @var  int */
    private $_numberAvailable;
    /** @var  Format $_format */
    private $_format;

    function __construct($externalRecordId, $loanOptionId, $_numberAvailable, Format $format = null)
    {
        $this->_externalRecordId = $externalRecordId;
        $this->_loanOptionId = $loanOptionId;
        $this->_numberAvailable = $_numberAvailable;
        $this->_format = $format;
    }

    /**
     * @return string
     */
    public function getLoanOptionId()
    {
        return $this->_loanOptionId;
    }

    /**
     * @return int
     */
    public function getNumberAvailable()
    {
        return $this->_numberAvailable;
    }

    /**
     * @return string
     */
    public function getExternalRecordId()
    {
        return $this->_externalRecordId;
    }

    /**
     * @return Format
     */
    public function getFormat()
    {
        return $this->_format;
    }
}
