<?php
/*
					COPYRIGHT
 
Copyright 2018 cryptapus <info@cryptapus.org>
 
This file is part of php-coinbase-miners.
 
JSON-RPC PHP is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.
 
JSON-RPC PHP is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
 
You should have received a copy of the GNU General Public License
along with JSON-RPC PHP; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

$config = include('config.php');

$settings = [
    'blocksperpage' => $config['blocksperpage'],
    'latest_version' => '00',
    'explorer_link_blk' => 'https://blockbook.myralicious.com/block/',
    'release_link' => 'https://www.reddit.com/r/myriadcoin/comments/9l0apt/myriadcoin_01440/',
    'rpcusername' => $config['rpcusername'],
    'rpcpassword' => $config['rpcpassword'],
    'rpcaddress' => $config['rpcaddress'],
];

require_once('jsonRPCClient.php');

date_default_timezone_set('UTC');

function hex2str($hex) {
    $str = '';
    for($i=0;$i<strlen($hex);$i+=2) $str .= chr(hexdec(substr($hex,$i,2)));
    return $str;
}

function getminerinfo($cb) {
    $name = 'unknown';
    $url = 'none';
    $scb = hex2str($cb);

    $mdb = include('pools.php');

    foreach ($mdb as $pool) {
        foreach ($pool["cbstrings"] as $cbs) {
            if (strpos($scb,$cbs) !== false) {
                $name = $pool["name"];
                $url = $pool["url"];
            }
        }
    }
    $ret = [ 'name' => $name, 'url' => $url];
    return $ret;
}

function blocklist($settings) {
    $coin = new jsonRPCClient("http://".$settings['rpcusername'].":".
        $settings['rpcpassword']."@".$settings['rpcaddress']);
    $info = $coin->getinfo();
    $tipblock = $info["blocks"];
    if (array_key_exists('startblock',$_GET)) {
        $lastblock = $_GET["startblock"];
    } else {
        $lastblock = $tipblock;
    }
    if ($lastblock==$tipblock) {
        print("<p><a href='.?startblock=".
            ($lastblock-$settings['blocksperpage']-1)."'>Previous ".
            $settings['blocksperpage']."</a></p>");
    } else {
        print("<p><a href=.?startblock=".
            ($lastblock+$settings['blocksperpage']+1)."> Next ".
            $settings['blocksperpage']."</a> -- <a href='.?startblock=".
            ($lastblock-$settings['blocksperpage']-1)."'>Previous ".
            $settings['blocksperpage']."</a></p>");
    }
    print("<hr>");
    print("<table class='table'>");
    print("<thead>");
    print("<tr>");
    print("<th scope='col'>Block Number</th>");
    print("<th scope='col'>Block Version</th>");
    print("<th scope='col'><a href=".$settings['release_link'].
        " target='_blank'>Latest Version</a></th>");
    print("<th scope='col'>Algo</th>");
    print("<th scope='col'>PoW</th>");
    print("<th scope='col'>TX Count</th>");
    print("<th scope='col'>Mined By</th>");
    print("<th scope='col'>Coinbase String</th>");
    print("</tr>");
    print("<tbody>");
    for ($i=0; $i<=$settings['blocksperpage']; $i++) {
        $blocknum = $lastblock - $i;
        $bhash = $coin->getblockhash($blocknum);
        $block = $coin->getblock($bhash);
        $blockversion = $block["version"];
        $algo = $block["pow_algo"];
        $isauxpow = false;
        $numtxs = sizeof($block["tx"]);;
        if (array_key_exists('auxpow',$block)) $isauxpow = true;
        // Get coinbase:
        if ($isauxpow) {
            $cb = $block["auxpow"]["tx"]["vin"][0]["coinbase"];
        } else {
            $cbtx = $coin->getrawtransaction($block["tx"][0],true);
            $cb = $cbtx["vin"][0]["coinbase"];
        }
        $pool = getminerinfo($cb);
        //print_r($cb);
        if ($pool["name"] == "unknown") {
            $poollink = "";
        } else {
            $poollink = "<a href='".$pool["url"]."'>".$pool["name"]."</a>";
        }
        if ($isauxpow) $saux = 'AuxPow'; else $saux = 'PoW';
        $supgraded_class = 'bg-danger';
        $supgraded = 'no';
        if (dechex($blockversion & 0x000000ff) == $settings['latest_version']) {
            $supgraded_class = 'bg-success';
            $supgraded = 'yes';
        }
        if (array_key_exists('filteralgo',$_GET)) {
            // we can use this to filter, so something like:
            // index.php?startblock=12345&filteralgo=sha256d
            if ($_GET['filteralgo']==$algo) {
                $printline = true;
            } else {
                $printline = false;
            }
        } else {
            $printline = true;
        }
        if ($printline) {
            print("<tr><th><a href='".$settings['explorer_link_blk'].$bhash.
                "' target='_blank'>".$blocknum."</a></th><td>0x".
                dechex($blockversion)."</th><td class='".$supgraded_class.
                "'>".$supgraded."</th><td>".$algo."</td><td>".$saux.
                "</td><td>".$numtxs."</td><td>".$poollink.
                "</td><td>".hex2str($cb)."</td></tr>");
        }
    }
    print("</tbody>");
    print("</table>");
    print("<hr>");
    if ($lastblock==$tipblock) {
        print("<p><a href='.?startblock=".
            ($lastblock-$settings['blocksperpage']-1)."'>Previous ".
            $settings['blocksperpage']."</a></p>");
    } else {
        print("<p><a href=.?startblock=".
            ($lastblock+$settings['blocksperpage']+1)."> Next ".
            $settings['blocksperpage']."</a> -- <a href='.?startblock=".
            ($lastblock-$settings['blocksperpage']-1)."'>Previous ".
            $settings['blocksperpage']."</a></p>");
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="refresh" content="600">
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap-theme.min.css" integrity="sha384-rHyoN1iRsVXV4nD0JutlnGaslCJuC7uwjduW9SVrLvRYooPp2bWYgmgJQIXwl/Sp" crossorigin="anonymous">
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.1.1/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>
<title>Myriad Miners</title>
</head>
<body>

<nav class="navbar navbar-inverse">
<div class="container-fluid">

<div class="navbar-header">
  <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#myNavbar">
    <span class="icon-bar"></span>
    <span class="icon-bar"></span>
    <span class="icon-bar"></span>
  </button>
  <a class="navbar-brand" href=".">Myriadcoin Miners</a>
</div>

<div class="collapse navbar-collapse" id="myNavbar">

  <!--
  <ul class="nav navbar-nav">

    <li><a href="#">nt</a></li>
    <li><a href="#">nt</a></li>
    <li><a href="#">nt</a></li>

  </ul>
  -->

  <!--
  <ul class="nav navbar-nav navbar-right">
    <li><a href="/logout"><span class="glyphicon glyphicon-log-out"></span> Logout</a></li>
    <li><a href="/login"><span class="glyphicon glyphicon-log-in"></span> Login</a></li>
  </ul>
  -->

</div>

</div>
</nav>

<p>
<?php blocklist($settings) ?>
</p>

<footer class="footer">
  <div class="container">
    <span class="text-muted">Please see <a href="https://github.com/cryptapus/php-coinbase-miners">php-coinbase-miners</a> to add your pool.</span>
  </div>
</footer>

</body>
</html>
