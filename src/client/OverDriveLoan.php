<?php
/**
 * Created by PhpStorm.
 * User: jbannon
 * Date: 9/11/14
 * Time: 11:47 AM
 */

namespace OverDrivePHPClient\client;

require_once dirname(__FILE__).'/../Loan.php';

class OverDriveLoan extends Loan {
    /** @var  AccessLink[] */
    private $_links;

    /**
     * @param String $loanId
     * @param String $externalItemId
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @param AccessLink[] $_links
     */
    function __construct($loanId, $externalItemId, \DateTime $startDate, \DateTime $endDate, $_links)
    {
        parent::__construct($loanId, $externalItemId, $startDate, $endDate);
        $this->_links = $_links;
    }

    /**
     * @return \EContentProvider\AccessLink[]
     */
    public function getLinks()
    {
        return $this->_links;
    }
} 