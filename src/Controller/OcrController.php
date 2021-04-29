<?php
declare(strict_types = 1);

namespace App\Controller;

use App\Engine\EngineBase;
use App\Engine\EngineFactory;
use App\Engine\TesseractEngine;
use App\Exception\OcrException;
use Krinkle\Intuition\Intuition;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class OcrController extends AbstractController
{
    /** @var Intuition */
    protected $intuition;

    /** @var EngineBase */
    protected $engine;

    /** @var mixed[] Output params for the view or API response. */
    protected $params = [];

    /** @var string */
    protected $imageUrl;

    /** @var string */
    protected $lang;

    /**
     * OcrController constructor.
     * @param RequestStack $requestStack
     * @param Intuition $intuition
     * @param EngineFactory $engineFactory
     */
    public function __construct(RequestStack $requestStack, Intuition $intuition, EngineFactory $engineFactory)
    {
        // Dependencies.
        $this->intuition = $intuition;

        $request = $requestStack->getCurrentRequest();

        // Engine.
        $this->engine = $engineFactory->get($request->get('engine', 'google'));
        $this->params['engine'] = $this->engine instanceof TesseractEngine ? 'tesseract' : 'google';

        // Parameters.
        $this->imageUrl = (string)$request->query->get('image');
        $this->lang = (string)$request->query->get('lang');
    }

    /**
     * The main form and result page.
     * @Route("/", name="home")
     * @return Response
     */
    public function home(): Response
    {
        if ($this->imageUrl) {
            try {
                $this->params['text'] = $this->engine->getText($this->imageUrl, $this->lang);
            } catch (OcrException $e) {
                $this->addFlash(
                    'error',
                    $this->intuition->msg($e->getI18nKey(), ['variables' => $e->getI18nParams()])
                );
            }
        }

        $this->params['image_hosts'] = implode(', ', $this->engine->getImageHosts());

        return $this->render('output.html.twig', $this->params);
    }

    /**
     * @Route("/api.php", name="api")
     * @return JsonResponse
     */
    public function api(): JsonResponse
    {
        $response = new JsonResponse();
        $response->setEncodingOptions(JSON_NUMERIC_CHECK);
        $response->setStatusCode(Response::HTTP_OK);

        try {
            $this->params['text'] = $this->engine->getText($this->imageUrl, $this->lang);
        } catch (OcrException $e) {
            $this->params['error'] = $this->intuition->msg($e->getI18nKey(), ['variables' => $e->getI18nParams()]);
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
        }

        // Allow API requests from the Wikisource extension wherever it's installed.
        $response->headers->set('Access-Control-Allow-Origin', '*');

        $response->setData($this->params);
        return $response;
    }
}
