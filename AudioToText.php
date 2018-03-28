<?php
/**
 * This class handles requests to an audio recognition api (api.wit.ai)
 * NOTE!!! --- ONE SINGLE REQUEST CAN BE MADE PER SECOND!
 *
 * User: Catalina
 * Date: 12/15/17
 * Time: 11:14 AM
 */

class AudioToText
{
    const API_ENDPOINT = 'https://api.wit.ai/speech';
    const API_VERSION = '?v=';

    private $_strAPIToken; //Get your wit.ai API Token
    private $_strFilePath;
    private $_intTimestamp;
    private $_bolIsNumeric;

    public function __construct($strAPIToken, $strFilePath, $intTimestamp, $bolIsNumeric = false)
    {
        $this->_strAPIToken = $strAPIToken;
        $this->_strFilePath = $strFilePath;
        $this->_intTimestamp = $intTimestamp;
        $this->_bolIsNumeric = $bolIsNumeric;
    }

    public function _getText()
    {
        $ch = curl_init();
        $strDMY = date('d/m/Y', $this->_intTimestamp);
        $strURL = self::API_ENDPOINT.self::API_VERSION.$strDMY;
        curl_setopt($ch, CURLOPT_URL, $strURL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $strFileContent = file_get_contents($this->_strFilePath);

        curl_setopt($ch, CURLOPT_POSTFIELDS, $strFileContent);
        curl_setopt($ch, CURLOPT_POST, 1);

        $arrHeaders = array();
        $arrHeaders[] = "Authorization: Bearer ".$this->_strAPIToken;
        $arrHeaders[] = "Content-Type: audio/wav";
        curl_setopt($ch, CURLOPT_HTTPHEADER, $arrHeaders);

        $strResult = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
            return false;
        }
        curl_close ($ch);

        $arrResult = json_decode($strResult);
        if(!$arrResult)
        {
            echo "Couldn't decode response\n";
            return false;
        }
        else if(empty($arrResult->_text))
        {
            print_r($arrResult);
            echo "No match found\n";
            return false;
        }
        $strText = $arrResult->_text;

        if($this->_bolIsNumeric)
            $strText = str_replace(
                array('one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'zero', 'to', 'hell'),
                array('1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '2', '3'), $strText);

        return $strText;
    }
}




