<?php
declare(strict_types = 1);

namespace App\Controller;

use App\Engine\GoogleCloudVisionEngine;
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

    /** @var GoogleCloudVisionEngine */
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
     * @param string $endpoint
     * @param string $key
     */
    public function __construct(RequestStack $requestStack, Intuition $intuition, string $endpoint, string $key)
    {
        $request = $requestStack->getCurrentRequest();

        // Dependencies.
        $this->intuition = $intuition;
        $this->engine = new GoogleCloudVisionEngine($endpoint, $key);

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

        $response->setData($this->params);
        return $response;
    }
}
