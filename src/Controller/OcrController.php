<?php
declare(strict_types = 1);

namespace App\Controller;

use App\Engine\EngineBase;
use App\Engine\EngineFactory;
use App\Engine\EngineResult;
use App\Engine\GoogleCloudVisionEngine;
use App\Engine\TesseractEngine;
use App\Exception\EngineNotFoundException;
use Krinkle\Intuition\Intuition;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class OcrController extends AbstractController
{
    /** @var Intuition */
    protected $intuition;

    /** @var TesseractEngine|GoogleCloudVisionEngine */
    protected $engine;

    /** @var string The default OCR engine. */
    public const DEFAULT_ENGINE = 'google';

    /** @var CacheInterface */
    protected $cache;

    /** @var Request */
    protected $request;

    /** @var SessionInterface */
    protected $session;

    /** @var EngineFactory */
    protected $engineFactory;

    /**
     * The output params for the view or API response.
     * This also serves as where you define the defaults.
     * Note this is used by the ExceptionListener.
     * @var mixed[]
     */
    public static $params = [
        'engine' => self::DEFAULT_ENGINE,
        'langs' => [],
        'psm' => TesseractEngine::DEFAULT_PSM,
        'crop' => [],
    ];

    /** @var string */
    protected $imageUrl;

    /**
     * OcrController constructor.
     * @param RequestStack $requestStack
     * @param SessionInterface $session
     * @param Intuition $intuition
     * @param EngineFactory $engineFactory
     * @param CacheInterface $cache
     */
    public function __construct(
        RequestStack $requestStack,
        SessionInterface $session,
        Intuition $intuition,
        EngineFactory $engineFactory,
        CacheInterface $cache
    ) {
        $this->request = $requestStack->getCurrentRequest();
        $this->session = $session;
        $this->intuition = $intuition;
        $this->engineFactory = $engineFactory;
        $this->cache = $cache;
    }

    /**
     * Setup the engine and parameters needed for the view. This must be called before every action.
     */
    private function setup(): void
    {
        $requestedEngine = $this->request->get('engine', static::$params['engine']);
        try {
            $this->engine = $this->engineFactory->get($requestedEngine);
        } catch (EngineNotFoundException $e) {
            $this->addFlash('error', $this->intuition->msg(
                'engine-not-found-warning',
                ['variables' => [$requestedEngine, static::DEFAULT_ENGINE]]
            ));
            $this->engine = $this->engineFactory->get(static::DEFAULT_ENGINE);
        }

        static::$params['engine'] = $this->engine::getId();
        $this->setEngineOptions();

        // Parameters.
        $this->imageUrl = (string)$this->request->query->get('image');
        static::$params['langs'] = $this->getLangs($this->request);
        static::$params['image_hosts'] = $this->engine->getImageHosts();
        $crop = $this->request->query->get('crop');
        if (!is_array($crop)) {
            $crop = [];
        }
        static::$params['crop'] = array_map('intval', $crop);
    }

    /**
     * Set Engine-specific options based on user-provided input or the defaults.
     */
    private function setEngineOptions(): void
    {
        // This is always set, even if Tesseract isn't initially chosen as the engine
        // because we want the default set if the user changes the engine to Tesseract.
        static::$params['psm'] = (int)$this->request->query->get('psm', (string)static::$params['psm']);

        // Apply the tesseract-specific settings
        // NOTE: Intentionally excluding `oem`, see T285262
        if (TesseractEngine::getId() === static::$params['engine']) {
            $this->engine->setPsm(static::$params['psm']);
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
        // Remove invalid chars.
        $langsSanitized = preg_replace('/[^a-zA-Z0-9\-_]/', '', $langArray);
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
    public function homeAction(): Response
    {
        $this->setup();

        // Pre-supply available langs for autocompletion in the form.
        static::$params['available_langs'] = $this->engine->getValidLangs();
        sort(static::$params['available_langs']);

        // Intution::listToText() isn't available via Twig, and we only want to do this for the view and not the API.
        static::$params['image_hosts'] = $this->intuition->listToText(static::$params['image_hosts']);

        if ($this->imageUrl) {
            $result = $this->getResult(EngineBase::ERROR_ON_INVALID_LANGS);
            static::$params['text'] = $result->getText();
            foreach ($result->getWarnings() as $warning) {
                $this->addFlash('warning', $warning);
            }
        }

        return $this->render('output.html.twig', static::$params);
    }

    /**
     * @Route("/api", name="api")
     * @Route("/api.php", name="apiPhp")
     * @return JsonResponse
     */
    public function apiAction(): JsonResponse
    {
        $this->setup();
        $result = $this->getResult(EngineBase::WARN_ON_INVALID_LANGS);
        $responseParams = array_merge(static::$params, [
            'text' => $result->getText(),
        ]);
        $warnings = $result->getWarnings();
        if ($warnings) {
            $responseParams['warnings'] = $warnings;
        }
        return $this->getApiResponse($responseParams);
    }

    /**
     * @Route("/api/available_langs", name="apiLangs")
     * @return JsonResponse
     */
    public function apiAvailableLangsAction(): JsonResponse
    {
        $this->setup();
        return $this->getApiResponse([
            'engine' => static::$params['engine'],
            'available_langs' => $this->engine->getValidLangs(true),
        ]);
    }

    /**
     * Return a new JsonResponse with the given $params merged into static::$params.
     * @param mixed[] $params
     * @return JsonResponse
     */
    private function getApiResponse(array $params): JsonResponse
    {
        $response = new JsonResponse();
        $response->setEncodingOptions(JSON_NUMERIC_CHECK);
        $response->setStatusCode(Response::HTTP_OK);

        // Expose flash messages.
        /** @var FlashBag $flashBag */
        $flashBag = $this->session->getBag('flashes');
        '@phan-var FlashBag $flashBag';
        if ($flashBag->has('error')) {
            $params['error'] = $flashBag->get('error');
        }

        // Allow API requests from the Wikisource extension wherever it's installed.
        $response->headers->set('Access-Control-Allow-Origin', '*');

        $response->setData($params);
        return $response;
    }

    /**
     * Get and cache the transcription based on options set in static::$params.
     * @param string $invalidLangsMode EngineBase::WARN_ON_INVALID_LANGS or EngineBase::ERROR_ON_INVALID_LANGS
     * @return EngineResult
     */
    private function getResult(string $invalidLangsMode): EngineResult
    {
        ksort(static::$params['crop']);
        $cacheKey = md5(implode(
            '|',
            [
                $this->imageUrl,
                static::$params['engine'],
                implode('|', static::$params['langs']),
                implode('|', array_map('strval', static::$params['crop'])),
                static::$params['psm'],
                // Warning messages are localized
                $this->intuition->getLang(),
            ]
        ));

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($invalidLangsMode) {
            $item->expiresAfter((int)$this->getParameter('cache_ttl'));
            return $this->engine->getResult(
                $this->imageUrl,
                $invalidLangsMode,
                static::$params['crop'],
                static::$params['langs']
            );
        });
    }
}
