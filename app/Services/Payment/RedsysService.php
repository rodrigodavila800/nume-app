<?php

namespace App\Services\Payment;

use App\Models\Setting;
use App\Support\Money;
use Exception;

class RedsysService
{
    public const TEST_URL = 'https://sis-t.redsys.es:25443/sis/realizarPago';
    public const LIVE_URL = 'https://sis.redsys.es/sis/realizarPago';

    public const SIGNATURE_VERSION = 'HMAC_SHA256_V1';

    public const CURRENCY_EUR = '978';

    protected string $merchantCode;
    protected string $terminal;
    protected string $secretKey;
    protected string $environment;

    public function __construct(
        ?string $merchantCode = null,
        ?string $terminal = null,
        ?string $secretKey = null,
        ?string $environment = null
    ) {
        $this->merchantCode = $merchantCode ?? (string) Setting::get('redsys_merchant_code', config('services.redsys.merchant_code', '999008881'));
        $this->terminal = $terminal ?? (string) Setting::get('redsys_terminal', config('services.redsys.terminal', '001'));
        $this->secretKey = $secretKey ?? (string) Setting::get('redsys_secret_key', config('services.redsys.secret_key', 'sq7HjrUOBfKmC5Jw6ZGg14hqEcTZip5t'));
        $this->environment = $environment ?? (string) Setting::get('redsys_environment', config('services.redsys.environment', 'test'));
    }

    public function merchantCode(): string
    {
        return $this->merchantCode;
    }

    /**
     * Retorna a URL do formulário Redsys conforme o ambiente.
     */
    public function getEndpointUrl(): string
    {
        return $this->environment === 'live' ? self::LIVE_URL : self::TEST_URL;
    }

    /**
     * Gera um código de pedido único de 12 dígitos compatível com as regras do Redsys:
     * - Os primeiros 4 caracteres DEVEM ser estritamente numéricos.
     * - Tamanho total: 12 caracteres.
     */
    public function generateOrderNumber(): string
    {
        // Formato: 4 dígitos de data (mdH) + 8 dígitos aleatórios numéricos
        $prefix = date('md');
        $random = str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
        return substr($prefix . $random, 0, 12);
    }

    /**
     * Prepara os parâmetros do formulário e calcula a assinatura SHA-256.
     *
     * @param float $amountInEuros Valor total já com IVA incluído
     * @param string $orderNumber Código do pedido (12 caracteres)
     * @param string $urlOk URL de retorno após sucesso
     * @param string $urlKo URL de retorno após cancelamento
     * @param string $notificationUrl URL do webhook assíncrono
     * @param string|null $payMethod 'z' para Bizum, 'T' para Cartão, null para aberto
     * @param string|null $productDescription Descrição do serviço
     * @param string|null $titular Nome do cliente
     * @return array
     */
    public function createPaymentPayload(
        float $amountInEuros,
        string $orderNumber,
        string $urlOk,
        string $urlKo,
        string $notificationUrl,
        ?string $payMethod = null,
        ?string $productDescription = null,
        ?string $titular = null
    ): array {
        // Mesmo cálculo usado na conferência do Ds_Amount (PackPaymentConfirmer)
        $amountInCents = Money::cents($amountInEuros);

        $params = [
            'DS_MERCHANT_AMOUNT' => (string) $amountInCents,
            'DS_MERCHANT_ORDER' => $orderNumber,
            'DS_MERCHANT_MERCHANTCODE' => $this->merchantCode,
            'DS_MERCHANT_CURRENCY' => self::CURRENCY_EUR,
            'DS_MERCHANT_TRANSACTIONTYPE' => '0', // Autorização / Compra
            'DS_MERCHANT_TERMINAL' => $this->terminal,
            'DS_MERCHANT_MERCHANTURL' => $notificationUrl,
            'DS_MERCHANT_URLOK' => $urlOk,
            'DS_MERCHANT_URLKO' => $urlKo,
        ];

        if (! empty($payMethod)) {
            $params['DS_MERCHANT_PAYMETHODS'] = $payMethod; // 'z' = Bizum, 'T' = Cartão
        }

        if (! empty($productDescription)) {
            $params['DS_MERCHANT_PRODUCTDESCRIPTION'] = mb_substr($productDescription, 0, 125);
        }

        if (! empty($titular)) {
            $params['DS_MERCHANT_TITULAR'] = mb_substr($titular, 0, 60);
        }

        $merchantParametersBase64 = base64_encode(json_encode($params, JSON_UNESCAPED_SLASHES));
        $signature = $this->calculateSignature($merchantParametersBase64, $orderNumber);

        return [
            'action_url' => $this->getEndpointUrl(),
            'signature_version' => self::SIGNATURE_VERSION,
            'merchant_parameters' => $merchantParametersBase64,
            'signature' => $signature,
            'order_number' => $orderNumber,
            'params' => $params,
        ];
    }

    /**
     * Calcula a assinatura oficial HMAC SHA-256 da Redsys.
     * Algoritmo:
     * 1. Decodifica chave secreta de Base64.
     * 2. Deriva chave 3DES usando o Order Number com IV de 8 zeros.
     * 3. Aplica HMAC SHA-256 sobre os parâmetros em Base64.
     */
    public function calculateSignature(string $merchantParametersBase64, string $orderNumber): string
    {
        $decodedKey = base64_decode($this->secretKey);
        $iv = "\0\0\0\0\0\0\0\0";

        // Derivação 3DES (Triple DES CBC)
        $derivedKey = openssl_encrypt(
            $orderNumber,
            'DES-EDE3-CBC',
            $decodedKey,
            OPENSSL_RAW_DATA,
            $iv
        );

        // HMAC SHA-256
        $res = hash_hmac('sha256', $merchantParametersBase64, $derivedKey, true);

        return base64_encode($res);
    }

    /**
     * Decodifica a resposta JSON recebida da Redsys.
     */
    public function decodeParameters(string $merchantParametersBase64): array
    {
        $json = base64_decode(strtr($merchantParametersBase64, '-_', '+/'));
        $data = json_decode($json, true);

        return is_array($data) ? $data : [];
    }

    /**
     * Valida a assinatura de retorno do Redsys contra ataques de falsificação (Timing Attack safe).
     */
    public function verifySignature(string $merchantParametersBase64, string $receivedSignature): bool
    {
        $data = $this->decodeParameters($merchantParametersBase64);
        $orderNumber = $data['Ds_Order'] ?? $data['DS_MERCHANT_ORDER'] ?? null;

        if (empty($orderNumber)) {
            return false;
        }

        $expectedSignature = $this->calculateSignature($merchantParametersBase64, (string) $orderNumber);

        // Normalização de Base64 URL Safe para comparação segura
        $cleanExpected = strtr($expectedSignature, '+/', '-_');
        $cleanReceived = strtr($receivedSignature, '+/', '-_');

        return hash_equals($cleanExpected, $cleanReceived);
    }

    /**
     * Verifica se o código de resposta indica aprovação da transação.
     * Códigos de 0000 a 0099 indicam transação autorizada com sucesso.
     */
    public function isResponseSuccessful(string|int|null $responseCode): bool
    {
        // Só código numérico (até 4 dígitos): vazio ou texto não pode virar 0 = "aprovado".
        $code = trim((string) $responseCode);

        if (! preg_match('/^\d{1,4}$/', $code)) {
            return false;
        }

        return (int) $code <= 99;
    }
}
