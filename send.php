<?php
// Behandler kontaktskjemaet: sender henvendelsen til Telegram-kanalen
// og sender brukeren videre til takk.html.
//
// Spam-vern: honningfelle, minimumstid for utfylling, grense per IP
// og en samlet døgngrense så kanalen ikke kan oversvømmes.

// ==== INNSTILLINGER ====================================================
const BOT_TOKEN = 'SETT_INN_BOT_TOKEN_HER';  // fra @BotFather
const CHAT_ID   = 'SETT_INN_CHAT_ID_HER';    // f.eks. '@kanalnavn' eller '-100xxxxxxxxxx'

const MAX_PER_IP      = 3;     // maks innsendinger per IP ...
const IP_WINDOW_SEC   = 3600;  // ... per time
const MAX_PER_DAY_ALL = 40;    // maks innsendinger totalt per døgn (alle IP-er)
const MIN_FILL_SEC    = 4;     // raskere utfylling enn dette = robot
// =======================================================================

function ferdig() { header('Location: takk.html'); exit; }
function avvis()  { header('Location: /'); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { avvis(); }

// 1) Honningfelle — feltet er usynlig for mennesker.
//    Roboten avvises stille (den tror den lyktes).
if (!empty($_POST['_honey'])) { ferdig(); }

// 2) Minimumstid: skjemaet legger inn lastetidspunkt via JavaScript.
//    Mangler feltet (robot uten JS) eller gikk det under MIN_FILL_SEC — avvis.
$fts = (int)($_POST['fts'] ?? 0);
$alder = time() - (int)($fts / 1000);
if ($fts <= 0 || $alder < MIN_FILL_SEC || $alder > 86400) { ferdig(); }

// 3) Grense per IP og samlet døgngrense (enkel tellerfil i temp-katalogen)
$ip     = $_SERVER['REMOTE_ADDR'] ?? '';
$fil    = sys_get_temp_dir() . '/hyttevask-form-limits.json';
$naa    = time();
$fp = fopen($fil, 'c+');
if ($fp && flock($fp, LOCK_EX)) {
    $data = json_decode(stream_get_contents($fp), true) ?: ['ips' => [], 'day' => []];
    $data['ips'] = array_map(
        fn($tider) => array_values(array_filter($tider, fn($t) => $naa - $t < IP_WINDOW_SEC)),
        $data['ips']
    );
    $data['ips'] = array_filter($data['ips']);
    $data['day'] = array_values(array_filter($data['day'], fn($t) => $naa - $t < 86400));

    $nokkel = hash('sha256', $ip);
    $overIp  = count($data['ips'][$nokkel] ?? []) >= MAX_PER_IP;
    $overAlt = count($data['day']) >= MAX_PER_DAY_ALL;

    if (!$overIp && !$overAlt) {
        $data['ips'][$nokkel][] = $naa;
        $data['day'][] = $naa;
    }
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data));
    flock($fp, LOCK_UN);
    fclose($fp);

    if ($overIp || $overAlt) { ferdig(); }   // stille avvisning
}

// 4) Feltvalidering
$felt = function ($navn, $maks = 3000) {
    return mb_substr(trim($_POST[$navn] ?? ''), 0, $maks);
};
$navn    = $felt('navn', 200);
$epost   = $felt('epost', 200);
$telefon = $felt('telefon', 60);
$melding = $felt('melding');

if ($navn === '' || $melding === '' || !filter_var($epost, FILTER_VALIDATE_EMAIL)) { avvis(); }

$tekst = "📩 Ny henvendelse fra nettsiden\n\n"
       . "👤 Navn: {$navn}\n"
       . "✉️ E-post: {$epost}\n"
       . ($telefon !== '' ? "📞 Telefon: {$telefon}\n" : '')
       . "\n💬 Melding:\n{$melding}";

if (BOT_TOKEN !== 'SETT_INN_BOT_TOKEN_HER') {
    $ch = curl_init('https://api.telegram.org/bot' . BOT_TOKEN . '/sendMessage');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['chat_id' => CHAT_ID, 'text' => $tekst]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

ferdig();
