<?php

namespace AIToolkit\AIToolkit\Services;

class EncryptionService
{
    /**
     * Encrypt an API key for storage in database
     */
    public function encryptApiKey(string $apiKey): string
    {
        return encrypt($apiKey);
    }

    /**
     * Decrypt an API key for use
     */
    public function decryptApiKey(string $encryptedApiKey): string
    {
        return decrypt($encryptedApiKey);
    }

    /**
     * Mask API key for display (shows only last 4 characters)
     */
    public function maskApiKey(string $encryptedApiKey): string
    {
        try {
            $decrypted = $this->decryptApiKey($encryptedApiKey);
            if (strlen($decrypted) <= 4) {
                return str_repeat('*', strlen($decrypted));
            }
            return str_repeat('*', strlen($decrypted) - 4) . substr($decrypted, -4);
        } catch (\Exception $e) {
            return 'Invalid key';
        }
    }

    /**
     * Check if a value is already encrypted
     */
    public function isEncrypted(string $value): bool
    {
        try {
            decrypt($value);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
