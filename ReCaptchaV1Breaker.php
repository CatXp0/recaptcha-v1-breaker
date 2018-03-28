<?php
/**
 * Tool that uses audio recognition in order to break reCAPTCHA V1 with the "is_audio=true" parameter
 * which gives to an audio challenge instead of a visual one
 *
 * User: Catalina
 * Date: 12/15/17
 * Time: 11:14 AM
 */

require_once "AudioToText.php";

class ReCaptchaV1Breaker
{
    const AUDIO_RECAPTCHA_URL = 'http://www.google.com/recaptcha/api/noscript?k=';
    const AUDIO_FILE_URL = 'https://www.google.com/recaptcha/api/';

    // Constructor variables
    private $_strURL;
    private $_strAudioRecognitionAPIToken;
    private $_strReCAPTCHAStub;
    private $_strFilePath;
    private $_intRetries;
    private $_intProcess;
    private $_arrProxy;

    private $_arrReCAPTCHAParams = array();
    private $_strDecodedText = '';

    /**
     * reCAPTCHA Breaker constructor.
     * @param string $strURL                            URL of page protected by reCAPTCHA
     * @param string $strAudioRecognitionAPIToken       Audio Recognition API wit.ai Token
     * @param string $strReCAPTCHAStub                  Public reCAPTCHA key
     * @param string $strFilePath                       Raw data folder path
     * @param $intRetries                               Number of retries to break captcha
     * @param int $intProcess                           Process number in case of multiprocessing
     * @param array $arrProxy                           Proxy array (e.g. array("IP:PORT" => "192.168.0.1", "ua" => "Chrome user agent example") )
     */
    public function __construct($strURL, $strAudioRecognitionAPIToken, $strReCAPTCHAStub, $strFilePath, $intRetries, $intProcess = 0, $arrProxy = array())
    {
        $this->_strURL = $strURL;
        $this->_strAudioRecognitionAPIToken = $strAudioRecognitionAPIToken;
        $this->_strReCAPTCHAStub = $strReCAPTCHAStub;
        $this->_strFilePath = $strFilePath;
        $this->_intRetries = $intRetries;
        $this->_intProcess = $intProcess;
        $this->_arrProxy = $arrProxy;
    }

    /**
     * Returns array of reCAPTCHA response parameters "recaptcha_challenge_field" && "recaptcha_response_field"
     * @return array
     */
    public function _getReCAPTCHAParams()
    {
        return $this->_arrReCAPTCHAParams;
    }

    /**
     * Breaks the recaptcha and returns response text
     * @return mixed|string
     */
    public function _getResponse()
    {
        // Construct URL with the public recaptcha key and "is_audio=true" parameter, to get an audio challenge
        $strAudioReCAPTCHAURL = self::AUDIO_RECAPTCHA_URL . $this->_strReCAPTCHAStub . '&is_audio=true';
        $intRetriesImage = 0;

        // Remove trailing '/' from file path
        if(substr($this->_strFilePath, -1) === '/')
            $this->_strFilePath = rtrim($this->_strFilePath, '/');


        while($intRetriesImage < $this->_intRetries)
        {
            $ch = $this->_initCurl($this->_arrProxy);
            // Gets reCAPTCHA audio challenge page with cURL
            curl_setopt($ch, CURLOPT_URL, $strAudioReCAPTCHAURL);

            $strResponse = curl_exec($ch);
            curl_close($ch);

            // If the audio download link exists, construct URL to get it
            if(preg_match('%"image\?c=(.*?)"%s', $strResponse, $arrMatchAudioURLStub))
            {
                $strAudioURL = self::AUDIO_FILE_URL.'image?c='.$arrMatchAudioURLStub[1];
            }
            else
            {
                print_r($strResponse.PHP_EOL);
                print_r("Getting reCAPTCHA token failed...\n");
                continue;
            }

            // Get audio file with cURL
            $ch = $this->_initCurl($this->_arrProxy);
            curl_setopt($ch, CURLOPT_URL, $strAudioURL);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER,true);

            $strResponse = curl_exec($ch);
            curl_close($ch);
            $intTimeStamp = time();

            $strFilePath = $this->_strFilePath;

            // Save audio file
            file_put_contents("$strFilePath/audio-$this->_intProcess-$intTimeStamp.mp3", $strResponse);

            // Use the ffmpeg library to convert .mp3 audio to .wav. We need the .wav file for audio recognition
            exec("ffmpeg -loglevel panic -i $strFilePath/audio-$this->_intProcess-$intTimeStamp.mp3 -ac 1 $strFilePath/audio-$this->_intProcess-$intTimeStamp.wav");

            // Remove .mp3 file since it is not needed anymore
            unlink("$strFilePath/audio-$this->_intProcess-$intTimeStamp.mp3");

            // Check if conversion worked
            if(!is_file("$strFilePath/audio-$this->_intProcess-$intTimeStamp.wav"))
            {
                print_r("Audio conversion failed\n");
                $intRetriesImage++;
                continue;
            }

            // Initialise an AudioToText object in order to decode the .wav file
            $objAudioToText = new AudioToText($this->_strAudioRecognitionAPIToken, "$strFilePath/audio-$this->_intProcess-$intTimeStamp.wav", $intTimeStamp);

            // Check if audio recognition failed
            if(!$objAudioToText || trim($objAudioToText->_getText()) === '')
            {
                print_r("No result received from AudioToText\n");
                $intRetriesImage++;
                sleep(1);
                continue;
            }

            // Save the audio recognition text
            $this->_strDecodedText = $objAudioToText->_getText();

            // Submit the challenge text
            $ch = $this->_initCurl($this->_arrProxy);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER,false);

