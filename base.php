<?php

define("URL", "https://api.estadisticasbcra.com/");
define("TOKEN", "Authorization: BEARER ***REMOVED-TOKEN***");

$ch =curl_init();

curl_setopt($ch, CURLOPT_URL, URL.'base_div_res');

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLINFO_HEADER_OUT, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(TOKEN));

$information = curl_exec($ch);
$extra_info = curl_getinfo($ch);

//var_dump($extra_info);

$status_code = $extra_info['http_code'];

if ($status_code == 200) {

    $json_base = json_decode($information, true);

    $info = '';
    for ($i = 0; $i < count($json_base); $i++) {

        $info .= "<p> Datos de la fecha: ".$json_base[$i]['d']." :          "."<b>".$json_base[$i]['v']."</b></p><br>";

    }

}

curl_close($ch);
?>

<?php require_once('header.php');?>

    <br><br><br>
    <section>
    <div class="container-sm">

    <h1>Base/Reservas</h1><br>
    <p>Historial de la base monetaria dividida por las reservas internacionales</p><br>

    <?php echo $info; ?>

    </div>
    </section>

<?php require_once('footer.php');?>
