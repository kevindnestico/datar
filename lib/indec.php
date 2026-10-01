<?php
/**
 * Series del INDEC, tomadas de la API de Series de Tiempo de datos.gob.ar (https://datos.gob.ar/series/api).
 * El INDEC no tiene API propia: publica sus series ahí. No requiere token.
 * Reutiliza la caché y los reintentos de lib/bcra.php.
 */
require_once __DIR__ . '/bcra.php';

const INDEC_API = 'https://apis.datos.gob.ar/series/api';

/**
 * Series que usa el sitio: id => [nombre, unidad, escala].
 * La escala pasa a porcentaje las series que vienen como fracción (0,079 → 7,9%).
 * Las fechas son el primer día del período: '2026-04-01' es abril (mensual) o el 2.º trimestre (trimestral).
 */
const INDEC_SERIES = [
    // Actividad
    '143.3_NO_PR_2004_A_21' => ['EMAE', 'Índice 2004=100'],
    '143.3_NO_PR_2004_A_31' => ['EMAE desestacionalizado', 'Índice 2004=100'],
    '11.3_ISOM_2004_M_39'   => ['Agro, ganadería y silvicultura', 'Índice 2004=100'],
    '11.3_VIPAA_2004_M_5'   => ['Pesca', 'Índice 2004=100'],
    '11.3_ISD_2004_M_26'    => ['Minas y canteras', 'Índice 2004=100'],
    '11.3_VMASD_2004_M_23'  => ['Industria manufacturera', 'Índice 2004=100'],
    '11.3_ITC_2004_M_21'    => ['Electricidad, gas y agua', 'Índice 2004=100'],
    '11.3_VMATC_2004_M_12'  => ['Construcción', 'Índice 2004=100'],
    '11.3_AGCS_2004_M_41'   => ['Comercio', 'Índice 2004=100'],
    '11.3_P_2004_M_20'      => ['Hoteles y restaurantes', 'Índice 2004=100'],
    '11.3_EMC_2004_M_25'    => ['Transporte y comunicaciones', 'Índice 2004=100'],
    '11.3_IM_2004_M_25'     => ['Intermediación financiera', 'Índice 2004=100'],
    '11.3_SEGA_2004_M_48'   => ['Inmobiliarias y empresariales', 'Índice 2004=100'],
    '11.3_C_2004_M_60'      => ['Administración pública', 'Índice 2004=100'],
    '11.3_CMMR_2004_M_10'   => ['Enseñanza', 'Índice 2004=100'],
    '11.3_HR_2004_M_24'     => ['Salud', 'Índice 2004=100'],
    '11.3_TAC_2004_M_60'    => ['Otros servicios comunitarios', 'Índice 2004=100'],
    '453.1_SERIE_ORIGNAL_0_0_14_46' => ['Industria (IPI manufacturero)', 'Índice 2004=100'],
    '33.2_ISAC_NIVELRAL_0_M_18_63'  => ['Construcción (ISAC)', 'Índice 2004=100'],
    '31.3_UNG_2004_M_18'    => ['Capacidad instalada utilizada en la industria', '%'],
    '9.2_PP2_2004_T_16'     => ['PIB a precios constantes', 'Millones de pesos de 2004'],
    '9.2_PPCDC_2004_T_33'   => ['PIB per cápita', 'Dólares corrientes'],

    // Precios
    '148.3_INIVELNAL_DICI_M_26' => ['IPC nivel general', 'Índice dic 2016=100'],
    '148.3_INUCLEONAL_DICI_M_19' => ['IPC núcleo', 'Índice dic 2016=100'],
    '148.3_IREGULANAL_DICI_M_22' => ['IPC regulados', 'Índice dic 2016=100'],
    '148.3_IESTACINAL_DICI_M_25' => ['IPC estacionales', 'Índice dic 2016=100'],
    '146.3_IALIMENNAL_DICI_M_45' => ['Alimentos y bebidas', 'Índice dic 2016=100'],
    '146.3_IBEBIDANAL_DICI_M_39' => ['Bebidas alcohólicas y tabaco', 'Índice dic 2016=100'],
    '146.3_IPRENDANAL_DICI_M_35' => ['Ropa y calzado', 'Índice dic 2016=100'],
    '146.3_IVIVIENNAL_DICI_M_52' => ['Vivienda y servicios', 'Índice dic 2016=100'],
    '146.3_IEQUIPANAL_DICI_M_46' => ['Equipamiento del hogar', 'Índice dic 2016=100'],
    '146.3_ISALUDNAL_DICI_M_18'  => ['Salud', 'Índice dic 2016=100'],
    '146.3_ITRANSPNAL_DICI_M_23' => ['Transporte', 'Índice dic 2016=100'],
    '146.3_ICOMUNINAL_DICI_M_27' => ['Comunicación', 'Índice dic 2016=100'],
    '146.3_IRECREANAL_DICI_M_31' => ['Recreación y cultura', 'Índice dic 2016=100'],
    '146.3_IEDUCACNAL_DICI_M_22' => ['Educación', 'Índice dic 2016=100'],
    '146.3_IRESTAUNAL_DICI_M_33' => ['Restaurantes y hoteles', 'Índice dic 2016=100'],
    '146.3_IBIENESNAL_DICI_M_36' => ['Bienes y servicios varios', 'Índice dic 2016=100'],
    '145.3_INGGBAGBA_DICI_M_10'  => ['GBA', 'Índice dic 2016=100'],
    '145.3_INGPAMANA_DICI_M_15'  => ['Pampeana', 'Índice dic 2016=100'],
    '145.3_INGNOANOA_DICI_M_10'  => ['Noroeste', 'Índice dic 2016=100'],
    '145.3_INGNEANEA_DICI_M_10'  => ['Noreste', 'Índice dic 2016=100'],
    '145.3_INGCUYUYO_DICI_M_11'  => ['Cuyo', 'Índice dic 2016=100'],
    '145.3_INGPATNIA_DICI_M_16'  => ['Patagonia', 'Índice dic 2016=100'],
    '150.1_LA_POBREZA_0_D_13'    => ['Línea de pobreza', 'Pesos corrientes'],
    '150.1_LA_INDICIA_0_D_16'    => ['Línea de indigencia', 'Pesos corrientes'],

    // Salarios
    '149.1_TL_INDIIOS_OCTU_0_21' => ['Índice de salarios', 'Índice oct 2016=100'],
    '149.1_SOR_PRIADO_OCTU_0_25' => ['Privados registrados', 'Índice oct 2016=100'],
    '149.1_SOR_PUBICO_OCTU_0_14' => ['Públicos', 'Índice oct 2016=100'],
    '149.1_SOR_PRIADO_OCTU_0_28' => ['Privados no registrados', 'Índice oct 2016=100'],

    // Trabajo e ingresos
    '42.3_EPH_PUNTUATAL_0_M_30' => ['Desocupación', '%', 100],
    '52.2_ASDJ_0_0_37'          => ['Asalariados sin descuento jubilatorio', '%', 100],
    '65.1_CGI_0_0_21'           => ['Coeficiente de Gini', 'Coeficiente (0 a 1)'],
    '53.1_TRTA_0_0_37'          => ['Remuneración del trabajo asalariado', '% del ingreso', 100],
    '53.1_EBE_0_0_27'           => ['Excedente bruto de explotación', '% del ingreso', 100],
    '53.1_TIMB_0_0_25'          => ['Ingreso mixto (cuentapropistas)', '% del ingreso', 100],

    // Comercio exterior
    '74.3_IET_0_M_16'   => ['Exportaciones', 'Millones de US$'],
    '74.3_IIT_0_M_25'   => ['Importaciones', 'Millones de US$'],
    '74.3_ISC_0_M_19'   => ['Saldo comercial', 'Millones de US$'],
    '74.3_IEPP_0_M_35'  => ['Productos primarios', 'Millones de US$'],
    '74.3_IEMOA_0_M_48' => ['Manufacturas agropecuarias (MOA)', 'Millones de US$'],
    '74.3_IEMOI_0_M_46' => ['Manufacturas industriales (MOI)', 'Millones de US$'],
    '74.3_IECE_0_M_35'  => ['Combustibles y energía', 'Millones de US$'],
    '74.3_IBCPP_0_M_32' => ['Bienes de capital y sus piezas', 'Millones de US$'],
    '74.3_IIBI_0_M_36'  => ['Bienes intermedios', 'Millones de US$'],
    '74.3_IICL_0_M_42'  => ['Combustibles y lubricantes', 'Millones de US$'],
    '74.3_IIBCVAPR_0_M_58' => ['Bienes de consumo y autos', 'Millones de US$'],
    '74.3_IIR_0_M_23'   => ['Resto', 'Millones de US$'],
    '82.2_ITI_2004_T_27' => ['Términos del intercambio', 'Índice 2004=100'],
    '82.2_IPE_2004_T_26' => ['Precios de exportación', 'Índice 2004=100'],
    '82.2_IPI_2004_T_26' => ['Precios de importación', 'Índice 2004=100'],
    '322.2_TURISMO_RETAL__23_18'  => ['Turistas que llegan (receptivo)', 'Miles de personas'],
    '322.2_TURISMO_EMTAL__21_100' => ['Turistas que salen (emisivo)', 'Miles de personas'],
];

