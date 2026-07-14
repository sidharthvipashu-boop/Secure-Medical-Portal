<?php

require_once __DIR__ . '/../config/config.php';

function encrypt_data($plaintext)
{
    if ($plaintext === null || $plaintext === '') {
        return '';
    }

    $iv_length = openssl_cipher_iv_length(ENCRYPTION_CIPHER);

    $iv = openssl_random_pseudo_bytes($iv_length);

    $ciphertext = openssl_encrypt(
        $plaintext,
        ENCRYPTION_CIPHER,
        ENCRYPTION_KEY,
        OPENSSL_RAW_DATA,
        $iv
    );

    return base64_encode($iv . $ciphertext);
}

function decrypt_data($stored)
{
    if ($stored === null || $stored === '') {
        return '';
    }

    $raw = base64_decode($stored);
    if ($raw === false) {
        return '';
    }

    $iv_length  = openssl_cipher_iv_length(ENCRYPTION_CIPHER);
    $iv         = substr($raw, 0, $iv_length);
    $ciphertext = substr($raw, $iv_length);

    $plaintext = openssl_decrypt(
        $ciphertext,
        ENCRYPTION_CIPHER,
        ENCRYPTION_KEY,
        OPENSSL_RAW_DATA,
        $iv
    );

    return $plaintext === false ? '' : $plaintext;
}

function make_hash(array $fields)
{
    $canonical = implode('||', $fields);

    return hash('sha256', $canonical);
}

function verify_integrity(array $fields, $stored_hash)
{
    $current_hash = make_hash($fields);

    return hash_equals($stored_hash, $current_hash);
}

function make_file_hash($file_contents)
{
    return hash('sha256', $file_contents);
}
