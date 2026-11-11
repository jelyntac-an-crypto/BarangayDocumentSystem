<?php
declare(strict_types=1);

// Encrypt a National ID using AES-256-GCM.
// Returns ciphertext, IV, and authentication tag.
function encryptNationalID(string $nationalID, string $key): array
{
    $keyBytes = hex2bin($key);

    if ($keyBytes === false || strlen($keyBytes) !==32) {
        throw new RuntimeException('Invalid encryption key.');
    }

    $iv =random_bytes(12);
    $tag = '';

    $ciphertext = openssl_encrypt(
        $nationalID,
        'aes-256-gcm',
        $keyBytes,
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );

    if ($ciphertext === false) {
        throw new RuntimeException('National ID encryption failed.');
    }

    return [
        'ciphertext' => base64_encode($ciphertext),
        'iv' => base64_encode($iv),
        'tag' => base64_encode($tag)
    ];
}

// Decrypt a National ID.
function decryptNationalId(
    string $ciphertext,
    string $iv,
    string $tag,
    string $key
): string {
    $keyBytes = hex2bin($key);

    if ($keyBytes === false || strlen($keyBytes) !==32) {
        throw new RuntimeException('Invalid encryption key.');
    }

    $encryptedBytes = base64_decode($ciphertext, true);
    $ivBytes = base64_decode($iv, true);
    $tagBytes = base64_decode($tag,true);

    if (
        $encryptedBytes === false ||
        $ivBytes === false ||
        strlen($ivBytes) !==12 ||
        $tagBytes === false ||
        strlen($tagBytes) !==16
    ) {
        throw new RuntimeException('Invalid encrypted National ID data.');
    }

    $plainText = openssl_decrypt(
        $encryptedBytes,
        'aes-256-gcm',
        $keyBytes,
        OPENSSL_RAW_DATA,
        $ivBytes,
        $tagBytes
    );

    if ($plainText === false) {
        throw new RuntimeException('National ID decryption failed.');
    }

    return $plainText;

}