<?php
// Apple StoreKit 2 (JWS) imza doğrulaması: leaf -> intermediate -> Apple Root CA G3 zinciri + ES256 imza.
// Başarılıysa payload dizisini, değilse null döner.

const APPLE_ROOT_CA_G3_B64 = "MIICQzCCAcmgAwIBAgIILcX8iNLFS5UwCgYIKoZIzj0EAwMwZzEbMBkGA1UEAwwSQXBwbGUgUm9vdCBDQSAtIEczMSYwJAYDVQQLDB1BcHBsZSBDZXJ0aWZpY2F0aW9uIEF1dGhvcml0eTETMBEGA1UECgwKQXBwbGUgSW5jLjELMAkGA1UEBhMCVVMwHhcNMTQwNDMwMTgxOTA2WhcNMzkwNDMwMTgxOTA2WjBnMRswGQYDVQQDDBJBcHBsZSBSb290IENBIC0gRzMxJjAkBgNVBAsMHUFwcGxlIENlcnRpZmljYXRpb24gQXV0aG9yaXR5MRMwEQYDVQQKDApBcHBsZSBJbmMuMQswCQYDVQQGEwJVUzB2MBAGByqGSM49AgEGBSuBBAAiA2IABJjpLz1AcqTtkyJygRMc3RCV8cWjTnHcFBbZDuWmBSp3ZHtfTjjTuxxEtX/1H7YyYl3J6YRbTzBPEVoA/VhYDKX1DyxNB0cTddqXl5dvMVztK517IDvYuVTZXpmkOlEKMaNCMEAwHQYDVR0OBBYEFLuw3qFYM4iapIqZ3r6966/ayySrMA8GA1UdEwEB/wQFMAMBAf8wDgYDVR0PAQH/BAQDAgEGMAoGCCqGSM49BAMDA2gAMGUCMQCD6cHEFl4aXTQY2e3v9GwOAEZLuN+yRhHFD/3meoyhpmvOwgPUnPWTxnS4at+qIxUCMG1mihDK1A3UT82NQz60imOlM27jbdoXt2QfyFMm+YhidDkLF1vLUagM6BgD56KyKA==";

function apple_b64url_decode($data)
{
    $pad = strlen($data) % 4;
    if ($pad) {
        $data .= str_repeat('=', 4 - $pad);
    }
    return base64_decode(strtr($data, '-_', '+/'));
}

function apple_verify_jws($jws)
{
    $parts = explode('.', $jws);
    if (count($parts) !== 3) {
        return null;
    }
    $header = json_decode(apple_b64url_decode($parts[0]), true);
    if (!isset($header['x5c']) || !is_array($header['x5c']) || count($header['x5c']) < 2) {
        return null;
    }

    $pem = function ($b64) {
        return "-----BEGIN CERTIFICATE-----\n" . chunk_split($b64, 64, "\n") . "-----END CERTIFICATE-----\n";
    };
    $rootPem = $pem(APPLE_ROOT_CA_G3_B64);
    $leafPem = $pem($header['x5c'][0]);
    $interPem = $pem($header['x5c'][1]);

    $leaf = openssl_x509_read($leafPem);
    $inter = openssl_x509_read($interPem);
    $interKey = openssl_pkey_get_public($interPem);
    $rootKey = openssl_pkey_get_public($rootPem);
    if (!$leaf || !$inter || !$interKey || !$rootKey) {
        return null;
    }
    if (openssl_x509_verify($leaf, $interKey) !== 1 || openssl_x509_verify($inter, $rootKey) !== 1) {
        return null;
    }

    // IEEE P1363 (r||s) -> ASN.1 DER
    $sig = apple_b64url_decode($parts[2]);
    $r = ltrim(substr($sig, 0, 32), "\x00") ?: "\x00";
    $s = ltrim(substr($sig, 32, 32), "\x00") ?: "\x00";
    if (ord($r[0]) > 0x7f) {
        $r = "\x00" . $r;
    }
    if (ord($s[0]) > 0x7f) {
        $s = "\x00" . $s;
    }
    $der = "\x30" . chr(4 + strlen($r) + strlen($s)) . "\x02" . chr(strlen($r)) . $r . "\x02" . chr(strlen($s)) . $s;

    if (openssl_verify($parts[0] . '.' . $parts[1], $der, openssl_pkey_get_public($leaf), OPENSSL_ALGO_SHA256) !== 1) {
        return null;
    }
    $payload = json_decode(apple_b64url_decode($parts[1]), true);
    return is_array($payload) ? $payload : null;
}
