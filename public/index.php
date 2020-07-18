<?php

$_SERVER["HTTP_HOST"] = "saucenao.com";

const COUNTER_DIR = __DIR__."/../counter";
const CACHE_DIR = __DIR__."/../storage/cache";
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

/**
 * @param string  $msg
 * @param int     $code
 * @param string  $contentType
 * @return void
 */
function http_error(string $msg, int $code = 500, string $contentType = "text/plain"): void
{
  header("Content-Type: ".$contentType);
  http_response_code($code);
  echo $msg;
  exit;
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
    CURLOPT_VERBOSE => true
  ];

  foreach ($opt as $k => $v) {
    $optf[$k] = $v;
  }

  $ch = curl_init($url);
  curl_setopt_array($ch, $optf);
  curl_exec($ch);
  $err = curl_error($ch);
  if ($err) {
    $ern = curl_errno($ch);
    curl_close($ch);
    http_error("Curl Error: {$ern}: {$err}", 500);
  }
  curl_close($ch);
}

if (!isset($_SERVER["HTTP_HOST"])) {
  http_error("HTTP_HOST is not defined", 400);
}

require __DIR__."/../mapper.php";

$domain = DOMAIN_MAP[$_SERVER["HTTP_HOST"]] ?? null;

if (!is_string($domain)) {
  if (!in_array($_SERVER["HTTP_HOST"], DOMAIN_MAP)) {
    http_error("DOMAIN_MAP for ".$domain." does not exist");
  } else {
    $domain = $_SERVER["HTTP_HOST"];
  }
}

$counterFile = COUNTER_DIR."/".$domain;

if (file_exists($counterFile)) {
  $counter = (int)file_get_contents($counterFile);
  if ($counter >= (count(TOR_PROXIES) - 1)) {
    $counter = 0;
  }
} else {
  $counter = 0;
}

file_put_contents($counterFile, $counter + 1);

$opt = [
  CURLOPT_HTTP_VERSION => "HTTP/1.1",
  CURLOPT_PROXY => TOR_PROXIES[$counter],
  CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5,
  CURLOPT_HEADERFUNCTION => function ($ch, $hdr) use ($domain) {

    if (preg_match("/^transfer-encoding:/Si", $hdr)) {
      goto ret;
    }

    if (preg_match("/^location:/Si", $hdr)) {
      $hdr = str_replace($domain, $_SERVER["HTTP_HOST"], $hdr);
    }

    header($hdr);
    ret:
    return strlen($hdr);
  },
  CURLOPT_WRITEFUNCTION => function ($ch, $str) {
    echo $str;
    return strlen($str);
  }
];

$ignoreHeaders = [
  "host" => 1,
  "content-type" => 1,
  "cf-connecting-ip" => 1
];

$headerReq = [];
foreach (getallheaders() as $k => $v) {
  if (!isset($ignoreHeaders[strtolower($k)])) {
    $headerReq[] = $k.": ".$v;
  }
}

$opt[CURLOPT_HTTPHEADER] = $headerReq;

if ($_SERVER["REQUEST_METHOD"] != "GET") {
  $opt[CURLOPT_CUSTOMREQUEST] = $_SERVER["REQUEST_METHOD"];

  if (isset($_SERVER["HTTP_CONTENT_TYPE"]) &&
    (substr($_SERVER["HTTP_CONTENT_TYPE"], 0, 19) == "multipart/form-data")
  ) {

    $postData = [];
    foreach ($_POST as $k => $v) {
      if (is_string($v)) {
        $postData[$k] = $v;
      } else {
        $cb = function ($k, $v, &$bound) use (&$cb) {
          foreach ($v as $kk => $vv) {
            if (is_string($vv) || is_int($vv)) {
              $bound[$k."[$kk]"] = $vv;
            } else if (is_array($vv)) {
              $cb($k."[$kk]", $vv, $bound);
            } else {
              $bound[$k."[$kk]"] = "";
            }
          }
        };
        $cb($k, $v, $postData);
      }
    }

    if (isset($_FILES) && $_FILES) {
      foreach ($_FILES as $k => $v) {
        if ((!empty($v["name"])) && (!empty($v["tmp_name"]))) {
          $postData[$k] = new \CurlFile(
            $v["tmp_name"], $v["type"], $v["name"]
          );
        }
      }
    }

    $opt[CURLOPT_POSTFIELDS] = $postData;
  } else {
    $postData = (string)file_get_contents("php://input");
    if ($postData !== "") {
      $opt[CURLOPT_POSTFIELDS] = $postData;
    }
  }
}

curl("https://".$domain.$_SERVER["REQUEST_URI"], $opt);
