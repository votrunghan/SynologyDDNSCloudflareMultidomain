#!/usr/bin/php -d open_basedir=/usr/syno/bin/ddns
<?php
/*
Usage Instructions ( Obviously your domain has to be hosted on Namecheap )

1) Copy this file to /usr/syno/bin/ddns/namecheap.php

2) Add the following entry in /etc.defaults/ddns_provider.conf
[Custom - Namecheap]
        modulepath=/usr/syno/bin/ddns/namecheap.php
        queryurl=dynamicdns.park-your-domain.com

* Please DO NOT change the url "dynamicdns.park-your-domain.com". It's vaild domain provide by namecheap.

3) In Synology External Access > DDNS
Hostname = subdomain.domain.com OR domain.com (Type your your domain you want)
Username = put-random-string-here-for-validation-purpose (Program doesn't need this value)
Password = Namecheap DDNS Token (Accounts > Domain List > Advanced DNS)
*/

if ($argc !== 5) {
    echo 'badparam';
    exit();
}

$account = (string)$argv[1];
$pwd = (string)$argv[2];
$hostname = (string)$argv[3];
$ip = (string)$argv[4];

// check the hostname contains '.'
if (strpos($hostname, '.') === false) {
    echo 'badparam';
    exit();
}

// only for IPv4 format
if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    echo "badparam";
    exit();
}

$array = explode('.', $hostname);
if (count($array) >= 3) {
    $domain = implode('.', array_slice($array, 1));
    $hostname = implode('.', array_slice($array, 0, 1));
} else {
    $domain = implode('.', $array);
    $hostname = '@';
}

// Please DO NOT change the url "dynamicdns.park-your-domain.com". It's vaild domain provide by namecheap.
$url = 'https://dynamicdns.park-your-domain.com/update?host='.$hostname.'&domain='.$domain.'&password='.$pwd.'&ip='.$ip;

$req = curl_init();
curl_setopt($req, CURLOPT_URL, $url);
curl_setopt($req, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($req);
curl_close($req);

/*

Success response:

<?xml version="1.0"?>
  <interface-response>
    <Command>SETDNSHOST</Command>
    <Language>eng</Language>
    <IP>%ip-address%</IP>
    <ErrCount>%error-count%</ErrCount>
    <ResponseCount>%response-count%</ResponseCount>
    <Done>true</Done>
    <debug><![CDATA[]]></debug>
</interface-response>

%ip-address% = IP address
%error-count% = 0
%response-count% = 0

Failure response:

<?xml version="1.0"?>
  <interface-response>
    <Command>SETDNSHOST</Command>
    <Language>eng</Language>
    <ErrCount>%error-count%</ErrCount>
    <errors>
      <Err1>%error-message%</Err1>
    </errors>
    <ResponseCount>%response-count%</ResponseCount>
    <responses>
      <response>
        <ResponseNumber>%response-number%</ResponseNumber>
        <ResponseString>%response-message%</ResponseString>
      </response>
    </responses>
    <Done>true</Done>
    <debug><![CDATA[]]></debug>
</interface-response>

%error-count% = 1
%error-message% = Error message
%response-count% = 1
%response-number% = Code for response message
%response-message% = Response message (similar to %error-message%)

*/

$xml = new SimpleXMLElement($res);
if ($xml->ErrCount > 0) {
    $error = $xml->errors[0]->Err1;
    if (strcmp($error, "Domain name not found") === 0) {
        echo "nohost";
    } elseif (strcmp($error, "Passwords do not match") === 0) {
        echo "badauth";
    } elseif (strcmp($error, "No Records updated. A record not Found;") === 0) {
        echo "nohost";
    } else {
        echo "911 [".$error."]";
    }
} else {
    echo "good";
}