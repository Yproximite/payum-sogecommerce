<?php

declare(strict_types=1);

namespace Yproximite\Payum\Sogecommerce\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Reply\HttpResponse;
use Payum\Core\Request\GetHttpRequest;
use Payum\Core\Request\Notify;
use Yproximite\Payum\Sogecommerce\Action\Api\BaseApiAwareAction;
use Yproximite\Payum\Sogecommerce\Api;

class NotifyAction extends BaseApiAwareAction implements ActionInterface, GatewayAwareInterface
{
    use GatewayAwareTrait;

    /**
     * @var array<int, string>
     */
    private array $ignorableParameters = [Api::FIELD_VADS_PAYMENT_CONFIG, Api::FIELD_VADS_ACTION_MODE];

    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());

        $this->gateway->execute($httpRequest = new GetHttpRequest());

        $parameters = $httpRequest->request;
        if (!$this->isValidSignature($parameters)) {
            throw new HttpResponse('Bad signature', 403);
        }

        $model->replace($httpRequest->request);

        throw new HttpResponse('OK', 200);
    }

    public function supports($request): bool
    {
        return $request instanceof Notify && $request->getModel() instanceof \ArrayAccess;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function isValidSignature(array $parameters): bool
    {
        if (!\array_key_exists('signature', $parameters)) {
            return false;
        }

        $signature         = $parameters['signature'];
        $computedSignature = $this->api->getSignature($parameters);
        if ($signature === $computedSignature) {
            return true;
        }

        foreach ($this->ignorableParameters as $parameter) {
            if ($this->isValidSignatureWithoutParameter($signature, $parameters, $parameter)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int|string, mixed> $parameters
     */
    private function isValidSignatureWithoutParameter(?string $signature, array &$parameters, string $parameter): bool
    {
        if (!\array_key_exists($parameter, $parameters)) {
            return false;
        }

        unset($parameters[$parameter]);
        $computedSignature = $this->api->getSignature($parameters);

        return $signature === $computedSignature;
    }
}
