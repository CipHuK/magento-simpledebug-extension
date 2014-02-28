<?php

class CipHuK_Debug_Model_Observer
{
    public function initDebug($observer)
    {
        Mage::helper('ciphuk_debug');
        return $observer;
    }
}