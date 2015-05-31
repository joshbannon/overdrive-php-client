<?php
/**
 * Created by PhpStorm.
 * User: jbannon
 * Date: 5/28/14
 * Time: 8:48 AM
 */

namespace OverDriveClient\data;


class LoanOptionsCollection {
    /** @var  LoanOption[] */
    private $_loanOptions;

    /** @var  String $_externalItemId */
    private $_externalItemId;

    function __construct($externalItemId)
    {
        $this->_loanOptions = array();
        $this->_externalItemId = $externalItemId;
    }

    /**
     * @return String
     */
    public function getExternalItemId()
    {
        return $this->_externalItemId;
    }

    /**
     * @param LoanOption $loanOption
     */
    public function addLoanOption(LoanOption $loanOption)
    {
        $this->_loanOptions[] =  $loanOption;
    }

    /**
     * @return LoanOption[]
     */
    public function getLoanOptions()
    {
        return $this->_loanOptions;
    }

    /**
     * @return Format[]
     */
    public function getFormats() {
        $formats = [];
        foreach($this->_loanOptions as $loanOption) {
            $formats[] = $loanOption->getFormat();
        }
        return $formats;
    }
}
