<?php

/**
 * Payzum — non-custodial crypto/stablecoin gateway (USDC, USDT and more,
 * multi-chain). Redirect-based: the contributor pays on a hosted checkout
 * page and the contribution completes through the signed payment
 * notification, which the Omnipay driver verifies (HMAC-SHA-512 over the
 * raw bytes, replay window) before any field is readable.
 *
 * The record will be automatically inserted, updated, or deleted from the
 * database as appropriate. For more details, see "hook_civicrm_managed" at:
 * http://wiki.civicrm.org/confluence/display/CRMDOC/Hook+Reference
 */
return [
  [
    'name' => 'OmniPay - Payzum',
    'entity' => 'payment_processor_type',
    'params' => [
      'version' => 3,
      'title' => 'OmniPay - Payzum',
      'name' => 'omnipay_Payzum',
      'description' => 'Omnipay Payzum Payment Processor (crypto/stablecoin, non-custodial)',
      'user_name_label' => 'apiKey',
      'password_label' => 'webhookSecret',
      'signature_label' => 'unused',
      'class_name' => 'Payment_OmnipayMultiProcessor',
      'url_site_default' => 'https://payzum.com',
      'url_api_default' => 'https://merchant.payzum.com',
      'billing_mode' => 4,
      'payment_type' => 1,
    ],
  ],
];
