<?php

declare(strict_types=1);

namespace Yproximite\Payum\Sogecommerce;

use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\GatewayFactory;
use Yproximite\Payum\Sogecommerce\Action\CaptureAction;
use Yproximite\Payum\Sogecommerce\Action\ConvertPaymentAction;
use Yproximite\Payum\Sogecommerce\Action\NotifyAction;
use Yproximite\Payum\Sogecommerce\Action\StatusAction;
use Yproximite\Payum\Sogecommerce\Request\RequestStatusApplier;

class SogecommerceGatewayFactory extends GatewayFactory
{
    /**
     * {@inheritdoc}
     */
    protected function populateConfig(ArrayObject $config): void
    {
        $config->defaults([
            'payum.factory_name'           => 'sogecommerce',
            'payum.factory_title'          => 'sogecommerce',
            'payum.action.capture'         => new CaptureAction(),
            'payum.action.convert_payment' => new ConvertPaymentAction(),
            'payum.action.notify'          => new NotifyAction(),
            'payum.action.status'          => function (ArrayObject $config) {
                return new StatusAction($config['payum.request_status_applier']);
            },
            'payum.request_status_applier' => new RequestStatusApplier(),
        ]);

        if (false === ($config['payum.api'] ?? false)) {
            $config['payum.default_options'] = [
                Api::FIELD_VADS_SITE_ID           => null,
                Api::FIELD_VADS_ACTION_MODE       => Api::ACTION_MODE_INTERACTIVE,
                Api::FIELD_VADS_PAGE_ACTION       => Api::PAGE_ACTION_PAYMENT,
                Api::FIELD_VADS_PAYMENT_CONFIG    => Api::PAYMENT_CONFIG_SINGLE,
                Api::FIELD_VADS_VERSION           => Api::V2,
                'sandbox'                         => true,
                'certif_prod'                     => null,
                'certif_test'                     => null,

                // Due to a limitation of Payum (https://github.com/Payum/Payum/issues/692),
                // the algorithm hash can not be "sha1" because it's a callable and will make Payum fails.
                // As a workaround, we prefix the algorithm hash by something and it's not seen a callable anymore.
                'hash_algorithm' => SignatureAlgorithm::toPayumOption(SignatureAlgorithm::SHA1),
            ];
            $config->defaults($config['payum.default_options']);
            $config['payum.required_options'] = [
                Api::FIELD_VADS_SITE_ID,
                Api::FIELD_VADS_ACTION_MODE,
                Api::FIELD_VADS_PAGE_ACTION,
                'certif_test',
                'certif_prod',
            ];

            $config['payum.api'] = function (ArrayObject $config) {
                $config->validateNotEmpty($config['payum.required_options']);

                return new Api((array) $config, new SignatureGenerator(), $config['payum.http_client'], $config['httplug.message_factory']);
            };
        }
    }
}
