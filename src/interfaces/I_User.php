<?php

/**
 * Created by PhpStorm.
 * User: jbannon
 * Date: 6/6/14
 * Time: 2:45 PM
 */
namespace OverDrivePHPClient\interfaces;

interface I_User
{
    public function getPin();

    function hasRatings();
}