<?php

namespace Tests\Unit;

use App\Services\Payment\RedsysService;
use Tests\TestCase;

class RedsysServiceTest extends TestCase
{
    private RedsysService $redsys;

    protected function setUp(): void
    {
        parent::setUp();
        // Usando chave de testes oficial da documentação do Redsys
        $this->redsys = new RedsysService(
            merchantCode: '999008881',
            terminal: '001',
            secretKey: 'sq7HjrUOBfKmC5Jw6ZGg14hqEcTZip5t',
            environment: 'test'
        );
    }

    public function test_endpoint_url_matches_environment(): void
    {
        $this->assertEquals(RedsysService::TEST_URL, $this->redsys->getEndpointUrl());

        $liveService = new RedsysService(environment: 'live');
        $this->assertEquals(RedsysService::LIVE_URL, $liveService->getEndpointUrl());
    }

    public function test_generates_valid_12_character_order_number_with_first_4_digits(): void
    {
        $order = $this->redsys->generateOrderNumber();

        $this->assertEquals(12, strlen($order));
        $this->assertTrue(ctype_digit(substr($order, 0, 4)), 'Os primeiros 4 caracteres devem ser estritamente numéricos.');
    }

    public function test_creates_payment_payload_with_cents_and_correct_params(): void
    {
        $payload = $this->redsys->createPaymentPayload(
            amountInEuros: 1210.00,
            orderNumber: '260900000001',
            urlOk: 'https://nume.app/sucesso',
            urlKo: 'https://nume.app/cancelado',
            notificationUrl: 'https://nume.app/webhooks/redsys',
            payMethod: 'z', // Bizum
            productDescription: 'Pack Sofi Retention 4 Vídeos',
            titular: 'Rodrigo Souza'
        );

        $this->assertEquals('HMAC_SHA256_V1', $payload['signature_version']);
        $this->assertNotEmpty($payload['merchant_parameters']);
        $this->assertNotEmpty($payload['signature']);

        $decoded = $this->redsys->decodeParameters($payload['merchant_parameters']);

        $this->assertEquals('121000', $decoded['DS_MERCHANT_AMOUNT']);
        $this->assertEquals('260900000001', $decoded['DS_MERCHANT_ORDER']);
        $this->assertEquals('999008881', $decoded['DS_MERCHANT_MERCHANTCODE']);
        $this->assertEquals('978', $decoded['DS_MERCHANT_CURRENCY']);
        $this->assertEquals('001', $decoded['DS_MERCHANT_TERMINAL']);
        $this->assertEquals('z', $decoded['DS_MERCHANT_PAYMETHODS']);
    }

    public function test_signature_verification_succeeds_for_valid_parameters(): void
    {
        $orderNumber = '260912345678';
        $params = [
            'Ds_Amount' => '50000',
            'Ds_Order' => $orderNumber,
            'Ds_MerchantCode' => '999008881',
            'Ds_Currency' => '978',
            'Ds_Response' => '0000',
            'Ds_AuthorisationCode' => '123456',
        ];

        $merchantParametersBase64 = base64_encode(json_encode($params));
        $signature = $this->redsys->calculateSignature($merchantParametersBase64, $orderNumber);

        $isValid = $this->redsys->verifySignature($merchantParametersBase64, $signature);
        $this->assertTrue($isValid, 'A assinatura gerada e validada deve ser idêntica.');

        // Teste de adulteração (Tampering)
        $tamperedParams = $params;
        $tamperedParams['Ds_Amount'] = '1000'; // Alterou o valor
        $tamperedBase64 = base64_encode(json_encode($tamperedParams));

        $this->assertFalse($this->redsys->verifySignature($tamperedBase64, $signature), 'Parâmetro adulterado deve falhar na assinatura.');
    }

    public function test_identifies_successful_response_codes(): void
    {
        $this->assertTrue($this->redsys->isResponseSuccessful('0000'));
        $this->assertTrue($this->redsys->isResponseSuccessful('0099'));
        $this->assertTrue($this->redsys->isResponseSuccessful(0));
        $this->assertFalse($this->redsys->isResponseSuccessful('0101'));
        $this->assertFalse($this->redsys->isResponseSuccessful('9999'));
    }
}
