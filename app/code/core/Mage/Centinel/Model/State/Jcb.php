<?php

/**
 * OpenMage
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available at https://opensource.org/license/osl-3-0-php
 *
 * @category   Mage
 * @package    Mage_Centinel
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://www.magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://www.openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Abstract Validation State Model for JCB
 *
 * @category   Mage
 * @package    Mage_Centinel
 */
class Mage_Centinel_Model_State_Jcb extends Mage_Centinel_Model_StateAbstract
{
    /**
     * Analyse lookup`s results. If it has require params for authenticate, return true
     *
     * @return bool
     */
    public function isAuthenticateAllowed()
    {
        return $this->_isLookupStrictSuccessful() && is_null($this->getAuthenticateEciFlag());
    }

    /**
     * Analyse authenticate`s results. If authenticate is successful return true and false if it failure
     * Result depends from flag self::getIsModeStrict()
     *
     * @return bool
     */
    public function isAuthenticateSuccessful()
    {
        //Test cases 5-9
        if (!$this->getIsModeStrict() && $this->_isLookupSoftSuccessful()) {
            return true;
        }

        $paResStatus = $this->getAuthenticatePaResStatus();
        $eciFlag = $this->getAuthenticateEciFlag();
        $xid = $this->getAuthenticateXid();
        $cavv = $this->getAuthenticateCavv();
        $errorNo = $this->getAuthenticateErrorNo();
        $signatureVerification = $this->getAuthenticateSignatureVerification();

        //Test cases 1-4, 10-11
        if ($this->_isLookupStrictSuccessful()) {
            if ($paResStatus == 'Y' && $eciFlag == '05' && $xid != '' && $cavv != '' && $errorNo == '0') {
                //Test case 1
                if ($signatureVerification == 'Y') {
                    return true;
                }
                //Test case 2
                if ($signatureVerification == 'N') {
                    return false;
                }
            }

            //Test case 3
            if ($paResStatus == 'N' && $signatureVerification == 'Y' && $eciFlag == '07' &&
                $xid != '' && $cavv == '' && $errorNo == '0'
            ) {
                return false;
            }

            //Test case 4
            if ($paResStatus == 'U' && $signatureVerification == 'Y' && $eciFlag == '07' &&
                $xid != '' && $cavv == '' && $errorNo == '0'
            ) {
                if ($this->getIsModeStrict()) {
                    return false;
                } else {
                    return true;
                }
            }

            //Test case 5
            if ($paResStatus == 'U' && $signatureVerification == 'Y' && $eciFlag == '07' &&
                $xid != '' && $cavv == '' && $errorNo == '0'
            ) {
                if ($this->getIsModeStrict()) {
                    return false;
                } else {
                    return true;
                }
            }

            //Test case 10
            if ($paResStatus == '' && $signatureVerification == '' && $eciFlag == '07' &&
                $xid == '' && $cavv == '' && $errorNo != '0'
            ) {
                return false;
            }

            //Test case 11
            if ($paResStatus == 'A' && $signatureVerification == 'Y' && $eciFlag == '06' &&
                $xid != '' && $cavv != '' && $errorNo == '0'
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Analyse lookup`s results. If lookup is strict successful return true
     *
     * @return bool
     */
    protected function _isLookupStrictSuccessful()
    {
        //Test cases 1-4, 6, 10-11
        if ($this->getLookupEnrolled() == 'Y' &&
            $this->getLookupAcsUrl() != '' &&
            $this->getLookupPayload() != '' &&
            $this->getLookupErrorNo() == '0'
        ) {
            return true;
        }
        return false;
    }

    /**
     * Analyse lookup`s results. If lookup is soft successful return true
     *
     * @return bool
     */
    protected function _isLookupSoftSuccessful()
    {
        $acsUrl = $this->getLookupAcsUrl();
        $payload = $this->getLookupPayload();
        $errorNo = $this->getLookupErrorNo();
        $enrolled = $this->getLookupEnrolled();

        //Test cases 5
        if ($enrolled == '' && $acsUrl == '' && $payload == '' && $errorNo == '0') {
            return true;
        }

        //Test case 7
        if ($enrolled == 'U' && $acsUrl == '' && $payload == '' && $errorNo == '0') {
            return true;
        }

        //Test cases 8,9
        if ($enrolled == 'U' && $acsUrl == '' && $payload == '' && $errorNo != '0') {
            return true;
        }

        return false;
    }
}
