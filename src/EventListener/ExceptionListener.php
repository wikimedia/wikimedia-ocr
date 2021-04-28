<?php
declare(strict_types = 1);

namespace App\EventListener;

use App\Controller\OcrController;
use App\Exception\OcrException;
use Krinkle\Intuition\Intuition;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Twig\Environment;

class ExceptionListener
{
    /** @var Request */
    private $request;

    /** @var SessionInterface */
    private $session;

    /** @var Environment */
    private $twig;

    /** @var Intuition */
    private $intuition;

    public function __construct(
        RequestStack $requestStack,
        SessionInterface $session,
        Environment $twig,
        Intuition $intuition
    ) {
        $this->request = $requestStack->getCurrentRequest();
        $this->session = $session;
        $this->twig = $twig;
        $this->intuition = $intuition;
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        // We only care about OcrExceptions.
        if (!$exception instanceof OcrException || !$event->isMasterRequest()) {
            return;
        }

        $isApi = str_contains($this->request->getPathInfo(), 'api.php');
        $params = array_merge(
            OcrController::$params,
            $this->request->query->all()
        );
        $errorMessage = $this->intuition->msg($exception->getI18nKey(), ['variables' => $exception->getI18nParams()]);

        if ($isApi) {
            $params['error'] = $errorMessage;
            $response = new JsonResponse($params);
        } else {
            /** @var FlashBagInterface $flashBag */
            $flashBag = $this->session->getBag('flashes');
            // @phan-suppress-next-line PhanUndeclaredMethod
            $flashBag->add('error', $errorMessage);
            $response = new Response(
                $this->twig->render('output.html.twig', $params)
            );
        }

        $response->setStatusCode(Response::HTTP_BAD_REQUEST);
        $event->setResponse($response);
    }
}
