<?php
/**
 * Created by PhpStorm.
 * User: jbannon
 * Date: 8/29/14
 * Time: 3:27 PM
 */

namespace OverDrivePHPClient\data;

use DateTime;

class Loan {
    /** @var  $_loanId String */
    private $_loanId;
    /** @var  $_loanId String */
    private $_externalItemId;
    /** @var  $_loanId DateTime */
    private $_startDate;
    /** @var  $_loanId DateTime */
    private $_endDate;

    function __construct( $loanId, $externalItemId, DateTime $startDate, DateTime $endDate)
    {
        $this->_endDate = $endDate;
        $this->_loanId = $loanId;
        $this->_externalItemId = $externalItemId;
        $this->_startDate = $startDate;
    }

    /**
     * @return \DateTime
     */
    public function getEndDate()
    {
        return $this->_endDate;
    }

    /**
     * @return String
     */
    public function getLoanId()
    {
        return $this->_loanId;
    }

    /**
     * @return String
     */
    public function getExternalItemId()
    {
        return $this->_externalItemId;
    }

    /**
     * @return \DateTime
     */
    public function getStartDate()
    {
        return $this->_startDate;
    }
}