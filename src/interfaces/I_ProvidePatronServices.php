<?php
/**
 * Created by PhpStorm.
 * User: jbannon
 * Date: 9/10/14
 * Time: 9:19 AM
 */

namespace OverDriveClient\interfaces;

use OverDriveClient\data\AccessLink,
    OverDriveClient\data\AlreadyReservedException,
    OverDriveClient\data\Loan,
    OverDriveClient\data\LoanOption,
    OverDriveClient\data\Hold;

interface I_ProvidePatronServicesInterface extends I_ProvideItemInformationInterface {

    /**
     * @param Loan $loan
     * @throws \Exception
     */
    public function returnItem(Loan $loan);

    /**
     * @return Loan[]
     */
    public function getCheckedOut();

    /**
     * @return Loan[]
     */
    public function getCheckOutHistory();

    /**
     * @param Loan $loan
     * @return AccessLink[]
     */
    public function getDownloadLinks(Loan $loan);

    /**
     * @param LoanOption $loanOption
     * @throws AlreadyReservedException
     * @throws \Exception
     * @return Hold
     */
    public function holdItem(LoanOption $loanOption);

    /**
     * @param Hold $hold
     */
    public function releaseHold(Hold $hold);

    /**
     * @return Hold[]
     */
    public function getHolds();

    /**
     * @param LoanOption $loanOption
     * @throws \Exception
     */
    public function checkoutItem(LoanOption $loanOption);

    /**
     * Choose the format. This generally fixes the format and makes downloading available.
     * @param LoanOption $loanOption
     * @throws \Exception
     */
    public function selectFormat(LoanOption $loanOption);

    /**
     * @param String $externalRecordId
     * @return boolean
     */
    public function isFormatSelected($externalRecordId);

    /**
     * @param Loan $loan
     * @return boolean
     */
    public function canReturn(Loan $loan);
}
