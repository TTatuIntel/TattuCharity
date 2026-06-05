<?php
/**
 * MTN Mobile Money Collections API client.
 * https://momodeveloper.mtn.com/api-documentation/api-description/
 */
if (!defined('TATTU_INTERNAL')) { http_response_code(403); exit('Forbidden'); }
require_once __DIR__ . '/config.php';

class MoMoClient
{
    private string $base;
    private string $targetEnv;
    private string $subKey;
    private string $apiUser;
    private string $apiKey;

    public function __construct()
    {
        $this->base      = MOMO_ENV === 'sandbox'
            ? 'https://sandbox.momodeveloper.mtn.com'
            : 'https://proxy.momoapi.mtn.com';
        $this->targetEnv = MOMO_ENV;
        $this->subKey    = MOMO_SUBSCRIPTION_KEY;
        $this->apiUser   = MOMO_API_USER;
        $this->apiKey    = MOMO_API_KEY;
    }

    public function isConfigured(): bool
    {
        return $this->subKey  && $this->subKey  !== 'YOUR_SUBSCRIPTION_KEY'
            && $this->apiUser && $this->apiUser !== 'YOUR_API_USER_UUID'
            && $this->apiKey  && $this->apiKey  !== 'YOUR_API_KEY';
    }

    /** @return array{success:bool, token?:string, message?:string} */
    public function getToken(): array
    {
        $ch = curl_init($this->base . '/collection/token/');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_USERPWD        => $this->apiUser . ':' . $this->apiKey,
            CURLOPT_HTTPHEADER     => [
                'Ocp-Apim-Subscription-Key: ' . $this->subKey,
                'Content-Length: 0',
            ],
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            return ['success' => false, 'message' => 'Network error: ' . $err];
        }
        $body = json_decode($resp, true);
        if ($code !== 200 || empty($body['access_token'])) {
            error_log('MoMo token error: ' . $resp);
            return ['success' => false, 'message' => 'Could not obtain MoMo token (' . $code . ')'];
        }
        return ['success' => true, 'token' => $body['access_token']];
    }

    /**
     * @return array{success:bool, referenceId?:string, message?:string}
     */
    public function requestToPay(string $phone, float $amount, string $currency, string $note = 'Donation'): array
    {
        $tokenRes = $this->getToken();
        if (!$tokenRes['success']) return $tokenRes;

        $referenceId = $this->uuidV4();
        $payload = [
            'amount'       => (string) $amount,
            'currency'     => $currency,
            'externalId'   => bin2hex(random_bytes(8)),
            'payer'        => [
                'partyIdType' => 'MSISDN',
                'partyId'     => $phone,
            ],
            'payerMessage' => substr($note, 0, 160),
            'payeeNote'    => 'Thank you for your donation to ' . CHARITY_NAME,
        ];

        $ch = curl_init($this->base . '/collection/v1_0/requesttopay');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $tokenRes['token'],
                'X-Reference-Id: ' . $referenceId,
                'X-Target-Environment: ' . $this->targetEnv,
                'Ocp-Apim-Subscription-Key: ' . $this->subKey,
                'Content-Type: application/json',
            ],
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            return ['success' => false, 'message' => 'Network error: ' . $err];
        }
        if ($code !== 202) {
            error_log("MoMo requestToPay error ($code): $resp");
            $body = json_decode($resp, true);
            $msg  = $body['message'] ?? 'Payment request failed (' . $code . ')';
            return ['success' => false, 'message' => $msg];
        }
        return ['success' => true, 'referenceId' => $referenceId];
    }

    /**
     * @return array{success:bool, status?:string, message?:string}
     */
    public function getTransactionStatus(string $referenceId): array
    {
        $referenceId = trim($referenceId);
        if ($referenceId === '') {
            return ['success' => false, 'message' => 'Reference ID required.'];
        }

        $tokenRes = $this->getToken();
        if (!$tokenRes['success']) {
            return $tokenRes;
        }

        $ch = curl_init($this->base . '/collection/v1_0/requesttopay/' . rawurlencode($referenceId));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $tokenRes['token'],
                'X-Target-Environment: ' . $this->targetEnv,
                'Ocp-Apim-Subscription-Key: ' . $this->subKey,
            ],
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            return ['success' => false, 'message' => 'Network error: ' . $err];
        }
        if ($code === 404) {
            return ['success' => false, 'message' => 'Transaction not found at MoMo.'];
        }
        if ($code !== 200) {
            error_log("MoMo getTransactionStatus error ($code): $resp");
            return ['success' => false, 'message' => 'Could not fetch MoMo status (' . $code . ')'];
        }
        $body = json_decode($resp, true);
        $status = (string)($body['status'] ?? '');
        if ($status === '') {
            return ['success' => false, 'message' => 'MoMo returned no status.'];
        }
        return ['success' => true, 'status' => $status, 'raw' => $body];
    }

    private function uuidV4(): string
    {
        $b = random_bytes(16);
        $b[6] = chr(ord($b[6]) & 0x0f | 0x40);
        $b[8] = chr(ord($b[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
