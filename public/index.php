<?php

const COUNTER_DIR = __DIR__."/../counter";
const TOR_PROXIES = [
  "68.183.184.174:64500",
  "68.183.184.174:64501",
  "68.183.184.174:64502",
  "68.183.184.174:64503",
  "68.183.184.174:64504",
  "68.183.184.174:64505",
  "68.183.184.174:64506",
  "68.183.184.174:64507",
  "68.183.184.174:64508",
  "68.183.184.174:64509",
  "68.183.184.174:64510",
  "68.183.184.174:64511",
  "68.183.184.174:64512",
  "68.183.184.174:64513",
  "68.183.184.174:64514",
  "68.183.184.174:64515",
  "68.183.184.174:64516",
  "68.183.184.174:64517",
  "68.183.184.174:64518",
  "68.183.184.174:64519",
  "68.183.184.174:64520",
  "68.183.184.174:64521",
  "68.183.184.174:64522",
  "68.183.184.174:64523",
  "68.183.184.174:64524",
  "68.183.184.174:64525",
  "68.183.184.174:64526",
  "68.183.184.174:64527",
  "68.183.184.174:64528",
  "68.183.184.174:64529"
];

if (!isset($_SERVER["DOMAIN_TARGET"])) {
  ex_err("DOMAIN_TARGET is not defined!");
}

$domain = $_SERVER["DOMAIN_TARGET"];
$counterFile = COUNTER_DIR."/".$domain;
$counter = file_exists($counterFile) ? (int)file_get_contents($counterFile) : 0;

if ($counter >= (count(TOR_PROXIES) - 1)) {
  $counter = 0;
}

file_put_contents($counterFile, $counter + 1);


$opt = [
  CURLOPT_PROXY => TOR_PROXIES[$counter],
  CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5,
  CURLOPT_HEADERFUNCTION => function ($ch, $hdr) {
    header($hdr);
    return strlen($hdr);
  },
  CURLOPT_WRITEFUNCTION => function ($ch, $str) {
    echo $str;
    return strlen($str);
  }
];

const IGNORE_HEADERS = [
  "Host"
];

$headersReq = [];
foreach (getallheaders() as $k => $v) {
  if (!in_array($k, IGNORE_HEADERS)) {
    $headersReq[] = "{$k}: {$v}";
  }
}

$opt[CURLOPT_HTTPHEADER] = $headersReq;

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
  $opt[CURLOPT_CUSTOMREQUEST] = $_SERVER["REQUEST_METHOD"];
  $opt[CURLOPT_POSTFIELDS] = file_get_contents("php://input");
}

curl("https://{$domain}".$_SERVER["REQUEST_URI"], $opt);

/** 
 * @param string $url
 * @param array  $opt
 * @return array
 */
function curl($url, $opt = [])
{

  $optf = [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_USERAGENT => "php-curl",
  ];

  foreach ($opt as $k => $v) {
    $optf[$k] = $v;
  }

  $ch = curl_init($url);
  curl_setopt_array($ch, $optf);
  $o = [
    "out" => curl_exec($ch),
    "info" => curl_getinfo($ch)
  ];
  $err = curl_error($ch);
  $ern = curl_errno($ch);

  if ($err) {
    throw new Exception("Curl Error: {$ern}: {$err}");
  }

  curl_close($ch);
  return $o;
}

/**
 * @param string $str
 * @param int    $httpCode
 * @param string $contentType
 * @return void
 */
function ex_err(
  string $str,
  int $httpCode = 500,
  string $contentType = "text/plain"
): void
{
  http_response_code($httpCode);
  header("Content-Type: ".$contentType);
  echo $str;
  exit;
}
