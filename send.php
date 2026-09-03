<?php
// Behandler kontaktskjemaet: sender henvendelsen til Telegram-kanalen
// og en kopi på e-post, og sender brukeren videre til takk.html.

// ==== INNSTILLINGER ====================================================
const BOT_TOKEN  = 'SETT_INN_BOT_TOKEN_HER';   // fra @BotFather
const CHAT_ID    = 'SETT_INN_CHAT_ID_HER';     // f.eks. '@kanalnavn' eller '-100xxxxxxxxxx'
const EMAIL_COPY = 'hallingdalarbeid88@gmail.com'; // tom streng '' = ingen e-postkopi
// =======================================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /'); exit; }

// Honningfelle mot roboter — feltet er usynlig for mennesker
if (!empty($_POST['_honey'])) { header('Location: takk.html'); exit; }

$felt = function ($navn, $maks = 3000) {
    $v = trim($_POST[$navn] ?? '');
    return mb_substr($v, 0, $maks);
};

$navn    = $felt('navn', 200);
$epost   = $felt('epost', 200);
$telefon = $felt('telefon', 60);
$melding = $felt('melding');

if ($navn === '' || $epost === '' || $melding === '') { header('Location: /'); exit; }

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

if (EMAIL_COPY !== '') {
    $emne = 'Ny henvendelse fra nettsiden';
    $hode = "From: nettside@hyttevaskogmaling.no\r\n"
          . "Reply-To: {$epost}\r\n"
          . "Content-Type: text/plain; charset=UTF-8\r\n";
    @mail(EMAIL_COPY, $emne, $tekst, $hode);
}

header('Location: takk.html');
exit;
