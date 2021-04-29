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
use Symfony\Component\HttpFoundation\Request;
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
        $this->params['langs'] = $this->getLangs($request);
    }

    /**
     * Get a list of language codes from the request.
     * Looks for both the string `?lang=xx` and array `?langs[]=xx&langs[]=yy` versions.
     * @param Request $request
     * @return string[]
     */
    public function getLangs(Request $request): array
    {
        $lang = $request->query->get('lang');
        $langs = $request->query->all('langs');
        $langArray = array_merge([ $lang ], $langs);
        // Remove non-alpha chars.
        $langsSanitized = preg_replace('/[^a-zA-Z]/', '', $langArray);
        // Remove empty and duplicated values, and then reorder keys (just for easier testing).
        $langsFiltered = array_values(array_unique(array_filter($langsSanitized)));
        // If no languages specified, default to the user's.
        // @TODO The default language code needs to vary based on the engine. T280617.
        //return 0 === count($langsFiltered) ? [$this->intuition->getLang()] : $langsFiltered;
        return $langsFiltered;
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
                $this->params['text'] = $this->engine->getText($this->imageUrl, $this->params['langs']);
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
            $this->params['text'] = $this->engine->getText($this->imageUrl, $this->params['langs']);
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
