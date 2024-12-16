<?php

declare(strict_types=1);

namespace Yproximite\Payum\Sogecommerce\Tests;

use Payum\Core\Bridge\Spl\ArrayObject;
use Yproximite\Payum\Sogecommerce\SogecommerceGatewayFactory;

class SogecommerceGatewayFactoryTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @test
     */
    public function shouldSubClassGatewayFactory()
    {
        $rc = new \ReflectionClass('Yproximite\Payum\SystemPay\SogecommerceGatewayFactory');

        $this->assertTrue($rc->isSubclassOf('Payum\Core\GatewayFactory'));
    }

    /**
     * @test
     * @expectedException \Payum\Core\Exception\LogicException
     * @expectedExceptionMessage The vads_site_id, certif_test, certif_prod fields are required.
     */
    public function shouldThrowIfRequiredOptionsAreNotPassed()
    {
        $factory = new SogecommerceGatewayFactory();

        $factory->create();
    }

    /**
     * @test
     */
    public function testDefaultOptions()
    {
        $factory = new SogecommerceGatewayFactory();

        $config = $factory->createConfig();

        $this->assertSame('sogecommerce', $config['payum.factory_name']);
        $this->assertSame('sogecommerce', $config['payum.factory_title']);

        $this->assertInstanceOf('Yproximite\Payum\Sogecommerce\Request\RequestStatusApplier', $config['payum.request_status_applier']);

        $this->assertInstanceOf('Yproximite\Payum\Sogecommerce\Action\CaptureAction', $config['payum.action.capture']);
        $this->assertInstanceOf('Yproximite\Payum\Sogecommerce\Action\NotifyAction', $config['payum.action.notify']);
        $this->assertInstanceOf('Yproximite\Payum\Sogecommerce\Action\StatusAction', $config['payum.action.status'](ArrayObject::ensureArrayObject($config)));
        $this->assertInstanceOf('Yproximite\Payum\Sogecommerce\Action\ConvertPaymentAction', $config['payum.action.convert_payment']);

        $this->assertNull($config['payum.default_options']['vads_site_id']);
        $this->assertSame('INTERACTIVE', $config['payum.default_options']['vads_action_mode']);
        $this->assertSame('PAYMENT', $config['payum.default_options']['vads_page_action']);
        $this->assertSame('SINGLE', $config['payum.default_options']['vads_payment_config']);
        $this->assertSame('V2', $config['payum.default_options']['vads_version']);
        $this->assertTrue($config['payum.default_options']['sandbox']);
        $this->assertNull($config['payum.default_options']['certif_prod']);
        $this->assertNull($config['payum.default_options']['certif_test']);
        $this->assertEquals('algo-sha1', $config['payum.default_options']['hash_algorithm']);
        $this->assertEquals([
            'vads_site_id',
            'vads_action_mode',
            'vads_page_action',
            'certif_test',
            'certif_prod',
        ], $config['payum.required_options']);

        $this->assertInstanceOf(\Closure::class, $config['payum.api']);
    }
}
