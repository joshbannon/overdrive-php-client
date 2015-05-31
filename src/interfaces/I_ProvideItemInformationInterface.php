<?php
/**
 * Created by PhpStorm.
 * User: jbannon
 * Date: 9/10/14
 * Time: 9:18 AM
 */

namespace OverDriveClient\interfaces;

use OverDriveClient\data\LoanOptionsCollection;

interface I_ProvideItemInformationInterface {

    /**
     * @param String $externalRecordId
     * @return LoanOptionsCollection
     */
    public function getLoanOptions($externalRecordId);

    /**
     * @param String[] $externalRecordIds
     * @return LoanOptionsCollection[]
     */
    public function getLoanOptionsForRecords(array $externalRecordIds);

    /**
     * How many copies are currently available for checkout?
     * @param String $externalRecordId
     * @return int
     */
    public function getAvailable($externalRecordId);

    /**
     * How many copies do we own?
     * @param String $externalRecordId
     * @return int
     */
    public function getTotalCopies($externalRecordId);

    /**
     * How many holds are there?
     * @param String $externalRecordId
     * @return int
     */
    public function getHoldLength($externalRecordId);

    /**
     * @param $externalRecordId
     * @return String
     */
    public function getCoverURL($externalRecordId);
}