            $arrPost = array(
                'recaptcha_challenge_field' => $arrMatchAudioURLStub[1],
                'recaptcha_response_field' => $this->_strDecodedText,
                'submit' => "I'm a human"
            );
            $strPost = http_build_query($arrPost);

            curl_setopt($ch, CURLOPT_POSTFIELDS, $strPost);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_URL, $strAudioReCAPTCHAURL);

            $strResponse = curl_exec($ch);
            curl_close($ch);

            // Remove exhausted .wav file
            unlink("$strFilePath/audio-$this->_intProcess-$intTimeStamp.wav");

            if(preg_match('%<textarea.*?>(.*?)<\/textarea%s', $strResponse, $arrMatchReCAPTCHAChallenge))
            {
                // reCAPTCHA response parameters
                $this->_arrReCAPTCHAParams = array(
                    'recaptcha_challenge_field' => $arrMatchReCAPTCHAChallenge[1],
                    'recaptcha_response_field' => $this->_strDecodedText
                );

                $strPost = http_build_query($this->_arrReCAPTCHAParams);

                // Try breaking reCAPTCHA
                $ch = $this->_initCurl($this->_arrProxy);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $strPost);
                curl_setopt($ch, CURLOPT_URL, $this->_strURL);
                $strResponse = curl_exec($ch);
                curl_close($ch);

                // If the reCAPTCHA persists, retry breaking it
                if(strpos($strResponse, 'var RecaptchaOptions') !== false || strpos($strResponse, 'make sure you are a human') !== false)
                {
                    $intRetriesImage++;
                    sleep(1);
                    continue;
                }

                // Succeeded in breaking reCAPTCHA
                return $strResponse;
            }
            print_r("Retrying reCAPTCHA...\n");
            $intRetriesImage++;
            sleep(1);
        }
        return false;
    }

    /**
     * Initialise cURL with proxies, cookies and user agent
     * @param array $arrProxy           Proxy array (e.g. array("IP:PORT" => "192.168.0.1", "ua" => "Chrome user agent example") )
     * @param string $strFilePath       Cookies folder location
     * @return bool|resource
     */
    private function _initCurl($arrProxy)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $strFilePath = $this->_strFilePath;

        // Set one cookie file per proxy
        if (!file_exists($strFilePath.'/cookies'))
        {
            mkdir($strFilePath.'/cookies', 0777, true);
        }

        if(count($arrProxy) > 0)
        {
            // Set cookies for each proxy
            curl_setopt($ch, CURLOPT_COOKIEFILE, $strFilePath.'/cookies/cookie-'.$arrProxy['IP:PORT'].'.txt');
            curl_setopt($ch, CURLOPT_COOKIEJAR, $strFilePath.'/cookies/cookie-'.$arrProxy['IP:PORT'].'.txt');

            // Set the cURL resource according to the type of proxy
            switch(trim(strtolower($arrProxy['PROTOCOL'])))
            {
                case 'http':
                    curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
                    break;
                case 'socks':
                case 'socks5':
                    curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
                    break;
                default:
                    return false;
            }

            // Set proxy
            curl_setopt($ch, CURLOPT_PROXY, $arrProxy['IP:PORT']);

            // Set user agent
            curl_setopt($ch, CURLOPT_USERAGENT, $arrProxy['ua']);
        }
        else
        {
            curl_setopt($ch, CURLOPT_COOKIEFILE, $strFilePath.'/cookies/cookie.txt');
            curl_setopt($ch, CURLOPT_COOKIEJAR, $strFilePath.'/cookies/cookie.txt');
        }

        return $ch;
    }
}