<?php

const CACHE_DIR = __DIR__."/../storage/cache";
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

require __DIR__."/../mapper.php";

$domain = DOMAIN_MAP[$_SERVER["DOMAIN_TARGET"]] ?? null;

if (!isset($domain)) {
  ex_err("DOMAIN_TARGET does not exist in DOMAIN_MAP: {$_SERVER["DOMAIN_TARGET"]}");
}

$needReqBody = ($_SERVER["REQUEST_METHOD"] !== "GET");

$url = "https://{$domain}".$_SERVER["REQUEST_URI"];
$urlHash = md5($url);

$cacheBodyFile = CACHE_DIR."/".$urlHash;
$cacheHeaderFile = CACHE_DIR."/".$urlHash.".hdr";

if (
  (!$needReqBody) &&
  file_exists($cacheBodyFile) &&
  file_exists($cacheHeaderFile)
) {
  $headers = json_decode(file_get_contents($cacheHeaderFile), true);
  foreach ($headers as $v) {
    header($v);
  }
  readfile($cacheBodyFile);
  exit;
}

$counterFile = COUNTER_DIR."/".$domain;
$counter = file_exists($counterFile) ? (int)file_get_contents($counterFile) : 0;

if ($counter >= (count(TOR_PROXIES) - 1)) {
  $counter = 0;
}

file_put_contents($counterFile, $counter + 1);

$cacheHeader = [];
$cacheBody = "";

$opt = [
  CURLOPT_PROXY => TOR_PROXIES[$counter],
  CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5,
  CURLOPT_HEADERFUNCTION => function ($ch, $hdr) use (&$cacheHeader) {
    header($hdr);
    $cacheHeader[] = $hdr;
    return strlen($hdr);
  },
  CURLOPT_WRITEFUNCTION => function ($ch, $str) use (&$cacheBody) {
    echo $str;
    $cacheBody .= $str;
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

if ($needReqBody) {
  $opt[CURLOPT_CUSTOMREQUEST] = $_SERVER["REQUEST_METHOD"];
  $opt[CURLOPT_POSTFIELDS] = file_get_contents("php://input");
}

curl($url, $opt);

if (!$needReqBody) {
  file_put_contents($cacheHeaderFile, json_encode($cacheHeader));
  file_put_contents($cacheBodyFile, $cacheBody);
}

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
