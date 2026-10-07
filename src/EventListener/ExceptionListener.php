<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Service\ProductApiException;
use App\Service\ProductNotFoundException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

final class ExceptionListener
{
    #[AsEventListener]
    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if ($exception instanceof ProductNotFoundException) {
            $replacement = new NotFoundHttpException('Produit introuvable', $exception);
        } elseif ($exception instanceof ProductApiException || $exception instanceof \PDOException) {
            $replacement = new ServiceUnavailableHttpException(null, 'Service momentanément indisponible', $exception);
        } else {
            return;
        }
        $event->setThrowable($replacement);
    }
}
