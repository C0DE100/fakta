<?php

class Encryption
{
    private string $key;
    private string $cipher = 'aes-256-cbc';

    public function __construct(string $key)
    {
        // Derive a proper 256-bit key from the passphrase
        $this->key = hash('sha256', $key, true);
    }

    public function encrypt(string $plainText): string
    {
        if (empty($plainText)) {
            return '';
        }

        $ivLength = openssl_cipher_iv_length($this->cipher);
        $iv = openssl_random_pseudo_bytes($ivLength);
        $encrypted = openssl_encrypt($plainText, $this->cipher, $this->key, 0, $iv);

        // Store IV with the encrypted data (IV is not secret, just needs to be unique)
        return base64_encode($iv . '::' . $encrypted);
    }

    public function decrypt(string $encryptedText): string
    {
        if (empty($encryptedText)) {
            return '';
        }

        $data = base64_decode($encryptedText);
        $parts = explode('::', $data, 2);

        if (count($parts) !== 2) {
            return '[Грешка при декрипција]';
        }

        $iv = $parts[0];
        $encrypted = $parts[1];

        $decrypted = openssl_decrypt($encrypted, $this->cipher, $this->key, 0, $iv);

        return $decrypted !== false ? $decrypted : '[Грешка при декрипција]';
    }
}