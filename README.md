# Payum Sogecommerce (Société générale)

> A Payum gateway to use [Sogecommerce](https://sogecommerce.societegenerale.eu) (a French payment system)

[![Latest Stable Version](https://poser.pugx.org/yproximite/payum-sogecommerce/version)](https://packagist.org/packages/yproximite/payum-sogecommerce)

## Requirements

- PHP 8.1+
- [Payum](https://github.com/Payum/Payum)
- Optionally [PayumBundle](https://github.com/Payum/PayumBundle) and Symfony 5+

## Installation

```bash
$ composer require yproximite/payum-sogecommerce
```

## Configuration

### With PayumBundle (Symfony)

First register the gateway factory in your services definition:
```yaml
# config/services.yaml or app/config/services.yml
services:
    yproximite.sogecommerce_gateway_factory:
        class: Payum\Core\Bridge\Symfony\Builder\GatewayFactoryBuilder
        arguments: [Yproximite\Payum\Sogecommerce\SogecommerceGatewayFactory]
        tags:
            - { name: payum.gateway_factory_builder, factory: sogecommerce }
```

Then configure the gateway:

```yaml
# config/packages/payum.yaml or app/config/config.yml

payum:
  gateways:
    sogecommerce:
      factory: sogecommerce
      vads_site_id: 'change it' # required 
      certif_prod: 'change it' # required 
      certif_test: 'change it' # required 
      sandbox: true
      hash_algorithm: 'algo-sha1' # or 'algo-hmac-sha256'
```

### With Payum

```php
<?php
//config.php

use Payum\Core\PayumBuilder;
use Payum\Core\Payum;

/** @var Payum $payum */
$payum = (new PayumBuilder())
    ->addDefaultStorages()

    ->addGateway('gatewayName', [
        'factory'        => 'sogecommerce',
        'vads_site_id'   => 'change it', // required
        'certif_prod'    => 'change it', // required
        'certif_test'    => 'change it', // required
        'sandbox'        => true,
        'hash_algorithm' => 'algo-sha1' // or 'algo-hmac-sha256'
    ])

    ->getPayum()
;
```

### Why `hash_algorithm` is prefixed by `algo-`?

We wanted to use `sha1` or `hmac-256`, but there is currently a [Payum limitation](https://github.com/Payum/Payum/issues/692) which try to call `sha1` because it's a valid callable.

As a workaround, the only easy solution we thought was to prefix them with `algo-`.
Since `algo-sha1` is not a valid callable, there is no Payum issues and everything works well. 

## Usage

Make sure your `Payment` entity overrides `getNumber()` method like this:
```php
<?php

namespace App\Entity\Payment;

use Doctrine\ORM\Mapping as ORM;
use Payum\Core\Model\Payment as BasePayment;

/**
 * @ORM\Table
 * @ORM\Entity
 */
class Payment extends BasePayment
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     *
     * @var int
     */
    protected $id;

    /**
     * {@inheritdoc}
     */
    public function getNumber()
    {
        return (string) $this->id;
    }
}
```

By doing this, the library will be able to pick the payment's id and use it for the payment with Sogecommerce (we should send a transaction id between `000000` and `999999`). 

### Payment in several instalments

If you planned to support payments in several instalments, somewhere in your code you will need to call `Payment#setPartialAmount` to keep a trace of the amount per payment:

```php
<?php
class Payment extends BasePayment
{
    // ...

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $partialAmount;

    public function getPartialAmount(): ?int
    {
        return $this->partialAmount;
    }

    public function setPartialAmount(?int $partialAmount): void
    {
        $this->partialAmount = $partialAmount;
    }
}
```

#### Usage

```php
<?php

use App\Entity\Payment;
use Yproximite\Payum\Sogecommerce\Api;
use Yproximite\Payum\Sogecommerce\PaymentConfigGenerator;

// Define the periods
$periods = [
    ['amount' => 1000, 'date' => new \DateTime()],
    ['amount' => 2000, 'date' => (new \DateTime())->add(new \DateInterval('P1M'))],
    ['amount' => 3000, 'date' => (new \DateTime())->add(new \DateInterval('P2M'))],
];

// Compute total amount
$totalAmount = array_sum(array_column($periods, 'amount'));

// Compute `paymentConfig` fields that will be sent to the API
// It will generates something like this: MULTI_EXT:20190102=1000;20190202=2000;20190302=3000
$paymentConfig = (new PaymentConfigGenerator())->generate($periods);

// Then create payments
$storage = $payum->getStorage(Payment::class);
$payments = [];

foreach ($periods as $period) {
    $payment = $storage->create();
    $payment->setTotalAmount($totalAmount);
    $payment->setPartialAmount($period['amount']);

    $details = $payment->getDetails();
    $details[Api::FIELD_VADS_PAYMENT_CONFIG] = $generatedPaymentConfig;
    $payment->setDetails($details);

    $storage->update($payment);
    $payments[] = $payment;
}
```
