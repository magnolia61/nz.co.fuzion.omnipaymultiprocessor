<?php

use Civi\Payment\Exception\PaymentProcessorException;
use Civi\Test\HeadlessInterface;
use Civi\Test\TransactionalInterface;
use GuzzleHttp\Psr7\Response;

/**
 * Tests for refund support (supportsRefund / doRefund) using the Mollie gateway.
 *
 * All gateway traffic is intercepted by the mock http client injected via
 * Civi::$statics['Omnipay_Test_Config']['client'] (the same seam used by
 * SagepayTest / EwayTest) - no real network calls are made.
 *
 * @group headless
 */
class MollieRefundTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, TransactionalInterface {
  use \Civi\Test\Api3TestTrait;
  use HttpClientTestTrait;

  /**
   * ID of the payment processor created for the test.
   *
   * @var int
   */
  protected $paymentProcessorID;

  /**
   * @return \Civi\Test\CiviEnvBuilder
   * @throws \CRM_Extension_Exception_ParseException
   */
  public function setUpHeadless(): \Civi\Test\CiviEnvBuilder {
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  /**
   * Setup for test.
   *
   * @throws \CRM_Core_Exception
   */
  public function setUp(): void {
    parent::setUp();
    // For Mollie the user_name field maps to the gateway apiKey parameter.
    $this->paymentProcessorID = (int) $this->callAPISuccess('PaymentProcessor', 'create', [
      'payment_processor_type_id' => 'omnipay_Mollie',
      'name' => 'omnipay_Mollie',
      'user_name' => 'test_xxxxxxxxxxxxxxxxxxxxxxxxxx',
      'is_test' => 1,
      'sequential' => 1,
    ])['values'][0]['id'];
  }

  /**
   * Cleanup - make sure the mock client does not leak into other tests.
   */
  public function tearDown(): void {
    unset(Civi::$statics['Omnipay_Test_Config']);
    parent::tearDown();
  }

  /**
   * Get the processor object for the created Mollie processor.
   *
   * @return \CRM_Core_Payment_OmnipayMultiProcessor
   */
  protected function getProcessor() {
    return \Civi\Payment\System::singleton()->getById($this->paymentProcessorID);
  }

  /**
   * Queue a mock json response on the mock http client.
   *
   * @param int $status
   *   Http status code.
   * @param array $body
   *   Body to be json encoded.
   */
  protected function addMockJsonResponse(int $status, array $body): void {
    $this->getMockClient()->addResponse(
      new Response($status, ['Content-Type' => 'application/json'], json_encode($body))
    );
  }

  /**
   * The Mollie gateway exposes a refund() method so refunds are supported.
   */
  public function testSupportsRefund(): void {
    $this->assertTrue($this->getProcessor()->supportsRefund());
  }

  /**
   * A successful refund returns the new refund id & Completed status.
   *
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   */
  public function testDoRefundSuccess(): void {
    Civi::$statics['Omnipay_Test_Config'] = ['client' => $this->getHttpClient()];
    // Mollie's create-refund endpoint returns 201 with the refund resource.
    $this->addMockJsonResponse(201, [
      'resource' => 'refund',
      'id' => 're_test123',
      'amount' => ['value' => '195.00', 'currency' => 'EUR'],
      'status' => 'pending',
      'paymentId' => 'tr_test456',
    ]);

    $params = ['trxn_id' => 'tr_test456', 'amount' => 195.00, 'currency' => 'EUR'];
    $result = $this->getProcessor()->doRefund($params);

    $this->assertEquals('re_test123', $result['refund_trxn_id']);
    $this->assertEquals('Completed', $result['refund_status']);
    $this->assertEquals(0, $result['fee_amount']);

    // Check the outgoing request hit the refund endpoint for the original
    // payment & carried the right amount.
    $this->assertStringContainsString('/payments/tr_test456/refunds', $this->getRequestURLs()[0]);
    $this->assertStringContainsString('195.00', $this->getRequestBodies()[0]);
  }

  /**
   * A rejected refund (Mollie error response) throws rather than returning.
   *
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   */
  public function testDoRefundFailure(): void {
    Civi::$statics['Omnipay_Test_Config'] = ['client' => $this->getHttpClient()];
    // Error responses have no 'id' so RefundResponse::isSuccessful() is FALSE.
    $this->addMockJsonResponse(422, [
      'status' => 422,
      'title' => 'Unprocessable Entity',
      'detail' => 'The payment is already refunded',
    ]);

    $this->expectException(PaymentProcessorException::class);
    $params = ['trxn_id' => 'tr_test456', 'amount' => 195.00, 'currency' => 'EUR'];
    $this->getProcessor()->doRefund($params);
  }

  /**
   * Missing trxn_id / amount is rejected before any gateway call is made.
   *
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   */
  public function testDoRefundRequiresParams(): void {
    $this->expectException(PaymentProcessorException::class);
    $params = [];
    $this->getProcessor()->doRefund($params);
  }

}
