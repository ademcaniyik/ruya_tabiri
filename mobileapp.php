<?php
// Kullanıcının tarayıcı bilgisini al
$userAgent = $_SERVER['HTTP_USER_AGENT'];

// Linklerini buraya tanımla
$androidLink = "https://play.google.com/store/apps/details?id=com.mws.ruya_tabiri_ai";
$iosLink = "https://apps.apple.com/tr/app/r%C3%BCya-tabiri-al/id6746343590"; // iOS linkin henüz yoksa buraya da Android linkini koyabilirsin geçici olarak.

// iPhone, iPad veya iPod mu kontrol et
if (strpos($userAgent, 'iPhone') !== false || strpos($userAgent, 'iPad') !== false || strpos($userAgent, 'iPod') !== false) {
    // Apple cihazsa App Store'a git
    header("Location: " . $iosLink);
} else {
    // Değilse (Android veya Masaüstü) Play Store'a git
    header("Location: " . $androidLink);
}
exit;
?>