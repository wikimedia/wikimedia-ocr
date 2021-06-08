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
use thiagoalessio\TesseractOCR\TesseractOcrException;
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

        // We only care about OcrExceptions, and TesseractOcrException thrown by the library (T282141).
        if (!( $exception instanceof OcrException || $exception instanceof TesseractOcrException )
            || !$event->isMasterRequest()
        ) {
            return;
        }

        $isApi = str_contains($this->request->getPathInfo(), '/api');
        $params = array_merge(
            OcrController::$params,
            $this->request->query->all()
        );
        $errorMessage = $exception instanceof TesseractOcrException
            ? $this->getMessageForTesseractException($exception)
            : $this->intuition->msg($exception->getI18nKey(), ['variables' => $exception->getI18nParams()]);

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

    /**
     * Given a tesseract-specific exception, try and extract a useful error message. Tries to balance between
     * being helpful and not giving away any potentially sensitive information (as might happen if we were
     * to pass any error message through).
     *
     * @param TesseractOcrException $exc @phan-unused-param
     * @return string
     */
    private function getMessageForTesseractException(TesseractOcrException $exc) : string
    {
        // TODO: How can we be more specific about what's gone wrong?
        return $this->intuition->msg('tesseract-internal-error');
    }
}
