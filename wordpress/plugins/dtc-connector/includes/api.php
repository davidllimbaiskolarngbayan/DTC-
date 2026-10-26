<?php

defined('ABSPATH') || exit;

/** Thin client for the DTC Laravel API; the shared key never reaches the browser. */
final class DTC_Api
{
    public static function configured(): bool
    {
        return defined('DTC_API_URL') && defined('DTC_API_KEY') && DTC_API_KEY !== '';
    }

    /** @return array{ok:bool, status:int, body:array, message:string} */
    public static function request(string $method, string $path, array $payload = []): array
    {
        if (! self::configured()) {
            return self::failure(0, 'The warranty service is not configured.');
        }

        $args = [
            'method' => $method,
            'timeout' => 20,
            'headers' => ['X-DTC-Key' => DTC_API_KEY, 'Accept' => 'application/json'],
        ];
        if ($payload) {
            $args['body'] = $payload;
        }

        $response = wp_remote_request(rtrim(DTC_API_URL, '/') . '/' . ltrim($path, '/'), $args);
        if (is_wp_error($response)) {
            return self::failure(0, 'The warranty service is unavailable. Please try again later.');
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $body = is_array($body) ? $body : [];

        if ($status >= 200 && $status < 300) {
            return ['ok' => true, 'status' => $status, 'body' => $body, 'message' => ''];
        }

        $message = 422 === $status
            ? 'Please check the form and try again.'
            : 'Something went wrong. Please try again later.';

        return ['ok' => false, 'status' => $status, 'body' => $body, 'message' => $message];
    }

    /** Flattens Laravel field errors ("message" on register, "errors" elsewhere) into a list. */
    public static function errorMessages(array $result): array
    {
        $source = $result['body']['message'] ?? ($result['body']['errors'] ?? []);
        $messages = [];
        if (is_array($source)) {
            foreach ($source as $fieldErrors) {
                foreach ((array) $fieldErrors as $message) {
                    $messages[] = (string) $message;
                }
            }
        }

        return $messages ?: [$result['message']];
    }

    private static function failure(int $status, string $message): array
    {
        return ['ok' => false, 'status' => $status, 'body' => [], 'message' => $message];
    }
}