/** Serie completa del INDEC: [['fecha' => 'Y-m-d', 'valor' => float], ...] */
function indec_serie(string $id): array
{
    if (!isset(INDEC_SERIES[$id])) {
        throw new BcraException("Serie del INDEC no disponible: $id");
    }
    $escala = INDEC_SERIES[$id][2] ?? 1;
    $q = http_build_query(['ids' => $id, 'limit' => 5000, 'format' => 'json', 'metadata' => 'none']);
    $json = bcra_get(INDEC_API . "/series/?$q");
    $out = [];
    foreach ($json['data'] ?? [] as [$fecha, $valor]) {
        if ($valor !== null) {
            $out[] = ['fecha' => $fecha, 'valor' => $valor * $escala];
        }
    }
    return $out;
}

/** true si la serie es trimestral (los datos están separados por ~3 meses). */
function indec_es_trimestral(array $serie): bool
{
    $n = count($serie);
    return $n > 1 && strtotime($serie[$n - 1]['fecha']) - strtotime($serie[$n - 2]['fecha']) > 45 * 86400;
}

/**
 * Transforma una serie: 'var_m' variación contra el período anterior, 'var_ia' contra el mismo período
 * del año anterior (12 meses o 4 trimestres). Cualquier otro modo la deja igual.
 */
function indec_transformar(array $serie, string $modo): array
{
    $lag = match ($modo) {
        'var_m'  => 1,
        'var_ia' => indec_es_trimestral($serie) ? 4 : 12,
        default  => 0,
    };
    if (!$lag) {
        return $serie;
    }
    $out = [];
    for ($i = $lag; $i < count($serie); $i++) {
        $out[] = ['fecha' => $serie[$i]['fecha'], 'valor' => ($serie[$i]['valor'] / $serie[$i - $lag]['valor'] - 1) * 100];
    }
    return $out;
}

/** Período legible: 'ago 2026' o '2.º trim. 2026'. */
function indec_periodo(string $fecha, bool $trimestral): string
{
    [$y, $m] = explode('-', $fecha);
    if ($trimestral) {
        return intdiv((int) $m - 1, 3) + 1 . '.º trim. ' . $y;
    }
    $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    return $meses[(int) $m - 1] . ' ' . $y;
}
