<?php

define("URL", "http://api.currencylayer.com/");
define("TOKEN", "***REMOVED-API-KEY***");

$ch =curl_init();

curl_setopt($ch, CURLOPT_URL, URL.'live?access_key='.TOKEN.'&currencies=ARS&format=1');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$information = curl_exec($ch);

$extra_info = curl_getinfo($ch);
$status_code = $extra_info['http_code'];

$data = json_decode($information);

curl_close($ch);
?>

<?php require_once('header.php');?>

<br><br><br>

    <section>
        <div class="container-sm">
            <h2>Dolar super oficial</h2>
            <?php
            if (($status_code == 200) && ($data->success == "true")) {
            
                echo "Dolar oficial: $ ".$data->quotes->USDARS;
                echo "<br>";
                echo "Dolar turista: $ ".($data->quotes->USDARS)*1.3;
            
            };
            ?>
        </div>

<?php require_once('footer.php');?>
