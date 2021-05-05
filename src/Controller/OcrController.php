<?php
declare(strict_types = 1);

namespace App\Controller;

use App\Engine\EngineFactory;
use App\Engine\GoogleCloudVisionEngine;
use App\Engine\TesseractEngine;
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

    /** @var TesseractEngine|GoogleCloudVisionEngine */
    protected $engine;

    /**
     * The output params for the view or API response.
     * This also serves as where you define the defaults.
     * Note this is used by the ExceptionListener.
     * @var mixed[]
     */
    public static $params = [
        'engine' => 'google',
        'langs' => [],
        'psm' => TesseractEngine::DEFAULT_PSM,
        'oem' => TesseractEngine::DEFAULT_OEM,
    ];

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
        $this->engine = $engineFactory->get($request->get('engine', static::$params['engine']));
        static::$params['engine'] = $this->engine instanceof TesseractEngine ? 'tesseract' : 'google';

        // Parameters.
        $this->imageUrl = (string)$request->query->get('image');
        static::$params['langs'] = $this->getLangs($request);
        static::$params['image_hosts'] = $this->intuition->listToText($this->engine->getImageHosts());

        $this->setEngineOptions($request);
    }

    /**
     * Set Engine-specific options based on user-provided input or the defaults.
     * @param Request $request
     */
    public function setEngineOptions(Request $request): void
    {
        // These are always set, even if Tesseract isn't initially chosen as the engine
        // because we want these defaults set if the user changes the engine to Tesseract.
        static::$params['psm'] = (int)$request->query->get('psm', (string)static::$params['psm']);
        static::$params['oem'] = (int)$request->query->get('oem', (string)static::$params['oem']);

        // Apply the settings to the Engine itself. This is only done when Tesseract is chosen
        // because these setters don't exist for the GoogleCloudVisionEngine.
        if ('tesseract' === static::$params['engine']) {
            $this->engine->setPsm(static::$params['psm']);
            $this->engine->setOem(static::$params['oem']);
        }
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
            static::$params['text'] = $this->engine->getText($this->imageUrl, static::$params['langs']);
        }

        return $this->render('output.html.twig', static::$params);
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

        static::$params['text'] = $this->engine->getText($this->imageUrl, static::$params['langs']);

        // Allow API requests from the Wikisource extension wherever it's installed.
        $response->headers->set('Access-Control-Allow-Origin', '*');

        $response->setData(static::$params);
        return $response;
    }
}
