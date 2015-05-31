<?php

/**
 * Created by PhpStorm.
 * User: jbannon
 * Date: 6/6/14
 * Time: 2:45 PM
 */
namespace OverDriveClient\interfaces;

interface UserInterface
{
    function getBarcode();
    function getPin();
    function getEmail();
}
