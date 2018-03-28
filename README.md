# reCAPTCHA V1 Breaker

## Description
This tool is used to bypass reCAPTCHA V1 (V1 is shutting down on 2018-03-31). It does that by making reCAPTCHA give audio
challenges instead of visual ones and then using audio recognition API to automate the process. 

## Prerequisites
An API Token for the audio recognition API (project's using wit.ai) is needed.

Also, to make sure your address is not so easily banned, you can use proxies to make the requests.
The proxies must have these parameters:
```
$arrProxy = array("IP:PORT" => "192.168.0.1:8080", "PROTOCOL" => "HTTP");
```
Protocol can be HTTP, SOCKS or SOCKS5.

## Usage
The class needs to be instantiated with:
- URL protected by reCAPTCHA
- Audio recognition API token
- Captcha Site-key
- Path to a data folder used for cookies
- Number of retries to get the page
```
$objReCaptcha = new ReCaptchaV1Breaker($strURLToScrap, $strAudioRecognitionAPIToken, $strPublicCaptchaKey, $strCookiesFolderPath, $intRetries);
```
You can also instantiate with the current process number in case you're forking, or a proxy. 
```
$objReCaptcha = new ReCaptchaV1Breaker($strURLToScrap, $strAudioRecognitionAPIToken, $strPublicCaptchaKey, $strDataFolderPath, $intRetries, $intProcessNumber, $arrProxy);
```

After this, the action needs to be started:
```
$strResponse = $objReCaptcha->getResponse();
```
$strResponse is the content of the web page after bypassing reCAPTCHA.