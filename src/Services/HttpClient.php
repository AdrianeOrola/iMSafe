<?php
declare(strict_types=1);
namespace ImSafe\Services;
use RuntimeException;

final class HttpClient {
    /** Fetch independent read-only lists concurrently (Manila's sub-municipalities). */
    public function getMany(array $urls, int $timeout = 12): array {
        $multi = curl_multi_init();
        $handles = [];
        try {
            foreach ($urls as $key => $url) {
                $handle = curl_init($url);
                if ($handle === false) throw new RuntimeException('Could not initialize location request.');
                curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => min(5, $timeout), CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: iMSafe/2.0']]);
                $handles[$key] = $handle;
                curl_multi_add_handle($multi, $handle);
            }
            do {
                $status = curl_multi_exec($multi, $running);
                if ($status !== CURLM_OK) throw new RuntimeException('Location requests failed.');
                if ($running) curl_multi_select($multi, .2);
            } while ($running);
            $bodies = [];
            foreach ($handles as $key => $handle) {
                $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
                if (curl_errno($handle) || $status < 200 || $status >= 300) throw new RuntimeException('A location list could not be retrieved.');
                $bodies[$key] = (string)curl_multi_getcontent($handle);
            }
            return $bodies;
        } finally {
            foreach ($handles as $handle) { curl_multi_remove_handle($multi, $handle); curl_close($handle); }
            curl_multi_close($multi);
        }
    }
    public function get(string $url, string $accept, int $timeout = 4): string {
        $handle = curl_init($url);
        if ($handle === false) throw new RuntimeException('Could not initialize the external request.');
        curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => min(10, $timeout), CURLOPT_HTTPHEADER => ['Accept: ' . $accept, 'User-Agent: iMSafe-localhost-oop/2.0']]);
        $body = curl_exec($handle); $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE); $error = curl_error($handle); curl_close($handle);
        if (!is_string($body) || $status < 200 || $status >= 300) throw new RuntimeException($error ?: 'Provider returned HTTP ' . $status . '.');
        return $body;
    }
    public function postJson(string $url, array $payload, array $headers = [], int $timeout = 20): array {
        $handle = curl_init($url);
        if ($handle === false) throw new RuntimeException('Could not initialize the external request.');
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $requestHeaders = array_merge(['Accept: application/json', 'Content-Type: application/json', 'User-Agent: iMSafe-iMAssist/2.0'], $headers);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(8, $timeout),
            CURLOPT_HTTPHEADER => $requestHeaders,
        ]);
        $response = curl_exec($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);
        if (!is_string($response) || $status < 200 || $status >= 300) throw new RuntimeException($error ?: 'AI provider returned HTTP ' . $status . '.');
        $decoded = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) throw new RuntimeException('AI provider returned an invalid response.');
        return $decoded;
    }
}
